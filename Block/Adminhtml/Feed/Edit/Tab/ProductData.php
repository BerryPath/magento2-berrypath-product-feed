<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Block\Adminhtml\Feed\Edit\Tab;

use BerryPath\ProductFeed\Model\Config\Source\ProductAttribute;
use BerryPath\ProductFeed\Model\Config\Source\ProductIdentifier;
use Magento\Backend\Block\Widget\Form as WidgetForm;

class ProductData extends AbstractTab
{
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Data\FormFactory $formFactory,
        private readonly ProductIdentifier $productIdentifierSource,
        private readonly ProductAttribute $productAttributeSource,
        array $data = []
    ) {
        parent::__construct($context, $registry, $formFactory, $data);
    }

    public function getTabLabel(): string
    {
        return (string)__('Product Data');
    }

    protected function _prepareForm(): WidgetForm
    {
        $form = $this->_formFactory->create();
        $fieldset = $form->addFieldset('product_data_fieldset', ['legend' => __('Product Data')]);

        $fieldset->addField('product_identifier', 'select', [
            'name' => 'product_identifier',
            'label' => __('Product ID Source'),
            'title' => __('Product ID Source'),
            'values' => $this->productIdentifierSource->toOptionArray(),
        ]);
        $fieldset->addField('active_products_only', 'select', [
            'name' => 'active_products_only',
            'label' => __('Active Products Only'),
            'title' => __('Active Products Only'),
            'values' => $this->getYesNoOptions(),
            'note' => __('Leave disabled products out of the feed.'),
        ]);
        $fieldset->addField('visible_products_only', 'select', [
            'name' => 'visible_products_only',
            'label' => __('Catalog Visible Products Only'),
            'title' => __('Catalog Visible Products Only'),
            'values' => $this->getYesNoOptions(),
            'note' => __('Leave products out when Magento hides them from catalog and search listings.'),
        ]);
        $fieldset->addField('salable_products_only', 'select', [
            'name' => 'salable_products_only',
            'label' => __('Products Available for Purchase Only'),
            'title' => __('Products Available for Purchase Only'),
            'values' => $this->getYesNoOptions(),
            'note' => __('When enabled, products Magento marks as not salable are left out.'),
        ]);
        $fieldset->addField('skip_child_products_of_inactive_parents', 'select', [
            'name' => 'skip_child_products_of_inactive_parents',
            'label' => __('Skip Variants of Inactive Parents'),
            'title' => __('Skip Variants of Inactive Parents'),
            'values' => $this->getYesNoOptions(),
            'note' => __('Useful when variant products are included but their configurable, grouped or bundle parent is inactive.'),
        ]);
        $fieldset->addField('extra_attributes', 'multiselect', [
            'name' => 'extra_attributes[]',
            'label' => __('Extra Attributes'),
            'title' => __('Extra Attributes'),
            'values' => $this->productAttributeSource->toOptionArray(),
            'note' => __('Optional product attributes added to each product item.'),
        ]);

        $form->setValues($this->getProfileData());
        $this->setForm($form);

        return parent::_prepareForm();
    }
}
