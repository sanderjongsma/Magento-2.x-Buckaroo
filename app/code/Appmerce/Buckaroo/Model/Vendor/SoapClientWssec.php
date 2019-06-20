<?php
/**
 * Copyright © 2019 Appmerce - Applications for Ecommerce
 * http://www.appmerce.com
 */
namespace Appmerce\Buckaroo\Model\Vendor;

use DOMDocument;
use DOMXPath;
use SoapClient;
use \Appmerce\Buckaroo\Model\Buckaroo;

class SoapClientWssec extends SoapClient
{
    protected $_privateThumbprint;
    protected $_privateKey;
    
    public function __doRequest($request, $location, $action, $version, $one_way = 0)
    {
        // Add code to inspect/dissect/debug/adjust the XML given in $request here
        $domDOC = new DOMDocument();
        $domDOC->loadXML($request);
        
        //Sign the document
        $this->SignDomDocument($domDOC);

        // Uncomment the following line, if you actually want to do the request
        return parent::__doRequest($domDOC->saveXML($domDOC->documentElement), $location, $action, $version, $one_way);
    }
    
    /**
     * Set private key
     */
    public function setPrivateKey($value) 
    {
        $this->_privateKey = $value;        
    }
    
    /**
     * Set private key
     */
    public function setPrivateThumbprint($value) 
    {
        $this->_privateThumbprint = $value;        
    }
    
    /**
     * Buckaroo API 3.0
     */
    public function SignDomDocument($domDocument)
    {
        //create xPath
        $xPath = new DOMXPath($domDocument);

        //register namespaces to use in xpath query's
        $xPath->registerNamespace('wsse', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd');
        $xPath->registerNamespace('sig', 'http://www.w3.org/2000/09/xmldsig#');
        $xPath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');

        //Set id on soap body to easily extract the body later.
        $bodyNodeList = $xPath->query('/soap:Envelope/soap:Body');
        $bodyNode = $bodyNodeList->item(0);
        $bodyNode->setAttribute('Id', '_body');

        //Get the digest values
        $controlHash = $this->CalculateDigestValue($this->GetCanonical($this->GetReference('_control', $xPath)));
        $bodyHash = $this->CalculateDigestValue($this->GetCanonical($this->GetReference('_body', $xPath)));

        //Set the digest value for the control reference
        $Control = '#_control';
        $controlHashQuery = $query = '//*[@URI="' . $Control . '"]/sig:DigestValue';
        $controlHashQueryNodeset = $xPath->query($controlHashQuery);
        $controlHashNode = $controlHashQueryNodeset->item(0);
        $controlHashNode->nodeValue = $controlHash;

        //Set the digest value for the body reference
        $Body = '#_body';
        $bodyHashQuery = $query = '//*[@URI="' . $Body . '"]/sig:DigestValue';
        $bodyHashQueryNodeset = $xPath->query($bodyHashQuery);
        $bodyHashNode = $bodyHashQueryNodeset->item(0);
        $bodyHashNode->nodeValue = $bodyHash;

        //Get the SignedInfo nodeset
        $SignedInfoQuery = '//wsse:Security/sig:Signature/sig:SignedInfo';
        $SignedInfoQueryNodeSet = $xPath->query($SignedInfoQuery);
        $SignedInfoNodeSet = $SignedInfoQueryNodeSet->item(0);

        //Canonicalize nodeset
        $signedINFO = $this->GetCanonical($SignedInfoNodeSet);

        //Get privatekey certificate
        $file_path = $this->_privateKey;
        $fp = fopen($file_path, "r");
        $priv_key = fread($fp, 8192);
        fclose($fp);
        $pkeyid = openssl_get_privatekey($priv_key, '');

        //Sign signedinfo with privatekey
        $signature2;
        openssl_sign($signedINFO, $signature2, $pkeyid);

        //Add signature value to xml document
        $sigValQuery = '//wsse:Security/sig:Signature/sig:SignatureValue';
        $sigValQueryNodeset = $xPath->query($sigValQuery);
        $sigValNodeSet = $sigValQueryNodeset->item(0);
        $sigValNodeSet->nodeValue = base64_encode($signature2);

        //Get signature node
        $sigQuery = '//wsse:Security/sig:Signature';
        $sigQueryNodeset = $xPath->query($sigQuery);
        $sigNodeSet = $sigQueryNodeset->item(0);

        //Create keyinfo element and Add public key to KeyIdentifier element
        $KeyTypeNode = $domDocument->createElementNS("http://www.w3.org/2000/09/xmldsig#", "KeyInfo");
        $SecurityTokenReference = $domDocument->createElementNS('http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd', 'SecurityTokenReference');
        $KeyIdentifier = $domDocument->createElement("KeyIdentifier");
        $KeyIdentifier->nodeValue = $this->_privateThumbprint;
        $KeyIdentifier->setAttribute('ValueType', 'http://docs.oasis-open.org/wss/oasis-wss-soap-message-security-1.1#ThumbPrintSHA1');
        $SecurityTokenReference->appendChild($KeyIdentifier);
        $KeyTypeNode->appendChild($SecurityTokenReference);
        $sigNodeSet->appendChild($KeyTypeNode);
    }

    /**
     * Get nodeset based on xpath and ID
     */
    public function GetReference($ID, $xPath)
    {
        $query = '//*[@Id="' . $ID . '"]';
        $nodeset = $xPath->query($query);
        $Object = $nodeset->item(0);
        return $Object;
    }

    /**
     * Canonicalize nodeset
     */
    public function GetCanonical($Object)
    {
        $output = $Object->C14N(true, false);
        return $output;
    }

    /**
     * Calculate digest value (sha1 hash)
     */
    public function CalculateDigestValue($input)
    {
        $digValueControl = base64_encode(pack("H*", sha1($input)));
        return $digValueControl;
    }
}
