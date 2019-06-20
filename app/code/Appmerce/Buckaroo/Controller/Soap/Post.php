<?php
/**
 * Copyright © 2019 Appmerce - Applications for Ecommerce
 * http://www.appmerce.com
 */
namespace Appmerce\Buckaroo\Controller\Soap;

use stdClass;
use SOAPHeader;

use \Appmerce\Buckaroo\Model\Vendor\Body;
use \Appmerce\Buckaroo\Model\Vendor\CanonicalizationMethodType;
use \Appmerce\Buckaroo\Model\Vendor\DigestMethodType;
use \Appmerce\Buckaroo\Model\Vendor\Header;
use \Appmerce\Buckaroo\Model\Vendor\IpAddress;
use \Appmerce\Buckaroo\Model\Vendor\MessageControlBlock;
use \Appmerce\Buckaroo\Model\Vendor\ReferenceType;
use \Appmerce\Buckaroo\Model\Vendor\RequestParameter;
use \Appmerce\Buckaroo\Model\Vendor\SecurityType;
use \Appmerce\Buckaroo\Model\Vendor\Service;
use \Appmerce\Buckaroo\Model\Vendor\Services;
use \Appmerce\Buckaroo\Model\Vendor\SignatureType;
use \Appmerce\Buckaroo\Model\Vendor\SignatureMethodType;
use \Appmerce\Buckaroo\Model\Vendor\SignedInfoType;
use \Appmerce\Buckaroo\Model\Vendor\SoapClientWssec;
use \Appmerce\Buckaroo\Model\Vendor\TransformType;

class Post extends \Appmerce\Buckaroo\Controller\Buckaroo
{
    const PRIVATE_KEY_PATH = 'pub/media/appmerce/buckaroo/key/';
    
