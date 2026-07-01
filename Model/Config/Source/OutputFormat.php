<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class OutputFormat implements OptionSourceInterface
{
    public const XML = 'xml';
    public const CSV = 'csv';
    public const JSON = 'json';

    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::XML, 'label' => __('XML')],
            ['value' => self::CSV, 'label' => __('CSV')],
            ['value' => self::JSON, 'label' => __('JSON')],
        ];
    }
}
