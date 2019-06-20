<?php
/**
 * Copyright © 2019 Appmerce - Applications for Ecommerce
 * http://www.appmerce.com
 */
namespace Appmerce\Buckaroo\Model\Api;

class Directdebit extends \Appmerce\Buckaroo\Model\Buckaroo
{
    const PAYMENT_METHOD_BUCKAROO_DIRECTDEBIT_CODE = 'appmerce_buckaroo_directdebit';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_BUCKAROO_DIRECTDEBIT_CODE;
    
    /**
     * @var string
     */
    protected $_formBlockType = 'Appmerce\Buckaroo\Block\Form\Directdebit';

    /**
     * @var string
     */
    protected $_infoBlockType = 'Appmerce\Buckaroo\Block\Info\Directdebit';
}
