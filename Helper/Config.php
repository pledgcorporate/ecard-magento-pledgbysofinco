<?php

namespace Pledg\PledgPaymentGateway\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;

class Config extends AbstractHelper
{
    const MODULE_VERSION = '1.2.12';
    const ORDER_REFERENCE_PREFIX = 'order_';

    const PLEDG_STAGING_BACK_URI = 'https://staging.back.ecard.pledg.co/api';
    const PLEDG_PROD_BACK_URI = 'https://back.ecard.pledg.co/api';

    const PLEDG_STAGING_DASHBOARD_URI = 'https://staging.dashboard.ecard.pledg.co/dashboard';
    const PLEDG_PROD_DASHBOARD_URI = 'https://dashboard.ecard.pledg.co/dashboard';

    const PLEDG_PAYMENT_TYPES = [
        'installment' => 'installment',
        'deferred' => 'deferred',
    ];

    const PAYMENT_SCHEDULE_ENDPOINT = '/users/me/merchants/<merchant_uid>/simulate_payment_schedule';
    const PURCHASE_ENDPOINT = '/merchants/<merchant_uid>/purchases/<purchase_uid>';

    /**
     * @var ProductMetadataInterface
     */
    private $_productMetadata;

    /**
     * @var ScopeConfigInterface
     */
    private $_scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    private $_storeManager;

    /**
     * @param Context                  $context
     * @param ProductMetadataInterface $productMetadata
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Context $context,
        ProductMetadataInterface $productMetadata,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($context);

        $this->_productMetadata = $productMetadata;
        $this->_storeManager = $storeManager;
        $this->_scopeConfig = $scopeConfig;
    }

    /**
     * @return string
     */
    public function getMagentoVersion(): string
    {
        return $this->_productMetadata->getVersion();
    }

    /**
     * @return string
     */
    public function getModuleVersion(): string
    {
        return self::MODULE_VERSION;
    }

    public function getCurrentStoreCountryCode()
    {
        $store = $this->_storeManager->getStore();
        $websiteId = $store->getWebsiteId();

        $countryCode = $this->_scopeConfig->getValue(
            'general/country/default',
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId
        );

        return $countryCode;
    }

    /**
     * @param Order $order
     *
     * @return string|null
     */
    public function getMerchantIdForOrder(Order $order): ?string
    {
        $apiKeyMapping = $order->getPayment()->getMethodInstance()->getConfigData('api_key_mapping', $order->getStoreId());
        $apiKeyMapping = json_decode($apiKeyMapping, true);

        foreach ($apiKeyMapping as $mapping) {
            if ($mapping['country'] === $order->getBillingAddress()->getCountryId()) {
                return $mapping['api_key'];
            }
        }

        return null;
    }
}
