<?php

namespace Pledg\PledgPaymentGateway\Gateway\Config;

use Magento\Payment\Gateway\Config\Config as BaseConfig;

class Config extends BaseConfig
{
    /**
     * @param string $field
     * @param int|null $storeId
     *
     * @return mixed
     */
    public function getValue($field, $storeId = null)
    {
        if ($field === 'order_place_redirect_url') {
            // Prevent order email sending when placing the order
            return true;
        }

        return parent::getValue($field, $storeId);
    }
}
