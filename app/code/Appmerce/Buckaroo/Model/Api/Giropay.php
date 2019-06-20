<?php
/**
 * Copyright © 2019 Appmerce - Applications for Ecommerce
 * http://www.appmerce.com
 */
namespace Appmerce\Buckaroo\Model\Api;

class Giropay extends \Appmerce\Buckaroo\Model\Buckaroo
{
    const PAYMENT_METHOD_BUCKAROO_GIROPAY_CODE = 'appmerce_buckaroo_giropay';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_BUCKAROO_GIROPAY_CODE;
    
    /**
     * @var string
     */
    protected $_formBlockType = 'Appmerce\Buckaroo\Block\Form\Giropay';

    /**
     * @var string
     */
    protected $_infoBlockType = 'Appmerce\Buckaroo\Block\Info\Giropay';
}
