<?php

namespace Pledg\PledgPaymentGateway\Helper;


use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Customer\Model\Session;
use Magento\Payment\Model\Method\Adapter as MethodAdapter;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Locale\Resolver as LocaleResolver;
use Magento\Checkout\Model\Session as CheckoutSession;

use Psr\Log\LoggerInterface;

use Pledg\PledgPaymentGateway\Model\Ui\ConfigProvider;
use Pledg\PledgPaymentGateway\Helper\Config as ConfigHelper;
use Pledg\PledgPaymentGateway\Helper\PaymentSchedule\PaymentSchedule as PaymentScheduleHelper;
use Pledg\PledgPaymentGateway\Helper\PaymentSchedule\Formatter as FormatterHelper;

class Widget extends AbstractHelper
{
    /**
     * @var PaymentHelper
     */
    protected $_paymentHelper;

    /**
     * @var PaymentScheduleHelper
     */
    protected $_paymentScheduleHelper;

    /**
     * @var ConfigHelper
     */
    protected $_configHelper;

    /**
     * @var FormatterHelper
     */
    protected $_formatterHelper;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var LocaleResolver
     */
    protected $_localeResolver;

    /**
     * @var CheckoutSession
     */
    protected $_checkoutSession;

    /**
     * @var LoggerInterface
     */
    protected $_logger;

    /**
     * Widget constructor.  
     * @param Context $context
     * @param PaymentHelper $paymentHelper
     * @param PaymentScheduleHelper $paymentScheduleHelper
     * @param ConfigHelper $configHelper
     * @param FormatterHelper $formatterHelper
     * @param StoreManagerInterface $storeManager
     * @param LocaleResolver $localeResolver
     * @param CheckoutSession $checkoutSession
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        PaymentHelper $paymentHelper,
        PaymentScheduleHelper $paymentScheduleHelper,
        ConfigHelper $configHelper,
        FormatterHelper $formatterHelper,
        StoreManagerInterface $storeManager,
        LocaleResolver $localeResolver,
        CheckoutSession $checkoutSession,
        LoggerInterface $logger,
    ) {
        parent::__construct($context);
        $this->_paymentHelper = $paymentHelper;
        $this->_paymentScheduleHelper = $paymentScheduleHelper;
        $this->_configHelper = $configHelper;
        $this->_formatterHelper = $formatterHelper;
        $this->_storeManager = $storeManager;
        $this->_localeResolver = $localeResolver;
        $this->_checkoutSession = $checkoutSession;
        $this->_logger = $logger;
    }

    /**
    * @return null|string
    */
    public function getLocale() {
        $locale = $this->_localeResolver->getLocale();
        $locale = str_replace('_', '-', $locale);
        return $locale;
    }

    /**
    * @return null|string
    */
    public function getLang() {
        $locale = $this->getLocale();
        $splittedLocale = explode('_', $locale);
        return $splittedLocale[0];
    }

    /**
    * @return null|string
    */
    public function getCurrencySign() {
        $lang = $this->getLang();

        $ret = 'after';
        if ($lang === 'en') {
            $ret = 'before';
        }

        return $ret;
    }

    private function shouldBeDisplayed(
        float $price,
        MethodAdapter $paymentMethod,
        Session $customerSession
    ): bool
    {
        $isActive = $paymentMethod->getConfigData('active');

        if ($isActive !== '1') {
            return false;
        }

        return $this->amountIsInPriceRange($paymentMethod, $price)
            && $this->customerIsEnabled($paymentMethod, $customerSession);
    }

    /**
     * We check min and max amount
     */
    private function amountIsInPriceRange(
        MethodAdapter $method,
        float $price
    ): bool
    {
        if ($price <= 0) {
            return false;
        }

        $min = $method->getConfigData('min_order_total');
        if (!is_numeric($min)) {
            $min = 0;
        }

        $max = $method->getConfigData('max_order_total');
        if (!is_numeric($max)) {
            $max = 0;
        }

        return ($max === 0 || $price < $max) && ($min === 0 || $price > $min);
    }

