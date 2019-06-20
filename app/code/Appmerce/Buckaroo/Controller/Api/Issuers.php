<?php
/**
 * Copyright © 2019 Appmerce - Applications for Ecommerce
 * http://www.appmerce.com
 */
namespace Appmerce\Buckaroo\Controller\Api;

class Issuers extends \Appmerce\Buckaroo\Controller\Buckaroo
{
    /**
     * Return JSON issuer fields
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $issuers = $this->api->getIssuers();

        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultJsonFactory->create();
        return $resultJson->setData($issuers);
    }
}

