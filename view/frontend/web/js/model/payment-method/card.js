/*browser:true*/
/*global define*/

define([
    'Worldline_Connect/js/model/payment/payment-data',
    'Worldline_Connect/js/model/payment/products',
    'Worldline_Connect/js/action/get-iin-details',
    'Worldline_Connect/js/action/get-payment-product'
], function (paymentData, productList, getIinDetails, getPaymentProduct) {
    'use strict';

    return {
        updateCardType: function (partialCardNumber) {
            getIinDetails(partialCardNumber).then(function (response) {
                paymentData.setCurrentCardIinDetails(response || null);

                if (response.paymentProductId) {
                    getPaymentProduct(response.paymentProductId).then(function (productResponse) {
                        paymentData.setCurrentCardPaymentProduct(productResponse);
                    }, function() {
                        paymentData.setCurrentCardPaymentProduct(null);
                    });

                    return;
                }

                paymentData.setCurrentCardPaymentProduct(null);
            }, function() {
                paymentData.setCurrentCardIinDetails(null);
                paymentData.setCurrentCardPaymentProduct(null);
            });
        },

        clearCardType: function() {
            paymentData.setCurrentCardIinDetails(null);
            paymentData.setCurrentCardPaymentProduct(null);
        }
    }
});
