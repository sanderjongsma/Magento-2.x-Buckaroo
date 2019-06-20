<?php
/**
 * Copyright © 2019 Appmerce - Applications for Ecommerce
 * http://www.appmerce.com
 */
namespace Appmerce\Buckaroo\Controller\Api;

class ReturnUser extends \Appmerce\Buckaroo\Controller\Buckaroo
{
    /**
     * @return void
     */
    public function execute()
    {
        // Status updated by push response
        $this->_redirect('checkout/onepage/success', array('_secure' => true));
    }
}
