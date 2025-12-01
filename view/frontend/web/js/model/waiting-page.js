define([
    'uiComponent',
    'jquery',
    'mage/translate'
], function (Component, $, $t) {
    'use strict';

    return Component.extend({
        defaults: {
            count: 0,
            storeCode: null,
            incrementId: null,
            checkOrderUrl: null,
            successUrl: null,
            failUrl: null,
            pendingPageUrl: null,
            pendingOrderUrl: null
        },

        initialize: function () {
            this._super();

            this.sendRequest(this);
        },

        showMessage: function(message) {
            let messageElement = $('.message');
            messageElement.html(message);
            messageElement.show();
        },

        sendRequest: function(uiClass) {
            let self = uiClass;
            self.count++;

            $.ajax({
                type: 'POST',
                url: self.checkOrderUrl,
                data: {
                    "incrementId": self.incrementId
                }
            })
                .always(function(result) {
                    if (result.status === true) {
                        window.location.replace(self.successUrl);
                    } else {
                        if (self.count < 7) {
                            setTimeout(self.sendRequest, 2000, self);
                        } else {
                            setTimeout(function () {
                                this.processPendingOrder(this);
                            }.bind(self), 1000);
                        }
                    }
                });
        },

        processPendingOrder: function (currentComponentObject) {
            let self = currentComponentObject;
            $.ajax({
                type: 'POST',
                url: self.pendingOrderUrl,
                data: {
                    "incrementId": self.incrementId
                }
            })
                .done(function(result) {
                    if (result.error) {
                        window.location.replace(self.pendingPageUrl);
                        return;
                    }

                    if (result.status) {
                        window.location.replace(self.successUrl);
                    } else {
                        window.location.replace(self.pendingPageUrl);
                    }
                })
                .fail(function() {
                    window.location.replace(self.pendingPageUrl);
                });
        }
    });
});
