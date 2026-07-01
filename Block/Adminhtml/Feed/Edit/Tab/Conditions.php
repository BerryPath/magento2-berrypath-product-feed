<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Block\Adminhtml\Feed\Edit\Tab;

use BerryPath\ProductFeed\Model\Feed\ProfileConditions;
use Magento\Backend\Block\Widget\Form as WidgetForm;
use Magento\Backend\Block\Widget\Form\Renderer\Fieldset;
use Magento\Rule\Block\Conditions as RuleConditions;

class Conditions extends AbstractTab
{
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Data\FormFactory $formFactory,
        private readonly ProfileConditions $profileConditions,
        private readonly RuleConditions $conditionsBlock,
        private readonly Fieldset $rendererFieldset,
        array $data = []
    ) {
        parent::__construct($context, $registry, $formFactory, $data);
    }

    public function getTabLabel(): string
    {
        return (string)__('Conditions');
    }

    protected function _prepareForm(): WidgetForm
    {
        $form = $this->_formFactory->create();
        $profile = $this->getProfile();
        $formName = ProfileConditions::FORM_NAME;
        $conditionsFieldSetId = $this->profileConditions->getConditionsFieldSetId($profile, $formName);
        $rule = $this->profileConditions->createRule($profile);
        $newChildUrl = $this->getUrl(
            'berrypath/feed/newConditionHtml/form/' . $conditionsFieldSetId,
            ['form_namespace' => $formName]
        );

        $this->rendererFieldset
            ->setTemplate('Magento_CatalogRule::promo/fieldset.phtml')
            ->setNewChildUrl($newChildUrl)
            ->setFieldSetId($conditionsFieldSetId);

        $fieldset = $form->addFieldset(
            'conditions_fieldset',
            [
                'legend' => __('Product Conditions'),
                'comment' => __('Leave empty to include all products that match the options above.'),
            ]
        )->setRenderer($this->rendererFieldset);

        $fieldset->addField(
            'conditions',
            'text',
            [
                'name' => 'conditions',
                'label' => __('Conditions'),
                'title' => __('Conditions'),
                'required' => false,
                'data-form-part' => $formName,
            ]
        )->setRule($rule)->setRenderer($this->conditionsBlock);

        $this->profileConditions->setConditionFormName($rule->getConditions(), $formName, $conditionsFieldSetId);

        $this->setForm($form);

        return parent::_prepareForm();
    }
}
