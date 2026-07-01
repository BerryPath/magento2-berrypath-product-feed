<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Ui\DataProvider\Profile;

use BerryPath\ProductFeed\Model\ResourceModel\Profile\CollectionFactory;
use Magento\Ui\DataProvider\AbstractDataProvider;

class Listing extends AbstractDataProvider
{
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);

        $this->collection = $collectionFactory->create();
    }
}
