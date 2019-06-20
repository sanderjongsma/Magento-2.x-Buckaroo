<?php
/**
 * Copyright © 2019 Appmerce - Applications for Ecommerce
 * http://www.appmerce.com
 */
namespace Appmerce\Buckaroo\Model\Api;

class Transfergarant extends \Appmerce\Buckaroo\Model\Buckaroo
{
    const PAYMENT_METHOD_BUCKAROO_TRANSFERGARANT_CODE = 'appmerce_buckaroo_transfergarant';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_BUCKAROO_TRANSFERGARANT_CODE;
    
    /**
     * @var string
     */
    protected $_formBlockType = 'Appmerce\Buckaroo\Block\Form\Transfergarant';

    /**
     * @var string
     */
    protected $_infoBlockType = 'Appmerce\Buckaroo\Block\Info\Transfergarant';
}
