<?php
/**
 * Copyright © 2019 Appmerce - Applications for Ecommerce
 * http://www.appmerce.com
 */
namespace Appmerce\Buckaroo\Block\Form;

use \Appmerce\Buckaroo\Model\Buckaroo;

class Ideal extends \Magento\Payment\Block\Form
{
    /**
     * Purchase order template
     *
     * @var string
     */
    protected $_template = 'Appmerce_Buckaroo::form/ideal.phtml';
    
    /**
     * Get issuers
     */
    public function _getIssuers() 
    {
        return $this->getIssuers($order);
    } 
}
