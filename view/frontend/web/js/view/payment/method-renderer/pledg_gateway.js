define([
    'jquery',
    'Magento_Checkout/js/view/payment/default',
    'mage/url',
    'Magento_Checkout/js/model/totals',
    'mage/translate',
    'Magento_Checkout/js/model/quote'
], function ($, Component, url, totals, translate, quote) {
    'use strict';

    return Component.extend({
        redirectAfterPlaceOrder: false,

        defaults: {
            template: 'Pledg_PledgPaymentGateway/payment/form'
        },

        afterPlaceOrder: function () {
            window.location.replace(url.build('pledgbysofinco/checkout/pay'));
        },

        getTitle: function () {
            return window.checkoutConfig.payment[this.getCode()].title;
        },

        getDescription: function () {
            return window.checkoutConfig.payment[this.getCode()].description;
        },

        getPledgLogo: function () {
            return window.checkoutConfig.payment[this.getCode()].logo;
        },

        showPaymentSchedule: function() {
            const pledgApiUrl = this.getPledgPaymentScheduleApiUrl(quote);

            if (totals.totals() && pledgApiUrl) {
                const total = Math.round(parseFloat(totals.totals()['grand_total'])*100);
                const paymentScheduleContainer = $('#' + this.getCode()).parents('.payment-method')
                    .find('.payment-method-schedule');

                let date = new Date()
                const offset = date.getTimezoneOffset()
                date = new Date(date.getTime() - (offset*60*1000));
                date = date.toISOString().split('T')[0];

                $.ajax({
                    url: pledgApiUrl,
                    crossDomain: true,
                    type: 'POST',
                    dataType: 'json',
                    contentType: 'application/json',
                    data: JSON.stringify({"created": date, "amount_cents": total}),
                    complete: function(response) {
                        
                        const locale = window.checkoutConfig.locale.replace('_', '-');
                        const pricePattern = window.checkoutConfig.priceFormat.pattern;

                        const deadlineTrad = $.mage.__('Deadline');
                        const theTrad = $.mage.__('the');
                        const feesTrad = $.mage.__('(including %s of fees)');
                        const deferredTrad = $.mage.__('I will pay %s1 on %s2.');

                        const data = JSON.parse(response.responseText);

                        if ('INSTALLMENT' in data) {
                            const paymentSchedule = data.INSTALLMENT;
                            let ret = `<div class="screen-section" style="padding-top: 0px;">`;

                            for (let i = 0; i < paymentSchedule.length; i++) {
                                const share = ((paymentSchedule[i].amount_cents
                                    + paymentSchedule[i].fees) / 100).toFixed(2);
                                const shareFormatted = pricePattern.replace('%s', share);

                                ret += `<p style="margin-top: 20px;"><b style="float: left;">
                                    ${deadlineTrad} ${(i + 1)} ${theTrad}
                                    ${new Date(paymentSchedule[i].payment_date).toLocaleDateString(locale)}</b>
                                    <b style="float: right; text-align: right;"> ${shareFormatted}</b></p>`;

                                if (paymentSchedule[i].fees) {
                                const fees = (paymentSchedule[i].fees / 100).toFixed(2);
                                const feesFormatted = pricePattern.replace('%s', fees);

                                ret += `<br><p><b style="float: right;"><span style="font-size: 0.85em;">
                                    ${feesTrad.replace('%s', feesFormatted)}
                                    </span></b></p>`;
                                }
                                ret += `<div style="clear: both; margin-bottom: 20px;"></div>`;
                            }
                            ret += `</div>`;
                            paymentScheduleContainer.html(ret);
                        } else if ('DEFERRED' in data) {
                            const paymentSchedule = data.DEFERRED;
                            const share = ((paymentSchedule.amount_cents
                                + paymentSchedule.fees) / 100).toFixed(2);
                            const shareFormatted = pricePattern.replace('%s', share);

                            let ret = `<div class="screen-section" style="padding-top: 0px;"><p><b>
                                ${deferredTrad.replace('%s1', shareFormatted)
                                .replace('%s2', new Date(paymentSchedule.payment_date).toLocaleDateString(locale))}
                                </b></p>`;

                            if (paymentSchedule.fees) {
                                const fees = (paymentSchedule.fees / 100).toFixed(2);
                                const feesFormatted = pricePattern.replace('%s', fees);

                                ret += `<p><span style="font-size: 0.85em;"> 
                                    ${feesTrad.replace('%s', feesFormatted)}
                                    </span></p></b>`;
                            }

                            ret += `</div>`;
                            paymentScheduleContainer.html(ret);
                        }
                    },
                    error: function (xhr, status, errorThrown) {
                        console.log(`Error happened while trying to access payment schedule for ${this.getCode()}: ${errorThrown}`);
                    }
                });
            }

            this.selectPaymentMethod();
        },

        getPledgPaymentScheduleApiUrl: function(quote) {
            let pledgApiUrl = (window.checkoutConfig.pledg_gateway.payment.staging === "0")
                ? window.checkoutConfig.pledg_gateway.payment.prod_api_url
                : window.checkoutConfig.pledg_gateway.payment.staging_api_url
            ;

            const customerCountry = quote.billingAddress._latestValue.countryId;
            const apiKeyMapping = window.checkoutConfig.payment[this.getCode()].api_key_mapping;
            let apiKey = null;
            
            for (const property in apiKeyMapping) {
                if (apiKeyMapping[property]['country'] === customerCountry) {
                    apiKey = apiKeyMapping[property]['api_key'];
                    break;
                }
            }

            if (!apiKey) return false;

            return `${pledgApiUrl}/users/me/merchants/${apiKey}/simulate_payment_schedule`;
        },
    });
});


