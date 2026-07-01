<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Model\Feed;

use BerryPath\ProductFeed\Model\Profile;
use BerryPath\ProductFeed\Model\ProfileRepository;
use Magento\Framework\Stdlib\DateTime\DateTime;

class ProfileGenerator
{
    public function __construct(
        private readonly ContentGenerator $contentGenerator,
        private readonly FileStorage $fileStorage,
        private readonly ProfileRepository $profileRepository,
        private readonly DateTime $dateTime
    ) {
    }

    /**
     * @return array{content: string, content_type: string, extension: string, products_output: int}
     */
    public function generate(Profile $profile): array
    {
        try {
            $result = $this->contentGenerator->generate($profile);
            $this->fileStorage->save($profile, $result['content']);
            $now = $this->dateTime->gmtDate();
            $profile->addData([
                'generated_at' => $now,
                'last_executed_at' => $now,
                'generated_products_count' => (int)$result['products_output'],
                'last_generation_error' => null,
            ]);
            $this->profileRepository->save($profile);

            return $result;
        } catch (\Throwable $exception) {
            $this->markFailed($profile, $exception);
            throw $exception;
        }
    }

    private function markFailed(Profile $profile, \Throwable $exception): void
    {
        $profile->addData([
            'last_executed_at' => $this->dateTime->gmtDate(),
            'last_generation_error' => mb_substr($exception->getMessage(), 0, 65535),
        ]);
        $this->profileRepository->save($profile);
    }
}
