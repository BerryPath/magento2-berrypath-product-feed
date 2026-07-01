<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Profile extends AbstractDb
{
    public const TABLE_NAME = 'berrypath_productfeed_profile';

    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, 'entity_id');
    }
}
