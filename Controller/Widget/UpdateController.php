<?php

namespace Pledg\PledgPaymentGateway\Controller\Widget;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Request\Http;

use Psr\Log\LoggerInterface;

use Pledg\PledgPaymentGateway\Helper\Widget as WidgetHelper;

class UpdateController extends \Magento\Framework\App\Action\Action
{
    /**
     * @var Session
     */
    protected $_customerSession;

    /**
     * @var WidgetHelper
     */
    protected $_helperWidget;

    /**
     * @var LoggerInterface
     */
    protected $_logger;

    /**
     * @var JsonFactory
     */
    protected $_jsonFactory;

    /**
     * @var Http
     */
    protected $_httpRequest;

    /**
     * UpdateController constructor.
     * @param Context $context
     * @param JsonFactory $jsonFactory
     * @param WidgetHelper $helperWidget
     * @param Session $customerSession
     * @param LoggerInterface $logger
     * @param Http $request
     * @param array $data
     */
    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        WidgetHelper $helperWidget,
        Session $customerSession,
        LoggerInterface $logger,
        Http $request
    ) {
        parent::__construct($context);
        $this->_helperWidget = $helperWidget;
        $this->_customerSession = $customerSession;
        $this->_logger = $logger;
        $this->_jsonFactory = $jsonFactory;
        $this->_httpRequest = $request;
    }

    /**
     * Execute action and return JSON response.
     */
    public function execute()
    {
        $widgetType = $this->_httpRequest->getParam('widgetType', 'cart');

        // for now, the widget is actually activated for product page only
        $widgetType = 'product';

        $activeMerchant = $this->_httpRequest->getParam('activeMerchant', 'merchantIcon0');

        $widgetOptions = $this->_helperWidget->getWidgetOptions($this->_customerSession, $widgetType, $activeMerchant);

        $block = $this->_view->getLayout()->createBlock('Pledg\PledgPaymentGateway\Block\Product\Widget');
        $block->setTemplate('Pledg_PledgPaymentGateway::widget/block.phtml');
        $block->setOptions($widgetOptions);
        $block->setData('widgetType', $widgetType);
        $block->setData('activeMerchant', $activeMerchant);

        $resultJson = $this->_jsonFactory->create();
        $resultJson->setData([ 'html' => $block->toHtml() ]);

        return $resultJson;
    }
}