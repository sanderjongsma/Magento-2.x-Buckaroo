<?php
/**
 * Copyright © 2019 Appmerce - Applications for Ecommerce
 * http://www.appmerce.com
 */
namespace Appmerce\Buckaroo\Block\Html;

class Issuers extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \Appmerce\Buckaroo\Model\Buckaroo
     */
    protected $api;
    
    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Appmerce\Buckaroo\Model\Buckaroo $api
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Appmerce\Buckaroo\Model\Buckaroo $api,
        array $data = []
    ) {
        $this->api = $api;
        parent::__construct($context, $data);
    }

    /**
     * Get issuers
     */
    public function _getIssuers() 
    {
        $issuers = $this->api->getIssuers();
        return json_encode($issuers);
    } 
}
