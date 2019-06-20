<?php
/**
 * Copyright © 2019 Appmerce - Applications for Ecommerce
 * http://www.appmerce.com
 */
namespace Appmerce\Buckaroo\Controller\Api;

class Error extends \Appmerce\Buckaroo\Controller\Buckaroo
{
    /**
     * Payment error
     *
     * @return void
     */
    public function execute()
    {
        $lastIncrementId = $this->checkoutSession->getLastRealOrder()->getIncrementId();
        $order = $this->_getOrder()->loadByIncrementId($lastIncrementId);
        $note = __('Payment Status: Cancelled.');
        $this->processCancel($order, $note, $lastIncrementId);
        $this->restoreCart();
        $this->_redirect('checkout/cart', array('_secure' => true));
    }

}

