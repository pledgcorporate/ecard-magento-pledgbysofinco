<?php

namespace Pledg\PledgPaymentGateway\Block;

use Magento\Framework\View\Element\Template;

class Head extends Template
{
    /**
     * @return string
     */
    public function getCustomJs()
    {
        if ($this->_scopeConfig->getValue('pledg_gateway/payment/staging')) {
            return 'https://s3-eu-west-1.amazonaws.com/pledg-assets/ecard-plugin/staging/plugin.min.js';
        }

        return 'https://s3-eu-west-1.amazonaws.com/pledg-assets/ecard-plugin/master/plugin.min.js';
    }
}
