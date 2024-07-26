<?php

namespace Pledg\PledgPaymentGateway\Block\Checkout;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\ResourceModel\CustomerRepository;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Template;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Store\Model\ScopeInterface;
use Pledg\PledgPaymentGateway\Helper\CustomerAttribute;
use Pledg\PledgPaymentGateway\Helper\Config;
use Pledg\PledgPaymentGateway\Helper\Crypto;

class Pay extends Template
{
    private const AUTHORIZED_ORDER_STATUS = ['complete', 'processing'];

    private const PLEDG_B2B_COMPANY_NATIONAL_ID_TYPE_SIRET = 'SIRET';
    private const PLEDG_B2B_COMPANY_NATIONAL_ID_TYPE_SIREN = 'SIREN';

    /**
     * @var Config
     */
    private $configHelper;

    /**
     * @var Crypto
     */
    private $crypto;

    /**
     * CustomerAttribute
     */
    private $customerAttribute;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var CollectionFactory
     */
    private $orderCollectionFactory;

    /**
     * @var CustomerRepository
     */
    private $customerRepository;

    /**
     * @var Order
     */
    private $order;

    /**
     * ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param Template\Context   $context
     * @param Config             $configHelper
     * @param Crypto             $crypto
     * @param CollectionFactory  $orderCollectionFactory
     * @param CustomerRepository $customerRepository
     * @param array              $data
     */
    public function __construct(
        CustomerAttribute $customerAttribute,
        Template\Context $context,
        Config $configHelper,
        Crypto $crypto,
        CollectionFactory $orderCollectionFactory,
        CustomerRepository $customerRepository,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->customerAttribute = $customerAttribute;
        $this->configHelper = $configHelper;
        $this->crypto = $crypto;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->customerRepository = $customerRepository;
        $this->scopeConfig = $scopeConfig;
    }

    public function setOrder(Order $order): self
    {
        $this->order = $order;

        return $this;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function setCustomerSession(CustomerSession $customerSession): self
    {
        $this->customerSession = $customerSession;

        return $this;
    }

    public function getCustomerSession(): CustomerSession
    {
        return $this->customerSession;
    }

    public function getPledgData(): array
    {
        /** @var Order $order */
        $order = $this->getOrder();
        $orderIncrementId = $order->getIncrementId();
        $orderAddress = $order->getBillingAddress();

        $customer = null;

        try {
            $customerId = (int) $order->getCustomerId();
            if (!empty($customerId)) {
                $customer = $this->customerRepository->getById($customerId);
            }
        }
        catch (\Exception $e) {
            $this->_logger->error('Could not resolve order customer for PledgBySofinco data', [
                'exception' => $e,
                'order' => $order->getIncrementId(),
            ]);
        }

        $pledgData = [
            'merchantUid' => $this->configHelper->getMerchantIdForOrder($order),
            'amountCents' => round($order->getGrandTotal() * 100),
            'email' => $order->getCustomerEmail(),
            'title' => 'Order ' . $orderIncrementId,
            'reference' => Config::ORDER_REFERENCE_PREFIX . $orderIncrementId,
            'firstName' => $orderAddress->getFirstname(),
            'lastName' => $orderAddress->getLastname(),
            'currency' => $order->getOrderCurrencyCode(),
            'lang' => $this->getLang(),
            'countryCode' => $orderAddress->getCountryId(),
            'address' => $this->getAddressData($orderAddress),
            'metadata' => $this->getMetaData($order, $customer),
            'showCloseButton' => true,
            'paymentNotificationUrl' => $this->getUrl('pledgbysofinco/checkout/ipn', [
                '_secure' => true,
                'ipn_store_id' => $order->getStoreId(),
                'pledg_method' => $order->getPayment()->getMethod(),
            ]),
        ];

        if (!$order->getIsVirtual()) {
            $pledgData['shipping_address'] = $this->getAddressData($order->getShippingAddress());
        }

        $telephone = $orderAddress->getTelephone();
        if (!empty($telephone)) {
            $pledgData['phoneNumber'] = preg_replace('/^(\+|00)(.*)$/', '$2', $telephone);
        }

        if ($customer) {
            $pledgData = \array_merge($pledgData, $this->getB2bData($order, $customer));
        }

        $secretKey = $order->getPayment()->getMethodInstance()->getConfigData('secret_key', $order->getStoreId());
        if (empty($secretKey)) {
            return $this->encodeData($pledgData);
        }

        return [
            'signature' => $this->crypto->encode(['data' => $pledgData], $secretKey),
        ];
    }

    private function getLang(): string
    {
        $lang = $this->_scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORES);

        $allowedLangs = [
            'fr_FR',
            'de_DE',
            'en_GB',
            'es_ES',
            'it_IT',
            'nl_NL',
        ];

        if (in_array($lang, $allowedLangs)) {
            return $lang;
        }

        return reset($allowedLangs);
    }

