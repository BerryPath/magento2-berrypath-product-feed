<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Model\Feed\Formatter;

class GenericFormatter implements ChannelFormatterInterface
{
    public function __construct(
        private readonly StructuredWriter $writer
    ) {
    }

    /**
     * @param array<string, mixed> $feed
     * @param array<string, mixed> $options
     */
    public function toXml(array $feed, array $options = []): string
    {
        return $this->writer->feedToXml($feed, (bool)($options['use_cdata'] ?? true));
    }

    /**
     * @param array<string, mixed> $feed
     * @param array<string, mixed> $options
     */
    public function toJson(array $feed, array $options = []): string
    {
        $json = json_encode($feed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $json !== false ? $json : '{}';
    }

    /**
     * @param array<string, mixed> $feed
     * @param array<string, mixed> $options
     */
    public function toCsv(array $feed, array $options = []): string
    {
        return $this->writer->rowsToCsv(is_array($feed['products'] ?? null) ? $feed['products'] : []);
    }
}
