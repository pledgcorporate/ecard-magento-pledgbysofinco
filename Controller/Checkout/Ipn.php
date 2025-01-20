<?php

namespace Pledg\PledgPaymentGateway\Controller\Checkout;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\OrderFactory;
use Magento\Store\Model\ScopeInterface;
use Pledg\PledgPaymentGateway\Helper\Config as ConfigHelper;
use Pledg\PledgPaymentGateway\Helper\Crypto;
use Pledg\PledgPaymentGateway\Model\Ui\ConfigProvider;
use Psr\Log\LoggerInterface;

class Ipn extends Action
{
    const MODE_TRANSFER = 'transfer';
    const MODE_BACK = 'back';

    const STATUS_PENDING = [
        "waiting",
        "pending",
        "authorized",
        "pending-capture",
        "in-review",
        "retrieval-request",
        "fraud-notification",
        "chargeback-initiated",
        "solved",
        "reversed"
    ];
    const STATUS_CANCELLED = [
        "failed",
        "voided",
        "refunded",
        "pending-capture",
        "blocked"
    ];
    const STATUS_COMPLETED = [
        "completed"
    ];

    /**
     * @var OrderFactory
     */
    private $orderFactory;

    /**
     * @var Crypto
     */
    private $cryptoHelper;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var OrderSender
     */
    private $orderSender;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Context              $context
     * @param FormKey              $formKey
     * @param OrderFactory         $orderFactory
     * @param Crypto               $cryptoHelper
     * @param ConfigHelper         $configHelper
     * @param ScopeConfigInterface $scopeConfig
     * @param OrderSender          $orderSender
     * @param LoggerInterface      $logger
     */
    public function __construct(
        Context $context,
        FormKey $formKey,
        OrderFactory $orderFactory,
        Crypto $cryptoHelper,
        ConfigHelper $configHelper,
        ScopeConfigInterface $scopeConfig,
        OrderSender $orderSender,
        LoggerInterface $logger
    ) {
        parent::__construct($context);

        $this->orderFactory = $orderFactory;
        $this->cryptoHelper = $cryptoHelper;
        $this->configHelper = $configHelper;
        $this->scopeConfig = $scopeConfig;
        $this->orderSender = $orderSender;
        $this->logger = $logger;

        $this->getRequest()->setParam('form_key', $formKey->getFormKey());
    }

    public function execute()
    {
        $params = json_decode($this->getRequest()->getContent(), true);

        $secretKey = $this->scopeConfig->getValue(
            sprintf('payment/%s/secret_key', $this->getRequest()->getParam('pledg_method')),
            ScopeInterface::SCOPE_STORES,
            (int)$this->getRequest()->getParam('ipn_store_id')
        ) ?? '';

        $this->logger->info('Received IPN', ['params' => $params]);

        $success = true;
        $responseCode = 200;
        $message = '';
        try {
            if (isset($params['signature'])) {
                if (count($params) === 1) {
                    $this->logger->info('Mode signed transfer');

                    $signature = $params['signature'];
                    $params = $this->cryptoHelper->decode($signature, $secretKey);
                    $this->logger->info('Decrypted message', ['params' => $params]);

                    $this->handleTransferMode($params);
                } else {
                    $this->logger->info('Mode signed back');

                    $paramsToValidate = [
                        'created_at',
                        'error',
                        'id',
                        'reference',
                        'sandbox',
                        'status',
                    ];

                    $stringToValidate = [];
                    foreach ($paramsToValidate as $param) {
                        $stringToValidate[] = $param . '=' . $params[$param] ?? '';
                    }
                    $stringToValidate = strtoupper(hash('sha256', implode($secretKey, $stringToValidate)));

                    if ($params['signature'] !== $stringToValidate) {
                        throw new \Exception('Invalid signature');
                    }

                    $this->handleBackMode($params);
                }
            } else {
                $this->logger->info('Mode unsigned transfer');
                $this->handleTransferMode($params);
            }
        } catch (\Exception $e) {
            $this->logger->error('An error occurred while processing IPN', [
                'exception' => $e,
            ]);

            $success = false;
            $responseCode = 500;
            $message = $e->getMessage();
        }

        /** @var Json $response */
        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $response->setHttpResponseCode($responseCode);
        $response->setData(['success' => $success, 'message' => $message]);

        $this->logger->info('IPN response', [
            'success' => $success,
            'message' => $message,
            'responseCode' => $responseCode,
        ]);

        return $response;
    }

    /**
     * @param Order  $order
     * @param string $transactionId
     * @param Phrase $ipnMessage
     *
     * @throws LocalizedException
     */
    private function invoiceOrder(Order $order, string $transactionId, Phrase $ipnMessage): void
    {
        if (!$order->canInvoice()) {
            throw new \Exception(sprintf('Order with state %s cannot be processed and invoiced', $order->getState()));
        }

        $invoice = $order->prepareInvoice();
        $invoice->register();
        $order->addRelatedObject($invoice);
        $invoice->setTransactionId($transactionId);

        $order->setState(Order::STATE_PROCESSING);
        $invoice->pay();
        $order->getPayment()->setBaseAmountPaidOnline($order->getBaseGrandTotal());
        $message = __('Registered update about approved payment.') . ' ' . __('Transaction ID: "%1"', $transactionId);
        $order->addStatusToHistory(
            $order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING),
            $message
        );

        try {
            $this->orderSender->send($order);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }

