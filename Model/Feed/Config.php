<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Model\Feed;

use BerryPath\ProductFeed\Model\Config\Source\FeedType;
use BerryPath\ProductFeed\Model\Config\Source\LocaleCode;
use BerryPath\ProductFeed\Model\Config\Source\ProductCondition;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Config
{
    public const XML_PATH_GENERAL_ENABLED = 'berrypath_productfeed/general/enabled';
    public const XML_PATH_MARKET_CODE = 'berrypath_productfeed/general/market_code';
    public const XML_PATH_LOCALE_CODE = 'berrypath_productfeed/general/locale_code';
    public const XML_PATH_FEED_TYPE = 'berrypath_productfeed/feed/type';
    public const XML_PATH_PRODUCT_IDENTIFIER = 'berrypath_productfeed/feed/product_identifier';
    public const XML_PATH_FEED_INCLUDE_NOT_VISIBLE = 'berrypath_productfeed/feed/include_not_visible';
    public const XML_PATH_FEED_EXTRA_ATTRIBUTES = 'berrypath_productfeed/feed/extra_attributes';
    public const XML_PATH_GOOGLE_CONDITION = 'berrypath_productfeed/google/condition';
    public const XML_PATH_GOOGLE_INCLUDE_SHIPPING = 'berrypath_productfeed/google/include_shipping';
    public const XML_PATH_GOOGLE_SHIPPING_COUNTRY = 'berrypath_productfeed/google/shipping_country';
    public const XML_PATH_GOOGLE_SHIPPING_SERVICE = 'berrypath_productfeed/google/shipping_service';
    public const XML_PATH_GOOGLE_SHIPPING_PRICE = 'berrypath_productfeed/google/shipping_price';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function isFeedEnabled(int $storeId): bool
    {
        try {
            $store = $this->storeManager->getStore($storeId);
        } catch (NoSuchEntityException) {
            return false;
        }

        if (!$store->isActive()) {
            return false;
        }

        return $this->scopeConfig->isSetFlag(self::XML_PATH_GENERAL_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function includeNotVisibleProducts(int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_FEED_INCLUDE_NOT_VISIBLE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getFeedType(int $storeId): string
    {
        $feedType = (string)$this->scopeConfig->getValue(self::XML_PATH_FEED_TYPE, ScopeInterface::SCOPE_STORE, $storeId);

        return $feedType === FeedType::GOOGLE_SHOPPING ? FeedType::GOOGLE_SHOPPING : FeedType::GENERIC;
    }

    public function isGoogleShoppingFeed(int $storeId): bool
    {
        return $this->getFeedType($storeId) === FeedType::GOOGLE_SHOPPING;
    }

    /**
     * @return array{condition: string, shipping: array{country: string, service: string, price: string}|null}
     */
    public function getGoogleOptions(int $storeId, string $currency): array
    {
        return [
            'condition' => $this->getGoogleCondition($storeId),
            'shipping' => $this->getGoogleShipping($storeId, $currency),
        ];
    }

    public function getProductIdentifierSource(int $storeId): string
    {
        $source = trim((string)$this->scopeConfig->getValue(
            self::XML_PATH_PRODUCT_IDENTIFIER,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));

        if ($source === '') {
            return 'entity_id';
        }

        return preg_match('/^[a-z][a-z0-9_]{0,254}$/', $source) === 1 ? $source : 'entity_id';
    }

    public function getMarketCode(int $storeId): string
    {
        $marketCode = (string)$this->scopeConfig->getValue(self::XML_PATH_MARKET_CODE, ScopeInterface::SCOPE_STORE, $storeId);
        $marketCode = strtolower(trim(str_replace('_', '-', $marketCode)));
        $marketCode = (string)preg_replace('/[^a-z0-9-]+/', '-', $marketCode);
        $marketCode = trim((string)preg_replace('/-+/', '-', $marketCode), '-');

        return preg_match('/^[a-z0-9](?:[a-z0-9-]{0,38}[a-z0-9])?$/', $marketCode) === 1 ? $marketCode : '';
    }

    public function getLocaleCode(int $storeId): string
    {
        $locale = (string)$this->scopeConfig->getValue(self::XML_PATH_LOCALE_CODE, ScopeInterface::SCOPE_STORE, $storeId);
        if ($locale === '') {
            $locale = (string)$this->scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE, $storeId);
        }

        return LocaleCode::normalizeLocaleCode($locale);
    }

    /**
     * @return array<int, string>
     */
    public function getExtraAttributeCodes(int $storeId): array
    {
        $value = (string)$this->scopeConfig->getValue(
            self::XML_PATH_FEED_EXTRA_ATTRIBUTES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $codes = preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $codes = array_filter(
            $codes,
            static fn (string $code): bool => preg_match('/^[a-z][a-z0-9_]{0,254}$/', $code) === 1
                && !DefaultAttributeCodes::contains($code)
        );

        return array_values(array_unique($codes));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getStoreFeedData(): array
    {
        $feedData = [];

        foreach ($this->storeManager->getStores() as $store) {
            $storeId = (int)$store->getId();

            $feedData[] = [
                'store_id' => $storeId,
                'code' => (string)$store->getCode(),
                'name' => (string)$store->getName(),
                'is_active' => (bool)$store->isActive(),
                'market_code' => $this->getMarketCode($storeId),
                'feed_type' => $this->getFeedType($storeId),
                'preview_url' => $this->getPreviewUrl($storeId),
                'feed_url' => $this->getFeedUrl($storeId),
            ];
        }

        return $feedData;
    }

    public function getFeedUrl(int $storeId): string
    {
        return $this->getStoreBaseUrl($storeId) . sprintf(
            'berrypath/feed/id/%d',
            $storeId
        );
    }

    public function getPreviewUrl(int $storeId): string
    {
        return $this->getStoreBaseUrl($storeId) . sprintf(
            'berrypath/feed/preview/id/%d/no-cache/%d',
            $storeId,
            time()
        );
    }

    private function getStoreBaseUrl(int $storeId): string
    {
        return rtrim($this->storeManager->getStore($storeId)->getBaseUrl(UrlInterface::URL_TYPE_WEB), '/') . '/';
    }

    private function getGoogleCondition(int $storeId): string
    {
        $condition = (string)$this->scopeConfig->getValue(
            self::XML_PATH_GOOGLE_CONDITION,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return in_array(
            $condition,
            [ProductCondition::NEW, ProductCondition::REFURBISHED, ProductCondition::USED],
            true
        ) ? $condition : ProductCondition::NEW;
    }

    /**
     * @return array{country: string, service: string, price: string}|null
     */
    private function getGoogleShipping(int $storeId, string $currency): ?array
    {
        if (!$this->scopeConfig->isSetFlag(self::XML_PATH_GOOGLE_INCLUDE_SHIPPING, ScopeInterface::SCOPE_STORE, $storeId)) {
            return null;
        }

        $country = strtoupper(trim((string)$this->scopeConfig->getValue(
            self::XML_PATH_GOOGLE_SHIPPING_COUNTRY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        )));
        if (preg_match('/^[A-Z]{2}$/', $country) !== 1) {
            return null;
        }

        $rawPrice = trim((string)$this->scopeConfig->getValue(
            self::XML_PATH_GOOGLE_SHIPPING_PRICE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
        $rawPrice = str_replace(',', '.', $rawPrice);
        if (!is_numeric($rawPrice) || (float)$rawPrice < 0.0) {
            return null;
        }

        $service = trim((string)$this->scopeConfig->getValue(
            self::XML_PATH_GOOGLE_SHIPPING_SERVICE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));

        return [
            'country' => $country,
            'service' => $service,
            'price' => number_format((float)$rawPrice, 2, '.', '') . ' ' . $currency,
        ];
    }
}
