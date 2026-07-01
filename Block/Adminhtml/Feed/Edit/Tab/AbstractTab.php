<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Block\Adminhtml\Feed\Edit\Tab;

use BerryPath\ProductFeed\Model\Profile;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Backend\Block\Widget\Tab\TabInterface;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Registry;

abstract class AbstractTab extends Generic implements TabInterface
{
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        array $data = []
    ) {
        parent::__construct($context, $registry, $formFactory, $data);
    }

    public function getTabTitle(): string
    {
        return $this->getTabLabel();
    }

    public function canShowTab(): bool
    {
        return true;
    }

    public function isHidden(): bool
    {
        return false;
    }

    /**
     * @return array<int, array{value: int, label: \Magento\Framework\Phrase}>
     */
    protected function getYesNoOptions(): array
    {
        return [
            ['value' => 1, 'label' => __('Yes')],
            ['value' => 0, 'label' => __('No')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getProfileData(): array
    {
        $profile = $this->getProfile();
        $data = $profile->getData();
        $data['entity_id'] = $profile->getId();
        $data['extra_attributes'] = $this->getSelectedExtraAttributes();

        return $data;
    }

    /**
     * @return array<int, string>
     */
    protected function getSelectedExtraAttributes(): array
    {
        $value = (string)$this->getProfile()->getData('extra_attributes');

        return preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    protected function getProfile(): Profile
    {
        return $this->_coreRegistry->registry('current_berrypath_productfeed_profile');
    }
}
