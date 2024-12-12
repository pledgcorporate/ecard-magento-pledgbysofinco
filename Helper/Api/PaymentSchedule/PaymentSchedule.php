<?php

namespace Pledg\PledgPaymentGateway\Helper\Api\PaymentSchedule;

use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

use Pledg\PledgPaymentGateway\Helper\Config as ConfigHelper;
use Pledg\PledgPaymentGateway\Helper\Api\Client as ApiClientHelper;

class PaymentSchedule extends AbstractHelper
{
    /**
     * @var ConfigHelper
     */
    protected $_configHelper;

    /**
     * @var ApiClientHelper
     */
    protected $_apiClientHelper;

    /**
     * PaymentSchedule constructor.
     * @param Context $context
     * @param ConfigHelper $configHelper
     * @param ApiClientHelper $apiClientHelper
     */
    public function __construct(
        Context $context,
        ConfigHelper $configHelper,
        ApiClientHelper $apiClientHelper
    ) {
        $this->_configHelper = $configHelper;
        $this->_apiClientHelper = $apiClientHelper;
        parent::__construct($context);
    }

    public function getBaseUrl(): string
    {
        $isStagingModeActivated = $this->scopeConfig->getValue('pledg_gateway/payment/staging', ScopeInterface::SCOPE_STORE);

        return $isStagingModeActivated
            ? $this->_configHelper::PLEDG_STAGING_BACK_URI
            : $this->_configHelper::PLEDG_PROD_BACK_URI
        ;
    }

    public function getPaymentSchedule(string $merchantUid, float $price, string $countryCode): array
    {
        $url = $this->getBaseUrl() . str_replace('<merchant_uid>', $merchantUid, $this->_configHelper::PAYMENT_SCHEDULE_ENDPOINT);

        /*
         * Magento prices have the EUR (or USD, CAD, etc) as unit.
         * Pledg back-office needs this price parameter in cents.
         */
        $options = [
            'created' => date('Y-m-d'),
            'amount_cents' => intval($price * 100),
        ];

        try {
            return $this->_apiClientHelper->post($url, $options);
        } catch (\Exception $e) {
            $logMsg = sprintf(
                '%s api exception for retrieving merchant %s : %s',
                'PledgBySofinco',
                $merchantUid,
                $e->getMessage()
            );
            $this->_logger->info($logMsg);
            return [];
        }
    }
}
