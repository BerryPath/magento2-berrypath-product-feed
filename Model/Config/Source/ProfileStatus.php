<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ProfileStatus implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: int, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 1, 'label' => __('Active')],
            ['value' => 0, 'label' => __('Inactive')],
        ];
    }
}
