<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Console\Command;

use BerryPath\ProductFeed\Model\Feed\FileStorage;
use BerryPath\ProductFeed\Model\Profile;
use BerryPath\ProductFeed\Model\ResourceModel\Profile\CollectionFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListFeeds extends Command
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly FileStorage $fileStorage,
        private readonly StoreManagerInterface $storeManager,
        private readonly State $appState
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('berrypath:product-feed:list');
        $this->setDescription('List BerryPath product feeds and their generated files.');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->setAreaCode();

        $collection = $this->collectionFactory->create();
        $collection->setOrder('entity_id', 'ASC');

        $table = new Table($output);
        $table->setHeaders(['ID', 'Name', 'Type', 'Format', 'Store View', 'Status', 'Schedule', 'Executed', 'File']);

        foreach ($collection as $profile) {
            if (!$profile instanceof Profile) {
                continue;
            }

            $table->addRow([
                (int)$profile->getId(),
                $profile->getName(),
                (string)$profile->getData('feed_type'),
                strtoupper($profile->getOutputFormat()),
                $this->getStoreName($profile->getStoreId()),
                $profile->isActive() ? 'Active' : 'Inactive',
                $profile->scheduleEnabled() ? $this->formatSchedule($profile->getScheduleCronExpression()) : 'Manual',
                $this->formatExecution($profile),
                $this->fileStorage->exists($profile) ? $this->fileStorage->getUrl($profile) : 'Not generated',
            ]);
        }

        $table->render();

        return 0;
    }

    private function getStoreName(int $storeId): string
    {
        try {
            $store = $this->storeManager->getStore($storeId);

            return sprintf('%s / ID %d', (string)$store->getName(), $storeId);
        } catch (\Throwable) {
            return sprintf('ID %d', $storeId);
        }
    }

    private function formatSchedule(string $expression): string
    {
        $lines = preg_split('/[;\r\n]+/', trim($expression), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($lines === []) {
            return 'Not scheduled';
        }

        return count($lines) === 1 ? $lines[0] : count($lines) . ' scheduled runs';
    }

    private function formatExecution(Profile $profile): string
    {
        $error = trim((string)$profile->getData('last_generation_error'));
        $lastExecutedAt = trim((string)$profile->getData('last_executed_at'));
        $generatedAt = trim((string)$profile->getData('generated_at'));
        $count = $profile->getData('generated_products_count');

        if ($error !== '') {
            $status = 'Failed';
        } elseif ($lastExecutedAt !== '' || $generatedAt !== '') {
            $status = 'Success';
        } else {
            return 'Not generated';
        }

        $date = $lastExecutedAt !== '' ? $lastExecutedAt : $generatedAt;
        $products = is_numeric($count) ? (int)$count : '-';

        return sprintf('%s, products: %s, %s', $status, $products, $date);
    }

    private function setAreaCode(): void
    {
        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (LocalizedException) {
            // Area code is already set by the current Magento entry point.
        }
    }
}
