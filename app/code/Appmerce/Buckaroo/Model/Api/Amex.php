<?php
/**
 * Copyright © 2019 Appmerce - Applications for Ecommerce
 * http://www.appmerce.com
 */
namespace Appmerce\Buckaroo\Model\Api;

class Amex extends \Appmerce\Buckaroo\Model\Buckaroo
{
    const PAYMENT_METHOD_BUCKAROO_AMEX_CODE = 'appmerce_buckaroo_amex';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_BUCKAROO_AMEX_CODE;
}
