/**
 * Copyright © 2019 Appmerce - Applications for Ecommerce
 * http://www.appmerce.com
 */
define(
        [
            'jquery',
            'Magento_Checkout/js/view/payment/default',
            'Magento_Checkout/js/action/place-order',
            'Magento_Checkout/js/action/select-payment-method',
            'Magento_Customer/js/model/customer',
            'Magento_Checkout/js/checkout-data',
            'Magento_Checkout/js/model/payment/additional-validators',
            'mage/url',
            "mage/validation"
        ],
        function ($, Component, placeOrderAction, selectPaymentMethodAction, customer, checkoutData, additionalValidators, url) {
            'use strict';
            return Component.extend({
                defaults: {
                    template: 'Appmerce_Buckaroo/payment/buckaroo-form'
                },
                initObservable: function () {
                    this._super();
                    return this;
                },
                getData: function () {
                    return {
                        "method": this.item.method,
                        "additional_data": null
                    };

                },
                validate: function () {
                    var form = 'form[data-role=buckaroo-form]';
                    return $(form).validation() && $(form).validation('isValid');
                },

                /**
                 * Place order (overridden).
                 */
                placeOrder: function (data, event) {
                    var self = this, placeOrder;

                    if (event) {
                        event.preventDefault();
                    }

                    if (this.validate() && additionalValidators.validate()) {
                        this.isPlaceOrderActionAllowed(false);
                        placeOrder = placeOrderAction(this.getData(), false, this.messageContainer);

                        $.when(placeOrder)
                                .fail(
                                        function () {
                                            self.isPlaceOrderActionAllowed(true);
                                        }
                                ).done(
                                this.afterPlaceOrder.bind(this)
                                );
                        return true;
                    }
                    return false;
                },

                selectPaymentMethod: function () {
                    selectPaymentMethodAction(this.getData());
                    checkoutData.setSelectedPaymentMethod(this.item.method);
                    return true;
                },

                afterPlaceOrder: function () {
                    $.get(url.build('buckaroo/soap/post/'))

                            // redirect to issuer
                            .done(function (data) {
                                if (data.url === false) {
                                    window.location.replace(url.build('buckaroo/api/error/'));
                                } else {
                                    window.location.replace(data.url);
                                }
                            })

                            // failure: cancel & retry
                            .fail(function (data) {
                                window.location.replace(url.build('buckaroo/api/error/'));
                            });
                }
            });
        }
);
