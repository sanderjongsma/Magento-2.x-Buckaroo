<?php
/**
 * Copyright © 2019 Appmerce - Applications for Ecommerce
 * http://www.appmerce.com
 */
namespace Appmerce\Buckaroo\Controller;

use stdClass;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;

abstract class Buckaroo extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    // Local constants
    const API_CONTROLLER_PATH = 'buckaroo/api/';
    const PUSH_CONTROLLER_PATH = 'buckaroo/push/';

    const RETURN_METHOD = 'GET';
    const TRANSFER_SEND_MAIL = 'YES';
    const ADDRESSTYPE_INVOICE = 'INVOICE';
    const ADDRESSTYPE_SHIPPING = 'SHIPPING';

    const IP_TYPE = 'IPv4';
    const CHANNEL = 'Web';
    const ACTION_PAY = 'Pay';
    const ACTION_PAYMENTINVITATION = 'paymentinvitation';

    // Default order statuses
    const DEFAULT_STATUS_PENDING = 'pending';
    const DEFAULT_STATUS_PENDING_PAYMENT = 'pending_payment';
    const DEFAULT_STATUS_PROCESSING = 'processing';

    protected $log;

    /**
     * @var \Appmerce\Buckaroo\Model\Buckaroo
     */
    protected $api;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    protected $localeResolver;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Psr\Log\LoggerInterface $log
     * @param \Magento\Framework\Locale\ResolverInterface $localeResolver
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Appmerce\Buckaroo\Model\Buckaroo $api
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $log,
        \Magento\Framework\Locale\ResolverInterface $localeResolver,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Appmerce\Buckaroo\Model\Buckaroo $api
    ) {
        $this->_storeManager = $storeManager;
        parent::__construct($context);
        $this->_scopeConfig = $scopeConfig;
        $this->log = $log;
        $this->localeResolver = $localeResolver;
        $this->checkoutSession = $checkoutSession;
        $this->api = $api;
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(
        RequestInterface $request
    ): ?InvalidRequestException {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
    
    /**
     * @return \Magento\Quote\Model\Quote
     */
    protected function _getQuote()
    {
        return $this->_objectManager->get('Magento\Quote\Model\Quote');
    }

    /**
     * @return \Magento\Sales\Model\Order
     */
    protected function _getOrder()
    {
        return $this->_objectManager->get('Magento\Sales\Model\Order');
    }

    /**
     * Array of payment methods
     *
     * Buckaroo uses a test parameter for testing, except for
     * Direct XML creditcards, which has a separate 'test' gateway
     */
    public function getGatewayUrl($type, $order)
    {
        $storeId = $order->getStoreId();
        
        $gateways = array(
            'soap' => array(
                '0' => 'https://checkout.buckaroo.nl/soap/',
                '1' => 'https://testcheckout.buckaroo.nl/soap/'
            ),
            'wsdl' => array(
                '0' => 'https://checkout.buckaroo.nl/soap/?WSDL',
                '1' => 'https://testcheckout.buckaroo.nl/soap/?WSDL'
            ),
            'cert' => 'https://checkout.buckaroo.nl/Checkout.cer',
        );

        $testMode = $this->api->getServiceConfigData('test_mode', $storeId);
        return isset($gateways[$type][$testMode]) ? $gateways[$type][$testMode] : $gateways[$type];
    }

    /**
     * Decide currency code type
     *
     * @return string
     */
    public function getCurrencyCode()
    {
        if ($this->api->getServiceConfigData('base_currency')) {
            $currencyCode = $this->_storeManager->getStore()->getBaseCurrencyCode();
        }
        else {
            $currencyCode = $this->_storeManager->getStore()->getCurrentCurrencyCode();
        }
        return $currencyCode;
    }

    /**
     * Decide grand total VAT
     *
     * @return float
     */
    public function _getTaxAmount($order)
    {
        if ($this->api->getServiceConfigData('base_currency')) {
            $grandTotal = $order->getBaseTaxAmount();
        }
        else {
            $grandTotal = $order->getTaxAmount();
        }
        return round($grandTotal * 100);
    }

    /**
     * Decide amount base or store
     *
     * @param $order
     * @param $currencyCode string
     * @return string
     */
    public function _getGrandTotal($order)
    {
        if ($this->api->getServiceConfigData('base_currency')) {
            $amount = $order->getBaseGrandTotal();
        }
        else {
            $amount = $order->getGrandTotal();
        }
        return number_format($amount, 2, '.', '');
    }

    /**
     * Create payment request
     */
    public function paymentRequest($order)
    {
        $storeId = $order->getStoreId();
        $address = $order->getBillingAddress();
        $shipping = $order->getShippingAddress();
        if (!$shipping || !is_object($shipping)) {
            $shipping = $address;
        }
        $paymentMethodCode = $order->getPayment()->getMethod();
        $profile_key = $this->api->getServiceConfigData('profile_key');

        $data = array();
        $data['amount'] = $this->_getGrandTotal($order);
        $data['description'] = __('Order %1', $order->getIncrementId());
        $data['redirectUrl'] = $this->getApiUrl('returnUser', $storeId) . '?increment_id=' . $order->getIncrementId();
        $data['webhookUrl'] = $this->getPushUrl('response', $storeId) . '?increment_id=' . $order->getIncrementId();
        $data['metadata']['order_id'] = $order->getIncrementId();

        // Valid methods: banktransfer, bitcoin, creditcard, ideal, mistercash, paypal, paysafecard, sofort
        $data['method'] = str_replace('appmerce_buckaroo_', '', $paymentMethodCode);

        // Method-specific fields
        switch ($paymentMethodCode) {
            case 'appmerce_buckaroo_banktransfer' :
                $data['billingEmail'] = $address->getEmail();
                //$data['dueDate'] = '';
                break;

            case 'appmerce_buckaroo_ideal' :
                $data['issuer'] = $order->getPayment()->getAdditionalInformation('issuer_id');
                break;

            case 'appmerce_buckaroo_creditcard' :
                $data['billingCity'] = substr($address->getCity(), 0, 100);
                $data['billingRegion'] = substr($address->getRegion(), 0, 100);
                $data['billingPostal'] = substr($address->getPostcode(), 0, 100);
                $data['billingCountry'] = substr($address->getCountryId(), 0, 2);
                $data['shippingAddress'] = substr($shipping->getStreet(-1), 0, 100);
                $data['shippingCity'] = substr($shipping->getCity(), 0, 100);
                $data['shippingRegion'] = substr($shipping->getRegion(), 0, 100);
                $data['shippingPostal'] = substr($shipping->getPostCode(), 0, 100);
                $data['shippingCountry'] = substr($shipping->getCountryId(), 0, 2);
                break;

            case 'appmerce_buckaroo_paypal' :
                $data['shippingAddress'] = substr($shipping->getStreet(-1), 0, 100);
                $data['shippingCity'] = substr($shipping->getCity(), 0, 40);
                $data['shippingRegion'] = substr($shipping->getRegion(), 0, 40);
                $data['shippingPostal'] = substr($shipping->getPostCode(), 0, 20);
                $data['shippingCountry'] = substr($shipping->getCountryId(), 0, 2);
                break;

            case 'appmerce_buckaroo_paysafecard' :
                $data['customerReference'] = $this->getRealIpAddr();
                break;
        }

        $response = new stdClass;
        $request = $this->api->curlPost('https://api.buckaroo.nl/v1/payments', $data);
        if ($request) {
            $json = json_decode($request);
            if (isset($json->id)) {
                $response->id = (string)$json->id;
                $response->url = (string)$json->links->paymentUrl;
            }
            else {
                $response->error = isset($json->error->message) ? __('Buckaroo Error: %1', $json->error->message) : 'Error';
            }
        }
        else {
            $response->error = __('Payment request failed. Please contact the merchant.');
        }
        return $response;
    }

    /**
     * Return URLs
     * 
     * @param string $key
     * @param int $storeId
     * @param bool $noSid
     * @return mixed
     */
    public function getApiUrl($key, $storeId = null, $noSid = false)
    {
        return $this->_url->getUrl(self::API_CONTROLLER_PATH . $key, ['_store' => $storeId, '_secure' => true, '_nosid' => $noSid]);
    }

    public function getPushUrl($key, $storeId = null, $noSid = false)
    {
        return $this->_url->getUrl(self::PUSH_CONTROLLER_PATH . $key, ['_store' => $storeId, '_secure' => true, '_nosid' => $noSid]);
    }

    /**
     * Get new order status
     */
    public function getOrderStatus($paymentMethodCode)
    {
        $status = $this->api->getPaymentConfigData($paymentMethodCode, 'order_status');
        if (empty($status)) {
            $status = self::DEFAULT_STATUS_PENDING;
        }
        return $status;
    }

    /**
     * Get order pending payment status
     */
    public function getPendingStatus($paymentMethodCode)
    {
        $status = $this->api->getPaymentConfigData($paymentMethodCode, 'pending_status');
        if (empty($status)) {
            $status = self::DEFAULT_STATUS_PENDING_PAYMENT;
        }
        return $status;
    }

    /**
     * Get order processing status
     */
    public function getProcessingStatus($paymentMethodCode)
    {
        $status = $this->api->getPaymentConfigData($paymentMethodCode, 'processing_status');
        if (empty($status)) {
            $status = self::DEFAULT_STATUS_PROCESSING;
        }
        return $status;
    }

    /**
     * Success process
     * [multi-method]
     *
     * Update succesful (paid) orders, send order email, create invoice
     * and send invoice email. Restore quote and clear cart.
     *
     * @param $order object
     * @param $note string Backend order history note
     * @param $transactionId string Transaction ID
     */
    public function processSuccess($order, $note, $transactionId)
    {
        $this->processCheck($order);
	    $transactionId = (string)$transactionId;
        $order->getPayment()->setAdditionalInformation('transaction_id', $transactionId)
                            ->setLastTransId($transactionId)
                            ->save();

        // Set Total Paid & Due
        // (The invoice will do this.)
        // $amount = $order->getGrandTotal();
        // $order->setTotalPaid($amount);

        // Multi-method API
        $paymentMethodCode = $order->getPayment()->getMethod();
        
        // Set processing status
        $status = $this->getProcessingStatus($paymentMethodCode);
        $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING)
              ->setStatus($status)
              ->addStatusHistoryComment($note)
              ->setIsCustomerNotified(true)
              ->save();

        // Create invoice
        if ($this->api->getServiceConfigData('invoice_create')) {
            $this->processInvoice($order);
            $this->log->addDebug(__('Invoice created.'));
        }
    }

    /**
     * Create automatic invoice
     * [multi-method]
     *
     * @param $order object
     */
    public function processInvoice($order)
    {
        if (!$order->hasInvoices() && $order->canInvoice()) {
            $invoice = $order->prepareInvoice();
            if ($invoice->getTotalQty() > 0) {
                $transactionId = $order->getPayment()->getTransactionId();
                $this->log->addDebug($transactionId);
                $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
                $invoice->setTransactionId($transactionId);
                $invoice->register();
                
                $transactionSave = $this->_objectManager->create('Magento\Framework\DB\Transaction')
                                        ->addObject($invoice)
                                        ->addObject($order)
                                        ->save();

                // Send invoice email
                if (!$invoice->getEmailSent() && $this->api->getServiceConfigData('invoice_email')) {
                    // @todo
                }
                $invoice->save();
            }
        }
    }

    /**
     * Pending process
     * [multi-method]
     *
     * Update orders with explicit payment pending status. Restore quote.
     *
     * @param $order object
     * @param $note string Backend order history note
     * @param $transactionId string Transaction ID
     */
    public function processPending($order, $note, $transactionId)
    {
        $this->processCheck($order);
	    $transactionId = (string)$transactionId;
        $order->getPayment()->setAdditionalInformation('transaction_id', $transactionId)
                            ->setLastTransId($transactionId)
                            ->save();

        // Multi-method API
        $paymentMethodCode = $order->getPayment()->getMethod();

        // Set pending_payment status
        $status = $this->getPendingStatus($paymentMethodCode);
        $order->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT)
              ->setStatus($status)
              ->addStatusHistoryComment($note)
              ->setIsCustomerNotified(true)
              ->save();
    }

    /**
     * Cancel process
     *
     * Update failed, cancelled, declined, rejected etc. orders. Cancel
     * the order and show user message. Restore quote.
     *
     * @param $order object
     * @param $note string Backend order history note
     * @param $transactionId string Transaction ID
     */
    public function processCancel($order, $note, $transactionId)
    {
        $this->processCheck($order);
	    $transactionId = (string)$transactionId;
        $order->getPayment()->setAdditionalInformation('transaction_id', $transactionId)
                            ->setLastTransId($transactionId)
                            ->save();

        // Cancel order
        $order->cancel()->save();
    }

    /**
     * Check order state
     *
     * If the order state (not status) is already one of:
     * canceled, closed, holded or completed,
     * then we do not update the order status anymore.
     *
     * @param $order object
     */
    public function processCheck($order)
    {
        if ($order->getId()) {
            $state = $order->getState();
            switch ($state) {
                
                // Do not allow further updates; prevent double invoices
                case \Magento\Sales\Model\Order::STATE_HOLDED :
                case \Magento\Sales\Model\Order::STATE_CANCELED :
                case \Magento\Sales\Model\Order::STATE_CLOSED :
                case \Magento\Sales\Model\Order::STATE_COMPLETE :
                    
                    // Kill process
                    $this->log->addDebug(__('Payment already processed.'));
                    http_response_code(200);
                    throw new \Exception('Full stop.');
                    break;
                    
                // Allow updates
                case \Magento\Sales\Model\Order::STATE_NEW :
                case \Magento\Sales\Model\Order::STATE_PROCESSING :
                    break;
            }
        }
        else {
            
            // No order
            $this->log->addDebug(__('Order not found.'));
            http_response_code(200);
            throw new \Exception('Full stop.');
        }
    }

    /**
     * Restore cart
     */
    public function restoreCart()
    {
        $lastQuoteId = $this->checkoutSession->getLastQuoteId();
        if ($quote = $this->_getQuote()->loadByIdWithoutStore($lastQuoteId)) {
            $quote->setIsActive(true)
                  ->setReservedOrderId(null)
                  ->save();
            $this->checkoutSession->setQuoteId($lastQuoteId);
        }

        $message = __('Payment failed. Please try again.');
        $this->messageManager->addError($message);
    }

    /**
     * Get Real IP Address
     *
     * @return string
     */
    public function getRealIpAddr()
    {
        $ip = '';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        }
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }
    
    /**
     * Validate response
     *
     * Comparing local $order data with remote params and signature
     *
     * @return boolean True or False
     */
    public function validateResponse($params, $order)
    {
        $storeId = $order->getStoreId();
        $secretKey = $this->api->getServiceConfigData('secret_key', $storeId);
        $signature = $params['brq_signature'];

        return $signature == $this->_calculateSignature($secretKey, $params) ? true : false;
    }

    protected function _calculateSignature($secretKey, $params)
    {
        $origArray = $params;
        unset($origArray['brq_signature']);

        //sort the array
        $sortableArray = $this->buckarooSort($origArray);

        //turn into string and add the secret key to the end
        $signatureString = '';
        foreach ($sortableArray as $key => $value) {
            $value = urldecode($value);
            $signatureString .= $key . '=' . $value;
        }
        $signatureString .= $secretKey;

        //return the SHA1 encoded string for comparison
        $signature = sha1($signatureString);

        return $signature;
    }

    public function buckarooSort($array)
    {
        $arrayToSort = array();
        $origArray = array();
        foreach ($array as $key => $value) {
            $arrayToSort[strtolower($key)] = $value;
            //stores the original value in an array
            $origArray[strtolower($key)] = $key;
        }

        ksort($arrayToSort);

        $sortedArray = array();
        foreach ($arrayToSort as $key => $value) {
            //switch the lowercase keys back to their originals
            $key = $origArray[$key];
            $sortedArray[$key] = $value;
        }

        return $sortedArray;
    }
}
