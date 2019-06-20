<?php
/**
 * Copyright © 2019 Appmerce - Applications for Ecommerce
 * http://www.appmerce.com
 */
namespace Appmerce\Buckaroo\Model\Api;

class Eps extends \Appmerce\Buckaroo\Model\Buckaroo
{
    const PAYMENT_METHOD_BUCKAROO_EPS_CODE = 'appmerce_buckaroo_eps';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_BUCKAROO_EPS_CODE;
}
