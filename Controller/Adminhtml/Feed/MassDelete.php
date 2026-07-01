<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Controller\Adminhtml\Feed;

use BerryPath\ProductFeed\Model\Feed\FileStorage;
use BerryPath\ProductFeed\Model\Profile;
use BerryPath\ProductFeed\Model\ProfileRepository;
use BerryPath\ProductFeed\Model\ResourceModel\Profile\CollectionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;

class MassDelete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'BerryPath_ProductFeed::feeds';

    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly ProfileRepository $profileRepository,
        private readonly FileStorage $fileStorage
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $deleted = 0;

            foreach ($collection as $profile) {
                if (!$profile instanceof Profile) {
                    continue;
                }

                $this->fileStorage->delete($profile);
                $this->profileRepository->delete($profile);
                $deleted++;
            }

            $this->messageManager->addSuccessMessage(__('A total of %1 feed(s) have been removed.', $deleted));
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }

        return $this->resultRedirectFactory->create()->setPath('berrypath/feed/index');
    }
}
