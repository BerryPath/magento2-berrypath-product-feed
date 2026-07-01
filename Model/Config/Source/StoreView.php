<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Store\Model\StoreManagerInterface;

class StoreView implements OptionSourceInterface
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->storeManager->getStores() as $store) {
            $options[] = [
                'value' => (int)$store->getId(),
                'label' => sprintf('%s / ID %d', $store->getName(), (int)$store->getId()),
            ];
        }

        return $options;
    }
}
