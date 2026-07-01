<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class BackfillExecutionData implements DataPatchInterface
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
                ['last_executed_at' => new \Zend_Db_Expr('generated_at')],
                [
                    'last_executed_at IS NULL',
                    'generated_at IS NOT NULL',
                ]
            );
        } finally {
            $this->moduleDataSetup->endSetup();
        }

        return $this;
    }

    public static function getDependencies(): array
    {
        return [SetDefaultSchedule::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
