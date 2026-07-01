<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Block\Adminhtml\Feed\Edit\Tab;

use BerryPath\ProductFeed\Model\Config\Source\FeedType;
use BerryPath\ProductFeed\Model\Config\Source\LocaleCode;
use BerryPath\ProductFeed\Model\Config\Source\OutputFormat;
use BerryPath\ProductFeed\Model\Config\Source\ProfileStatus;
use BerryPath\ProductFeed\Model\Config\Source\StoreView;
use BerryPath\ProductFeed\Model\Feed\FileStorage;
use Magento\Backend\Block\Widget\Form as WidgetForm;

class General extends AbstractTab
{
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Data\FormFactory $formFactory,
        private readonly FeedType $feedTypeSource,
        private readonly LocaleCode $localeCodeSource,
        private readonly OutputFormat $outputFormatSource,
        private readonly StoreView $storeViewSource,
        private readonly ProfileStatus $profileStatusSource,
        private readonly FileStorage $fileStorage,
        array $data = []
    ) {
        parent::__construct($context, $registry, $formFactory, $data);
    }

    public function getTabLabel(): string
    {
        return (string)__('General');
    }

    protected function _prepareForm(): WidgetForm
    {
        $form = $this->_formFactory->create();
        $fieldset = $form->addFieldset('general_fieldset', ['legend' => __('General')]);

        $fieldset->addField('entity_id', 'hidden', ['name' => 'entity_id']);
        $fieldset->addField('name', 'text', [
            'name' => 'name',
            'label' => __('Name'),
            'title' => __('Name'),
            'required' => true,
            'class' => 'required-entry',
        ]);
        $fieldset->addField('is_active', 'select', [
            'name' => 'is_active',
            'label' => __('Status'),
            'title' => __('Status'),
            'values' => $this->profileStatusSource->toOptionArray(),
        ]);
        $fieldset->addField('store_id', 'select', [
            'name' => 'store_id',
            'label' => __('Store View'),
            'title' => __('Store View'),
            'required' => true,
            'class' => 'required-entry',
            'values' => $this->storeViewSource->toOptionArray(),
        ]);
        $fieldset->addField('market_code', 'text', [
            'name' => 'market_code',
            'label' => __('Market Code'),
            'title' => __('Market Code'),
            'note' => __('Optional market code for this feed, for example nl or nl-b2b.'),
        ]);
        $fieldset->addField('locale_code', 'select', [
            'name' => 'locale_code',
            'label' => __('Locale Code'),
            'title' => __('Locale Code'),
            'values' => $this->localeCodeSource->toOptionArray(),
            'note' => __('Leave empty to use the selected store view locale.'),
        ]);
        $fieldset->addField('feed_type', 'select', [
            'name' => 'feed_type',
            'label' => __('Feed Type'),
            'title' => __('Feed Type'),
            'values' => $this->feedTypeSource->toOptionArray(),
        ]);
        $fieldset->addField('output_format', 'select', [
            'name' => 'output_format',
            'label' => __('Format'),
            'title' => __('Format'),
            'values' => $this->outputFormatSource->toOptionArray(),
        ]);
        $fieldset->addField('use_cdata', 'select', [
            'name' => 'use_cdata',
            'label' => __('Use CDATA'),
            'title' => __('Use CDATA'),
            'values' => $this->getYesNoOptions(),
            'note' => __('Wrap text fields in CDATA sections in XML output.'),
        ]);
        $fieldset->addField('live_link', 'note', [
            'label' => __('Live link'),
            'text' => $this->getLiveLinkHtml(),
        ]);

        $form->setValues($this->getProfileData());
        $this->setForm($form);

        return parent::_prepareForm();
    }

    private function getLiveLinkHtml(): string
    {
        $profile = $this->getProfile();
        if (!$profile->getId() || !$this->fileStorage->exists($profile)) {
            return '<div class="bp-productfeed-live-link bp-productfeed-live-link--empty">'
                . '<strong>' . $this->escapeHtml(__('Live feed file')) . '</strong>'
                . '<span>' . $this->escapeHtml(__('Not generated. Generate the feed to create the live file.')) . '</span>'
                . '</div>';
        }

        $url = $this->fileStorage->getUrl($profile);
        $fileName = $this->fileStorage->getFileName($profile);

        return sprintf(
            '<div class="bp-productfeed-live-link">'
                . '<div class="bp-productfeed-live-link__header">'
                    . '<strong>%s</strong>'
                    . '<span>%s</span>'
                . '</div>'
                . '<a class="bp-productfeed-live-link__url" href="%s" target="_blank" rel="noopener noreferrer">%s</a>'
                . '<div class="bp-productfeed-live-link__actions">'
                    . '<button type="button" class="action-default" data-bp-copy-feed-url="%s">%s</button>'
                    . '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>'
                . '</div>'
            . '</div>',
            $this->escapeHtml(__('Live feed file')),
            $this->escapeHtml($fileName),
            $this->escapeUrl($url),
            $this->escapeHtml($url),
            $this->escapeHtmlAttr($url),
            $this->escapeHtml(__('Copy link')),
            $this->escapeUrl($url),
            $this->escapeHtml(__('Open feed'))
        );
    }
}
