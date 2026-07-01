<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Model\Feed;

use BerryPath\ProductFeed\Model\Config\Source\FeedType;
use BerryPath\ProductFeed\Model\Feed\Formatter\CatalogFormatter;
use BerryPath\ProductFeed\Model\Feed\Formatter\ChannelFormatterInterface;
use BerryPath\ProductFeed\Model\Feed\Formatter\GenericFormatter;
use BerryPath\ProductFeed\Model\Feed\Formatter\GoogleShoppingFormatter;
use BerryPath\ProductFeed\Model\Feed\Formatter\OpenAiProductFormatter;
use BerryPath\ProductFeed\Model\Feed\Formatter\TikTokCatalogFormatter;

class Formatter
{
    public function __construct(
        private readonly GenericFormatter $genericFormatter,
        private readonly GoogleShoppingFormatter $googleShoppingFormatter,
        private readonly CatalogFormatter $catalogFormatter,
        private readonly TikTokCatalogFormatter $tikTokCatalogFormatter,
        private readonly OpenAiProductFormatter $openAiProductFormatter
    ) {
    }

    /**
     * @param array<string, mixed> $feed
     */
    public function toXml(array $feed): string
    {
        return $this->genericFormatter->toXml($feed);
    }

    /**
     * @param array<string, mixed> $feed
     */
    public function toJson(array $feed): string
    {
        return $this->genericFormatter->toJson($feed);
    }

    /**
     * @param array<string, mixed> $feed
     */
    public function toCsv(array $feed): string
    {
        return $this->genericFormatter->toCsv($feed);
    }

    /**
     * @param array<string, mixed> $feed
     * @param array{condition?: string, shipping?: array{country: string, service: string, price: string}|null} $options
     */
    public function toGoogleRss(array $feed, array $options = []): string
    {
        return $this->googleShoppingFormatter->toXml($feed, $options);
    }

    /**
     * @param array<string, mixed> $feed
     * @param array{condition?: string, shipping?: array{country: string, service: string, price: string}|null} $options
     */
    public function toGoogleJson(array $feed, array $options = []): string
    {
        return $this->googleShoppingFormatter->toJson($feed, $options);
    }

    /**
     * @param array<string, mixed> $feed
     * @param array{condition?: string, shipping?: array{country: string, service: string, price: string}|null} $options
     */
    public function toGoogleCsv(array $feed, array $options = []): string
    {
        return $this->googleShoppingFormatter->toCsv($feed, $options);
    }

    /**
     * @param array<string, mixed> $feed
     * @param array{condition?: string, shipping?: array{country: string, service: string, price: string}|null} $options
     */
    public function toChannelXml(array $feed, string $feedType, array $options = []): string
    {
        return $this->getChannelFormatter($feedType)->toXml($feed, $options);
    }

    /**
     * @param array<string, mixed> $feed
     * @param array{condition?: string, shipping?: array{country: string, service: string, price: string}|null} $options
     */
    public function toChannelJson(array $feed, string $feedType, array $options = []): string
    {
        return $this->getChannelFormatter($feedType)->toJson($feed, $options);
    }

    /**
     * @param array<string, mixed> $feed
     * @param array{condition?: string, shipping?: array{country: string, service: string, price: string}|null} $options
     */
    public function toChannelCsv(array $feed, string $feedType, array $options = []): string
    {
        return $this->getChannelFormatter($feedType)->toCsv($feed, $options);
    }

    private function getChannelFormatter(string $feedType): ChannelFormatterInterface
    {
        return match ($feedType) {
            FeedType::GOOGLE_SHOPPING => $this->googleShoppingFormatter,
            FeedType::TIKTOK_CATALOG => $this->tikTokCatalogFormatter,
            FeedType::OPENAI_PRODUCT => $this->openAiProductFormatter,
            FeedType::META_CATALOG,
            FeedType::PINTEREST_CATALOG,
            FeedType::MICROSOFT_SHOPPING => $this->catalogFormatter,
            default => $this->genericFormatter,
        };
    }
}
