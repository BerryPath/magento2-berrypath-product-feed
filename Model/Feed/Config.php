<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Model\Feed;

use BerryPath\ProductFeed\Model\Config\Source\FeedType;
use BerryPath\ProductFeed\Model\Config\Source\LocaleCode;
use BerryPath\ProductFeed\Model\Config\Source\OutputFormat;
use BerryPath\ProductFeed\Model\Config\Source\ProductCondition;
use BerryPath\ProductFeed\Model\Profile;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Config
{
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

        return true;
    }

    public function includeNotVisibleProducts(int $storeId, ?Profile $profile = null): bool
    {
        return !$this->visibleProductsOnly($storeId, $profile);
    }

    public function activeProductsOnly(int $storeId, ?Profile $profile = null): bool
    {
        if ($profile !== null && $profile->getId()) {
            return (bool)$profile->getData('active_products_only');
        }

        return true;
    }

    public function visibleProductsOnly(int $storeId, ?Profile $profile = null): bool
    {
        if ($profile !== null && $profile->getId()) {
            return (bool)$profile->getData('visible_products_only');
        }

        return true;
    }

    public function salableProductsOnly(int $storeId, ?Profile $profile = null): bool
    {
        if ($profile !== null && $profile->getId()) {
            return (bool)$profile->getData('salable_products_only');
        }

        return false;
    }

    public function skipChildProductsOfInactiveParents(int $storeId, ?Profile $profile = null): bool
    {
        if ($profile !== null && $profile->getId()) {
            return (bool)$profile->getData('skip_child_products_of_inactive_parents');
        }

        return true;
    }

    public function getFeedType(int $storeId, ?Profile $profile = null): string
    {
        $feedType = $profile !== null && $profile->getId()
            ? (string)$profile->getData('feed_type')
            : FeedType::PRODUCT;

        return $this->normalizeFeedType($feedType);
    }

    public function isGoogleShoppingFeed(int $storeId, ?Profile $profile = null): bool
    {
        return $this->getFeedType($storeId, $profile) === FeedType::GOOGLE_SHOPPING;
    }

    public function getOutputFormat(int $storeId, ?Profile $profile = null): string
    {
        $format = $profile !== null && $profile->getId()
            ? (string)$profile->getData('output_format')
            : OutputFormat::XML;

        return $this->normalizeOutputFormat($format);
    }

    public function useCdata(int $storeId, ?Profile $profile = null): bool
    {
        if ($profile !== null && $profile->getId()) {
            return (bool)$profile->getData('use_cdata');
        }

        return true;
    }

    /**
     * @return array{use_cdata: bool, condition: string, shipping: array{country: string, service: string, price: string}|null}
     */
    public function getGoogleOptions(int $storeId, string $currency, ?Profile $profile = null): array
    {
        return [
            'use_cdata' => $this->useCdata($storeId, $profile),
            'condition' => $this->getGoogleCondition($storeId, $profile),
            'shipping' => $this->getGoogleShipping($storeId, $currency, $profile),
        ];
    }

    public function getProductIdentifierSource(int $storeId, ?Profile $profile = null): string
    {
        $source = trim($profile !== null && $profile->getId()
            ? (string)$profile->getData('product_identifier')
            : 'entity_id');

        if ($source === '') {
            return 'entity_id';
        }

        return preg_match('/^[a-z][a-z0-9_]{0,254}$/', $source) === 1 ? $source : 'entity_id';
    }

    public function getMarketCode(int $storeId, ?Profile $profile = null): string
    {
        $marketCode = $profile !== null && $profile->getId()
            ? (string)$profile->getData('market_code')
            : '';
        $marketCode = strtolower(trim(str_replace('_', '-', $marketCode)));
        $marketCode = (string)preg_replace('/[^a-z0-9-]+/', '-', $marketCode);
        $marketCode = trim((string)preg_replace('/-+/', '-', $marketCode), '-');

        return preg_match('/^[a-z0-9](?:[a-z0-9-]{0,38}[a-z0-9])?$/', $marketCode) === 1 ? $marketCode : '';
    }

    public function getLocaleCode(int $storeId, ?Profile $profile = null): string
    {
        $locale = $profile !== null && $profile->getId()
            ? (string)$profile->getData('locale_code')
            : '';
        if ($locale === '') {
            $locale = (string)$this->scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE, $storeId);
        }

        return LocaleCode::normalizeLocaleCode($locale);
    }

    /**
     * @return array<int, string>
     */
    public function getExtraAttributeCodes(int $storeId, ?Profile $profile = null): array
    {
        $value = $profile !== null && $profile->getId()
            ? (string)$profile->getData('extra_attributes')
            : '';

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
                'preview_url' => $this->getPreviewUrl($storeId),
                'feed_url' => $this->getFeedUrl($storeId),
            ];
        }

        return $feedData;
    }

    public function getFeedUrl(int $storeId): string
    {
        return $this->getStoreBaseUrl($storeId) . 'media/berrypath/product-feed/';
    }

    public function getPreviewUrl(int $storeId): string
    {
        return $this->getStoreBaseUrl($storeId) . sprintf(
            'berrypath/feed/preview/id/%d/no-cache/%d',
            $storeId,
            time()
        );
    }

    public function getFeedUrlForProfile(Profile $profile): string
    {
        return $this->getStoreBaseUrl($profile->getStoreId()) . sprintf(
            'media/berrypath/product-feed/feed_%d.%s',
            $profile->getProfileId(),
            $this->getOutputFormat($profile->getStoreId(), $profile)
        );
    }

    public function getPreviewUrlForProfile(Profile $profile): string
    {
        return $this->getStoreBaseUrl($profile->getStoreId()) . sprintf(
            'berrypath/feed/preview/id/%d/no-cache/%d',
            $profile->getProfileId(),
            time()
        );
    }

    private function getStoreBaseUrl(int $storeId): string
    {
        return rtrim($this->storeManager->getStore($storeId)->getBaseUrl(UrlInterface::URL_TYPE_WEB), '/') . '/';
    }

    private function getGoogleCondition(int $storeId, ?Profile $profile = null): string
    {
        $condition = $profile !== null && $profile->getId()
            ? (string)$profile->getData('google_condition')
            : ProductCondition::NEW;

        return in_array(
            $condition,
            [ProductCondition::NEW, ProductCondition::REFURBISHED, ProductCondition::USED],
            true
        ) ? $condition : ProductCondition::NEW;
    }

    /**
     * @return array{country: string, service: string, price: string}|null
     */
    private function getGoogleShipping(int $storeId, string $currency, ?Profile $profile = null): ?array
    {
        $includeShipping = $profile !== null && $profile->getId()
            ? (bool)$profile->getData('google_include_shipping')
            : false;
        if (!$includeShipping) {
            return null;
        }

        $country = strtoupper(trim($profile !== null && $profile->getId()
            ? (string)$profile->getData('google_shipping_country')
            : ''));
        if (preg_match('/^[A-Z]{2}$/', $country) !== 1) {
            return null;
        }

        $rawPrice = trim($profile !== null && $profile->getId()
            ? (string)$profile->getData('google_shipping_price')
            : '');
        $rawPrice = str_replace(',', '.', $rawPrice);
        if (!is_numeric($rawPrice) || (float)$rawPrice < 0.0) {
            return null;
        }

        $service = trim($profile !== null && $profile->getId()
            ? (string)$profile->getData('google_shipping_service')
            : '');

        return [
            'country' => $country,
            'service' => $service,
            'price' => number_format((float)$rawPrice, 2, '.', '') . ' ' . $currency,
        ];
    }

    private function normalizeFeedType(string $feedType): string
    {
        return in_array($feedType, FeedType::values(), true) ? $feedType : FeedType::PRODUCT;
    }

    private function normalizeOutputFormat(string $format): string
    {
        $format = trim($format);

        return in_array($format, [OutputFormat::XML, OutputFormat::CSV, OutputFormat::JSON], true)
            ? $format
            : OutputFormat::XML;
    }
}
