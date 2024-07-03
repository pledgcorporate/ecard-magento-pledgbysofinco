<?php

namespace Pledg\PledgPaymentGateway\Test\Unit\Observer;

use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Payment\Model\Method\Adapter;
use Magento\Quote\Api\Data\CartInterface;
use PHPUnit\Framework\TestCase;
use Pledg\PledgPaymentGateway\Observer\PaymentMethodAvailable;
use Pledg\PledgPaymentGateway\Test\Unit\Mock\CustomerAttribute;

class PaymentMethodAvailableTest extends TestCase
{
    private const QUOTE_ID = 30;
    private const CUSTOMER_ID = 50;
    private const ADDRESS_ID = 1200;
    private const GROUP_A_ID = 4300;
    private const GROUP_B_ID = 4400;

    public function testCustomerCannotSeeDisabledGateway()
    {
        $testedClass = $this->getTestedClass();

        $result = new DataObject();
        // Inactive gateway
        $observer = $this->getObserverMock($result, false, null, false);

        $testedClass->execute($observer);
        $this->assertFalse($result->getData('is_available'));
    }

    public function testB2bCustomerWithCustomCompanyCannotSeeB2cGateway()
    {
        // B2B customer
        $testedClass = $this->getTestedClass(true, true, true);

        // B2C gateway
        $result = new DataObject();
        $observer = $this->getObserverMock($result, false);

        $testedClass->execute($observer);
        $this->assertFalse($result->getData('is_available'));
    }

    public function testB2bCustomerWithNativeCompanyCannotSeeB2cGateway()
    {
        // B2B customer
        $testedClass = $this->getTestedClass(
            true,
            true,
            false,
            'COMPANY_NAME'
        );

        // B2C gateway
        $result = new DataObject();
        $observer = $this->getObserverMock($result, false);

        $testedClass->execute($observer);
        $this->assertFalse($result->getData('is_available'));
    }

    public function testB2bCustomerWithCustomCompanyCanSeeB2bGateway()
    {
        // B2B customer
        $testedClass = $this->getTestedClass(true, true, true);

        // B2B gateway
        $result = new DataObject();
        $observer = $this->getObserverMock($result, true);

        $testedClass->execute($observer);
        $this->assertNull($result->getData('is_available'));
    }

    public function testB2bCustomerWithNativeCompanyCanSeeB2bGateway()
    {
        // B2B customer
        $testedClass = $this->getTestedClass(
            true,
            true,
            false,
            'COMPANY_NAME'
        );

        // B2B gateway
        $result = new DataObject();
        $observer = $this->getObserverMock($result, true);

        $testedClass->execute($observer);
        $this->assertNull($result->getData('is_available'));
    }

    public function testB2cCustomerCanSeeB2cGateway()
    {
        // B2C customer
        $testedClass = $this->getTestedClass(false);

        // B2C gateway
        $result = new DataObject();
        $observer = $this->getObserverMock($result, false);

        $testedClass->execute($observer);
        $this->assertNull($result->getData('is_available'));
    }

    public function testB2cCustomerCannotSeeB2bGateway()
    {
        // B2C customer
        $testedClass = $this->getTestedClass();

        // B2B gateway
        $result = new DataObject();
        $observer = $this->getObserverMock($result, true);

        $testedClass->execute($observer);
        $this->assertFalse($result->getData('is_available'));
    }

    public function testNotLoggedCustomerCannotSeeB2bGateway()
    {
        // Not logged customer
        $testedClass = $this->getTestedClass(false);

        // B2B gateway
        $result = new DataObject();
        $observer = $this->getObserverMock($result, true);

        $testedClass->execute($observer);
        $this->assertFalse($result->getData('is_available'));
    }

    public function testNotLoggedCustomerCanSeeB2cGateway()
    {
        // Not logged customer
        $testedClass = $this->getTestedClass(false);

        // B2C gateway
        $result = new DataObject();
        $observer = $this->getObserverMock($result, false);

        $testedClass->execute($observer);
        $this->assertNull($result->getData('is_available'));
    }

    public function testCustomerInGroupACannotSeeGatewayInGroupB()
    {
        $testedClass = $this->getTestedClass(false);

        $result = new DataObject();
        $observer = $this->getObserverMock($result, false, self::GROUP_B_ID);

        $testedClass->execute($observer);
        $this->assertFalse($result->getData('is_available'));
    }

    public function testCustomerInGroupBCanSeeGatewayInGroupB()
    {
        $testedClass = $this->getTestedClass(false);

        $result = new DataObject();
        $observer = $this->getObserverMock($result, false, self::GROUP_A_ID);

        $testedClass->execute($observer);
        $this->assertNull($result->getData('is_available'));
    }

