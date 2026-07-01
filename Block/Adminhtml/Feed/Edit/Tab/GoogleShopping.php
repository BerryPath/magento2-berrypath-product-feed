<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Block\Adminhtml\Feed\Edit\Tab;

use BerryPath\ProductFeed\Model\Config\Source\FeedType;
use BerryPath\ProductFeed\Model\Config\Source\ProductCondition;
use Magento\Backend\Block\Widget\Form as WidgetForm;

class GoogleShopping extends AbstractTab
{
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Data\FormFactory $formFactory,
        private readonly ProductCondition $productConditionSource,
        array $data = []
    ) {
        parent::__construct($context, $registry, $formFactory, $data);
    }

    public function getTabLabel(): string
    {
        return (string)__('Google Shopping');
    }

    public function isHidden(): bool
    {
        return $this->getProfile()->getFeedType() !== FeedType::GOOGLE_SHOPPING;
    }

    public function getTabClass(): string
    {
        return 'bp-feed-type-tab bp-feed-type-tab--google-shopping';
    }

    protected function _prepareForm(): WidgetForm
    {
        $form = $this->_formFactory->create();
        $fieldset = $form->addFieldset('google_shopping_fieldset', ['legend' => __('Google Shopping')]);

        $fieldset->addField('google_condition', 'select', [
            'name' => 'google_condition',
            'label' => __('Condition'),
            'title' => __('Condition'),
            'values' => $this->productConditionSource->toOptionArray(),
        ]);
        $fieldset->addField('google_include_shipping', 'select', [
            'name' => 'google_include_shipping',
            'label' => __('Include Shipping'),
            'title' => __('Include Shipping'),
            'values' => $this->getYesNoOptions(),
        ]);
        $fieldset->addField('google_shipping_country', 'text', [
            'name' => 'google_shipping_country',
            'label' => __('Shipping Country'),
            'title' => __('Shipping Country'),
            'maxlength' => 2,
            'note' => __('Two-letter ISO country code, for example NL.'),
        ]);
        $fieldset->addField('google_shipping_service', 'text', [
            'name' => 'google_shipping_service',
            'label' => __('Shipping Service'),
            'title' => __('Shipping Service'),
        ]);
        $fieldset->addField('google_shipping_price', 'text', [
            'name' => 'google_shipping_price',
            'label' => __('Shipping Price'),
            'title' => __('Shipping Price'),
        ]);

        $form->setValues($this->getProfileData());
        $this->setForm($form);

        return parent::_prepareForm();
    }
}
