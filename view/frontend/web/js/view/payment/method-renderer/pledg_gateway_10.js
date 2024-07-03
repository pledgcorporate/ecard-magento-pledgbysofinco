define([
    'Pledg_PledgPaymentGateway/js/view/payment/method-renderer/pledg_gateway',
], function (Component) {
    'use strict';

    return Component.extend({
        getCode: function() {
            return 'pledg_gateway_10';
        },
    });
});
