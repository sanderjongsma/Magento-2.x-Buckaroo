<?php
/**
 * Copyright © 2019 Appmerce - Applications for Ecommerce
 * http://www.appmerce.com
 */
namespace Appmerce\Buckaroo\Model;

use DOMDocument;
use \Magento\Framework\Module\Dir;
use \Magento\Framework\App\Filesystem\DirectoryList;

class Buckaroo extends \Magento\Payment\Model\Method\AbstractMethod
{
    const PAYMENT_METHOD_BUCKAROO_CODE = 'appmerce_buckaroo';

    // Local constants
    const LANGUAGE_NL = 'nl';
    const LANGUAGE_DE = 'de';
    const LANGUAGE_FR = 'fr';
    const LANGUAGE_EN = 'en';

    const COUNTRY_NL = 'NL';
    const COUNTRY_DE = 'DE';
    const COUNTRY_BE = 'BE';
    const COUNTRY_FR = 'FR';

    const RETURN_METHOD = 'GET';
    const TRANSFER_SEND_MAIL = 'YES';
    const ADDRESSTYPE_INVOICE = 'INVOICE';
    const ADDRESSTYPE_SHIPPING = 'SHIPPING';
    const ACTION_REFUND = 'Refund';
    const ACTION_REVERSAL = 'Reversal';
    const CHANNEL = 'Web';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_BUCKAROO_CODE;

    /**
     * @var boolean
     */
    protected $_canRefund = false;

    /**
     * @var boolean
     */
    protected $_canRefundInvoicePartial = false;

    /**
     * @var boolean
     */
    protected $_canUseInternal = false;
    
    /**
     * @var \Magento\Framework\Module\Dir\Reader
     */
    protected $_modulereader;

