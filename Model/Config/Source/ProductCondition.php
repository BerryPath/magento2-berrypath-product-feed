<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ProductCondition implements OptionSourceInterface
{
    public const NEW = 'new';
    public const REFURBISHED = 'refurbished';
    public const USED = 'used';

    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::NEW, 'label' => __('New')],
            ['value' => self::REFURBISHED, 'label' => __('Refurbished')],
            ['value' => self::USED, 'label' => __('Used')],
        ];
    }
}
