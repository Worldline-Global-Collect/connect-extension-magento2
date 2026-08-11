define([
    'jquery',
    'uiCollection',
    'uiLayout',
    'Worldline_Connect/js/action/get-payment-product',
    'Worldline_Connect/js/model/payment/config',
    'Worldline_Connect/js/model/payment/payment-data',
    'mage/translate',
    'uiRegistry',
    'ko'
], function ($, Collection, layout, fetchProduct, config, paymentData, $t, registry, ko) {
    'use strict';

    const DEFAULT_SORT_ORDER_REDIRECT = 1;
    const DEFAULT_SORT_ORDER_CARD_NUMBER = 2;
    const DEFAULT_SORT_ORDER_FIELD = 3;
    const DEFAULT_SORT_ORDER_CARDHOLDER_NAME = 500;
    const DEFAULT_SORT_ORDER_TOKENIZE_CHECKBOX = 10000;

    return Collection.extend({

        defaults: {
            isLoading: false,
            visible: true,
            containerVisible: false,
            fieldsLoaded: false,
        },

        initObservable() {
            return this._super()
                .observe([
                    'visible',
                    'containerVisible',
                    'isLoading',
                ]);
        },

        initialize: function () {
            this._super();
            this.initLoader();
            this.fieldsVisiblity(true);

            paymentData.currentCardPaymentProduct.subscribe(this.onCardProductUpdate.bind(this));

            if (!this.fieldsLoaded) {
                this.isLoading(true);

                layout(this.createLayout(this.product));
                this.fieldsVisiblity(true);
                this.fieldsLoaded = true;
                this.isLoading(false);
            }
        },

        onCardProductUpdate: function (product) {

            if (this.product.id !== 'cards') {
                return;
            }

            var me = this;
            var targetFieldId = 'cardholderName';
            var containerUid = this.uid;

            const cardholderNameField = product?.paymentProductFields
                ? product.paymentProductFields.find(f => f.id === targetFieldId)
                : null;

            registry.get('uid = ' + containerUid, function (parentComponent) {
                const existingComponent = parentComponent.elems().find(elem => elem.field && elem.field.id === targetFieldId);

                if (cardholderNameField) {
                    if (!existingComponent) {
                        var componentName = parentComponent.name + '.' + targetFieldId;

                        layout([{
                            component: 'Worldline_Connect/js/view/payment/component/field',
                            parent: parentComponent.name,
                            name: componentName,
                            displayArea: 'elems',
                            field: cardholderNameField,
                            account: me.account || null,
                            sortOrder: cardholderNameField?.displayHints?.displayOrder ?? DEFAULT_SORT_ORDER_CARDHOLDER_NAME
                        }]);

                        registry.get(componentName, function() {
                            self.sortChildren();
                        });
                    }
                } else {
                    if (existingComponent) {
                        existingComponent.destroy();
                    }
                }
            });
        },

        sortChildren: function () {
            var sortedElems = this.elems().sort(function (a, b) {
                return (a.sortOrder || 0) - (b.sortOrder || 0);
            });
            this.elems(sortedElems);
        },

        createLayout: function (product) {
            let layouts = [];
            if (product.paymentProductFields.length === 0) {
                layouts.push(this.getRedirectInfoLayout());
            } else {
                for (let field of product.paymentProductFields) {
                    layouts.push(this.getProductFieldLayout(field, product));
                }
            }

            if (!this.account && product.allowsTokenization && !product.autoTokenized && config.isCustomerLoggedIn() && config.saveForLaterVisible()) {
                layouts.push(this.getTokenizeCheckboxLayout());
            }

            return layouts;
        },

        getProductFieldLayout: function (field, product) {
            const customProductFieldLayout = this.getCustomProductFieldLayout(field, product);
            if (customProductFieldLayout) {
                return customProductFieldLayout;
            }

            return {
                parent: this.name,
                component: 'Worldline_Connect/js/view/payment/component/field',
                field: field,
                account: this.account,
                sortOrder: field?.displayHints?.displayOrder ?? DEFAULT_SORT_ORDER_FIELD
            }
        },

        getCustomProductFieldLayout: function (field, product) {
            if ((product.id === 'cards' || product.paymentMethod === 'card') && field.id === 'cardNumber') {
                return {
                    parent: this.name,
                    component: 'Worldline_Connect/js/view/payment/component/card/field/cardnumber',
                    field: field,
                    account: this.account,
                    showBrandLogos: product.id === 'cards',
                    sortOrder: field?.displayHints?.displayOrder ?? DEFAULT_SORT_ORDER_CARD_NUMBER
                }
            }
        },

        getTokenizeCheckboxLayout: function () {
            // Tokenization can change per card:
            // Also see cardnumber.js
            if (this.product.id === 'cards') {
                registry.set('cardAllowsTokenization', ko.observable(false));
            }
            let cardAllowsTokenization = registry.get('cardAllowsTokenization');

            return {
                parent: this.name,
                component: 'Magento_Ui/js/form/element/single-checkbox',
                elementTmpl: 'Worldline_Connect/payment/product/field/token-checkbox',
                checkedValue: this.product.id,
                cardAllowsTokenization: cardAllowsTokenization,
                value: paymentData.tokenize,
                enabled: this.product.id === 'cards' ? cardAllowsTokenization : true,
                dataScope: this.name + '-tokenize',
                description: $t('Save for later'),
                sortOrder: DEFAULT_SORT_ORDER_TOKENIZE_CHECKBOX
            }
        },

        getRedirectInfoLayout: function () {
            return {
                parent: this.name,
                component: 'Magento_Ui/js/lib/core/element/element',
                template: 'Worldline_Connect/payment/product/field/info',
                text: config.redirectText(),
                sortOrder: DEFAULT_SORT_ORDER_REDIRECT
            }
        },

        /**
         * @public
         * @param {boolean} bool
         */
        fieldsVisiblity: function (bool) {
            this.containerVisible(bool)
        },

        /**
         * @private
         */
        initLoader: function () {
            const loaderContainer = $('[value=' + this.uid + ']').first().parent();
            loaderContainer.loader({
                icon: config.getLoaderImage(),
                template:
                    '<div class="loading-mask" data-role="loader" style="position:absolute;">' +
                    '<div class="loader">' +
                    '<img src="<%- data.icon %>" style="position:absolute">' +
                    '</div>' +
                    '</div>',
            });
            this.isLoading.subscribe(function (isLoading) {
                if (isLoading) {
                    loaderContainer.trigger('processStart');
                } else {
                    loaderContainer.trigger('processStop');
                }
            })
        },
    });
});
