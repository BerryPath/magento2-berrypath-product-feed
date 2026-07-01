<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ScheduleStatus implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: int, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 0, 'label' => __('Manual refresh')],
            ['value' => 1, 'label' => __('Scheduled refresh')],
        ];
    }
}
