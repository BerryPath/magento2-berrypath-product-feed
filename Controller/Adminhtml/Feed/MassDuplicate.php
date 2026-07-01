<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Controller\Adminhtml\Feed;

use BerryPath\ProductFeed\Model\Profile;
use BerryPath\ProductFeed\Model\ProfileFactory;
use BerryPath\ProductFeed\Model\ProfileRepository;
use BerryPath\ProductFeed\Model\ResourceModel\Profile\CollectionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;

class MassDuplicate extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'BerryPath_ProductFeed::feeds';

    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly ProfileFactory $profileFactory,
        private readonly ProfileRepository $profileRepository
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $duplicated = 0;

            foreach ($collection as $profile) {
                if (!$profile instanceof Profile) {
                    continue;
                }

                $this->duplicateProfile($profile);
                $duplicated++;
            }

            $this->messageManager->addSuccessMessage(__('A total of %1 feed(s) have been duplicated.', $duplicated));
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }

        return $this->resultRedirectFactory->create()->setPath('berrypath/feed/index');
    }

    private function duplicateProfile(Profile $profile): void
    {
        $data = $profile->getData();
        unset($data['entity_id'], $data['created_at'], $data['updated_at']);
        $data['name'] = (string)__('Copy of %1', $profile->getName());

        $copy = $this->profileFactory->create();
        $copy->setData($data);

        $this->profileRepository->save($copy);
    }
}