    /**
     * We check that the customer is member of an allowed group
     */
    private function customerIsEnabled(
        MethodAdapter $method,
        Session $customerSession
    ): bool
    {
        $user_group_id = $customerSession->getCustomer()->getGroupId();

        $method_allowed_groups_ids = [];
        $list_method_allowed_groups_ids = $method->getConfigData('allowed_groups');

        if (!empty($list_method_allowed_groups_ids)) {
            $method_allowed_groups_ids = explode(',', $list_method_allowed_groups_ids);

            foreach ($method_allowed_groups_ids as $group_id) {
                if ($user_group_id === $group_id) {
                    return true;
                }
            }
            return false;
        }

        return true;
    }

    public function logTotals(
        $quote
    ) {
        $colTotals = $quote->getTotals();

        $this->_logger->info('***');
        $this->_logger->info('***    TOTALS:    ***');
        $this->_logger->info('***');

        foreach ($colTotals as $key => $totalItem) {
            $this->_logger->info('code[ ' . $totalItem->getCode() . ' ]' . "\t\t\t\t" . ' => amount[ ' . $totalItem->getValue() . ' ]');
        }

        return;

        // $totals = [
        //     'chQuote' => [
        //         'before' => [],
        //         'after' => []
        //     ],
        //     'quote' => [
        //         'before' => [],
        //         'after' => []
        //     ]
        // ];

        // $tmpTotals = [
        //     'grand_total' => $chQuote->getGrandTotalInclTax(),
        //     'sub_total' => $chQuote->getSubtotalInclTax(),
        //     'tax_amount' => $chQuote->getTaxAmount(),
        //     'shipping_amount' => $chQuote->getShippingAmount(),
        //     'discount_amount' => $chQuote->getDiscountAmount(),
        //     'totals' => $chQuote->getTotals()
        // ];
        // $totals['chQuote']['before'] = $tmpTotals;

        // $this->_logger->info('widgetHelper $chQuote->collectTotals()');
        // $chQuote->collectTotals()->save();


    }

    /**
     * @param array  $payload
     * @param string $secretKey
     *
     * @return array
     */
    public function getMerchantsToDisplay(
        Session $customerSession,
        string $widgetType = 'cart'
    ) {
        $ret = [];
        $price = 0;

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        switch ($widgetType) {
            case 'product':
                $product = $objectManager->get('Magento\Framework\Registry')->registry('current_product');
                $price = $product->getFinalPrice();
                break;

            case 'cart':
            default:
                $chQuote = $this->_checkoutSession->getQuote();

                $this->_logger->info('widgetHelper 111 $chQuote:');
                $this->logTotals($chQuote);

                $rates = $chQuote->getShippingAddress()->getShippingRatesCollection();
                $this->_logger->info('widgetHelper 111 $rates:');
                foreach ($rates as $rate)
                {
                    $out = '[' . $rate->getPrice() . '||'.  $rate->getMethod() . '||'. $rate->getMethodTitle() . ']';
                    $this->_logger->info($out);
                }

                $chQuote->setTotalsCollectedFlag(false);
                $chQuote->getShippingAddress()->unsetData('cached_items_all');
                $chQuote->getShippingAddress()->unsetData('cached_items_nominal');
                $chQuote->getShippingAddress()->unsetData('cached_items_nonnominal');
                $chQuote->collectTotals()->save();

                $this->_logger->info('widgetHelper 222 $chQuote:');
                $this->logTotals($chQuote);

                $rates = $chQuote->getShippingAddress()->getShippingRatesCollection();
                $this->_logger->info('widgetHelper 111 $rates:');
                foreach ($rates as $rate)
                {
                    $out = '[' . $rate->getPrice() . '||'.  $rate->getMethod() . '||'. $rate->getMethodTitle() . ']';
                    $this->_logger->info($out);
                }

                $price = $chQuote->getBaseGrandTotal();
                break;
        }

        $this->_logger->info('widgetHelper price: ' . $price);

        $deferredMerchants = [];
        $installmentMerchants = [];

        $countryCode = $this->_configHelper->getCurrentStoreCountryCode();

        $arrPaymentMethodCodes = ConfigProvider::getPaymentMethodCodes();

        foreach ($arrPaymentMethodCodes as $paymentMethodCode) {
            $method = $this->_paymentHelper->getMethodInstance($paymentMethodCode);

            if (!$this->shouldBeDisplayed($price, $method, $customerSession)) {
                continue;
            }

            $merchantSchedule = $this->_paymentScheduleHelper->retrievePaymentSchedule(
                $countryCode,
                $method,
                $price
            );

            if (!empty($merchantSchedule)) {
                $formattedMerchant = $this->_formatterHelper->getFormattedMerchant($merchantSchedule);

                if ($formattedMerchant['icon'] && $formattedMerchant['caption']) {
                    $merchant = [
                        'icon' => $formattedMerchant['icon']['number'] . $formattedMerchant['icon']['code'],
                        'caption' => $formattedMerchant['caption'],
                        'fees' => $formattedMerchant['fees'],
                        'schedule' => json_encode($merchantSchedule),
                        'options' => [
                            'currency' => $this->_storeManager->getStore()->getCurrentCurrency()->getCurrencySymbol(),
                            'currencySign' => $this->getCurrencySign()
                        ],
                        'code' => $paymentMethodCode
                    ];
                    if (array_key_exists(
                        strtoupper($this->_configHelper::PLEDG_PAYMENT_TYPES['installment']),
                        $merchantSchedule
                    )) {
                        $installmentMerchants[$formattedMerchant['icon']['number']] = array_merge(
                            $merchant,
                            ['type' => $this->_configHelper::PLEDG_PAYMENT_TYPES['installment']]
                        );
                    } elseif (array_key_exists(
                        strtoupper($this->_configHelper::PLEDG_PAYMENT_TYPES['deferred']),
                        $merchantSchedule
                    )) {
                        $deferredMerchants[$formattedMerchant['icon']['number']] = array_merge(
                            $merchant,
                            ['type' => $this->_configHelper::PLEDG_PAYMENT_TYPES['deferred']]
                        );
                    }
                }
            }
        }

        ksort($installmentMerchants);
        ksort($deferredMerchants);

        $ret = array_merge($installmentMerchants, $deferredMerchants);

        return $ret;
    }

