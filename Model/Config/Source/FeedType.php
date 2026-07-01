<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class FeedType implements OptionSourceInterface
{
    public const PRODUCT = 'product_feed';
    public const GENERIC = self::PRODUCT;
    public const LEGACY_GENERIC = 'generic';
    public const GOOGLE_SHOPPING = 'google_shopping';
    public const META_CATALOG = 'meta_catalog';
    public const TIKTOK_CATALOG = 'tiktok_catalog';
    public const PINTEREST_CATALOG = 'pinterest_catalog';
    public const MICROSOFT_SHOPPING = 'microsoft_shopping';
    public const OPENAI_PRODUCT = 'openai_product';

    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::PRODUCT, 'label' => __('Product Feed')],
            ['value' => self::GOOGLE_SHOPPING, 'label' => __('Google Shopping Feed')],
            ['value' => self::META_CATALOG, 'label' => __('Meta Catalog Feed')],
            ['value' => self::TIKTOK_CATALOG, 'label' => __('TikTok Catalog Feed')],
            ['value' => self::PINTEREST_CATALOG, 'label' => __('Pinterest Catalog Feed')],
            ['value' => self::MICROSOFT_SHOPPING, 'label' => __('Microsoft Shopping Feed')],
            ['value' => self::OPENAI_PRODUCT, 'label' => __('OpenAI Product Feed')],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [
            self::PRODUCT,
            self::GOOGLE_SHOPPING,
            self::META_CATALOG,
            self::TIKTOK_CATALOG,
            self::PINTEREST_CATALOG,
            self::MICROSOFT_SHOPPING,
            self::OPENAI_PRODUCT,
        ];
    }
}
