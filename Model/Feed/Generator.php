<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Model\Feed;

use BerryPath\ProductFeed\Model\Profile;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Media\Config as MediaConfig;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\UrlInterface;
use Magento\Review\Model\ResourceModel\Review\Summary as ReviewSummaryResource;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;

class Generator
{
    /**
     * @var array<int, string>
     */
    private const BASE_ATTRIBUTES = [
        'name',
        'sku',
        'url_key',
        'url_path',
        'image',
        'small_image',
        'thumbnail',
        'price',
        'special_price',
        'special_from_date',
        'special_to_date',
        'status',
        'tax_class_id',
        'visibility',
        'description',
        'short_description',
    ];

    public function __construct(
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly Config $feedConfig,
        private readonly MediaConfig $mediaConfig,
        private readonly ReviewSummaryResource $reviewSummaryResource,
        private readonly Emulation $appEmulation,
        private readonly StoreManagerInterface $storeManager,
        private readonly DateTime $dateTime
    ) {
    }

    /**
     * @return array{config: array<string, mixed>, products: array<int, array<string, mixed>>}
     */
    public function generate(
        int $storeId,
        ?string $productId = null,
        ?int $productLimit = null,
        ?Profile $profile = null
    ): array {
        $timeStart = microtime(true);
        $this->appEmulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);

