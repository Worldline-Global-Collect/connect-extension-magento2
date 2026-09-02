/*browser:true*/
/*global define*/

define([
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/checkout-data'
], function (quote, checkoutData) {
    'use strict';

    return {
        /**
         * Marks a payment method renderer unavailable after its Worldline product lookup failed
         * (amount out of range, unsupported country, unknown product group, transport error, ...).
         * The method is then hidden by the `visible: isMethodAvailable()` binding in its template,
         * so the shopper never sees a payment option the platform has already refused to serve.
         *
         * The failure itself is logged by the action that made the call - it owns the request id -
         * so this only records which method was dropped.
         *
         * @param {Object} component - the payment method renderer
         * @param {*} error - whatever the lookup rejected with, or the unusable response
         */
        markUnavailable: function (component, error) {
            console.error('Worldline: payment product lookup failed for ' + component.getCode(), error);

            component.isMethodAvailable(false);

            if (quote.paymentMethod() && quote.paymentMethod().method === component.getCode()) {
                quote.paymentMethod(null);
                checkoutData.setSelectedPaymentMethod(null);
            }
        },

        /**
         * @param {Object} component - the payment method renderer
         */
        markAvailable: function (component) {
            component.isMethodAvailable(true);
        }
    };
});
