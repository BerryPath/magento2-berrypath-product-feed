<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Block\Adminhtml\Feed\Edit;

use BerryPath\ProductFeed\Block\Adminhtml\Feed\Edit\Tab\General;
use BerryPath\ProductFeed\Block\Adminhtml\Feed\Edit\Tab\GoogleShopping;
use BerryPath\ProductFeed\Block\Adminhtml\Feed\Edit\Tab\ProductData;
use BerryPath\ProductFeed\Block\Adminhtml\Feed\Edit\Tab\Schedule;
use BerryPath\ProductFeed\Model\Config\Source\FeedType;
use BerryPath\ProductFeed\Model\Profile;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Tabs as WidgetTabs;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\Json\EncoderInterface;
use Magento\Framework\Registry;

class Tabs extends WidgetTabs
{
    public function __construct(
        Context $context,
        EncoderInterface $jsonEncoder,
        Session $authSession,
        private readonly Registry $registry,
        array $data = []
    ) {
        parent::__construct($context, $jsonEncoder, $authSession, $data);
    }

    protected function _construct(): void
    {
        $this->setId('berrypath_productfeed_profile_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle(__('Options'));
        parent::_construct();
    }

    protected function _prepareLayout()
    {
        $this->addTab('general', $this->getLayout()->createBlock(General::class));
        $this->addTab('product_data', $this->getLayout()->createBlock(ProductData::class));
        $this->addTab('schedule', $this->getLayout()->createBlock(Schedule::class));
        $this->addTab('google_shopping', $this->getLayout()->createBlock(GoogleShopping::class));

        return parent::_prepareLayout();
    }

    private function getProfile(): Profile
    {
        return $this->registry->registry('current_berrypath_productfeed_profile');
    }
}
