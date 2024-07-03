<?php

namespace Pledg\PledgPaymentGateway\Observer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Message\ManagerInterface;
use Pledg\PledgPaymentGateway\Model\Ui\ConfigProvider;

class PaymentConfigObserver implements ObserverInterface
{
    /**
     * @var Curl
     */
    private $curl;

    /**
     * @var Http
     */
    private $request;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    public function __construct(
        Curl $curl,
        Http $request,
        ManagerInterface $messageManager,
        WriterInterface $configWriter,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->curl = $curl;
        $this->request = $request;
        $this->messageManager = $messageManager;
        $this->configWriter = $configWriter;
        $this->scopeConfig = $scopeConfig;
    }

    public function execute(EventObserver $observer)
    {
        $postParams = $this->request->getPost();

        if (!isset($postParams['config_state'])) {
            return;
        }

        $groups = $postParams['groups'];
        foreach (ConfigProvider::getPaymentMethodCodes() as $paymentMethodCode) {
            if ($this->canProcessSection($postParams, $paymentMethodCode)) {
                $fields = $groups[$paymentMethodCode]['fields'];

                if (!empty($fields['active']['value']) && array_key_exists('api_key_mapping', $fields)) {
                    $countryMapping = $fields['api_key_mapping']['value'] ?? [];
                    $hasError = false;
                    $countries = [];
                    foreach ($countryMapping as $row) {
                        if (!isset($row['country'])) {
                            // Magento adds an empty line
                            continue;
                        }
                        if (empty($row['country'])) {
                            $hasError = true;
                            $this->messageManager->addErrorMessage(
                                __('Please select a country on PledgBySofinco payment method %1', $paymentMethodCode)
                            );
                        }
                        if (empty($row['api_key'])) {
                            $hasError = true;
                            $this->messageManager->addErrorMessage(
                                __('Please fill in an api key on PledgBySofinco payment method %1', $paymentMethodCode)
                            );
                        } else {
                            // Check B2B consistance
                            $pledgMerchantApiUrl = $this->scopeConfig->getValue('pledg_gateway/payment/staging')
                                ? $this->scopeConfig->getValue('pledg_gateway/payment/staging_api_url')
                                : $this->scopeConfig->getValue('pledg_gateway/payment/prod_api_url')
                            ;
                            $pledgMerchantApiUrl = $pledgMerchantApiUrl . '/merchants/' . $row['api_key'];

                            $this->curl->get($pledgMerchantApiUrl);
                            $result = json_decode($this->curl->getBody());

                            if (isset($result->error)) {
                                $hasError = true;
                                $this->messageManager->addErrorMessage($result->error->debug);
                            } else {
                                $merchantB2B = $fields['is_b2b']['value'];

                                if ($result->is_b2b != $merchantB2B) {
                                    $hasError = true;
                                    $this->messageManager->addErrorMessage(sprintf(
                                        __('You are trying to configure a %s payment method whereas one or more of its merchants Uid are %s'),
                                        $merchantB2B ? __('Business to Business') : __('Business to Customer'),
                                        $result->is_b2b ? __('Business to Business') : __('Business to Customer')
                                    ));
                                }
                            }
                        }

                        if (in_array($row['country'], $countries)) {
                            $hasError = true;
                            $this->messageManager->addErrorMessage(__(
                                'Please remove duplicate mapping for country %1 on PledgBySofinco payment method %2',
                                $row['country'],
                                $paymentMethodCode
                            ));
                        }

                        $countries[] = $row['country'];
                    }

                    if (count($countries) === 0) {
                        $hasError = true;
                        $this->messageManager->addErrorMessage(__(
                            'You must select at least one country to be able to activate PledgBySofinco payment method %1',
                            $paymentMethodCode
                        ));
                    }

                    if ($hasError) {
                        $groups[$paymentMethodCode]['fields']['active']['value'] = 0;
                        continue;
                    }

                    $this->configWriter->save(sprintf('payment/%s/allowspecific', $paymentMethodCode), 1);
                    $this->configWriter->save(sprintf('payment/%s/specificcountry', $paymentMethodCode), implode(',', $countries));
                }
            }
        }

        $this->request->setPostValue('groups', $groups);
    }

    /**
     * @return bool
     */
    private function canProcessSection($postParams, $sectionCode)
    {
        $sections = $postParams['config_state'];
        foreach (array_keys($sections) as $sectionKey) {
            if (strpos($sectionKey, $sectionCode) !== false) {
                if (isset($postParams['groups'][$sectionCode]['fields'])) {
                    return true;
                }
            }
        }

        return false;
    }
}
