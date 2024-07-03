define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (Component, rendererList) {
    'use strict';

    rendererList.push({
        type: 'pledg_gateway_6',
        component: 'Pledg_PledgPaymentGateway/js/view/payment/method-renderer/pledg_gateway_6'
    });

    return Component.extend({});
});
