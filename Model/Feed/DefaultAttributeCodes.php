<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Model\Feed;

class DefaultAttributeCodes
{
    /**
     * @var array<int, string>
     */
    private const CODES = [
        'category_ids',
        'description',
        'entity_id',
        'image',
        'minimal_price',
        'name',
        'price',
        'short_description',
        'sku',
        'small_image',
        'thumbnail',
        'tax_class_id',
        'url_key',
        'url_path',
        'visibility',
    ];

    public static function contains(string $attributeCode): bool
    {
        return in_array($attributeCode, self::CODES, true)
            || str_starts_with($attributeCode, 'berrypath_flow_');
    }
}
