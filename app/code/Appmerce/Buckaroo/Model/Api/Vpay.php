<?php
/**
 * Copyright © 2019 Appmerce - Applications for Ecommerce
 * http://www.appmerce.com
 */
namespace Appmerce\Buckaroo\Model\Api;

class Vpay extends \Appmerce\Buckaroo\Model\Buckaroo
{
    const PAYMENT_METHOD_BUCKAROO_VPAY_CODE = 'appmerce_buckaroo_vpay';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_BUCKAROO_VPAY_CODE;
}
