<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Block\Adminhtml\Feed\Edit;

use Magento\Backend\Block\Widget\Form as WidgetForm;
use Magento\Backend\Block\Widget\Form\Generic;

class Form extends Generic
{
    protected function _construct(): void
    {
        parent::_construct();
        $this->setId('berrypath_productfeed_profile_form');
        $this->setTitle(__('Feed Information'));
    }

    protected function _prepareForm(): WidgetForm
    {
        $form = $this->_formFactory->create([
            'data' => [
                'id' => 'edit_form',
                'class' => 'berrypath-productfeed-edit-form',
                'action' => $this->getData('action') ?: $this->getUrl('berrypath/feed/save'),
                'method' => 'post',
            ],
        ]);
        $form->setUseContainer(true);
        $this->setForm($form);

        return parent::_prepareForm();
    }
}
