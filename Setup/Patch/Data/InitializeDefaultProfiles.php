<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Setup\Patch\Data;

use BerryPath\ProductFeed\Model\Config\Source\FeedType;
use BerryPath\ProductFeed\Model\Config\Source\OutputFormat;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class InitializeDefaultProfiles implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $profileTable = $this->moduleDataSetup->getTable('berrypath_productfeed_profile');

        $this->moduleDataSetup->startSetup();
        try {
            if ((int)$connection->fetchOne($connection->select()->from($profileTable, 'COUNT(*)')) > 0) {
                return $this;
            }

            $stores = $connection->fetchAll(
                $connection->select()
                    ->from($this->moduleDataSetup->getTable('store'), ['store_id', 'name'])
                    ->where('store_id > ?', 0)
                    ->order('store_id ASC')
            );
            $now = gmdate('Y-m-d H:i:s');

            foreach ($stores as $store) {
                $storeId = (int)$store['store_id'];
                $connection->insert($profileTable, [
                    'name' => trim((string)$store['name']) . ' Product Feed',
                    'is_active' => 1,
                    'feed_type' => FeedType::PRODUCT,
                    'output_format' => OutputFormat::XML,
                    'use_cdata' => 1,
                    'store_id' => $storeId,
                    'market_code' => null,
                    'locale_code' => null,
                    'product_identifier' => 'entity_id',
                    'active_products_only' => 1,
                    'visible_products_only' => 1,
                    'salable_products_only' => 0,
                    'skip_child_products_of_inactive_parents' => 1,
                    'include_not_visible' => 0,
                    'extra_attributes' => '',
                    'google_condition' => 'new',
                    'google_include_shipping' => 0,
                    'google_shipping_country' => null,
                    'google_shipping_service' => 'Standard',
                    'google_shipping_price' => null,
                    'schedule_enabled' => 1,
                    'schedule_cron_expression' => '0 2 * * *',
                    'generated_at' => null,
                    'last_executed_at' => null,
                    'generated_products_count' => null,
                    'last_generation_error' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        } finally {
            $this->moduleDataSetup->endSetup();
        }

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
