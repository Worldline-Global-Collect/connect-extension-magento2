/*browser:true*/
/*global define*/

define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/redirect-on-success',
        'Worldline_Connect/js/model/payment/config',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/model/customer',
        'Worldline_Connect/js/model/payment/payment-data',
    ],
    function ($, Component, redirectOnSuccessAction, config, fullScreenLoader, quote, customer, paymentData) {
        'use strict';

        return Component.extend({
            config: {},

            defaults: {
                template: 'Worldline_Connect/payment/hpp',
                code: ''
            },

            title: function () {
                config.init(window.checkoutConfig.payment['worldline']);
                return config.getHostedCheckoutTitle();
            },

            getData: function () {
                let flow = window.checkoutConfig.payment.worldline.orderCreationFlow;
                let flowAfter = window.checkoutConfig.payment.worldline.orderCreationFlowAfter;

                let data = {
                    'method': this.item.method,
                    'additional_data': {
                        'input': paymentData.getCurrentPayload(),
                        'payment_flow': 'hosted'
                    }
                };

                if (flow === flowAfter) {
                    data['cartId'] = quote.getQuoteId();
                }

                return data;
            },

            placeOrder: function (data, event) {
                if (window.checkoutConfig.payment.worldline.orderCreationFlow === window.checkoutConfig.payment.worldline.orderCreationFlowAfter) {
                    var self = this;

                    if (event && typeof event.preventDefault === 'function') {
                        event.preventDefault();
                    }

                    try {
                        fullScreenLoader.startLoader();
                    } catch (e) {
                        console.warn('fullScreenLoader.startLoader failed', e);
                    }

                    var executePayment = function () {
                        var payload = self.getData();
                        var serviceUrl;
                        var storeCode = window.checkoutConfig.storeCode || 'default';

                        try {
                            if (customer.isLoggedIn()) {
                                // Logged-in user
                                serviceUrl = window.BASE_URL + 'rest/' + storeCode + '/V1/carts/worldline-connect/create-payment';

                                payload = {
                                    cartId: quote.getQuoteId(),
                                    paymentMethod: {
                                        method: self.item.method,
                                        additional_data: payload.additional_data
                                    }
                                };

                            } else {
                                // Guest user
                                serviceUrl = window.BASE_URL + 'rest/' + storeCode + '/V1/guest-carts/' + quote.getQuoteId() + '/worldline-connect/create-payment';

                                payload = {
                                    cartId: quote.getQuoteId(),
                                    paymentMethod: {
                                        method: self.item.method,
                                        additional_data: payload.additional_data
                                    },
                                    email: quote.guestEmail || jQuery('[name="username"]').val()
                                };
                            }
                        } catch (e) {
                            console.error('Error building service URL', e);
                            try { fullScreenLoader.stopLoader(); } catch (ee) {}
                            alert('Unable to build payment URL.');
                            return;
                        }

                        $.ajax({
                            url: serviceUrl,
                            type: 'POST',
                            contentType: 'application/json',
                            data: JSON.stringify(payload),
                            success: function (response) {
                                console.log(response);
                                console.log(response[0]);
                                try { fullScreenLoader.stopLoader(); } catch (e) {}
                                if (response && response[0]) {
                                    window.location.replace(response[0]);
                                } else {
                                    console.error('No redirect URL returned', response);
                                    alert('No redirect URL returned from payment service.');
                                }
                            },
                            error: function (xhr) {
                                try { fullScreenLoader.stopLoader(); } catch (e) {}
                                console.error('Payment request failed', xhr);
                                alert('Payment initialization failed.');
                            },
                            timeout: 30000
                        });
                    };

                    try {
                        executePayment();
                    } catch (e) {
                        try { fullScreenLoader.stopLoader(); } catch (ee) {}
                        console.error('placeOrder unexpected error', e);
                        alert('Unexpected error. See console for details.');
                    }
                }

                if (window.checkoutConfig.payment.worldline.orderCreationFlow === window.checkoutConfig.payment.worldline.orderCreationFlowBefore) {
                    let parentMethod = this._super.bind(this);

                    parentMethod(data, event);
                }
            },

            afterPlaceOrder: function () {
                if (window.checkoutConfig.payment.worldline.orderCreationFlow === window.checkoutConfig.payment.worldline.orderCreationFlowBefore) {
                    redirectOnSuccessAction.redirectUrl = config.getHostedCheckoutUrl();
                    this.redirectAfterPlaceOrder = true;
                }
            },
        });
    }
);
