<?php

namespace Pledg\PledgPaymentGateway\Controller\Checkout;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Pledg\PledgPaymentGateway\Model\Ui\ConfigProvider;

abstract class CheckoutAction extends Action
{
    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var OrderFactory
     */
    private $orderFactory;

    /**
     * @param Context      $context
     * @param Session      $checkoutSession
     * @param OrderFactory $orderFactory
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        OrderFactory $orderFactory
    ) {
        parent::__construct($context);

        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
    }

    /**
     * @param array $validStates
     *
     * @return Order
     *
     * @throws \Exception
     */
    protected function getLastOrder(array $validStates): Order
    {
        $lastIncrementId = $this->checkoutSession->getLastRealOrderId();

        if (!$lastIncrementId) {
            throw new \Exception('Could not retrieve last order id');
        }
        $order = $this->orderFactory->create();
        $order->loadByIncrementId($lastIncrementId);

        if (!$order->getId()) {
            throw new \Exception(sprintf('Could not retrieve order with id %s', $lastIncrementId));
        }

        $paymentMethod = $order->getPayment()->getMethod();

        if (!in_array($paymentMethod, ConfigProvider::getPaymentMethodCodes())) {
            throw new \Exception(sprintf('Order with method %s wrongfully accessed PledgBySofinco page', $paymentMethod));
        }

        if (!in_array($order->getState(), $validStates)) {
            throw new \Exception(sprintf('Order with state %s wrongfully accessed PledgBySofinco page', $order->getState()));
        }

        return $order;
    }

    /**
     * @return Session
     */
    protected function getCheckoutSession(): Session
    {
        return $this->checkoutSession;
    }
}
