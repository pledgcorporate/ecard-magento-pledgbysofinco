<?php

namespace Pledg\PledgPaymentGateway\Block\System\Config;

use Magento\Directory\Model\ResourceModel\Country\CollectionFactory;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\Html\Select;

class CountryColumn extends Select
{
    /**
     * @var CollectionFactory
     */
    private $countryCollectionFactory;

    /**
     * @param Context           $context
     * @param CollectionFactory $countryCollectionFactory
     * @param array             $data
     */
    public function __construct(
        Context $context,
        CollectionFactory $countryCollectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->countryCollectionFactory = $countryCollectionFactory;
    }

    /**
     * @return array
     */
    private function getCountries()
    {
        $options = $this->countryCollectionFactory->create()->load()->toOptionArray(false);
        array_unshift($options, ['value' => '', 'label' => __('Select country')]);

        return $options;
    }

    /**
     * Set "name" for <select> element
     *
     * @param string $value
     * @return $this
     */
    public function setInputName($value)
    {
        return $this->setName($value);
    }

    /**
     * Set "id" for <select> element
     *
     * @param $value
     * @return $this
     */
    public function setInputId($value)
    {
        return $this->setId($value);
    }

    /**
     * Render block HTML
     *
     * @return string
     */
    public function _toHtml(): string
    {
        if (!$this->getOptions()) {
            $this->setOptions($this->getCountries());
        }
        return parent::_toHtml();
    }
}
