/**
 * Copyright © 2019 Appmerce - Applications for Ecommerce
 * http://www.appmerce.com
 */
define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
],
function (Component, rendererList) {
    'use strict';
    rendererList.push(
        {
            type: 'appmerce_buckaroo_amex',
            component: 'Appmerce_Buckaroo/js/view/payment/method-renderer/buckaroo-method'
        },
        {
            type: 'appmerce_buckaroo_bcmc',
            component: 'Appmerce_Buckaroo/js/view/payment/method-renderer/buckaroo-method'
        },
        {
            type: 'appmerce_buckaroo_directdebit',
            component: 'Appmerce_Buckaroo/js/view/payment/method-renderer/buckaroo-directdebit-method'
        },
        {
            type: 'appmerce_buckaroo_eps',
            component: 'Appmerce_Buckaroo/js/view/payment/method-renderer/buckaroo-method'
        },
        {
            type: 'appmerce_buckaroo_giropay',
            component: 'Appmerce_Buckaroo/js/view/payment/method-renderer/buckaroo-giropay-method'
        },
        {
            type: 'appmerce_buckaroo_ideal',
            component: 'Appmerce_Buckaroo/js/view/payment/method-renderer/buckaroo-ideal-method'
        },
        {
            type: 'appmerce_buckaroo_idealprocessing',
            component: 'Appmerce_Buckaroo/js/view/payment/method-renderer/buckaroo-ideal-method'
        },
        {
            type: 'appmerce_buckaroo_maestro',
            component: 'Appmerce_Buckaroo/js/view/payment/method-renderer/buckaroo-method'
        },
        {
            type: 'appmerce_buckaroo_mastercard',
            component: 'Appmerce_Buckaroo/js/view/payment/method-renderer/buckaroo-method'
        },
        {
            type: 'appmerce_buckaroo_onlinegiro',
            component: 'Appmerce_Buckaroo/js/view/payment/method-renderer/buckaroo-method'
        },
        {
            type: 'appmerce_buckaroo_paypal',
            component: 'Appmerce_Buckaroo/js/view/payment/method-renderer/buckaroo-method'
        },
        {
            type: 'appmerce_buckaroo_payperemail',
            component: 'Appmerce_Buckaroo/js/view/payment/method-renderer/buckaroo-method'
        },
        {
            type: 'appmerce_buckaroo_paysafecard',
            component: 'Appmerce_Buckaroo/js/view/payment/method-renderer/buckaroo-method'
        },
        {
            type: 'appmerce_buckaroo_sofort',
            component: 'Appmerce_Buckaroo/js/view/payment/method-renderer/buckaroo-method'
        },
        {
            type: 'appmerce_buckaroo_transfer',
            component: 'Appmerce_Buckaroo/js/view/payment/method-renderer/buckaroo-method'
        },
        {
            type: 'appmerce_buckaroo_transfergarant',
            component: 'Appmerce_Buckaroo/js/view/payment/method-renderer/buckaroo-transfergarant-method'
        },
        {
            type: 'appmerce_buckaroo_ukash',
            component: 'Appmerce_Buckaroo/js/view/payment/method-renderer/buckaroo-method'
        },
        {
            type: 'appmerce_buckaroo_visa',
            component: 'Appmerce_Buckaroo/js/view/payment/method-renderer/buckaroo-method'
        },
        {
            type: 'appmerce_buckaroo_vpay',
            component: 'Appmerce_Buckaroo/js/view/payment/method-renderer/buckaroo-method'
        }
    );

    /** Add view logic here if needed */
        return Component.extend({});
    }
);
