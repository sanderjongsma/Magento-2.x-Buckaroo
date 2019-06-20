<?php
/**
 * Copyright © 2019 Appmerce - Applications for Ecommerce
 * http://www.appmerce.com
 */
namespace Appmerce\Buckaroo\Controller\Push;

class Failure extends \Appmerce\Buckaroo\Controller\Buckaroo
{
    /**
     * Cancel payment
     *
     * @return void
     */
    public function execute()
    {
        $this->log->addDebug(__('Processing Buckaroo response...'));
        $params = $this->getRequest()->getParams();
        $this->log->addDebug(json_encode($params));

        $incrementId = $params['brq_invoicenumber'];
        if ($incrementId && isset($params['brq_signature']) && $order = $this->_getOrder()->loadByIncrementId($incrementId)) {
            if ($this->validateResponse($params, $order)) {
                $responseCode = $params['brq_statuscode'];
                $note = __('Response message: %1 (%2)', $params['brq_statusmessage'], $responseCode);

                $pendingCodes = $this->api->getPendingCodes();
                if (in_array($responseCode, $pendingCodes)) {
                    $this->processPending($order, $note, $params['brq_transactions']);
                }
                else {
                    $this->processCancel($order, $note, $params['brq_transactions']);
                }
            }
        }

        // Return HTTP OK
        http_response_code(200);
        return;
    }
}
