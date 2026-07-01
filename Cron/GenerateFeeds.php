<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Cron;

use BerryPath\ProductFeed\Model\Feed\CronExpression;
use BerryPath\ProductFeed\Model\Feed\ProfileGenerator;
use BerryPath\ProductFeed\Model\Profile;
use BerryPath\ProductFeed\Model\ResourceModel\Profile\CollectionFactory;
use Psr\Log\LoggerInterface;

class GenerateFeeds
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly CronExpression $cronExpression,
        private readonly ProfileGenerator $profileGenerator,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('is_active', 1);
        $collection->addFieldToFilter('schedule_enabled', 1);
        $collection->addFieldToFilter('schedule_cron_expression', ['notnull' => true]);

        foreach ($collection as $profile) {
            if (!$profile instanceof Profile) {
                continue;
            }

            $expression = trim((string)$profile->getData('schedule_cron_expression'));
            if (!$this->cronExpression->isDue(
                $expression,
                (string)$profile->getData('generated_at'),
                (string)$profile->getData('created_at')
            )) {
                continue;
            }

            try {
                $this->profileGenerator->generate($profile);
            } catch (\Throwable $exception) {
                $this->logger->error(
                    'BerryPath product feed cron generation failed.',
                    ['feed_id' => $profile->getId(), 'exception' => $exception]
                );
            }
        }
    }
}