    /**
     * @var \Magento\Framework\Filesystem\Directory\Write
     */
    protected $_directory;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $resultJsonFactory;
    
    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Framework\Module\Dir\Reader $moduleReader
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Framework\Module\Dir\Reader $moduleReader,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger
        );
        $this->_directory = $filesystem->getDirectoryWrite(DirectoryList::ROOT);
        $this->_modulereader = $moduleReader;
        $this->resultJsonFactory = $resultJsonFactory;
    }

    /**
     * Assign data to info model instance
     *
     * @param \Magento\Framework\DataObject|mixed $data
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function assignData(\Magento\Framework\DataObject $data)
    {
    		parent::assignData($data);
				$adinfo = $data->getData();
				
				if (isset($adinfo['additional_data']['issuer_id'])) {
					$this->getInfoInstance()->setAdditionalInformation(
	                'issuer_id',
	                $adinfo['additional_data']['issuer_id']
	            );
	
					$this->getInfoInstance()->setAdditionalInformation(
	                'issuer_name',
	                $adinfo['additional_data']['issuer_name']
	            );
				}

				if (isset($adinfo['additional_data']['bankleitzahl'])) {
					$this->getInfoInstance()->setAdditionalInformation(
	                'bankleitzahl',
	                $adinfo['additional_data']['bankleitzahl']
	            );
				}
				
				if (isset($adinfo['additional_data']['consumerAccountName'])) {
					$this->getInfoInstance()->setAdditionalInformation(
	                'consumerAccountName',
	                $adinfo['additional_data']['consumerAccountName']
	            );
				}
				
				if (isset($adinfo['additional_data']['consumerAccountNumber'])) {
					$this->getInfoInstance()->setAdditionalInformation(
	                'consumerAccountNumber',
	                $adinfo['additional_data']['consumerAccountNumber']
	            );
				}

        return $this;
    }
    
    /**
     * Get store configuration
     */
    public function getPaymentConfigData($code, $field, $storeId = null)
    {
        $path = 'payment/' . $code . '/' . $field;
        return $this->_scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getServiceConfigData($field, $storeId = null)
    {
        $path = 'payment/appmerce_buckaroo_shared/' . $field;
        return $this->_scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }
    
    /**
     * Return iDEAL Issuers
     */
    public function getIssuers()
    {
        return array(
            'ABNANL2A' => 'ABN AMRO',
            'BUNQNL2A' => 'Bunq',
            'ASNBNL21' => 'Friesland Bank',
            'INGBNL2A' => 'ING',
            'KNABNL2H' => 'Knab',
            'RABONL2U' => 'Rabobank',
            'SNSBNL2A' => 'SNS Bank',
            'ASNBNL21' => 'ASN Bank',
            'RBRBNL21' => 'RegioBank',
            'TRIONL2U' => 'Triodos Bank',
            'FVLBNL22' => 'Van Lanschot',
        );
    }

    /**
     * Get base dir
     *
     * @return string
     */
    protected function _getBaseDir()
    {
        return $this->_directory->getAbsolutePath();
    }

    /**
     * Get local file path
     *
     * @param $path string Relative path
     *
     * @return string Absolute path
     */
    public function getLocalPath($path, $configKey, $storeId = null)
    {
        return $this->_getBaseDir() . $path . $this->getServiceConfigData($configKey, $storeId);
    }
    
    /**
     * Response messages by code
     *
     * @param $code string Response code
     *
     * @return string Untranslated response message
     */
    public function getResponseMessage($responseCode)
    {
        $responseMessages = array(

            // CreditCard XML
            '001' => 'The credit card is a 3DSecure / Verified by Visa card. The transaction has not yet been completed.',
            '100' => 'Authorisation successful.',
            '101' => 'Authorisation rejected by the credit card issuer.',
            '102' => 'Authorisation error (unsuccessful payment).',
            '103' => 'The transaction has not been completed within the set time limit.',
            '104' => 'The card is expired.',
            '201' => 'A time-out occurred during processing.',

            // PayPal
            '120' => 'This PayPal transaction has not been fully processed. (Status not definite.)',
            '121' => 'Transaction status: authorisation successful.',
            '122' => 'This PayPal transaction has been cancelled by the consumer.',
            '123' => 'This PayPal transaction has not been completed within the set time limit.',
            '124' => 'This PayPal transaction has failed for unknown reasons.',
            '125' => 'This PayPal transaction has not been accepted. (Status not definite.)',
            '126' => 'This PayPal transaction is pending. (Status not definite.)',
            '135' => 'This PayPal transaction has not been fully processed.',
            '136' => 'The status of this transaction could not be retrieved due to technical reasons. The transaction may not have been completed. (Status not definite.)',
            '138' => 'System error.',
            '139' => 'PayPal transaction ID is invalid or not available.',
            '140' => 'Transaction not found.',

            // Paysafecard
            '150' => 'This paysafecard transaction has not been fully processed.',
            '151' => 'This paysafecard has been successfully processed.',
            '152' => 'This paysafecard transaction has been cancelled by the consumer.',
            '153' => 'This paysafecard transaction has not been completed within the set time limit.',
            '154' => 'This paysafepaysafecard transaction has failed for unknown reasons. Try again later.',
            '155' => 'This paysafecard has been rejected by paysafecard for unknown reasons.',
            '156' => 'This paysafecard transaction has not been fully processed.',
            '157' => 'The status of this paysafecard transaction is unknown. (Status not definite.',
            '158' => 'System error. Status could not be updated. (Status not definite.)',
            '159' => 'This paysafecard transaction ID is invalid or not available. Payment has not been completed.',
            '160' => 'This paysafecard transactie exceeds EUR 1,000 limit.',

            // Cash-Ticket
            '170' => 'This Cash-Ticket transaction has not been fully processed.',
            '171' => 'This Cash-Ticket has been successfully processed.',
            '172' => 'This Cash-Ticket transaction has been cancelled by the consumer.',
            '173' => 'This Cash-Ticket transaction has not been completed within the set time limit.',
            '174' => 'This Cash-Ticket transaction has failed for unknown reasons. Try again later.',
            '175' => 'This Cash-Ticket has been rejected by Cash-Ticket for unknown reasons.',
            '176' => 'This Cash-Ticket transaction has not been fully processed.',
            '177' => 'The status of this Cash-Ticket transaction is unknown. (Status not definite.)',
            '178' => 'System error. Status could not be updated. (Status not definite.)',
            '179' => 'This Cash-Ticket transaction ID is invalid or not available. Payment has not been completed.',
            '180' => 'This Cash-Ticket transaction exceeds EUR 1,000 limit.',

            // Betaalgarant / Transfer Garant
            '251' => 'This BetaalGarant request has been cancelled by the consumer.',
            '252' => 'This BetaalGarant request has been rejected by the warranty provider.',
            '253' => 'This BetaalGarant request is still pending.',
            '254' => 'This BetaalGarant request has been accepted. The warranty provider guarantees the invoice amount and the invoice is included in the credit management process.',
            '260' => 'The BetaalGarant subscription is not active or has expired.',
            '261' => 'A technical error occurred while processing your request.',
            '262' => 'One of more compulsory fields are missing or incorrect. For further information browse transaction details by clicking on the Vars button and then on the Logentries tab on the transaction page.',

            // Bank Transfer
            '300' => 'This bank transfer has not been fully processed.',
            '301' => 'This bank transfer has been successfully completed.',
            '302' => 'This bank transfer has been rejected.',
            '303' => 'This bank transfer was not received within the set period.',
            '309' => 'This bank transfer has been cancelled before the money was transferred.',

            // Giftcard
            '400' => 'Deze Cadeaukaart transactie is nog niet volledig verwerkt.',
            '401' => 'Deze Cadeaukaart transactie is inclusief eventuele restantbetaling met succes verwerkt.',
            '409' => 'Deze Cadeaukaart transactie is door de consument geannuleerd.',
            '411' => 'Deze Cadeaukaart transactie is met succes verwerkt; restant betaling wordt nog afgerond worden.',
            '414' => 'Er is een systeemfout opgetreden; raadpleeg het transactielog in de Payment Plaza',

            // PayperEmail
            '500' => 'PayperEmail: outstanding transaction.',

            // Direct Debit
            '600' => 'This direct debit has not been fully processed.',
            '601' => 'This direct debit has been successfully processed by Interpay.',
            '602' => 'This direct debit has not been processed by Interpay.',
            '605' => 'This direct debit has been reversed.',
            '609' => 'This direct debit has been cancelled prior to payment.',
            '610' => 'This direct debit has not been accepted by the bank. Invalid bank account.',
            '612' => 'Melding onterechte incasso (MOI).',

            // iDEAL
            '800' => 'This iDEAL transaction has not been fully processed.',
            '801' => 'This iDEAL transaction has been successfully processed.',
            '802' => 'This iDEAL transaction has been cancelled by the consumer.',
            '803' => 'This iDEAL transaction has not been completed within the set time limit.',
            '804' => 'This iDEAL transaction has failed for unknown reasons.',
            '810' => 'Issuer (bank) unknown.',
            '811' => 'The status of this transaction could not be retrieved due to technical reasons.',
            '812' => 'The entrance code for this transaction is invalid.',
            '813' => '(Internal) acquirer code unknown.',
            '814' => 'System error. Status could not be updated. (Status not definite.)',
            '815' => 'iDEAL transaction ID is invalid or not available. Transaction has not been carried out.',
            '816' => 'Transaction not found based on invoice number and amount (specified in XML StatusRequest).',

            // Giropay
            '820' => 'This Giropay transaction has not been fully processed.',
            '821' => 'This Giropay transaction has been successfully processed.',
            '822' => 'This Giropay transaction has been cancelled by the consumer.',
            '823' => 'This Giropay transaction has not been completed within the set time limit.',
            '824' => 'The Giropay transaction has been refused by the issuer.',
            '830' => 'Issuer (bankleitzahl) unknown.',
            '831' => 'The status of this transaction could not be retrieved due to technical issues.',
            '833' => 'Invalid entrance code.',
            '834' => 'System error. We will solve the problem as quickly as possible.',
            '835' => 'Giropay transaction ID is invalid or not available.',
            '836' => 'No transaction could be found.',
        );

        return array_key_exists($responseCode, $responseMessages) ? $responseMessages[$responseCode] : 'Customer succesfully returned from Buckaroo.';
    }

    /**
     * Summary of succes response codes
     * @see https://payment.buckaroo.nl/common/statuscodes_csv.asp
     *
     * @return array
     */
    public function getSuccessCodes()
    {
        return array(
            '071', // De refund is succesvol verwerkt.
            '091', // Deze transactie is met succes verwerkt.
            '100', // De transactie is door de credit-maatschappij goedgekeurd.
            '121', // Transactiestatus: authorisatie geslaagd (PayPal)
            '151', // Transactiestatus: authorisatie geslaagd (Paysafecard)
            '171', // Transactiestatus: authorisatie geslaagd (Cash-Ticket)
            '190', // Success (Credit Card)
            '254', // BetaalGarant aanvraag succesvol verwerkt
            '301', // De overschrijving is ontvangen.
            '401', // De betaling middels kado-kaart is geslaagd.
            '461', // Transactie voltooid
            '462', // Transactie voltooid
            '463', // Transactie voltooid
            '464', // Transactie voltooid
            '501', // OnlineGiro transactie verwerkt
            '551', // De uitbetaling is succesvol verwerkt.
            '601', // Eenmalige machtiging is met succes verwerkt.
            '701', // De betaalopdracht is verwerkt.
            '801', // Deze iDeal-transactie is met succes verwerkt.
            '821', // Deze Giropay-transactie is met succes verwerkt.
        );
    }

    /**
     * Summary of pending response codes
     * @see https://payment.buckaroo.nl/common/statuscodes_csv.asp
     *
     * @return array
     */
    public function getPendingCodes()
    {
        return array(
            '000', // De credit card transactie is pending.
            '001', // De credit card transactie is pending. De MPI-status van de klant wordt gecheckt.
            '070', // De refund is nog niet verwerkt.
            '090', // Deze transactie is nog niet volledig verwerkt.
            '120', // Deze PayPal-transactie is nog niet volledig verwerkt.
            '150', // Deze Paysafecard-transactie is nog niet volledig verwerkt
            '170', // Deze Cash-Ticket transactie is nog niet volledig verwerkt.
            '253', // BetaalGarant aanvraag nog niet verwerkt
            '300', // Betaling voor deze overschrijving wordt nog verwacht.
            '400', // De kadokaart-transactie is nog in behandeling
            '460', // In behandeling
            '500', // Paypermail: transactie pending
            '550', // De uitbetaling is nog niet verwerkt.
            '600', // Eenmalige machtiging is nog niet verwerkt.
            '700', // De betaalopdracht is geaccepteerd en wordt in behandeling genomen.
            '710', // Betaalopdracht nog niet geverifieerd.
            '790', // Pending Input
            '791', // Pending Processing
            '792', // AwaitingConsumer
            '800', // Deze iDeal-transactie is nog niet volledig verwerkt.
            '820', // Deze Giropay-transactie is nog niet volledig verwerkt.
        );
    }
}