    private function getAddressData(OrderAddressInterface $orderAddress): array
    {
        return [
            'street' => is_array($orderAddress->getStreet()) ?
                implode(' ', $orderAddress->getStreet()) : $orderAddress->getStreet(),
            'city' => $orderAddress->getCity(),
            'zipcode' => (string)$orderAddress->getPostcode(),
            'stateProvince' => (string)$orderAddress->getRegion(),
            'country' => $orderAddress->getCountryId(),
        ];
    }

    private function getMetaData(Order $order, ?CustomerInterface $customer): array
    {
        $physicalProductTypes = [
            'simple',
            'configurable',
            'bundle',
            'grouped',
        ];

        $products = [];
        /** @var Order\Item $item */
        foreach ($order->getAllVisibleItems() as $item) {
            $productType = $item->getProductType();
            $products[] = [
                'reference' => $item->getSku(),
                'type' => in_array($productType, $physicalProductTypes) ? 'physical' : 'virtual',
                'quantity' => (int)$item->getQtyOrdered(),
                'name' => $item->getName(),
                'unit_amount_cents' => round($item->getPriceInclTax() * 100),
                // delivery data must be in each product details:
                'delivery_mode' => $order->getShippingDescription(),
                'delivery_mode_reference' => $order->getShippingDescription(),
                'delivery_cost' => $order->getShippingInclTax(),
            ];
            if (count($products) === 5) {
                // Metadata field is limited in size
                // Include max 5 products information
                break;
            }
        }

        return array_merge(
            [
                'plugin' => sprintf(
                    'magento%s-%s-plugin%s',
                    $this->configHelper->getMagentoVersion(),
                    'pledgbysofinco',
                    $this->configHelper->getModuleVersion()
                ),
                'products' => $products,
                'departure_date' => date('Y-m-d'),
            ],
            $customer ? $this->getCustomerData($order, $customer) : []
        );
    }

    private function getCustomerData(Order $order, CustomerInterface $customer): array
    {
        return [
            'account' => [
                'creation_date' => (new \DateTime($customer->getCreatedAt()))->format('Y-m-d'),
                'number_of_purchases' => $this->orderCollectionFactory->create($customer->getId())
                    ->addFieldToFilter('status', ['in' => self::AUTHORIZED_ORDER_STATUS])
                    ->getSize(),
            ]
        ];
    }

    private function getB2bData(Order $order, CustomerInterface $customer): array
    {
        $gatewayIsB2b = $order->getPayment()->getMethodInstance()->getConfigData('is_b2b', $order->getStoreId());

        if ($gatewayIsB2b) {
            $siretCustomFieldName = $this->scopeConfig->getValue('pledg_gateway/payment/siret_custom_field_name')
                ?: 'siret_number';
            $companyCustomFieldName = $this->scopeConfig->getValue('pledg_gateway/payment/company_custom_field_name')
                ?: 'company_name';

            $companyIdAttribute = $this->customerAttribute->getCustomerAttributeValue($customer, $siretCustomFieldName);
            $companyNameAttribute = $this->customerAttribute->getCustomerAttributeValue($customer, $companyCustomFieldName);
            if (!$companyNameAttribute) {
                $companyNameAttribute = $order->getBillingAddress()->getCompany();
            }

            if ($companyIdAttribute && $companyNameAttribute) {
                $companyNationalIdType = self::PLEDG_B2B_COMPANY_NATIONAL_ID_TYPE_SIREN;
                if (strlen($companyIdAttribute) > 9) {
                    $companyNationalIdType = self::PLEDG_B2B_COMPANY_NATIONAL_ID_TYPE_SIRET;
                }
                return [
                    'b2bCompanyNationalId' => $companyIdAttribute,
                    'b2bCompanyName' => $companyNameAttribute,
                    'b2bCompanyNationalIdType' => $companyNationalIdType,
                ];
            }
        }

        return [];
    }

    private function encodeData(array $data): array
    {
        $convertedData = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $convertedData[$key] = $this->encodeData($value);
                continue;
            }

            if (mb_check_encoding($value, 'UTF-8') === false) {
                $value = $this->convToUtf8($value);
            }
            $convertedData[$key] = $value;
        }

        return $convertedData;
    }

    /**
     * @param string $stringToEncode
     * @param string $encodingTypes
     *
     * @return string
     */
    private function convToUtf8(
        string $stringToEncode,
        string $encodingTypes = "UTF-8,ASCII,windows-1252,ISO-8859-15,ISO-8859-1"
    ): string {
        $detect = mb_detect_encoding($stringToEncode, $encodingTypes, true);
        if ($detect && $detect !== "UTF-8") {
            if ($detect === 'ISO-8859-15') {
                $stringToEncode = preg_replace('/\x9c/', '|oe|', $stringToEncode);
            }
            $stringToEncode = iconv($detect, "UTF-8", $stringToEncode);
            if ($detect === 'ISO-8859-15') {
                $stringToEncode = preg_replace('/\|oe\|/', 'œ', $stringToEncode);
            }
        }

        return $stringToEncode;
    }
}
