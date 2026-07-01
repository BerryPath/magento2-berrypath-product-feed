<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Console\Command;

use BerryPath\ProductFeed\Model\Feed\ProfileGenerator;
use BerryPath\ProductFeed\Model\Profile;
use BerryPath\ProductFeed\Model\ProfileRepository;
use BerryPath\ProductFeed\Model\ResourceModel\Profile\CollectionFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateFeed extends Command
{
    private const ARG_FEED_ID = 'feed_id';
    private const OPT_ALL = 'all';

    public function __construct(
        private readonly ProfileRepository $profileRepository,
        private readonly CollectionFactory $collectionFactory,
        private readonly ProfileGenerator $profileGenerator,
        private readonly State $appState
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('berrypath:product-feed:generate');
        $this->setDescription('Generate BerryPath product feed files.');
        $this->addArgument(self::ARG_FEED_ID, InputArgument::OPTIONAL, 'Feed ID to generate.');
        $this->addOption(self::OPT_ALL, null, InputOption::VALUE_NONE, 'Generate all active feeds.');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->setAreaCode();

        $feedId = (int)$input->getArgument(self::ARG_FEED_ID);
        $generateAll = (bool)$input->getOption(self::OPT_ALL);

        if ($feedId > 0 && $generateAll) {
            $output->writeln('<error>Use either a feed ID or --all, not both.</error>');

            return 1;
        }

        if (!$generateAll && $feedId <= 0) {
            $output->writeln('<error>Provide a feed ID or use --all.</error>');

            return 1;
        }

        return $generateAll
            ? $this->generateAll($output)
            : $this->generateOne($feedId, $output);
    }

    private function generateOne(int $feedId, OutputInterface $output): int
    {
        try {
            $profile = $this->profileRepository->getById($feedId);
            $this->generateProfile($profile, $output);

            return 0;
        } catch (\Throwable $exception) {
            $output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));

            return 1;
        }
    }

    private function generateAll(OutputInterface $output): int
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('is_active', 1);
        $collection->setOrder('entity_id', 'ASC');

        $generated = 0;
        $failed = 0;

        foreach ($collection as $profile) {
            if (!$profile instanceof Profile) {
                continue;
            }

            try {
                $this->generateProfile($profile, $output);
                $generated++;
            } catch (\Throwable $exception) {
                $failed++;
                $output->writeln(sprintf(
                    '<error>Feed #%d failed: %s</error>',
                    (int)$profile->getId(),
                    $exception->getMessage()
                ));
            }
        }

        $output->writeln(sprintf('<info>%d feed(s) generated.</info>', $generated));

        return $failed > 0 ? 1 : 0;
    }

    private function generateProfile(Profile $profile, OutputInterface $output): void
    {
        $result = $this->profileGenerator->generate($profile);
        $output->writeln(sprintf(
            '<info>Generated feed #%d (%d product(s)).</info>',
            (int)$profile->getId(),
            (int)$result['products_output']
        ));
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
