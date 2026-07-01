<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Model\Feed;

use BerryPath\ProductFeed\Model\Config\Source\FeedType;
use BerryPath\ProductFeed\Model\Feed\Formatter\ChannelFormatterInterface;
use BerryPath\ProductFeed\Model\Feed\Formatter\CriteoFormatter;
use BerryPath\ProductFeed\Model\Feed\Formatter\GenericFormatter;
use BerryPath\ProductFeed\Model\Feed\Formatter\GoogleShoppingFormatter;
use BerryPath\ProductFeed\Model\Feed\Formatter\OpenAiProductFormatter;
use BerryPath\ProductFeed\Model\Feed\Formatter\TikTokCatalogFormatter;

class Formatter
{
    public function __construct(
        private readonly GenericFormatter $genericFormatter,
        private readonly GoogleShoppingFormatter $googleShoppingFormatter,
        private readonly TikTokCatalogFormatter $tikTokCatalogFormatter,
        private readonly OpenAiProductFormatter $openAiProductFormatter,
        private readonly CriteoFormatter $criteoFormatter
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
        return $this->getChannelFormatter($feedType)->toXml($feed, $this->channelOptions($feedType, $options));
    }

    /**
     * @param array<string, mixed> $feed
     * @param array{condition?: string, shipping?: array{country: string, service: string, price: string}|null} $options
     */
    public function toChannelJson(array $feed, string $feedType, array $options = []): string
    {
        return $this->getChannelFormatter($feedType)->toJson($feed, $this->channelOptions($feedType, $options));
    }

    /**
     * @param array<string, mixed> $feed
     * @param array{condition?: string, shipping?: array{country: string, service: string, price: string}|null} $options
     */
    public function toChannelCsv(array $feed, string $feedType, array $options = []): string
    {
        return $this->getChannelFormatter($feedType)->toCsv($feed, $this->channelOptions($feedType, $options));
    }

    /**
     * Apply channel-specific output tweaks on top of the shared Google-compatible formatter.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function channelOptions(string $feedType, array $options): array
    {
        return match ($feedType) {
            // Meta prefers the space-form availability values (in stock / out of stock).
            FeedType::META_CATALOG => array_merge($options, ['availability_style' => 'space']),
            // Microsoft Merchant Center (Bing) recommends a seller_name attribute.
            FeedType::MICROSOFT_SHOPPING => array_merge($options, ['seller_name' => true]),
            default => $options,
        };
    }

    private function getChannelFormatter(string $feedType): ChannelFormatterInterface
    {
        return match ($feedType) {
            // Meta, Pinterest, Microsoft and Snapchat all ingest the Google-compatible
            // RSS 2.0 feed (base.google.com/ns/1.0 namespace), so they share that formatter.
            FeedType::GOOGLE_SHOPPING,
            FeedType::META_CATALOG,
            FeedType::PINTEREST_CATALOG,
            FeedType::MICROSOFT_SHOPPING,
            FeedType::SNAPCHAT_CATALOG => $this->googleShoppingFormatter,
            FeedType::TIKTOK_CATALOG => $this->tikTokCatalogFormatter,
            FeedType::OPENAI_PRODUCT => $this->openAiProductFormatter,
            FeedType::CRITEO => $this->criteoFormatter,
            default => $this->genericFormatter,
        };
    }
}
