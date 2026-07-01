<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Model\Feed\Formatter;

class OpenAiProductFormatter implements ChannelFormatterInterface
{
    public function __construct(
        private readonly CatalogRowBuilder $rowBuilder,
        private readonly StructuredWriter $writer
    ) {
    }

    /**
     * @param array<string, mixed> $feed
     * @param array<string, mixed> $options
     */
    public function toXml(array $feed, array $options = []): string
    {
        return $this->writer->rowsToXml(
            $this->rowBuilder->openAiRows($feed, $options),
            'openai_product_feed',
            'product',
            (bool)($options['use_cdata'] ?? true)
        );
    }

    /**
     * @param array<string, mixed> $feed
     * @param array<string, mixed> $options
     */
    public function toJson(array $feed, array $options = []): string
    {
        $json = json_encode(
            [
                'channel' => $this->rowBuilder->channel($feed),
                'items' => $this->rowBuilder->openAiRows($feed, $options),
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        return $json !== false ? $json : '{}';
    }

    /**
     * @param array<string, mixed> $feed
     * @param array<string, mixed> $options
     */
    public function toCsv(array $feed, array $options = []): string
    {
        return $this->writer->rowsToCsv($this->rowBuilder->openAiRows($feed, $options));
    }
}
