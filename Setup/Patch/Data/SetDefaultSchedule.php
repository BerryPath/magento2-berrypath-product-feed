<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class SetDefaultSchedule implements DataPatchInterface
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
            if (!$connection->isTableExists($profileTable)) {
                return $this;
            }

            $connection->update(
                $profileTable,
                [
                    'schedule_enabled' => 1,
                    'schedule_cron_expression' => '0 2 * * *',
                ],
                [
                    'schedule_enabled = ?' => 0,
                ]
            );
            $connection->update(
                $profileTable,
                ['schedule_cron_expression' => '0 2 * * *'],
                [
                    'schedule_cron_expression IS NULL OR schedule_cron_expression = ?' => '',
                ]
            );
        } finally {
            $this->moduleDataSetup->endSetup();
        }

        return $this;
    }

    public static function getDependencies(): array
    {
        return [InitializeDefaultProfiles::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
