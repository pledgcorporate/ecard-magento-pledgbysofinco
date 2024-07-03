<?php

namespace Pledg\PledgPaymentGateway\Block\System\Config;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;

class MerchantCountryMapping extends AbstractFieldArray
{
    /**
     * @var CountryColumn
     */
    private $countryRenderer;

    /**
     * Prepare rendering the new field by adding all the needed columns
     */
    protected function _prepareToRender()
    {
        $this->addColumn('country', [
            'label' => __('Country'),
            'renderer' => $this->getCountryRenderer(),
        ]);
        $this->addColumn('api_key', [
            'label' => __('Merchant UID'),
            'class' => 'required-entry'
        ]);
        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add Merchant UID Mapping');
    }

    /**
     * Prepare existing row data object
     *
     * @param DataObject $row
     * @throws LocalizedException
     */
    protected function _prepareArrayRow(DataObject $row)
    {
        $options = [];

        $country = $row->getCountry();
        if ($country !== null) {
            $optionKey = 'option_' . $this->getCountryRenderer()->calcOptionHash($country);
            $options[$optionKey] = 'selected="selected"';
        }

        $row->setData('option_extra_attrs', $options);
    }

    /**
     * @return CountryColumn
     * @throws LocalizedException
     */
    private function getCountryRenderer()
    {
        if (!$this->countryRenderer) {
            $this->countryRenderer = $this->getLayout()->createBlock(
                CountryColumn::class,
                '',
                ['data' => ['is_render_to_js_template' => true, 'class' => 'required-entry']]
            );
        }
        return $this->countryRenderer;
    }
}