    /**
    * @return string
    */
    public function getTranslationCaptions() {
        $arrCaptions = [
            'feesCaption'                   => __('Free of charge.'),
            'installmentCaption'            => __('Pay in installments*.'),
            'installmentSecondBulletPoint'  => __('Select the payment in installment'),
            'installmentFourthBulletPoint'  => __('The first share is debited today. The following shares will be automatically debited in the following months'),
            'deferredCaption'               => __('Pay later*.'),
            'deferredSecondBulletPoint'     => __('Select the deferred payment'),
            'deferredFourthBulletPoint'     => __('The payment will be debited later, depending on the deadline you have chosen'),
        ];

        return json_encode($arrCaptions);
    }

    /**
    * @return string
    */
    public function getTranslationOptions() {
        $arrTranslations = [
            'currencySign'  => $this->getCurrencySign(),
            'deadlineTrad'  => __('Deadline'),
            'theTrad'       => __('the'),
            'feesTrad'      => __('(including %s of fees)'),
            'deferredTrad'  => __('I will pay %s1 on %s2.'),
        ];

        if (!in_array($arrTranslations['currencySign'], ['before', 'after'])) {
            $arrTranslations['currencySign'] = 'after';
        }

        $arrTranslations['currency'] = $this->_storeManager->getStore()->getCurrentCurrency()->getCurrencySymbol();
        $arrTranslations['locale'] = $this->getLocale();

        return json_encode($arrTranslations);
    }

    /**
    * @return array
    */
    public function getWidgetOptions(
        Session $customerSession,
        string $widgetType = 'cart',
        string $activeMerchant = 'merchantIcon0'
    ) {
        $ret = [];

        $widgetActivation = false;
        $widgetActivationConfigPath = null;

        switch ($widgetType) {
            case 'product':
                $widgetActivationConfigPath = 'pledg_gateway/payment/enable_widget_in_product_page';
                break;
            case 'cart':
            default:
                $widgetActivationConfigPath = 'pledg_gateway/payment/enable_widget_in_checkout_page';
                break;
        }

        $widgetActivation = $this->scopeConfig->getValue($widgetActivationConfigPath);

        if ($widgetActivation === '1') {
            $ret = [
                'widgetType'                => $widgetType,
                'activeMerchant'            => $activeMerchant,
                'merchants'                 => $this->getMerchantsToDisplay($customerSession, $widgetType),
                'options'                   => $this->getTranslationOptions(),
                'captions'                  => $this->getTranslationCaptions(),
                'lang'                      => $this->getLang(),
                'urlWidgetUpdateController' => $this->_storeManager->getStore()->getUrl("pledg/widget/updatecontroller")
            ];
        }
        $ret['widgetActivation'] = $widgetActivation;

        return $ret;
    }
}