        $this->addMessageOnOrder($order, $ipnMessage);
    }

    /**
     * @param array $params
     *
     * @throws LocalizedException
     */
    private function handleBackMode(array $params): void
    {
        $order = $this->getOrder($params);

        $pledgStatus = $params['status'] ?? '';
        $transactionId = $params['id'] ?? '';
        $this->logger->info('Payment status received with back mode : ' . $pledgStatus);

        $this->addPaymentInformation($order, $transactionId, self::MODE_BACK, $pledgStatus);

        if (in_array($pledgStatus, self::STATUS_COMPLETED)) {
            $this->logger->info('Invoice order after receiving back notification');
            $this->invoiceOrder($order, $transactionId, __(
                'Received invoicing order from PledgBySofinco back notification with status %1',
                $pledgStatus
            ));

            return;
        }

        if (in_array($pledgStatus, self::STATUS_CANCELLED)) {
            $this->logger->info('Cancel order after receiving back notification');
            if (!$order->canCancel()) {
                throw new \Exception(sprintf('Order %s cannot be canceled', $order->getIncrementId()));
            }
            $order->registerCancellation(__(
                'Received cancellation order from PledgBySofinco back notification with status %1',
                $pledgStatus
            ))->save();

            return;
        }

        if (in_array($pledgStatus, self::STATUS_PENDING)) {
            $this->logger->info('Received back notification with Pending status. Do nothing');
            $this->addMessageOnOrder($order, __(
                'Received PledgBySofinco back notification with status %1. Waiting for further instructions to update order.',
                $pledgStatus
            ));

            return;
        }

        $this->logger->error('Received unhandled status from PledgBySofinco back notification', ['status' => $pledgStatus]);
    }

    /**
     * @param array $params
     *
     * @throws LocalizedException
     */
    private function handleTransferMode(array $params): void
    {
        $order = $this->getOrder($params);

        // In tranfer mode, notification is only sent when payment is validated
        $transactionId = $params['purchase_uid'] ?? '';
        $this->logger->info('Invoice order after receiving transfer notification');

        $this->addPaymentInformation($order, $transactionId, self::MODE_TRANSFER, 'completed');
        $this->invoiceOrder($order, $transactionId, __('Received invoicing order from PledgBySofinco transfer notification'));
    }

    /**
     * @param Order  $order
     * @param string $transactionId
     * @param string $mode
     * @param string $pledgStatus
     */
    private function addPaymentInformation(Order $order, string $transactionId, string $mode, string $pledgStatus): void
    {
        $paymentData = $order->getPayment()->getAdditionalInformation();

        $paymentData['transaction_id'] = $transactionId;
        $paymentData['pledg_mode'] = $mode;
        $paymentData['pledg_status'] = $pledgStatus;
        $paymentData['pledg_dashboard_purchase_url'] = $this->getDashboardPurchaseUrl($order, $transactionId);

        $order->getPayment()->setAdditionalInformation($paymentData)->save();
    }

    /**
     * @param Order  $order
     * @param string $transactionId
     */
    private function getDashboardPurchaseUrl(Order $order, string $transactionId): string
    {
        /*
         * We need a dashboard link so the merchant can go to the purchase page in our dashboard from magento aadmin.
         * This link depends on
         * - environment (staging/prod), given by the 'core_config_data' table
         * - purchase_uid (pur_XXXXXXXX), given by configHelper->getMerchantIdForOrder()
         * - merchant_uid (mer_YYYYYYYY), given by $transactionId
         * We store it at this step because environment value is set in the pledg module general config (not in each merchant config).
         */
        $merchantUid = $this->configHelper->getMerchantIdForOrder($order);
        $isStagingModeActivated = $this->scopeConfig->getValue('pledg_gateway/payment/staging', ScopeInterface::SCOPE_STORE);

        $dashboardUrl = $isStagingModeActivated
            ? $this->configHelper::PLEDG_STAGING_DASHBOARD_URI
            : $this->configHelper::PLEDG_PROD_DASHBOARD_URI
        ;

        $dashboardUrl .= $this->configHelper::PURCHASE_ENDPOINT;
        $dashboardUrl = str_replace('<merchant_uid>', $merchantUid, $dashboardUrl);
        $dashboardUrl = str_replace('<purchase_uid>', $transactionId, $dashboardUrl);

        return $dashboardUrl;
    }

    /**
     * @param Order  $order
     * @param string $message
     *
     * @throws \Exception
     */
    private function addMessageOnOrder(Order $order, string $message): void
    {
        $order->addStatusHistoryComment($message);
        $order->save();
    }

    /**
     * @param array $params
     *
     * @return Order
     *
     * @throws \Exception
     */
    private function getOrder(array $params): Order
    {
        $orderIncrementId = str_replace($this->configHelper::ORDER_REFERENCE_PREFIX, '', $params['reference']);
        $order = $this->orderFactory->create();
        $order->loadByIncrementId($orderIncrementId);

        if (!$order->getId()) {
            throw new \Exception(sprintf('Could not retrieve order with id %s', $orderIncrementId));
        }

        $paymentMethod = $order->getPayment()->getMethod();
        if (!in_array($paymentMethod, ConfigProvider::getPaymentMethodCodes())) {
            throw new \Exception(sprintf('Order with method %s should not be updated via PledgBySofinco notification', $paymentMethod));
        }

        return $order;
    }
}