    /**
     * Return JSON form fields
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $error = $url = false;
        $data = array('url' => $url, 'error' => $error);
        $incrementId = $this->checkoutSession->getLastRealOrder()->getIncrementId();
        if ($incrementId && $order = $this->_getOrder()->loadByIncrementId($incrementId)) {
            $storeId = $order->getStoreId();
            $paymentMethodCode = $order->getPayment()->getMethod();
            $address = $order->getBillingAddress();
            $shipping = $order->getShippingAddress();
            if (!$shipping || !is_object($shipping)) {
                $shipping = $address;
            }
    
            $wsdl_url = $this->getGatewayUrl('wsdl', $order);
            $client = new SoapClientWssec($wsdl_url, array('trace' => 1));
    
            $client->setPrivateThumbPrint($this->api->getServiceConfigData('private_thumbprint', $order->getStoreId()));
            $client->setPrivateKey($this->api->getLocalPath(self::PRIVATE_KEY_PATH, 'private_key', $order->getStoreId()));
     
            $TransactionRequest = new Body();
            
            // Order details
            $TransactionRequest->Currency = $this->getCurrencyCode();
            $TransactionRequest->AmountDebit = $this->_getGrandTotal($order);
            // $TransactionRequest->AmountCredit = $this->_getGrandTotal($order);
            $TransactionRequest->Invoice = $order->getIncrementId();
            $TransactionRequest->Description = __('Order %1', $order->getIncrementId());
            $TransactionRequest->ClientIP = new IpAddress();
            $TransactionRequest->ClientIP->Type = self::IP_TYPE;
            $TransactionRequest->ClientIP->_ = $this->getRealIpAddr();
            $TransactionRequest->ReturnURL = $this->getApiUrl('returnUser', $storeId);
            $TransactionRequest->ReturnURLCancel = $this->getApiUrl('cancelUser', $storeId);
            $TransactionRequest->ReturnURLError = $this->getApiUrl('errorUser', $storeId);
            $TransactionRequest->ReturnURLReject = $this->getApiUrl('rejectUser', $storeId);
            // $TransactionRequest->OriginalTransactionKey = false;
            $TransactionRequest->ContinueOnIncomplete = 'RedirectToHTML';
            $TransactionRequest->StartRecurrent = false;
    
            // Service details
            $TransactionRequest->Services = new Services();
            $TransactionRequest->Services->Service = new Service();
            $TransactionRequest->Services->Service->Action = self::ACTION_PAY;
    
            // Method-specific service details
            switch ($paymentMethodCode) {
                case 'appmerce_buckaroo_bcmc' :
                    // Refund action = 'Reversal'
                    $TransactionRequest->Services->Service->Name = 'bancontactmrcash';
                    break;
    
                case 'appmerce_buckaroo_directdebit' :
                    $TransactionRequest->Services->Service->Name = 'directdebit';
    
                    // Service request parameters (multiple)
                    $TransactionRequest->Services->Service->RequestParameter = array();
    
                    $TransactionRequest->Services->Service->RequestParameter[0] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[0]->Name = 'customeraccountname';
                    $TransactionRequest->Services->Service->RequestParameter[0]->_ = $order->getPayment()->getAdditionalInformation('customer_account_name');
    
                    $TransactionRequest->Services->Service->RequestParameter[1] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[1]->Name = 'customeraccountnumber';
                    $TransactionRequest->Services->Service->RequestParameter[1]->_ = $order->getPayment()->getAdditionalInformation('customer_account_number');
                    break;
    
                case 'appmerce_buckaroo_giropay' :
                    $TransactionRequest->Services->Service->Name = 'giropay';
    
                    // Service request parameters
                    $TransactionRequest->Services->Service->RequestParameter = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter->Name = 'Bankleitzahl';
                    $TransactionRequest->Services->Service->RequestParameter->_ = $order->getPayment()->getAdditionalInformation('bankleitzahl');
                    break;
    
                case 'appmerce_buckaroo_ideal' :
                    $TransactionRequest->Services->Service->Name = 'ideal';
    
                    // Service request parameters
                    $TransactionRequest->Services->Service->RequestParameter = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter->Name = 'issuer';
                    $TransactionRequest->Services->Service->RequestParameter->_ = $order->getPayment()->getAdditionalInformation('issuer_id');
                    break;
    
                case 'appmerce_buckaroo_idealprocessing' :
                    $TransactionRequest->Services->Service->Name = 'idealprocessing';
    
                    // Service request parameters
                    $TransactionRequest->Services->Service->RequestParameter = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter->Name = 'issuer';
                    $TransactionRequest->Services->Service->RequestParameter->_ = $order->getPayment()->getAdditionalInformation('issuer_id');
                    break;
    
                case 'appmerce_buckaroo_onlinegiro' :
                    $TransactionRequest->Services->Service->Action = self::ACTION_PAYMENTINVITATION;
                    $TransactionRequest->Services->Service->Name = 'onlinegiro';
    
                    // Service request parameters (multiple)
                    $TransactionRequest->Services->Service->RequestParameter = array();
    
                    $customerGender = $this->api->getBuckarooGenderCode($order->getCustomerGender());
                    $TransactionRequest->Services->Service->RequestParameter[0] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[0]->Name = 'customergender';
                    $TransactionRequest->Services->Service->RequestParameter[0]->_ = !empty($customerGender) ? $customerGender : '0';
    
                    $TransactionRequest->Services->Service->RequestParameter[1] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[1]->Name = 'customeremail';
                    $TransactionRequest->Services->Service->RequestParameter[1]->_ = $order->getCustomerEmail();
    
                    $TransactionRequest->Services->Service->RequestParameter[2] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[2]->Name = 'customerfirstname';
                    $TransactionRequest->Services->Service->RequestParameter[2]->_ = $address->getFirstname();
    
                    $TransactionRequest->Services->Service->RequestParameter[3] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[3]->Name = 'customerlastname';
                    $TransactionRequest->Services->Service->RequestParameter[3]->_ = $address->getLastname();
                    break;
    
                case 'appmerce_buckaroo_paypal' :
                    $TransactionRequest->Services->Service->Name = 'paypal';
                    break;
    
                case 'appmerce_buckaroo_payperemail' :
                    $TransactionRequest->Services->Service->Action = self::ACTION_PAYMENTINVITATION;
                    $TransactionRequest->Services->Service->Name = 'payperemail';
    
                    // Service request parameters (multiple)
                    $TransactionRequest->Services->Service->RequestParameter = array();
    
                    $customerGender = $this->api->getBuckarooGenderCode($order->getCustomerGender());
                    $TransactionRequest->Services->Service->RequestParameter[0] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[0]->Name = 'customergender';
                    $TransactionRequest->Services->Service->RequestParameter[0]->_ = !empty($customerGender) ? $customerGender : '0';
    
                    $TransactionRequest->Services->Service->RequestParameter[1] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[1]->Name = 'customeremail';
                    $TransactionRequest->Services->Service->RequestParameter[1]->_ = $order->getCustomerEmail();
    
                    $TransactionRequest->Services->Service->RequestParameter[2] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[2]->Name = 'customerfirstname';
                    $TransactionRequest->Services->Service->RequestParameter[2]->_ = $address->getFirstname();
    
                    $TransactionRequest->Services->Service->RequestParameter[3] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[3]->Name = 'customerlastname';
                    $TransactionRequest->Services->Service->RequestParameter[3]->_ = $address->getLastname();
                    break;
    
                case 'appmerce_buckaroo_paysafecard' :
                    $TransactionRequest->Services->Service->Name = 'paysafecard';
                    break;
    
                case 'appmerce_buckaroo_sofort' :
                    $TransactionRequest->Services->Service->Name = 'sofortueberweisung';
                    break;
    
                case 'appmerce_buckaroo_visa' :
                    $TransactionRequest->Services->Service->Name = 'visa';
                    break;
    
                case 'appmerce_buckaroo_mastercard' :
                    $TransactionRequest->Services->Service->Name = 'mastercard';
                    break;
                    
                case 'appmerce_buckaroo_maestro' :
                    $TransactionRequest->Services->Service->Name = 'maestro';
                    break;
                    
                case 'appmerce_buckaroo_vpay' :
                    $TransactionRequest->Services->Service->Name = 'vpay';
                    break;
                    
                case 'appmerce_buckaroo_eps' :
                    $TransactionRequest->Services->Service->Name = 'eps';
                    break;
    
                case 'appmerce_buckaroo_amex' :
                    $TransactionRequest->Services->Service->Name = 'amex';
                    break;
    
                case 'appmerce_buckaroo_transfer' :
                    $TransactionRequest->Services->Service->Name = 'transfer';
    
                    // Service request parameters (multiple)
                    $TransactionRequest->Services->Service->RequestParameter = array();
    
                    $customerGender = $this->api->getBuckarooGenderCode($order->getCustomerGender());
                    $TransactionRequest->Services->Service->RequestParameter[0] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[0]->Name = 'CustomerGender';
                    $TransactionRequest->Services->Service->RequestParameter[0]->_ = !empty($customerGender) ? $customerGender : '0';
    
                    $TransactionRequest->Services->Service->RequestParameter[1] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[1]->Name = 'customeremail';
                    $TransactionRequest->Services->Service->RequestParameter[1]->_ = $order->getCustomerEmail();
    
                    $TransactionRequest->Services->Service->RequestParameter[2] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[2]->Name = 'customerfirstname';
                    $TransactionRequest->Services->Service->RequestParameter[2]->_ = $address->getFirstname();
    
                    $TransactionRequest->Services->Service->RequestParameter[3] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[3]->Name = 'customerlastname';
                    $TransactionRequest->Services->Service->RequestParameter[3]->_ = $address->getLastname();
                    break;
    
                case 'appmerce_buckaroo_transfergarant' :
                    $TransactionRequest->Services->Service->Action = self::ACTION_PAYMENTINVITATION;
                    $TransactionRequest->Services->Service->Name = 'paymentguarantee';
    
                    $customerDob = $order->getCustomerDob();
                    $locale_language = $this->api->getPaymentConfigData($paymentMethodCode, 'locale_language', $storeId);
                    $locale_country = $this->api->getPaymentConfigData($paymentMethodCode, 'locale_country', $storeId);
    
                    // Service request parameters (multiple)
                    $TransactionRequest->Services->Service->RequestParameter = array();
    
                    $TransactionRequest->Services->Service->RequestParameter[0] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[0]->Name = 'AmountVat';
                    $TransactionRequest->Services->Service->RequestParameter[0]->_ = $this->_getTaxAmount($order);
    
                    $invoiceDate = date('Y-m-d', strtotime('+' . intval($this->api->getPaymentConfigData($paymentMethodCode, 'invoice_days')) . ' days'));
                    $TransactionRequest->Services->Service->RequestParameter[1] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[1]->Name = 'InvoiceDate';
                    $TransactionRequest->Services->Service->RequestParameter[1]->_ = $invoiceDate;
    
                    $dueDate = date('Y-m-d', strtotime('+' . intval($this->api->getPaymentConfigData($paymentMethodCode, 'invoice_days') + 14) . ' days'));
                    $TransactionRequest->Services->Service->RequestParameter[2] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[2]->Name = 'DateDue';
                    $TransactionRequest->Services->Service->RequestParameter[2]->_ = $dueDate;
    
                    $TransactionRequest->Services->Service->RequestParameter[3] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[3]->Name = 'CustomerCode';
                    $TransactionRequest->Services->Service->RequestParameter[3]->_ = $order->getCustomerEmail();
    
                    $TransactionRequest->Services->Service->RequestParameter[4] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[4]->Name = 'CustomerInitials';
                    $TransactionRequest->Services->Service->RequestParameter[4]->_ = substr($address->getFirstname(), 0, 1) . substr($address->getLastname(), 0, 1);
    
                    $TransactionRequest->Services->Service->RequestParameter[5] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[5]->Name = 'CustomerFirstName';
                    $TransactionRequest->Services->Service->RequestParameter[5]->_ = substr($address->getFirstname(), 0, 200);
    
                    $TransactionRequest->Services->Service->RequestParameter[6] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[6]->Name = 'CustomerLastName';
                    $TransactionRequest->Services->Service->RequestParameter[6]->_ = substr($address->getLastname(), 0, 200);
    
                    $customerGender = $this->api->getBuckarooGenderCode($order->getCustomerGender());
                    $TransactionRequest->Services->Service->RequestParameter[7] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[7]->Name = 'CustomerGender';
                    $TransactionRequest->Services->Service->RequestParameter[7]->_ = !empty($customerGender) ? $customerGender : '0';
    
                    $TransactionRequest->Services->Service->RequestParameter[8] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[8]->Name = 'CustomerBirthDate';
                    $TransactionRequest->Services->Service->RequestParameter[8]->_ = !empty($customerDob) ? substr($customerDob, 0, 10) : date('Y-m-d');
    
                    $TransactionRequest->Services->Service->RequestParameter[9] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[9]->Name = 'CustomerEmail';
                    $TransactionRequest->Services->Service->RequestParameter[9]->_ = substr($order->getCustomerEmail(), 0, 255);
    
                    $TransactionRequest->Services->Service->RequestParameter[10] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[10]->Name = 'PhoneNumber';
                    $TransactionRequest->Services->Service->RequestParameter[10]->_ = $address->getTelephone();
    
                    $TransactionRequest->Services->Service->RequestParameter[11] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[11]->Name = 'CustomerAccountNumber';
                    $TransactionRequest->Services->Service->RequestParameter[11]->_ = $order->getPayment()->getAdditionalInformation('customer_account_number');
    
                    // Addresses: Billing address
                    $TransactionRequest->Services->Service->RequestParameter[12] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[12]->Name = 'AddressType';
                    $TransactionRequest->Services->Service->RequestParameter[12]->Group = 'Address';
                    $TransactionRequest->Services->Service->RequestParameter[12]->GroupID = 'Billing';
                    $TransactionRequest->Services->Service->RequestParameter[12]->_ = 'INVOICE';
    
                    // Split billing address street / number (Magento stores both in 1 field)
                    // @todo test this
                    preg_match('/([^\d]+)\s?(.+)/i', str_replace("\n", ' ', $address->getStreet(-1)), $result);
                    $billingStreetName = $result[1];
                    $billingStreetNumber = $result[2];
                    $TransactionRequest->Services->Service->RequestParameter[13] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[13]->Name = 'Street';
                    $TransactionRequest->Services->Service->RequestParameter[13]->Group = 'Address';
                    $TransactionRequest->Services->Service->RequestParameter[13]->GroupID = 'Billing';
                    $TransactionRequest->Services->Service->RequestParameter[13]->_ = $billingStreetName;
    
                    $TransactionRequest->Services->Service->RequestParameter[14] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[14]->Name = 'HouseNumber';
                    $TransactionRequest->Services->Service->RequestParameter[14]->Group = 'Address';
                    $TransactionRequest->Services->Service->RequestParameter[14]->GroupID = 'Billing';
                    $TransactionRequest->Services->Service->RequestParameter[14]->_ = $billingStreetNumber;
    
                    $TransactionRequest->Services->Service->RequestParameter[15] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[15]->Name = 'ZipCode';
                    $TransactionRequest->Services->Service->RequestParameter[15]->Group = 'Address';
                    $TransactionRequest->Services->Service->RequestParameter[15]->GroupID = 'Billing';
                    $TransactionRequest->Services->Service->RequestParameter[15]->_ = $address->getPostcode();
    
                    $TransactionRequest->Services->Service->RequestParameter[16] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[16]->Name = 'City';
                    $TransactionRequest->Services->Service->RequestParameter[16]->Group = 'Address';
                    $TransactionRequest->Services->Service->RequestParameter[16]->GroupID = 'Billing';
                    $TransactionRequest->Services->Service->RequestParameter[16]->_ = $address->getCity();
    
                    $TransactionRequest->Services->Service->RequestParameter[17] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[17]->Name = 'Country';
                    $TransactionRequest->Services->Service->RequestParameter[17]->Group = 'Address';
                    $TransactionRequest->Services->Service->RequestParameter[17]->GroupID = 'Billing';
                    $TransactionRequest->Services->Service->RequestParameter[17]->_ = $address->getCountryId();
    
                    // Addresses: Shipping address
                    $TransactionRequest->Services->Service->RequestParameter[18] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[18]->Name = 'AddressType';
                    $TransactionRequest->Services->Service->RequestParameter[18]->Group = 'Address';
                    $TransactionRequest->Services->Service->RequestParameter[18]->GroupID = 'Shipping';
                    $TransactionRequest->Services->Service->RequestParameter[18]->_ = 'SHIPPING';
    
                    // Split billing address street / number (Magento stores both in 1 field)
                    preg_match('/([^\d]+)\s?(.+)/i', str_replace("\n", ' ', $shipping->getStreet(-1)), $result);
                    $billingStreetName = $result[1];
                    $billingStreetNumber = $result[2];
                    $TransactionRequest->Services->Service->RequestParameter[19] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[19]->Name = 'Street';
                    $TransactionRequest->Services->Service->RequestParameter[19]->Group = 'Address';
                    $TransactionRequest->Services->Service->RequestParameter[19]->GroupID = 'Shipping';
                    $TransactionRequest->Services->Service->RequestParameter[19]->_ = $billingStreetName;
    
                    $TransactionRequest->Services->Service->RequestParameter[20] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[20]->Name = 'HouseNumber';
                    $TransactionRequest->Services->Service->RequestParameter[20]->Group = 'Address';
                    $TransactionRequest->Services->Service->RequestParameter[20]->GroupID = 'Shipping';
                    $TransactionRequest->Services->Service->RequestParameter[20]->_ = $billingStreetNumber;
    
                    $TransactionRequest->Services->Service->RequestParameter[21] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[21]->Name = 'ZipCode';
                    $TransactionRequest->Services->Service->RequestParameter[21]->Group = 'Address';
                    $TransactionRequest->Services->Service->RequestParameter[21]->GroupID = 'Shipping';
                    $TransactionRequest->Services->Service->RequestParameter[21]->_ = $shipping->getPostcode();
    
                    $TransactionRequest->Services->Service->RequestParameter[22] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[22]->Name = 'City';
                    $TransactionRequest->Services->Service->RequestParameter[22]->Group = 'Address';
                    $TransactionRequest->Services->Service->RequestParameter[22]->GroupID = 'Shipping';
                    $TransactionRequest->Services->Service->RequestParameter[22]->_ = $shipping->getCity();
    
                    $TransactionRequest->Services->Service->RequestParameter[23] = new RequestParameter();
                    $TransactionRequest->Services->Service->RequestParameter[23]->Name = 'Country';
                    $TransactionRequest->Services->Service->RequestParameter[23]->Group = 'Address';
                    $TransactionRequest->Services->Service->RequestParameter[23]->GroupID = 'Shipping';
                    $TransactionRequest->Services->Service->RequestParameter[23]->_ = $shipping->getCountryId();
    
                    break;
    
                case 'appmerce_buckaroo_ukash' :
                    $TransactionRequest->Services->Service->Name = 'Ukash';
                    break;
    
                default :
            }
    
            // Soap headers
            $Header = new Header();
            $Header->MessageControlBlock = new MessageControlBlock();
            $Header->MessageControlBlock->Id = '_control';
            $Header->MessageControlBlock->WebsiteKey = $this->api->getServiceConfigData('website_key', $storeId);
    
            // Buckaroo locale supports only nl-NL and en-US
            $locale = $this->localeResolver->getLocale();
            if (substr($locale, 0, 2) == 'nl') {
                $Header->MessageControlBlock->Culture = 'nl-NL';
            }
            else {
                $Header->MessageControlBlock->Culture = 'en-US';
            }
            $Header->MessageControlBlock->TimeStamp = $_SERVER['REQUEST_TIME'];
            $Header->MessageControlBlock->Channel = self::CHANNEL;
    
            // Security signature
            $Header->Security = new SecurityType();
            $Header->Security->Signature = new SignatureType();
            $Header->Security->Signature->SignedInfo = new SignedInfoType();
            $Header->Security->Signature->SignedInfo->CanonicalizationMethod = new CanonicalizationMethodType();
            $Header->Security->Signature->SignedInfo->CanonicalizationMethod->Algorithm = 'http://www.w3.org/2001/10/xml-exc-c14n#';
            $Header->Security->Signature->SignedInfo->SignatureMethod = new SignatureMethodType();
            $Header->Security->Signature->SignedInfo->SignatureMethod->Algorithm = 'http://www.w3.org/2000/09/xmldsig#rsa-sha1';
    
            // Reference control
            $Reference = new ReferenceType();
            $Reference->URI = '#_body';
            $Transform = new TransformType();
            $Transform->Algorithm = 'http://www.w3.org/2001/10/xml-exc-c14n#';
            $Reference->Transforms = array($Transform);
            $Reference->DigestMethod = new DigestMethodType();
            $Reference->DigestMethod->Algorithm = 'http://www.w3.org/2000/09/xmldsig#sha1';
            $Reference->DigestValue = '';
            $Transform2 = new TransformType();
            $Transform2->Algorithm = 'http://www.w3.org/2001/10/xml-exc-c14n#';
            $ReferenceControl = new ReferenceType();
            $ReferenceControl->URI = '#_control';
            $ReferenceControl->DigestMethod = new DigestMethodType();
            $ReferenceControl->DigestMethod->Algorithm = 'http://www.w3.org/2000/09/xmldsig#sha1';
            $ReferenceControl->DigestValue = '';
            $ReferenceControl->Transforms = array($Transform2);
    
            $Header->Security->Signature->SignedInfo->Reference = array(
                $Reference,
                $ReferenceControl
            );
            $Header->Security->Signature->SignatureValue = '';
    
            // Soap headers
            $soapHeaders[] = new SOAPHeader('https://checkout.buckaroo.nl/PaymentEngine/', 'MessageControlBlock', $Header->MessageControlBlock);
            $soapHeaders[] = new SOAPHeader('http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd', 'Security', $Header->Security);
            $client->__setSoapHeaders($soapHeaders);
    
            $soap_url = $this->getGatewayUrl('soap', $order);
            $client->__SetLocation($soap_url);
            try {
                $response = $client->TransactionRequest($TransactionRequest);
            }
            catch (\Exception $e) {
                $this->log->addDebug(json_encode($e));
                $error = $e->faultstring;
            }

            // Debug
            $this->log->addDebug(json_encode($TransactionRequest));
            $this->log->addDebug(json_encode($response));
    
            // Decide where to redirect
            if (isset($response->Status)) {
                if (isset($response->RequiredAction) && $response->RequiredAction->Type == 'Redirect') {
                    $url = $response->RequiredAction->RedirectURL;
                    $transactionKey = $response->Key;
                    $responseCode = $response->Status->Code->Code;
                    $note = __('Response message: %1 (%2)', $response->Status->Code->_, $responseCode);
    
                    $order->getPayment()->setAdditionalInformation('transaction_key', $transactionKey)
                                        ->setLastTransId($transactionKey)
                                        ->save();
    
                    // Setting a History is required to store Payment Info!
                    $paymentMethodCode = $order->getPayment()->getMethod();
                    $status = $this->getOrderStatus($paymentMethodCode);
                    $order->setState(\Magento\Sales\Model\Order::STATE_NEW)
                          ->setStatus($status)
                          ->addStatusHistoryComment(__('Initiated payment process.'))
                          ->setIsCustomerNotified(true)
                          ->save();
                }
                else {
                    $error = $response->Status->SubCode->_;
                }
            }
            else {
                $error = __('Could not read response from Buckaroo.');
            }
        }

        // Return json for cart ajax
        $data['url'] = $url;
        $data['error'] = $error;

        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultJsonFactory->create();
        return $resultJson->setData($data);
    }
}
