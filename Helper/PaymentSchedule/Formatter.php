<?php

namespace Pledg\PledgPaymentGateway\Helper\PaymentSchedule;

use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\Pricing\PriceCurrencyInterface;

use Pledg\PledgPaymentGateway\Helper\Config;

/**
 * Format payment schedule to display it in one sentence
 */
class Formatter extends AbstractHelper
{
    /**
     * @var PriceCurrencyInterface
     */
    protected $_priceCurrency;

    /**
     * @var ConfigHelper
     */
    protected $_configHelper;

    /**
     * Formatter constructor.
     * @param Context $context
     * @param ConfigHelper $configHelper
     * @param ApiClientHelper $apiClientHelper
     */
    public function __construct(
        Context $context,
        PriceCurrencyInterface $priceCurrency,
        Config $configHelper
    ) {
        $this->_priceCurrency = $priceCurrency;
        $this->_configHelper = $configHelper;
        parent::__construct($context);
    }

    public function formatPaymentScheduleIcon(array $paymentSchedule): ?array
    {
        if (array_key_exists(strtoupper($this->_configHelper::PLEDG_PAYMENT_TYPES['installment']), $paymentSchedule)) {
            $installmentSchedule = $paymentSchedule[strtoupper($this->_configHelper::PLEDG_PAYMENT_TYPES['installment'])];

            return [
                'number' => count($installmentSchedule),
                'code' => 'x',
            ];
        } elseif (array_key_exists(strtoupper($this->_configHelper::PLEDG_PAYMENT_TYPES['deferred']), $paymentSchedule)) {
            $deferredSchedule = $paymentSchedule[strtoupper($this->_configHelper::PLEDG_PAYMENT_TYPES['deferred'])];
            $todayTime = strtotime(date('Y-m-d'));
            $deferredTime = strtotime($deferredSchedule['payment_date']);
            $delay = round(($deferredTime - $todayTime) / 86400);

            return [
                'number' => $delay,
                'code' => __('d'),
            ];
        }

        return null;
    }

    public function formatPaymentScheduleCaption(array $paymentSchedule): ?string
    {
        if (array_key_exists(strtoupper($this->_configHelper::PLEDG_PAYMENT_TYPES['installment']), $paymentSchedule)) {
            $installmentSchedule = $paymentSchedule[strtoupper($this->_configHelper::PLEDG_PAYMENT_TYPES['installment'])];

            return $this->formatInstallmentPaymentScheduleCaption($installmentSchedule);
        } elseif (array_key_exists(strtoupper($this->_configHelper::PLEDG_PAYMENT_TYPES['deferred']), $paymentSchedule)) {
            $deferredSchedule = $paymentSchedule[strtoupper($this->_configHelper::PLEDG_PAYMENT_TYPES['deferred'])];

            return $this->formatDeferredPaymentScheduleCaption($deferredSchedule);
        }

        return null;
    }

    private function formatInstallmentPaymentScheduleCaption(array $installmentSchedule): string
    {
        $installmentNb = count($installmentSchedule);

        $firstShare = ($installmentSchedule[0]['amount_cents'] + $installmentSchedule[0]['fees']) / 100;
        $formattedFirstShare = $this->_priceCurrency->format($firstShare, false);

        $otherShare = ($installmentSchedule[1]['amount_cents'] + $installmentSchedule[1]['fees']) / 100;
        $formattedOtherShare = $this->_priceCurrency->format($firstShare, false);

        if (1 === $installmentNb) {
            return sprintf(
                __('Pay %s now'),
                $formattedFirstShare
            );
        }
        if (2 === $installmentNb) {
            $nextDeadline = date(
                $this->context->language->date_format_lite,
                strtotime($installmentSchedule[1]['payment_date'])
            );

            return sprintf(
                __('Pay %s now then %s the %s'),
                $formattedFirstShare,
                $formattedOtherShare,
                $nextDeadline
            );
        }

        return sprintf(
            __('Pay %s now then %d x %s'),
            $formattedFirstShare,
            $installmentNb - 1,
            $formattedOtherShare
        );
    }

    private function formatDeferredPaymentScheduleCaption(array $deferredSchedule): string
    {
        $share = ($deferredSchedule['amount_cents'] + $deferredSchedule['fees']) / 100;
        $formattedShare = $this->_priceCurrency->format($share, false);
        $deadline = date(
            'd/m/Y',
            strtotime($deferredSchedule['payment_date'])
        );

        return sprintf(
            __('Pay %s the %s'),
            $formattedShare,
            $deadline
        );
    }

    public function getFees(array $paymentSchedule): int
    {
        if (array_key_exists(strtoupper($this->_configHelper::PLEDG_PAYMENT_TYPES['installment']), $paymentSchedule)) {
            return $paymentSchedule[strtoupper($this->_configHelper::PLEDG_PAYMENT_TYPES['installment'])][0]['fees'];
        } elseif (array_key_exists(strtoupper($this->_configHelper::PLEDG_PAYMENT_TYPES['deferred']), $paymentSchedule)) {
            return $paymentSchedule[strtoupper($this->_configHelper::PLEDG_PAYMENT_TYPES['deferred'])]['fees'];
        }

        return 0;
    }

    public function getFormattedMerchant(array $merchantSchedule): array
    {
        return [
            'icon' => $this->formatPaymentScheduleIcon($merchantSchedule),
            'caption' => $this->formatPaymentScheduleCaption($merchantSchedule),
            'fees' => $this->getFees($merchantSchedule),
        ];
    }
}