    private function getTestedClass(
        bool $isCustomerLogged = true,
        bool $hasCustomSiret = false,
        bool $hasCustomCompany = false,
        ?string $addressCompany = null
    ): PaymentMethodAvailable {
        $billingAddress = $this->createMock(AddressInterface::class);
        $billingAddress
            ->expects($this->any())
            ->method('getCompany')
            ->willReturn($addressCompany)
        ;

        $addressRepository = $this->createMock(AddressRepositoryInterface::class);
        $addressRepository
            ->expects($this->any())
            ->method('getById')
            ->willReturn($billingAddress)
        ;

        $customerInterface = $this->getMockBuilder(CustomerInterface::class)
            ->addMethods(['hasCustomSiret', 'hasCustomCompany'])
            ->getMockForAbstractClass();
        if ($isCustomerLogged) {
            $customerInterface
                ->expects($this->any())
                ->method('hasCustomSiret')
                ->willReturn($hasCustomSiret)
            ;
            $customerInterface
                ->expects($this->any())
                ->method('hasCustomCompany')
                ->willReturn($hasCustomCompany)
            ;
            $customerInterface
                ->expects($this->any())
                ->method('getDefaultBilling')
                ->willReturn(self::ADDRESS_ID)
            ;
        }
        $customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $customerRepository
            ->expects($this->any())
            ->method('getById')
            ->with(self::CUSTOMER_ID)
            ->willReturn($customerInterface)
        ;

        return new PaymentMethodAvailable(
            $addressRepository,
            new CustomerAttribute(),
            $customerRepository,
            $this->getCustomerSessionMock($isCustomerLogged),
            $this->getScopeConfigMock()
        );
    }

    private function getObserverMock(
        DataObject $result,
        bool $isGatewayB2b,
        ?string $gatewayGroups = null,
        bool $isActiveGateway = true
    ): Observer {
        $observer = $this->createMock(Observer::class);
        $observer
            ->expects($this->exactly(3))
            ->method('getData')
            ->withConsecutive(
                ['quote'],
                ['result'],
                ['method_instance']
            )
            ->willReturn(
                $this->getQuoteMock(),
                $result,
                $this->getGatewayAdapterMock($isGatewayB2b, $gatewayGroups, $isActiveGateway)
            )
        ;

        return $observer;
    }

    private function getGatewayAdapterMock(
        bool $isGatewayB2b,
        ?string $gatewayGroups,
        bool $isActiveGateway
    ): Adapter {
        $adapter = $this->createMock(Adapter::class);
        $adapter
            ->expects($this->any())
            ->method('getCode')
            ->willReturn('pledg_gateway_1')
        ;
        $adapter
            ->expects($this->any())
            ->method('getConfigData')
            ->withConsecutive(
                ['active', self::QUOTE_ID],
                ['allowed_groups', self::QUOTE_ID],
                ['is_b2b', self::QUOTE_ID]
            )
            ->willReturn(
                $isActiveGateway,
                $gatewayGroups,
                $isGatewayB2b
            )
        ;

        return $adapter;
    }

    private function getQuoteMock(): CartInterface
    {
        $quote = $this->createMock(CartInterface::class);
        $quote
            ->expects($this->any())
            ->method('getStoreId')
            ->willReturn(self::QUOTE_ID)
        ;

        return $quote;
    }

    private function getCustomerSessionMock(bool $isCustomerLogged): Session
    {
        $customer = $this->createMock(Customer::class);
        $customer
            ->expects($this->any())
            ->method('getId')
            ->willReturn(self::CUSTOMER_ID)
        ;

        $customerSession = $this->createMock(Session::class);
        $customerSession
            ->expects($this->any())
            ->method('isLoggedIn')
            ->willReturn($isCustomerLogged)
        ;
        $customerSession
            ->expects($this->any())
            ->method('getCustomer')
            ->willReturn($customer)
        ;
        $customerSession
            ->expects($this->any())
            ->method('getCustomerGroupId')
            ->willReturn(self::GROUP_A_ID)
        ;

        return $customerSession;
    }

    private function getScopeConfigMock(): ScopeConfigInterface
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);

        $scopeConfig
            ->expects($this->any())
            ->method('getValue')
            ->withConsecutive(
                ['pledg_gateway/payment/siret_custom_field_name'],
                ['pledg_gateway/payment/company_custom_field_name']
            )
            ->willReturn(
                'pledg_siret_number',
                'pledg_company_name'
            )
        ;

        return $scopeConfig;
    }
}
