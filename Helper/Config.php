<?php

namespace Pledg\PledgPaymentGateway\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Sales\Model\Order;

class Config extends AbstractHelper
{
    const MODULE_VERSION = '1.2.7';
    const ORDER_REFERENCE_PREFIX = 'order_';

    /**
     * @var ProductMetadataInterface
     */
    private $productMetadata;

    /**
     * @param Context                  $context
     * @param ProductMetadataInterface $productMetadata
     */
    public function __construct(Context $context, ProductMetadataInterface $productMetadata)
    {
        parent::__construct($context);

        $this->productMetadata = $productMetadata;
    }

    /**
     * @return string
     */
    public function getMagentoVersion(): string
    {
        return $this->productMetadata->getVersion();
    }

    /**
     * @return string
     */
    public function getModuleVersion(): string
    {
        return self::MODULE_VERSION;
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
