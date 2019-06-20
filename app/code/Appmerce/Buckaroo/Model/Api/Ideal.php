<?php
/**
 * Copyright © 2019 Appmerce - Applications for Ecommerce
 * http://www.appmerce.com
 */
namespace Appmerce\Buckaroo\Model\Api;

class Ideal extends \Appmerce\Buckaroo\Model\Buckaroo
{
    const PAYMENT_METHOD_BUCKAROO_IDEAL_CODE = 'appmerce_buckaroo_ideal';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_BUCKAROO_IDEAL_CODE;
    
    /**
     * @var string
     */
    protected $_formBlockType = 'Appmerce\Buckaroo\Block\Form\Ideal';

    /**
     * @var string
     */
    protected $_infoBlockType = 'Appmerce\Buckaroo\Block\Info\Ideal';
}
