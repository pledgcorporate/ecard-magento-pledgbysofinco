<?php

namespace Pledg\PledgPaymentGateway\Block\Product;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context as Context;
use Magento\Store\Model\StoreManagerInterface;

use Pledg\PledgPaymentGateway\Helper\Widget as WidgetHelper;
use Magento\Customer\Model\Session;

class Widget extends Template
{
    /**
     * @var array
     */
    protected $_data;

    /**
     * @var WidgetHelper
     */
    protected $_helperWidget;

    /**
     * @var Session
     */
    protected $_customerSession;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * Widget constructor.
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param WidgetHelper $helperWidget
     * @param Session $customerSession
     * @param array $data
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        WidgetHelper $helperWidget,
        Session $customerSession,
        array $data = []
    ) {
        $this->_helperWidget = $helperWidget;
        $this->_customerSession = $customerSession;
        $this->_storeManager = $storeManager;
        parent::__construct($context, $data);
    }

    /**
    * @return string
    */
    private function getWidgetType() {
        $ret = 'cart';

        $widgetType = strtolower($this->_data['widgetType']);

        // for now, the widget is actually activated for product page only
        $widgetType = 'product';

        if (in_array($widgetType, ['cart', 'product'])) {
            $ret = $widgetType;
        }

        return $ret;
    }

    /**
    * @return string
    */
    private function getActiveMerchant() {
        $ret = 'merchantIcon0';

        if (!array_key_exists('activeMerchant', $this->_data)) {
            return $ret;
        }

        $activeMerchant = $this->_data['activeMerchant'];
        $pattern = '/^merchantIcon[0-9]+$/';

        if (preg_match($pattern, $activeMerchant)) {
            $ret = $activeMerchant;
        }

        return $ret;
    }

    /**
     * Returns the data for widget initialization
     *
     * @return array
     */
    public function getOptions() {
        $widgetType = $this->getWidgetType();
        $activeMerchant = $this->getActiveMerchant();

        $ret = $this->_helperWidget->getWidgetOptions($this->_customerSession, $widgetType, $activeMerchant);

        return $ret;
    }
}
