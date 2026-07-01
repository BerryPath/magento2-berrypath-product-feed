<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Model\Feed;

use BerryPath\ProductFeed\Model\Config\Source\OutputFormat;
use BerryPath\ProductFeed\Model\Profile;

class ContentGenerator
{
    public function __construct(
        private readonly Config $feedConfig,
        private readonly Generator $generator,
        private readonly Formatter $formatter
    ) {
    }

    /**
     * @return array{content: string, content_type: string, extension: string, products_output: int}
     */
    public function generate(Profile $profile, ?string $productId = null, ?int $productLimit = null): array
    {
        $storeId = $profile->getStoreId();
        $feed = $this->generator->generate($storeId, $productId, $productLimit, $profile);
        $format = $this->feedConfig->getOutputFormat($storeId, $profile);
        $feedType = $this->feedConfig->getFeedType($storeId, $profile);
        $options = $this->getOptions($feed, $profile);

        if ($format === OutputFormat::JSON) {
            $content = $this->formatter->toChannelJson($feed, $feedType, $options);
        } elseif ($format === OutputFormat::CSV) {
            $content = $this->formatter->toChannelCsv($feed, $feedType, $options);
        } else {
            $content = $this->formatter->toChannelXml($feed, $feedType, $options);
        }

        return [
            'content' => $content,
            'content_type' => $this->getContentType($format),
            'extension' => $format,
            'products_output' => (int)($feed['config']['products_output'] ?? 0),
        ];
    }

    public function getContentType(string $format): string
    {
        return match ($format) {
            OutputFormat::CSV => 'text/csv; charset=UTF-8',
            OutputFormat::JSON => 'application/json; charset=UTF-8',
            default => 'application/xml; charset=UTF-8',
        };
    }

    /**
     * @param array<string, mixed> $feed
     * @return array{use_cdata: bool, condition: string, shipping: array{country: string, service: string, price: string}|null}
     */
    private function getOptions(array $feed, Profile $profile): array
    {
        $currency = is_array($feed['config'] ?? null) ? (string)($feed['config']['currency'] ?? '') : '';

        return $this->feedConfig->getGoogleOptions($profile->getStoreId(), $currency, $profile);
    }
}
