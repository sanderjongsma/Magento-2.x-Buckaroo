<?php
/**
 * Copyright © 2019 Appmerce - Applications for Ecommerce
 * http://www.appmerce.com
 */
namespace Appmerce\Buckaroo\Controller\Push;

class Success extends \Appmerce\Buckaroo\Controller\Buckaroo
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

                // Walk through case insensitive $params for service keys
                $tr_method = strtolower($params['brq_transaction_method']);
                foreach ($params as $key => $param) {
                    $key = strtolower($key);
                    if (strpos($key, $tr_method) !== FALSE) {
                        $keys = explode('_', $key);
                        $order->getPayment()->setAdditionalInformation(ucfirst($keys[3]), $param);
                    }
                }
                $order->getPayment()->save();

                if (isset($params['brq_service_antifraud_action'])) {
                    $note .= '<br />' . __('Fraud: %s', $params['brq_service_antifraud_action']);
                    $this->processPending($order, $note, $params['brq_transactions']);
                }
                else {
                    $successCodes = $this->api->getSuccessCodes();
                    $pendingCodes = $this->api->getPendingCodes();
                    if (in_array($responseCode, $successCodes)) {
                        $note .= '<br />' . __('Payment Status: Success.');
                        $this->processSuccess($order, $note, $params['brq_transactions']);
                    }
                    elseif (in_array($responseCode, $pendingCodes)) {
                        $note .= '<br />' . __('Payment Status: Pending Payment.');
                        $this->processPending($order, $note, $params['brq_transactions']);
                    }
                    else {
                        $note .= '<br />' . __('Payment Status: Failed.');
                        $this->processCancel($order, $note, $params['brq_transactions']);
                    }
                }
            }
        }

        // Return HTTP OK
        http_response_code(200);
        return;
    }
}
