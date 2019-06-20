<?php
/**
 * Copyright © 2019 Appmerce - Applications for Ecommerce
 * http://www.appmerce.com
 */
namespace Appmerce\Buckaroo\Block\Info;

class Transfergarant extends \Magento\Payment\Block\Info
{
    /**
     * @var string
     */
    protected $_template = 'Appmerce_Buckaroo::info/transfergarant.phtml';

    /**
     * @return string
     */
    public function toPdf()
    {
        $this->setTemplate('Appmerce_Buckaroo::pdf/info/transfergarant.phtml');
        return $this->toHtml();
    }
}
