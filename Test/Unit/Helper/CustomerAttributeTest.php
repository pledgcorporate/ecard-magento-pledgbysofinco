<?php

namespace Pledg\PledgPaymentGateway\Test\Unit\Helper;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Api\AttributeInterface;
use PHPUnit\Framework\TestCase;
use Pledg\PledgPaymentGateway\Helper\CustomerAttribute;

class CustomerAttributeTest extends TestCase
{
    /**
     * @var CustomerAttribute
     */
    private $testedClass;

    public function setUp(): void
    {
        $this->testedClass = new CustomerAttribute();
    }

    public function testReturnCustomAttribute()
    {
        $siretAttribute = $this->createMock(AttributeInterface::class);
        $siretAttribute
            ->method('getValue')
            ->willReturn('123456789')
        ;
        $customer = $this->createMock(CustomerInterface::class);
        $customer
            ->method('getCustomAttribute')
            ->with('custom_siret')
            ->willReturn($siretAttribute)
        ;

        $siret = $this->testedClass->getCustomerAttributeValue($customer, 'custom_siret');
        $this->assertEquals('123456789', $siret);
    }

    public function testReturnNoCustomAttribute()
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer
            ->method('getCustomAttribute')
            ->with('custom_siret')
            ->willReturn(null)
        ;

        $siret = $this->testedClass->getCustomerAttributeValue($customer, 'custom_siret');
        $this->assertEquals(null, $siret);
    }
}
