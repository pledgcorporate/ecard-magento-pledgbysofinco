<?php

namespace Pledg\PledgPaymentGateway\Helper\PaymentSchedule;

use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Payment\Model\Method\Adapter as MethodAdapter;

use Psr\Log\LoggerInterface;

use Pledg\PledgPaymentGateway\Helper\Api\PaymentSchedule\PaymentSchedule as ApiPaymentScheduleHelper;
use Pledg\PledgPaymentGateway\Helper\Merchant as MerchantHelper;

class PaymentSchedule extends AbstractHelper
{
    /**
     * @var ApiPaymentScheduleHelper
     */
    protected $_apiPaymentScheduleHelper;

    /**
     * @var MerchantHelper
     */
    protected $_merchantHelper;

    /**
     * @var LoggerInterface
     */
    protected $_logger;

    /**
     * PaymentSchedule constructor.
     * @param Context $context
     * @param MerchantHelper $merchantHelper
     * @param ApiPaymentScheduleHelper $apiPaymentScheduleHelper,
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        MerchantHelper $merchantHelper,
        ApiPaymentScheduleHelper $apiPaymentScheduleHelper,
        LoggerInterface $logger
    ) {
        $this->_merchantHelper = $merchantHelper;
        $this->_apiPaymentScheduleHelper = $apiPaymentScheduleHelper;
        $this->_logger = $logger;
        parent::__construct($context);
    }

    public function retrievePaymentSchedule(
        string $countryCode,
        MethodAdapter $gateway,
        float $price
    ): array
    {
        try {
            $gatewayApiKeyMapping = $gateway->getConfigData('api_key_mapping');
            $merchantUid = $this->_merchantHelper->getMerchandUidByCountryCode($countryCode, $gatewayApiKeyMapping);
        } catch (\Exception $e) {
            $logMsg = sprintf(
                '%s api exception for retrieving merchant : %s',
                'PledgBySofinco',
                $e->getMessage()
            );
            $this->_logger->info($logMsg);
            return [];
        }

        return $this->_apiPaymentScheduleHelper->getPaymentSchedule($merchantUid, $price, $countryCode);
    }
}
