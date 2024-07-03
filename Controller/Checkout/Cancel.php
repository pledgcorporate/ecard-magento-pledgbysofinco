<?php

namespace Pledg\PledgPaymentGateway\Controller\Checkout;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Psr\Log\LoggerInterface;

class Cancel extends CheckoutAction
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Context         $context
     * @param Session         $checkoutSession
     * @param OrderFactory    $orderFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        OrderFactory $orderFactory,
        LoggerInterface $logger
    ) {
        parent::__construct($context, $checkoutSession, $orderFactory);

        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        try {
            $order = $this->getLastOrder([Order::STATE_NEW, Order::STATE_PENDING_PAYMENT]);

            $comment = __('Payment has been cancelled by customer');
            $errorMessage = $this->_request->getParam('pledg_error');
            if (!empty($errorMessage)) {
                $comment = __($errorMessage);
            }
            $order->registerCancellation($comment)->save();
            $this->getCheckoutSession()->restoreQuote();

            if (!empty($errorMessage)) {
                $this->messageManager->addErrorMessage(__('An error occurred while processing your payment.'));
            } else {
                $this->messageManager->addSuccessMessage(__('Your payment has successfully been cancelled.'));
            }
        } catch (\Exception $e) {
            $this->logger->error('An error occurred on PledgBySofinco cancel page', [
                'exception' => $e,
            ]);

            $this->messageManager->addErrorMessage(
                __('An error occurred while cancelling your order. Please try again.')
            );
        }

        return $this->resultRedirectFactory->create()->setPath('checkout/cart');
    }
}
