<?php

namespace Pledg\PledgPaymentGateway\Block;

use Magento\Payment\Block\Info as BaseInfo;
use Pledg\PledgPaymentGateway\Controller\Checkout\Ipn;

/**
 * Class Info
 */
class Info extends BaseInfo
{
    /**
     * @var string
     */
    protected $_template = 'Pledg_PledgPaymentGateway::info/default.phtml';

    /**
     * @param string $dashboardUrl
     * @return array
     */
    private function getDashboardPurchaseLink(string $dashboardUrl): string
    {
        return '<a href="' . $dashboardUrl . '" target="_blank" rel="noopener noreferrer">Purchase link</a>';
    }

    /**
     * @return array
     */
    public function getAdminSpecificInformation(): array
    {
        $orderPaymentInfo = $this->getInfo()->getOrder()->getPayment()->getAdditionalInformation();

        $unknownLabel = __('Unknown');
        $modeLabel = $unknownLabel;
        $mode = $orderPaymentInfo['pledg_mode'] ?? '';
        if ($mode === Ipn::MODE_TRANSFER) {
            $modeLabel = __('Mode Transfer');
        } elseif ($mode === Ipn::MODE_BACK) {
            $modeLabel = __('Mode Back');
        }

        $arrInfo = [];

        $arrInfo[__('Transaction ID')->getText()] = $orderPaymentInfo['transaction_id'] ?? $unknownLabel;

        if (
            array_key_exists('pledg_dashboard_purchase_url', $orderPaymentInfo)
            && !empty($orderPaymentInfo['pledg_dashboard_purchase_url'])
        ) {
            $arrInfo[__('View in PledgBySofinco dashboard')->getText()] = $this->getDashboardPurchaseLink($orderPaymentInfo['pledg_dashboard_purchase_url']);
        }

        $arrInfo[__('PledgBySofinco Mode')->getText()] = $modeLabel;
        $arrInfo[__('PledgBySofinco Status')->getText()] = $orderPaymentInfo['pledg_status'] ?? $unknownLabel;

        return $arrInfo;
    }
}
