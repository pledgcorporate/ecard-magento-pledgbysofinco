<?php

namespace Pledg\PledgPaymentGateway\Test\Unit\Mock;

use Magento\Customer\Api\Data\CustomerInterface;
use Pledg\PledgPaymentGateway\Helper\CustomerAttribute as BaseCustomerAttribute;

class CustomerAttribute extends BaseCustomerAttribute
{
    public const CUSTOMER_SIRET_NUMBER = '1234567890';
    public const CUSTOMER_COMPANY_NAME = 'COMPANY_NAME';

    public static function getCustomerAttributeValue(CustomerInterface $customer, string $attributeCode): ?string
    {
        switch ($attributeCode) {
            case 'pledg_siret_number':
                if ($customer->hasCustomSiret()) {
                    return self::CUSTOMER_SIRET_NUMBER;
                }
                return null;
            case 'pledg_company_name':
                if ($customer->hasCustomCompany()) {
                    return self::CUSTOMER_COMPANY_NAME;
                }
                return null;
            default:
                return null;
        }
    }
}
