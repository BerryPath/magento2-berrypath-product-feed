<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Controller\Adminhtml\Feed;

use BerryPath\ProductFeed\Model\Feed\ProfileGenerator;
use BerryPath\ProductFeed\Model\Profile;
use BerryPath\ProductFeed\Model\ResourceModel\Profile\CollectionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;

class MassGenerate extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'BerryPath_ProductFeed::feeds';

    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly ProfileGenerator $profileGenerator
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $generated = 0;

            foreach ($collection as $profile) {
                if (!$profile instanceof Profile) {
                    continue;
                }

                $this->profileGenerator->generate($profile);
                $generated++;
            }

            $this->messageManager->addSuccessMessage(__('A total of %1 feed(s) have been generated.', $generated));
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }

        return $this->resultRedirectFactory->create()->setPath('berrypath/feed/index');
    }
}
