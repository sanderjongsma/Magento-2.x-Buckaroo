<?php
/**
 * Copyright © 2019 Appmerce - Applications for Ecommerce
 * http://www.appmerce.com
 */
namespace Appmerce\Buckaroo\Block\Info;

class Directdebit extends \Magento\Payment\Block\Info
{
    /**
     * @var string
     */
    protected $_template = 'Appmerce_Buckaroo::info/giropay.phtml';

    /**
     * @return string
     */
    public function toPdf()
    {
        $this->setTemplate('Appmerce_Buckaroo::pdf/info/giropay.phtml');
        return $this->toHtml();
    }
}
