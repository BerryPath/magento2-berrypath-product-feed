<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Controller\Adminhtml\Feed;

use BerryPath\ProductFeed\Model\Feed\Config as FeedConfig;
use BerryPath\ProductFeed\Model\Profile;
use BerryPath\ProductFeed\Model\ProfileFactory;
use BerryPath\ProductFeed\Model\ProfileRepository;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'BerryPath_ProductFeed::feeds';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly ProfileRepository $profileRepository,
        private readonly ProfileFactory $profileFactory,
        private readonly FeedConfig $feedConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly Registry $registry
    ) {
        parent::__construct($context);
    }

    public function execute(): Page|Redirect
    {
        $profileId = (int)$this->getRequest()->getParam('id');

        try {
            $profile = $profileId > 0
                ? $this->profileRepository->getById($profileId)
                : $this->createDefaultProfile();
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage(__('This feed no longer exists.'));

            return $this->resultRedirectFactory->create()->setPath('berrypath/feed/index');
        }

        $this->registry->register('current_berrypath_productfeed_profile', $profile);
        $title = $profile->getId() ? __($profile->getName()) : __('New Feed');

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('BerryPath_ProductFeed::feeds');
        $resultPage->getConfig()->getTitle()->prepend(__('Product Feeds'));
        $resultPage->getConfig()->getTitle()->prepend($title);

        return $resultPage;
    }

    private function createDefaultProfile(): Profile
    {
        $storeId = $this->getFirstStoreId();
        $profile = $this->profileFactory->create();
        $profile->setData([
            'is_active' => 1,
            'feed_type' => $this->feedConfig->getFeedType($storeId),
            'output_format' => $this->feedConfig->getOutputFormat($storeId),
            'use_cdata' => (int)$this->feedConfig->useCdata($storeId),
            'store_id' => $storeId,
            'market_code' => '',
            'locale_code' => '',
            'product_identifier' => $this->feedConfig->getProductIdentifierSource($storeId),
            'active_products_only' => (int)$this->feedConfig->activeProductsOnly($storeId),
            'visible_products_only' => (int)$this->feedConfig->visibleProductsOnly($storeId),
            'salable_products_only' => (int)$this->feedConfig->salableProductsOnly($storeId),
            'skip_child_products_of_inactive_parents' => (int)$this->feedConfig
                ->skipChildProductsOfInactiveParents($storeId),
            'include_not_visible' => (int)!$this->feedConfig->visibleProductsOnly($storeId),
            'extra_attributes' => implode(',', $this->feedConfig->getExtraAttributeCodes($storeId)),
            'google_condition' => 'new',
            'google_include_shipping' => 0,
            'google_shipping_service' => 'Standard',
            'schedule_enabled' => 1,
            'schedule_cron_expression' => '0 2 * * *',
            'generated_at' => null,
            'last_generation_error' => null,
        ]);

        return $profile;
    }

    private function getFirstStoreId(): int
    {
        foreach ($this->storeManager->getStores() as $store) {
            return (int)$store->getId();
        }

        throw new NoSuchEntityException(__('No store view is available.'));
    }
}
