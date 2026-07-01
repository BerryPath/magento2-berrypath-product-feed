<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Controller\Adminhtml\Feed;

use BerryPath\ProductFeed\Model\Feed\ProfileGenerator;
use BerryPath\ProductFeed\Model\ProfileRepository;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;

class Generate extends Action implements HttpGetActionInterface, HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'BerryPath_ProductFeed::feeds';

    public function __construct(
        Context $context,
        private readonly ProfileRepository $profileRepository,
        private readonly ProfileGenerator $profileGenerator
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $profileId = (int)$this->getRequest()->getParam('id');
        if ($profileId <= 0) {
            $this->messageManager->addErrorMessage(__('Unable to find the feed to generate.'));

            return $this->resultRedirectFactory->create()->setPath('berrypath/feed/index');
        }

        try {
            $profile = $this->profileRepository->getById($profileId);
            $result = $this->profileGenerator->generate($profile);
            $this->messageManager->addSuccessMessage(
                __('The feed has been generated. %1 product(s) exported.', $result['products_output'])
            );
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }

        return $this->resultRedirectFactory->create()->setPath('berrypath/feed/edit', ['id' => $profileId]);
    }
}
