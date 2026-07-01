<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Controller\Adminhtml\Feed;

use BerryPath\ProductFeed\Model\Feed\FileStorage;
use BerryPath\ProductFeed\Model\ProfileRepository;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;

class Delete extends Action implements HttpGetActionInterface, HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'BerryPath_ProductFeed::feeds';

    public function __construct(
        Context $context,
        private readonly ProfileRepository $profileRepository,
        private readonly FileStorage $fileStorage
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $profileId = (int)$this->getRequest()->getParam('id');
        if ($profileId <= 0) {
            $this->messageManager->addErrorMessage(__('Unable to find the feed to delete.'));

            return $this->resultRedirectFactory->create()->setPath('berrypath/feed/index');
        }

        try {
            $profile = $this->profileRepository->getById($profileId);
            $this->fileStorage->delete($profile);
            $this->profileRepository->delete($profile);
            $this->messageManager->addSuccessMessage(__('The feed has been deleted.'));
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }

        return $this->resultRedirectFactory->create()->setPath('berrypath/feed/index');
    }
}
