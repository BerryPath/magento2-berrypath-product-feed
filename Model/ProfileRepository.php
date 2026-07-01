<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Model;

use BerryPath\ProductFeed\Model\ResourceModel\Profile as ProfileResource;
use BerryPath\ProductFeed\Model\ResourceModel\Profile\CollectionFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class ProfileRepository
{
    public function __construct(
        private readonly ProfileFactory $profileFactory,
        private readonly ProfileResource $profileResource,
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    public function getById(int $profileId): Profile
    {
        $profile = $this->profileFactory->create();
        $this->profileResource->load($profile, $profileId);

        if (!$profile->getId()) {
            throw NoSuchEntityException::singleField('entity_id', $profileId);
        }

        return $profile;
    }

    public function getFirstActiveByStoreId(int $storeId): ?Profile
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('store_id', $storeId);
        $collection->addFieldToFilter('is_active', 1);
        $collection->setOrder('entity_id', 'ASC');
        $collection->setPageSize(1);

        $profile = $collection->getFirstItem();

        return $profile->getId() ? $profile : null;
    }

    public function save(Profile $profile): Profile
    {
        try {
            $this->profileResource->save($profile);
        } catch (\Throwable $exception) {
            throw new CouldNotSaveException(__('Unable to save feed options: %1', $exception->getMessage()), $exception);
        }

        return $profile;
    }

    public function delete(Profile $profile): void
    {
        try {
            $this->profileResource->delete($profile);
        } catch (\Throwable $exception) {
            throw new CouldNotDeleteException(__('Unable to delete feed: %1', $exception->getMessage()), $exception);
        }
    }
}
