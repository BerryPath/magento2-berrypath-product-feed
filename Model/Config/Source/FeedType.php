<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class FeedType implements OptionSourceInterface
{
    public const GENERIC = 'generic';
    public const GOOGLE_SHOPPING = 'google_shopping';

    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::GENERIC, 'label' => __('Generic XML')],
            ['value' => self::GOOGLE_SHOPPING, 'label' => __('Google Shopping RSS 2.0')],
        ];
    }
}
