define(['jquery'],function($) {
    'use strict';

    return {

        amountFormat: function (amount, currency, currencySign) {
            if (currencySign === 'before') {
                return currency + amount;
            }
            return amount + currency;
        },

        formatInstallmentSchedule: function (paymentSchedule, options) {
            const {
                currency, currencySign, locale, theTrad, deadlineTrad, feesTrad,
            } = options;

            let ret = "<div class='screen-section pledg-schedule' style='padding-top: 0px;'>";

            for (let i = 0; i < paymentSchedule.length; i++) {
                const share = ((paymentSchedule[i].amount_cents + paymentSchedule[i].fees) / 100).toFixed(2);
                const shareFormatted = this.amountFormat(share, currency, currencySign);

                ret += `<p class="installment-statement"><span style="float: left;">
                    ${deadlineTrad} ${(i + 1)} ${theTrad}
                    <b>${new Date(paymentSchedule[i].payment_date).toLocaleDateString(locale)}</b></span>
                <span style="float: right; text-align: right;"> ${shareFormatted}</span></p>`;

                if (paymentSchedule[i].fees) {
                    const fees = (paymentSchedule[i].fees / 100).toFixed(2);
                    const feesFormatted = this.amountFormat(fees, currency, currencySign);

                    ret += `<p><span style="float: right;"><span style="font-size: 0.85em;">
                        ${feesTrad.replace('%s', feesFormatted)}
                        </span></span></p>`;
                }
                if (i !== (paymentSchedule.length - 1)) {
                    ret += '<div style="clear: both; margin-bottom: 20px;"></div>';
                }
            }

            ret += '<br></div>';

            return ret;
        },

        formatDeferredSchedule: function (paymentSchedule, options) {
            const {
                currency, currencySign, locale, deferredTrad, feesTrad,
            } = options;

            const share = ((paymentSchedule.amount_cents + paymentSchedule.fees) / 100).toFixed(2);
            const shareFormatted = this.amountFormat(share, currency, currencySign);

            let ret = `<div class='screen-section pledg-schedule' style='padding-top: 0px;'><p><span>
                ${deferredTrad.replace('%s1', shareFormatted)
                .replace('%s2', new Date(paymentSchedule.payment_date).toLocaleDateString(locale))}
            </span></p>`;

            if (paymentSchedule.fees) {
                const fees = (paymentSchedule.fees / 100).toFixed(2);
                const feesFormatted = this.amountFormat(fees, currency, currencySign);

                ret += `<p style="font-size: 0.85em;"> 
                    ${feesTrad.replace('%s', feesFormatted)}
                    </p>`;
            }

            ret += '<br></div>';

            return ret;
        },

        formatPaymentSchedule: function (paymentSchedule, options) {
            if ('INSTALLMENT' in paymentSchedule) {
                return this.formatInstallmentSchedule(paymentSchedule.INSTALLMENT, options);
            }

            if ('DEFERRED' in paymentSchedule) {
                return this.formatDeferredSchedule(paymentSchedule.DEFERRED, options);
            }

            return '';
        }

    };

});
