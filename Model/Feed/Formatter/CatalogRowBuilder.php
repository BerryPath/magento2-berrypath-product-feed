<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Model\Feed\Formatter;

class CatalogRowBuilder
{
    /**
     * @var array<int, string>
     */
    private const OPTIONAL_FIELDS = [
        'sku',
        'gtin',
        'ean',
        'upc',
        'isbn',
        'mpn',
        'color',
        'size',
        'material',
        'gender',
        'age_group',
        'pattern',
        'condition',
        'review_count',
        'rating_average',
        'rating_summary',
        'tax_class_id',
        'visibility',
    ];

    /**
     * @var array<int, string>
     */
    private const GOOGLE_OPTIONAL_FIELDS = [
        'gtin',
        'mpn',
        'color',
        'size',
        'material',
        'gender',
        'age_group',
        'pattern',
    ];

    public function __construct(
        private readonly FeedValue $value
    ) {
    }

    /**
     * @param array<string, mixed> $feed
     * @return array<string, string>
     */
    public function channel(array $feed): array
    {
        $config = $this->config($feed);

        return [
            'title' => $this->value->channelTitle($config),
            'link' => $this->value->asString($config['store_url'] ?? ''),
            'description' => 'Magento product feed',
        ];
    }

    /**
     * @param array<string, mixed> $feed
     * @param array<string, mixed> $options
     * @return array<int, array<string, mixed>>
     */
    public function googleRows(array $feed, array $options): array
    {
        $currency = $this->currency($feed);
        $rows = [];

        foreach ($this->products($feed) as $product) {
            $rows[] = $this->googleRow($product, $currency, $options);
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $feed
     * @param array<string, mixed> $options
     * @return array<int, array<string, mixed>>
     */
    public function catalogRows(array $feed, array $options): array
    {
        $currency = $this->currency($feed);
        $rows = [];

        foreach ($this->products($feed) as $product) {
            $rows[] = $this->catalogRow($product, $currency, $options);
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $feed
     * @param array<string, mixed> $options
     * @return array<int, array<string, mixed>>
     */
    public function tikTokRows(array $feed, array $options): array
    {
        $currency = $this->currency($feed);
        $rows = [];

        foreach ($this->products($feed) as $product) {
            $rows[] = $this->tikTokRow($product, $currency, $options);
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $feed
     * @param array<string, mixed> $options
     * @return array<int, array<string, mixed>>
     */
    public function openAiRows(array $feed, array $options): array
    {
        $config = $this->config($feed);
        $currency = $this->currency($feed);
        $rows = [];

        foreach ($this->products($feed) as $product) {
            $rows[] = $this->openAiRow($product, $config, $currency, $options);
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function googleRow(array $product, string $currency, array $options): array
    {
        $title = $this->title($product);
        $description = $this->description($product, $title);
        $price = $this->value->asString($product['price'] ?? '');
        $finalPrice = $this->value->asString($product['final_price'] ?? $price);
        $row = [
            'id' => $this->productId($product),
            'title' => $title,
            'description' => $description,
            'link' => $this->value->asString($product['url'] ?? ''),
            'image_link' => $this->value->asString($product['image'] ?? ''),
            'availability' => !empty($product['is_salable']) ? 'in_stock' : 'out_of_stock',
            'condition' => $this->value->asString($options['condition'] ?? 'new') ?: 'new',
            'price' => $this->value->priceWithCurrency($price, $currency),
        ];

        if ($this->isSalePrice($price, $finalPrice)) {
            $row['sale_price'] = $this->value->priceWithCurrency($finalPrice, $currency);
        }

        $productType = $this->value->productType($product);
        if ($productType !== '') {
            $row['product_type'] = $productType;
        }

        $this->addGoogleOptionalProductFields($row, $product);

        return $this->value->filterEmptyRow($row);
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function catalogRow(array $product, string $currency, array $options): array
    {
        $row = $this->googleRow($product, $currency, $options);
        $row['availability'] = !empty($product['is_salable']) ? 'in stock' : 'out of stock';
        $row['brand'] = $this->brand($product, '');

        $this->addOptionalProductFields($row, $product);
        $this->addShippingFields($row, $options);

        return $this->value->filterEmptyRow($row);
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function tikTokRow(array $product, string $currency, array $options): array
    {
        $row = $this->catalogRow($product, $currency, $options);
        $row = array_merge(
            ['sku_id' => $this->productId($product)],
            $row
        );
        unset($row['id']);

        if (!isset($row['google_product_category']) && isset($row['product_type'])) {
            $row['google_product_category'] = $row['product_type'];
        }

        return $this->value->filterEmptyRow($row);
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $config
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function openAiRow(array $product, array $config, string $currency, array $options): array
    {
        $title = $this->title($product);
        $description = $this->description($product, $title);
        $price = $this->value->asString($product['price'] ?? '');
        $finalPrice = $this->value->asString($product['final_price'] ?? $price);
        $row = [
            'is_eligible_search' => 'true',
            'is_eligible_checkout' => 'false',
            'item_id' => $this->productId($product),
            'title' => $title,
            'description' => $description,
            'url' => $this->value->asString($product['url'] ?? ''),
            'brand' => $this->brand($product, $this->sellerName($config)),
            'condition' => $this->value->asString($options['condition'] ?? 'new') ?: 'new',
            'product_category' => $this->value->productType($product),
            'image_url' => $this->value->asString($product['image'] ?? ''),
            'price' => $this->value->priceWithCurrency($price, $currency),
            'availability' => !empty($product['is_salable']) ? 'in_stock' : 'out_of_stock',
            'seller_name' => $this->sellerName($config),
            'seller_url' => $this->value->asString($config['store_url'] ?? ''),
            'target_countries' => $this->targetCountry($config),
        ];

        if ($this->isSalePrice($price, $finalPrice)) {
            $row['sale_price'] = $this->value->priceWithCurrency($finalPrice, $currency);
        }

        $this->addOptionalProductFields($row, $product);

        return $this->value->filterEmptyRow($row);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $product
     */
    private function addOptionalProductFields(array &$row, array $product): void
    {
        foreach (self::OPTIONAL_FIELDS as $field) {
            if (array_key_exists($field, $row)) {
                continue;
            }

            $value = $this->value->fieldValue($product[$field] ?? '');
            if ($value !== '') {
                $row[$field] = $value;
            }
        }

        $gtin = $row['gtin'] ?? $this->firstFieldValue($product, ['ean', 'upc', 'isbn', 'barcode']);
        if ($gtin !== '') {
            $row['gtin'] = $gtin;
        }
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $product
     */
    private function addGoogleOptionalProductFields(array &$row, array $product): void
    {
        $brand = $this->brand($product, '');
        if ($brand !== '') {
            $row['brand'] = $brand;
        }

        foreach (self::GOOGLE_OPTIONAL_FIELDS as $field) {
            if (array_key_exists($field, $row)) {
                continue;
            }

            $value = $this->value->fieldValue($product[$field] ?? '');
            if ($value !== '') {
                $row[$field] = $value;
            }
        }

        $gtin = $row['gtin'] ?? $this->firstFieldValue($product, ['ean', 'upc', 'isbn', 'barcode']);
        if ($gtin !== '') {
            $row['gtin'] = $gtin;
        }
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $options
     */
    private function addShippingFields(array &$row, array $options): void
    {
        if (!is_array($options['shipping'] ?? null)) {
            return;
        }

        $shipping = $options['shipping'];
        $row['shipping_country'] = $this->value->asString($shipping['country'] ?? '');
        $row['shipping_service'] = $this->value->asString($shipping['service'] ?? '');
        $row['shipping_price'] = $this->value->asString($shipping['price'] ?? '');
    }

    /**
     * @param array<string, mixed> $feed
     * @return array<string, mixed>
     */
    private function config(array $feed): array
    {
        return is_array($feed['config'] ?? null) ? $feed['config'] : [];
    }

    /**
     * @param array<string, mixed> $feed
     */
    private function currency(array $feed): string
    {
        $config = $this->config($feed);

        return $this->value->asString($config['currency'] ?? '');
    }

    /**
     * @param array<string, mixed> $feed
     * @return array<int, array<string, mixed>>
     */
    private function products(array $feed): array
    {
        return array_values(array_filter(
            is_array($feed['products'] ?? null) ? $feed['products'] : [],
            static fn (mixed $product): bool => is_array($product)
        ));
    }

    /**
     * @param array<string, mixed> $product
     */
    private function productId(array $product): string
    {
        return $this->value->asString($product['id'] ?? $product['entity_id'] ?? '');
    }

    /**
     * @param array<string, mixed> $product
     */
    private function title(array $product): string
    {
        return $this->value->asString($product['name'] ?? '');
    }

    /**
     * @param array<string, mixed> $product
     */
    private function description(array $product, string $fallback): string
    {
        $description = $this->value->asString($product['description'] ?? '');
        if ($description !== '') {
            return $description;
        }

        $shortDescription = $this->value->asString($product['short_description'] ?? '');

        return $shortDescription !== '' ? $shortDescription : $fallback;
    }

    private function isSalePrice(string $price, string $finalPrice): bool
    {
        $priceAmount = $this->value->amountValue($price);
        $finalPriceAmount = $this->value->amountValue($finalPrice);

        return $priceAmount > 0.0 && $finalPriceAmount > 0.0 && $finalPriceAmount < $priceAmount;
    }

    /**
     * @param array<string, mixed> $product
     */
    private function brand(array $product, string $fallback): string
    {
        $brand = $this->firstFieldValue($product, ['brand', 'manufacturer']);

        return $brand !== '' ? $brand : $fallback;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function sellerName(array $config): string
    {
        $feedName = $this->value->asString($config['feed_name'] ?? '');
        if ($feedName !== '') {
            return $feedName;
        }

        $storeCode = $this->value->asString($config['store_code'] ?? '');

        return $storeCode !== '' ? $storeCode : 'Store';
    }

    /**
     * @param array<string, mixed> $config
     */
    private function targetCountry(array $config): string
    {
        $marketCode = strtoupper($this->value->asString($config['market_code'] ?? ''));
        if (preg_match('/^[A-Z]{2}$/', $marketCode) === 1) {
            return $marketCode;
        }

        $locale = $this->value->asString($config['locale'] ?? '');
        if (preg_match('/^[a-z]{2}_([A-Z]{2})$/', $locale, $matches) === 1) {
            return $matches[1];
        }

        return 'US';
    }

    /**
     * @param array<string, mixed> $product
     * @param array<int, string> $fields
     */
    private function firstFieldValue(array $product, array $fields): string
    {
        foreach ($fields as $field) {
            $value = $this->value->fieldValue($product[$field] ?? '');
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
