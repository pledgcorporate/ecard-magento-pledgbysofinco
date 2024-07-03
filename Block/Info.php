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

        return [
            __('Transaction ID')->getText() => $orderPaymentInfo['transaction_id'] ?? $unknownLabel,
            __('PledgBySofinco Mode')->getText() => $modeLabel,
            __('PledgBySofinco Status')->getText() => $orderPaymentInfo['pledg_status'] ?? $unknownLabel,
        ];
    }
}
