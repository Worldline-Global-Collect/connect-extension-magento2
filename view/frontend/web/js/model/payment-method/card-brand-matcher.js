/*browser:true*/
/*global define*/

define([], function () {
    'use strict';

    const BRAND_BY_PRODUCT_ID = {
        2: 'AE',    // American Express
        146: 'AU',  // Bancontact / domestic
        132: 'DN',  // Diners Club
        128: 'DI',  // Discover
        163: 'HC',  // Hipercard
        125: 'JCB', // JCB
        117: 'SM',  // Maestro
        3: 'MC',    // Mastercard
        119: 'MC',  // Mastercard Debit
        1: 'VI',    // Visa
        114: 'VI',  // Visa Debit
        122: 'VI',  // Visa Electron
        130: 'CB',  // Cartes Bancaires
        56: 'UP',   // UnionPay ExpressPay
        430: 'UP'   // UnionPay International SecurePay
    };

    // All product ids the card resolves to: the primary detected product plus every co-brand.
    let candidateProductIds = function (iinDetails) {
        let ids = [];

        if (iinDetails && iinDetails.paymentProductId) {
            ids.push(Number(iinDetails.paymentProductId));
        }

        if (iinDetails && Array.isArray(iinDetails.coBrands)) {
            iinDetails.coBrands.forEach(function (coBrand) {
                if (coBrand && coBrand.paymentProductId) {
                    ids.push(Number(coBrand.paymentProductId));
                }
            });
        }

        return ids;
    };

    return {
        /**
         * Whether the given payment product id belongs to a card brand this matcher knows about.
         *
         * @param {number} productId
         * @return {boolean}
         */
        isKnownBrand: function (productId) {
            return BRAND_BY_PRODUCT_ID[Number(productId)] !== undefined;
        },

        /**
         * Whether the card described by a Worldline getIinDetails response is acceptable for the
         * selected payment product, considering the primary product AND all co-brands.
         *
         * A co-branded card (e.g. Carte Bancaire + Mastercard) is accepted on any of its real brands
         * and rejected on the rest: acceptable when the selected product id is one of the card's
         * product ids, or shares a brand family with one of them. An unknown selected method (not in
         * the map) is never blocked; unknown card brands no longer auto-accept every method.
         *
         * @param {number} selectedProductId
         * @param {Object} iinDetails
         * @return {boolean}
         */
        matchesByIin: function (selectedProductId, iinDetails) {
            let selectedBrand = BRAND_BY_PRODUCT_ID[Number(selectedProductId)];
            if (!selectedBrand) {
                return true;
            }

            let ids = candidateProductIds(iinDetails);
            if (ids.length === 0) {
                return true;
            }

            if (ids.indexOf(Number(selectedProductId)) !== -1) {
                return true;
            }

            return ids.some(function (id) {
                return BRAND_BY_PRODUCT_ID[id] === selectedBrand;
            });
        }
    };
});