        try {
            $store = $this->storeManager->getStore($storeId);
            $currency = (string)$store->getCurrentCurrencyCode();
            $identifierSource = $this->feedConfig->getProductIdentifierSource($storeId, $profile);
            $extraAttributes = $this->feedConfig->getExtraAttributeCodes($storeId, $profile);
            $salableProductsOnly = $this->feedConfig->salableProductsOnly($storeId, $profile);
            $collection = $this->createProductCollection(
                $storeId,
                $identifierSource,
                $extraAttributes,
                $productId,
                $productLimit,
                $profile
            );
            $this->reviewSummaryResource->appendSummaryFieldsToCollection($collection, $storeId, 'product');
            $total = (int)$collection->getSize();
            $products = [];

            $collection->load();
            $categoryNames = $this->getCategoryNames($collection, $storeId);
            $childIdsWithInactiveParents = $this->feedConfig->skipChildProductsOfInactiveParents($storeId, $profile)
                ? $this->getChildIdsWithInactiveParents($collection, $storeId)
                : [];

            foreach ($collection as $product) {
                if (!$product instanceof Product) {
                    continue;
                }

                if (isset($childIdsWithInactiveParents[(int)$product->getId()])) {
                    continue;
                }

                $row = $this->getProductRow(
                    $product,
                    $identifierSource,
                    $extraAttributes,
                    $categoryNames,
                    $currency,
                    $salableProductsOnly
                );
                if ($row !== []) {
                    $products[] = $row;
                }
            }

            return [
                'config' => $this->getSummary($storeId, $timeStart, $total, count($products), $currency, $profile),
                'products' => $products,
            ];
        } finally {
            $this->appEmulation->stopEnvironmentEmulation();
        }
    }

    /**
     * @param array<int, string> $extraAttributes
     */
    private function createProductCollection(
        int $storeId,
        string $identifierSource,
        array $extraAttributes,
        ?string $productId,
        ?int $productLimit,
        ?Profile $profile
    ): ProductCollection {
        $attributeCodes = array_values(array_unique(array_filter(array_merge(
            self::BASE_ATTRIBUTES,
            $extraAttributes,
            $identifierSource !== 'entity_id' ? [$identifierSource] : []
        ))));

        $collection = $this->productCollectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addStoreFilter($storeId);
        $collection->addAttributeToSelect($attributeCodes);
        $collection->setOrder('entity_id', 'ASC');
        $collection->addUrlRewrite();
        $this->joinPriceIndex($collection, $storeId);

        if ($this->feedConfig->activeProductsOnly($storeId, $profile)) {
            $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
        }

        if ($this->feedConfig->visibleProductsOnly($storeId, $profile)) {
            $collection->addAttributeToFilter('visibility', ['neq' => Visibility::VISIBILITY_NOT_VISIBLE]);
        }

        if ($productId !== null && $productId !== '') {
            if ($identifierSource === 'entity_id') {
                $collection->addAttributeToFilter('entity_id', ['eq' => $productId]);
            } else {
                $collection->addAttributeToFilter($identifierSource, ['eq' => $productId]);
            }
        } elseif ($productLimit !== null && $productLimit > 0) {
            $collection->setPageSize($productLimit);
            $collection->setCurPage(1);
        }

        return $collection;
    }

    private function joinPriceIndex(ProductCollection $collection, int $storeId): void
    {
        $websiteId = (int)$this->storeManager->getStore($storeId)->getWebsiteId();
        $collection->getSelect()->joinLeft(
            ['price_index' => $collection->getTable('catalog_product_index_price')],
            sprintf(
                'price_index.entity_id = e.entity_id AND price_index.website_id = %d AND price_index.customer_group_id = 0',
                $websiteId
            ),
            [
                'indexed_price' => 'price',
                'indexed_final_price' => 'final_price',
                'indexed_min_price' => 'min_price',
                'indexed_max_price' => 'max_price',
            ]
        );
    }

    /**
     * @param array<int, string> $extraAttributes
     * @param array<int, string> $categoryNames
     * @return array<string, mixed>
     */
    private function getProductRow(
        Product $product,
        string $identifierSource,
        array $extraAttributes,
        array $categoryNames,
        string $currency,
        bool $salableProductsOnly
    ): array {
        $feedId = $this->getProductIdentifier($product, $identifierSource);
        if ($feedId === '') {
            return [];
        }

        $categoryIds = array_map('intval', $product->getCategoryIds() ?: []);
        $prices = $this->getProductPrices($product);
        $isSalable = $this->isSalable($product);
        if ($salableProductsOnly && !$isSalable) {
            return [];
        }

        $row = [
            'id' => $feedId,
            'entity_id' => (int)$product->getId(),
            'sku' => (string)$product->getSku(),
            'type' => (string)$product->getTypeId(),
            'name' => $this->cleanText((string)$product->getName()),
            'url_key' => $this->cleanText((string)$product->getUrlKey()),
            'url' => (string)$product->getProductUrl(),
            'image' => $this->getImageUrl($product),
            'price' => $this->formatAmount($prices['price']),
            'final_price' => $this->formatAmount($prices['final_price']),
            'currency' => $currency,
            'is_salable' => $isSalable,
            'visibility' => $this->getAttributeDisplayValue($product, 'visibility'),
            'tax_class_id' => $this->getAttributeDisplayValue($product, 'tax_class_id'),
            'review_count' => (int)$product->getData('reviews_count'),
            'rating_summary' => (int)$product->getData('rating_summary'),
            'rating_average' => $this->formatAmount(((float)$product->getData('rating_summary')) / 20),
            'category_ids' => $categoryIds,
            'categories' => $this->getProductCategories($categoryIds, $categoryNames),
        ];

        if ($this->hasRelevantPriceRange($prices)) {
            $row['min_price'] = $this->formatAmount($prices['min_price']);
            $row['max_price'] = $this->formatAmount($prices['max_price']);
        }

        $shortDescription = $this->cleanText((string)$product->getShortDescription());
        if ($shortDescription !== '') {
            $row['short_description'] = $shortDescription;
        }

        $description = $this->cleanText((string)$product->getDescription());
        if ($description !== '') {
            $row['description'] = $description;
        }

        $attributes = $this->getExtraAttributes($product, $extraAttributes);
        foreach ($attributes as $attributeCode => $attributeValue) {
            if (!array_key_exists($attributeCode, $row)) {
                $row[$attributeCode] = $attributeValue;
            }
        }

        return $row;
    }

    /**
     * @return array<int, bool>
     */
    private function getChildIdsWithInactiveParents(ProductCollection $products, int $storeId): array
    {
        $childIds = [];
        foreach ($products as $product) {
            if ($product instanceof Product) {
                $childIds[] = (int)$product->getId();
            }
        }

        $childIds = array_values(array_unique(array_filter($childIds)));
        if ($childIds === []) {
            return [];
        }

        $connection = $products->getConnection();
        $relations = $connection->fetchAll(
            $connection->select()
                ->from($products->getTable('catalog_product_relation'), ['parent_id', 'child_id'])
                ->where('child_id IN (?)', $childIds)
        );
        if ($relations === []) {
            return [];
        }

        $parentIds = [];
        $parentsByChild = [];
        foreach ($relations as $relation) {
            $childId = (int)($relation['child_id'] ?? 0);
            $parentId = (int)($relation['parent_id'] ?? 0);
            if ($childId <= 0 || $parentId <= 0) {
                continue;
            }

            $parentsByChild[$childId][] = $parentId;
            $parentIds[] = $parentId;
        }

        $parentIds = array_values(array_unique($parentIds));
        if ($parentIds === []) {
            return [];
        }

        $activeParentIds = [];
        $parentCollection = $this->productCollectionFactory->create();
        $parentCollection->setStoreId($storeId);
        $parentCollection->addStoreFilter($storeId);
        $parentCollection->addAttributeToSelect('status');
        $parentCollection->addAttributeToFilter('entity_id', ['in' => $parentIds]);
        $parentCollection->load();

        foreach ($parentCollection as $parent) {
            if ($parent instanceof Product && (int)$parent->getData('status') === Status::STATUS_ENABLED) {
                $activeParentIds[(int)$parent->getId()] = true;
            }
        }

        $childIdsToSkip = [];
        foreach ($parentsByChild as $childId => $parentIdsForChild) {
            $hasActiveParent = false;
            foreach ($parentIdsForChild as $parentId) {
                if (isset($activeParentIds[(int)$parentId])) {
                    $hasActiveParent = true;
                    break;
                }
            }

            if (!$hasActiveParent) {
                $childIdsToSkip[(int)$childId] = true;
            }
        }

        return $childIdsToSkip;
    }

    /**
     * @return array{price: float, final_price: float, min_price: float, max_price: float}
     */
    private function getProductPrices(Product $product): array
    {
        $indexedPrice = $this->positiveFloat($product->getData('indexed_price'));
        $indexedFinalPrice = $this->positiveFloat($product->getData('indexed_final_price'));
        $indexedMinPrice = $this->positiveFloat($product->getData('indexed_min_price'));
        $indexedMaxPrice = $this->positiveFloat($product->getData('indexed_max_price'));
        $productPrice = $this->positiveFloat($product->getPrice());
        $productFinalPrice = $this->positiveFloat($product->getFinalPrice());

        $price = $indexedPrice ?? $productPrice ?? $indexedMinPrice ?? $indexedFinalPrice ?? 0.0;
        $finalPrice = $indexedFinalPrice ?? $productFinalPrice ?? $indexedMinPrice ?? $price;
        $minPrice = $indexedMinPrice ?? min($price, $finalPrice);
        $maxPrice = $indexedMaxPrice ?? max($price, $finalPrice);

        if ($price <= 0.0 && $minPrice > 0.0) {
            $price = $minPrice;
        }

        if ($finalPrice <= 0.0 && $minPrice > 0.0) {
            $finalPrice = $minPrice;
        }

        return [
            'price' => $price,
            'final_price' => $finalPrice,
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
        ];
    }

    /**
     * @param array{price: float, final_price: float, min_price: float, max_price: float} $prices
     */
    private function hasRelevantPriceRange(array $prices): bool
    {
        return $prices['min_price'] > 0.0
            && $prices['max_price'] > 0.0
            && abs($prices['max_price'] - $prices['min_price']) >= 0.01;
    }

    private function positiveFloat(mixed $value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        $value = (float)$value;

        return $value > 0.0 ? $value : null;
    }

    private function getProductIdentifier(Product $product, string $source): string
    {
        if ($source === 'entity_id') {
            return $this->normalizeIdentifier((string)$product->getId());
        }

        $value = $product->getData($source);
        if (!is_scalar($value) && !$value instanceof \Stringable) {
            return '';
        }

        return $this->normalizeIdentifier((string)$value);
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function getProductCategories(array $categoryIds, array $categoryNames): array
    {
        $categories = [];
        foreach ($categoryIds as $categoryId) {
            $name = $categoryNames[$categoryId] ?? '';
            $categories[] = ['id' => $categoryId, 'name' => $name];
        }

        return $categories;
    }

    /**
     * @return array<int, string>
     */
    private function getCategoryNames(ProductCollection $products, int $storeId): array
    {
        $categoryIds = [];
        foreach ($products as $product) {
            if (!$product instanceof Product) {
                continue;
            }

            $categoryIds = array_merge($categoryIds, array_map('intval', $product->getCategoryIds() ?: []));
        }

        $categoryIds = array_values(array_unique(array_filter($categoryIds)));
        if ($categoryIds === []) {
            return [];
        }

        $categories = [];
        $collection = $this->categoryCollectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addAttributeToSelect('name');
        $collection->addAttributeToFilter('entity_id', ['in' => $categoryIds]);

        foreach ($collection as $category) {
            if (!$category instanceof Category) {
                continue;
            }

            $categories[(int)$category->getId()] = $this->cleanText((string)$category->getName());
        }

        return $categories;
    }

    private function getImageUrl(Product $product): string
    {
        foreach (['image', 'small_image', 'thumbnail'] as $attributeCode) {
            $image = trim((string)$product->getData($attributeCode));
            if ($image === '' || $image === 'no_selection') {
                continue;
            }

            return $this->mediaConfig->getMediaUrl($image);
        }

        return '';
    }

    /**
     * @param array<int, string> $attributeCodes
     * @return array<string, mixed>
     */
    private function getExtraAttributes(Product $product, array $attributeCodes): array
    {
        $attributes = [];
        foreach ($attributeCodes as $attributeCode) {
            if (in_array($attributeCode, ['entity_id', 'sku'], true)) {
                continue;
            }

            $value = $product->getAttributeText($attributeCode);
            if ($value === false || $value === null || $value === '') {
                $value = $product->getData($attributeCode);
            }

            $value = $this->normalizeAttributeValue($value);
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $attributes[$attributeCode] = $value;
        }

        return $attributes;
    }

    private function getAttributeDisplayValue(Product $product, string $attributeCode): string
    {
        $value = $product->getAttributeText($attributeCode);
        if ($value === false || $value === null || $value === '') {
            $value = $product->getData($attributeCode);
        }

        $value = $this->normalizeAttributeValue($value);
        if (is_array($value)) {
            return implode(', ', $value);
        }

        return is_scalar($value) || $value instanceof \Stringable ? (string)$value : '';
    }

    private function normalizeAttributeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            $values = [];
            foreach ($value as $item) {
                if (!is_scalar($item) && !$item instanceof \Stringable) {
                    continue;
                }

                $cleanValue = $this->cleanText((string)$item);
                if ($cleanValue !== '') {
                    $values[] = $cleanValue;
                }
            }

            return $values;
        }

        if (!is_scalar($value) && !$value instanceof \Stringable) {
            return null;
        }

        return $this->cleanText((string)$value);
    }

    private function isSalable(Product $product): bool
    {
        try {
            return (bool)$product->isSalable();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getSummary(
        int $storeId,
        float $timeStart,
        int $total,
        int $output,
        string $currency,
        ?Profile $profile
    ): array {
        try {
            $store = $this->storeManager->getStore($storeId);
            $storeCode = (string)$store->getCode();
            $storeUrl = rtrim($store->getBaseUrl(UrlInterface::URL_TYPE_WEB), '/') . '/';
        } catch (NoSuchEntityException) {
            $storeCode = '';
            $storeUrl = '';
        }

        return [
            'system' => 'Magento 2',
            'extension' => 'BerryPath_ProductFeed',
            'feed_id' => $profile?->getProfileId(),
            'feed_name' => $profile?->getName(),
            'feed_type' => $this->feedConfig->getFeedType($storeId, $profile),
            'output_format' => $this->feedConfig->getOutputFormat($storeId, $profile),
            'store_id' => $storeId,
            'store_code' => $storeCode,
            'store_url' => $storeUrl,
            'market_code' => $this->feedConfig->getMarketCode($storeId, $profile),
            'locale' => $this->feedConfig->getLocaleCode($storeId, $profile),
            'currency' => $currency,
            'products_total' => $total,
            'products_output' => $output,
            'time' => number_format(microtime(true) - $timeStart, 2, '.', '') . ' sec',
            'date' => $this->dateTime->gmtDate(),
        ];
    }

    private function normalizeIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        if ($identifier === '' || strlen($identifier) > 190 || str_contains($identifier, "\0")) {
            return '';
        }

        return $identifier;
    }

    private function cleanText(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = (string)preg_replace('/\s+/u', ' ', $value);

        return trim($value);
    }

    private function formatAmount(float $amount): string
    {
        return number_format(max(0.0, $amount), 2, '.', '');
    }
}
