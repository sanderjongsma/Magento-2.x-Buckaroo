<?php
/**
 * Copyright © 2019 Appmerce - Applications for Ecommerce
 * http://www.appmerce.com
 */
namespace Appmerce\Buckaroo\Controller\Api;

class ErrorUser extends \Appmerce\Buckaroo\Controller\Buckaroo
{
    /**
     * @return void
     */
    public function execute()
    {
        // Status updated by push response
        $this->restoreCart();
        $this->_redirect('checkout/cart', array('_secure' => true));
    }
}
