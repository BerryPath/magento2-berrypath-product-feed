<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Model\ResourceModel\Profile;

use BerryPath\ProductFeed\Model\Profile as ProfileModel;
use BerryPath\ProductFeed\Model\ResourceModel\Profile as ProfileResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_eventPrefix = 'berrypath_productfeed_profile_collection';
    protected $_eventObject = 'profile_collection';

    protected function _construct(): void
    {
        $this->_init(ProfileModel::class, ProfileResource::class);
    }
}
