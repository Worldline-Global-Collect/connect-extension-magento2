/*browser:true*/
/*global define*/

define(
    [
        'jquery',
        'underscore',
        'Magento_Checkout/js/view/payment/default',
        'uiLayout',
        'uiRegistry',
        'Worldline_Connect/js/model/payment/payment-data',
        'Worldline_Connect/js/action/create-payload',
        'Magento_Checkout/js/action/redirect-on-success',
        'Worldline_Connect/js/model/payment/config',
        'Worldline_Connect/js/action/get-payment-product',
        'ko',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Customer/js/model/customer',
        'Worldline_Connect/js/model/payment-method/card-brand-matcher',
        'mage/translate',
        'Magento_Checkout/js/model/url-builder'
    ],
    function ($, _, Component, layout, registry, paymentData, createPayload, redirectOnSuccessAction, config, getPaymentProduct, ko, quote, fullScreenLoader, customer, cardBrandMatcher, $t) {
        'use strict';

        return Component.extend({
            config: {},

            defaults: {
                code: '',
                title: null,
                logo: null,
                currentCountry: '',
                product: null,
                template: 'Worldline_Connect/payment/method'
            },

            initialize: function () {
                this._super();
                this.title = ko.observable('');
                this.logo = ko.observable('');
                this.cardBrandMismatch = ko.observable(false);
                this.cardBrandErrorMessage = $t('The entered card type does not match the selected payment method.');
                paymentData.currentCardIinDetails.subscribe(this.checkCardBrand.bind(this));
                this.initChildren();
                return this;
            },

            initChildren: function () {
                this._super();
                let code = this.getCode();
                let name = this.name;
                let me = this;

                this.code = code;

                quote.billingAddress.subscribe(function (address) {
                    if (!address || $.isEmptyObject(address)) {
                        return;
                    }

                    let billingCountry = address.countryId;
                    if (billingCountry === me.currentCountry) {
                        return;
                    }

                    me.currentCountry = billingCountry;

                    let product = window.checkoutConfig.payment.worldline.products[code];
                    if (!product) {
                        return;
                    }

                    getPaymentProduct(product.id).then(function (productResponse) {
                        me.product = productResponse;
                        me.title(productResponse.displayHints.label);
                        me.logo(productResponse.displayHints.logo);
                        if (product.hosted) {
                            layout([{
                                component: 'Worldline_Connect/js/view/payment/component/collection/hosted',
                                uid: 'worldline-' + code + '-fields',
                                displayArea: 'worldline-cc-fields',
                                parent: name,
                                template: 'Worldline_Connect/payment/product/field-collection'
                            }]);
                        } else {
                            layout([{
                                component: 'Worldline_Connect/js/view/payment/component/collection/fields-inline',
                                uid: 'worldline-' + code + '-fields',
                                displayArea: 'worldline-cc-fields',
                                parent: name,
                                template: 'Worldline_Connect/payment/product/field-collection',
                                product: productResponse
                            }]);
                        }
                    });
                }.bind(this));

                return this;
            },

            createPayload: function () {
                let data = this.assemblePayloadData();
                return createPayload(data).then(function (payload) {
                    paymentData.setCurrentPayload(payload);
                });
            },

            assemblePayloadData: function () {
                let data = {};
                data['paymentProduct'] = this.product;
                if (paymentData.getCurrentAccountOnFile()) {
                    data['accountOnFile'] = paymentData.getCurrentAccountOnFile();
                }
                data = Object.assign(paymentData.fieldData, data);
                return data;
            },

            validate: function () {
                paymentData.fieldData = {};

                let product = window.checkoutConfig.payment.worldline.products[this.code];
                if (product.hosted) {
                    return true;
                }

                let fieldsValid = true;
                let activeFieldsCollection = registry.get('uid = worldline-' + this.code + '-fields');
                for (let fieldComponent of activeFieldsCollection.elems()) {
                    if (fieldComponent.field) {
                        paymentData.fieldData[fieldComponent.field.id] = fieldComponent.value();
                        if (!fieldComponent.validate().valid) {
                            fieldsValid = false;
                        }
                    }
                }

                if (this.cardBrandMismatch()) {
                    fieldsValid = false;
                }

                return fieldsValid;
            },

            /**
             * React to the brand the Worldline IIN lookup detected while the shopper types (the same
             * detection the grouped "Cards" flow uses, published on paymentData.currentCardIinDetails).
             *
             * The selected product id is sent to the gateway regardless of the card actually typed and
             * the encrypted card never reaches the server, so a mismatch can only be caught here. The
             * card's primary product AND its co-brands are considered, so a co-branded card (e.g. Carte
             * Bancaire + Mastercard) is accepted on any of its real brands and blocked on the rest.
             * Runs only for the active method; unknown methods never block.
             */
            checkCardBrand: function () {
                let selectedMethod = quote.paymentMethod();
                if (!selectedMethod || selectedMethod.method !== this.getCode()) {
                    return;
                }

                let product = window.checkoutConfig.payment.worldline.products[this.code];
                let iinDetails = paymentData.getCurrentCardIinDetails();
                let mismatch = Boolean(product) && !product.hosted && cardBrandMatcher.isKnownBrand(product.id)
                    && Boolean(iinDetails) && !cardBrandMatcher.matchesByIin(product.id, iinDetails);

                this.cardBrandMismatch(mismatch);
            },

            getData: function () {
                let product = window.checkoutConfig.payment.worldline.products[this.code];
                let flow = window.checkoutConfig.payment.worldline.orderCreationFlow;
                let flowAfter = window.checkoutConfig.payment.worldline.orderCreationFlowAfter;

                let data = {
                    'method': this.item.method,
                    'additional_data': {
                        'input': paymentData.getCurrentPayload(),
                        'product': product.id,
                        'tokenize': paymentData.tokenize().indexOf(product.id) !== -1,
                        'payment_flow': product.hosted ? 'hosted' : 'inline'
                    }
                };

                if (product.hosted && flow === flowAfter) {
                    data['cartId'] = quote.getQuoteId();
                }

                return data;
            },

            placeOrder: function (data, event) {
                if (window.checkoutConfig.payment.worldline.orderCreationFlow == null) {
                    return false;
                }

                var product = window.checkoutConfig.payment.worldline.products[this.code];

                if (window.checkoutConfig.payment.worldline.orderCreationFlow === window.checkoutConfig.payment.worldline.orderCreationFlowAfter) {
                    var self = this;

                    if (event && typeof event.preventDefault === 'function') {
                        event.preventDefault();
                    }

                    if (!this.validate()) {
                        return false;
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

                    var runPayment = function () {
                        try {
                            executePayment();
                        } catch (e) {
                            try { fullScreenLoader.stopLoader(); } catch (ee) {}
                            console.error('placeOrder unexpected error', e);
                            alert('Unexpected error. See console for details.');
                        }
                    };

                    if (product && product.hosted) {
                        runPayment();
                    } else {
                        paymentData.setCurrentPaymentProduct(this.product);
                        this.createPayload().then(runPayment, function (error) {
                            try { fullScreenLoader.stopLoader(); } catch (e) {}
                            console.error('Could not create payload.', error);
                            alert('Payment error: ' + error);
                        });
                    }
                } else {
                    let parentMethod = this._super.bind(this);
                    paymentData.setCurrentPaymentProduct(this.product);
                    if (!this.validate()) {
                        return false;
                    }
                    let product = window.checkoutConfig.payment.worldline.products[this.code];
                    if (product.hosted) {
                        parentMethod(data, event);
                    } else {
                        this.createPayload().then(function () {
                            parentMethod(data, event);
                        }, function (error) {
                            console.error('Could not create payload.', error);
                            alert('Payment error: ' + error);
                        });
                    }
                }
            },

            afterPlaceOrder: function () {
                var product = window.checkoutConfig.payment.worldline.products[this.code];

                if (!product.hosted || window.checkoutConfig.payment.worldline.orderCreationFlow === window.checkoutConfig.payment.worldline.orderCreationFlowBefore) {
                    if (paymentData.getCurrentPayload()) {
                        redirectOnSuccessAction.redirectUrl = config.getInlineSuccessUrl();
                    } else {
                        redirectOnSuccessAction.redirectUrl = config.getHostedCheckoutUrl();
                    }
                    this.redirectAfterPlaceOrder = true;
                }
            },
        });
    }
);
