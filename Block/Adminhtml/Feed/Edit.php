<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Block\Adminhtml\Feed;

use BerryPath\ProductFeed\Model\Feed\Config as FeedConfig;
use BerryPath\ProductFeed\Model\Feed\FileStorage;
use BerryPath\ProductFeed\Model\Profile;
use Magento\Backend\Block\Widget\Context;
use Magento\Backend\Block\Widget\Form\Container;
use Magento\Framework\Registry;

class Edit extends Container
{
    public function __construct(
        Context $context,
        private readonly FeedConfig $feedConfig,
        private readonly FileStorage $fileStorage,
        private readonly Registry $registry,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _construct(): void
    {
        $this->_objectId = 'id';
        $this->_blockGroup = 'BerryPath_ProductFeed';
        $this->_controller = 'adminhtml_feed';

        parent::_construct();

        $this->buttonList->update('save', 'label', __('Save Feed'));
        $this->buttonList->add(
            'save_and_continue_edit',
            [
                'class' => 'save',
                'label' => __('Save and Continue Edit'),
                'data_attribute' => [
                    'mage-init' => ['button' => ['event' => 'saveAndContinueEdit', 'target' => '#edit_form']],
                ],
            ],
            10
        );
    }

    protected function _prepareLayout()
    {
        $profile = $this->getProfile();
        if ($profile->getId() && $this->fileStorage->exists($profile)) {
            $this->buttonList->add(
                'open_feed',
                [
                    'class' => 'action-secondary',
                    'label' => __('Open Feed'),
                    'onclick' => "window.open('" . $this->escapeJs($this->fileStorage->getUrl($profile))
                        . "', '_blank', 'noopener');",
                ],
                0
            );
        }

        if ($profile->getId()) {
            $this->buttonList->add(
                'generate_feed',
                [
                    'class' => 'action-secondary',
                    'label' => __('Generate Feed'),
                    'onclick' => "setLocation('" . $this->escapeJs(
                        $this->getUrl('berrypath/feed/generate', ['id' => (int)$profile->getId()])
                    ) . "');",
                ],
                0
            );

            $this->buttonList->add(
                'preview_feed',
                [
                    'class' => 'action-secondary',
                    'label' => __('Preview'),
                    'onclick' => "window.open('" . $this->escapeJs($this->feedConfig->getPreviewUrlForProfile($profile))
                        . "', '_blank', 'noopener');",
                ],
                0
            );
        }

        return parent::_prepareLayout();
    }

    public function getHeaderText(): \Magento\Framework\Phrase
    {
        $profile = $this->getProfile();

        return $profile->getId()
            ? __('Edit Feed "%1"', $this->escapeHtml($profile->getName()))
            : __('New Feed');
    }

    public function getFormActionUrl(): string
    {
        return $this->getUrl('berrypath/feed/save');
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('berrypath/feed/index');
    }

    public function getDeleteUrl(): string
    {
        return $this->getUrl('berrypath/feed/delete', ['id' => (int)$this->getProfile()->getId()]);
    }

    private function getProfile(): Profile
    {
        return $this->registry->registry('current_berrypath_productfeed_profile');
    }
}
