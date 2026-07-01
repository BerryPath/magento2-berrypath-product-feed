<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Model;

use BerryPath\ProductFeed\Model\Config\Source\OutputFormat;
use BerryPath\ProductFeed\Model\ResourceModel\Profile as ProfileResource;
use Magento\Framework\Model\AbstractModel;

class Profile extends AbstractModel
{
    public const CACHE_TAG = 'berrypath_productfeed_profile';

    protected $_cacheTag = self::CACHE_TAG;
    protected $_eventPrefix = 'berrypath_productfeed_profile';

    protected function _construct(): void
    {
        $this->_init(ProfileResource::class);
    }

    public function getProfileId(): int
    {
        return (int)$this->getId();
    }

    public function getName(): string
    {
        return (string)$this->getData('name');
    }

    public function getFeedType(): string
    {
        return (string)$this->getData('feed_type');
    }

    public function getOutputFormat(): string
    {
        $format = (string)$this->getData('output_format');

        return in_array($format, [OutputFormat::XML, OutputFormat::CSV, OutputFormat::JSON], true)
            ? $format
            : OutputFormat::XML;
    }

    public function getStoreId(): int
    {
        return (int)$this->getData('store_id');
    }

    public function getMarketCode(): string
    {
        return (string)$this->getData('market_code');
    }

    public function getLocaleCode(): string
    {
        return (string)$this->getData('locale_code');
    }

    public function useCdata(): bool
    {
        return (bool)$this->getData('use_cdata');
    }

    public function activeProductsOnly(): bool
    {
        return (bool)$this->getData('active_products_only');
    }

    public function visibleProductsOnly(): bool
    {
        return (bool)$this->getData('visible_products_only');
    }

    public function salableProductsOnly(): bool
    {
        return (bool)$this->getData('salable_products_only');
    }

    public function skipChildProductsOfInactiveParents(): bool
    {
        return (bool)$this->getData('skip_child_products_of_inactive_parents');
    }

    public function isActive(): bool
    {
        return (bool)$this->getData('is_active');
    }

    public function scheduleEnabled(): bool
    {
        return (bool)$this->getData('schedule_enabled');
    }

    public function getScheduleCronExpression(): string
    {
        return (string)$this->getData('schedule_cron_expression');
    }
}
