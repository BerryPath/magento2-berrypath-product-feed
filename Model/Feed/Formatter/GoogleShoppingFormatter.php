<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Model\Feed\Formatter;

class GoogleShoppingFormatter implements ChannelFormatterInterface
{
    private const GOOGLE_NAMESPACE = 'http://base.google.com/ns/1.0';

    public function __construct(
        private readonly CatalogRowBuilder $rowBuilder,
        private readonly StructuredWriter $writer,
        private readonly FeedValue $value
    ) {
    }

    /**
     * @param array<string, mixed> $feed
     * @param array<string, mixed> $options
     */
    public function toXml(array $feed, array $options = []): string
    {
        $useCdata = (bool)($options['use_cdata'] ?? true);
        $writer = $this->writer->createXmlWriter();
        $writer->startElement('rss');
        $writer->writeAttribute('version', '2.0');
        $writer->writeAttribute('xmlns:g', self::GOOGLE_NAMESPACE);
        $writer->startElement('channel');

        $channel = $this->rowBuilder->channel($feed);
        $this->writer->writeElement($writer, 'title', $channel['title'], $useCdata);
        $this->writer->writeElement($writer, 'link', $channel['link'], $useCdata);
        $this->writer->writeElement($writer, 'description', $channel['description'], $useCdata);

        foreach ($this->rowBuilder->googleRows($feed, $options) as $row) {
            $this->writeItem($writer, $row, is_array($options['shipping'] ?? null) ? $options['shipping'] : null, $useCdata);
        }

        $writer->endElement();
        $writer->endElement();
        $writer->endDocument();

        return (string)$writer->outputMemory();
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
                'items' => $this->rowBuilder->googleRows($feed, $options),
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
        $rows = $this->rowBuilder->googleRows($feed, $options);

        foreach ($rows as &$row) {
            if (!is_array($options['shipping'] ?? null)) {
                continue;
            }

            $shipping = $options['shipping'];
            $row['shipping_country'] = $this->value->asString($shipping['country'] ?? '');
            $row['shipping_service'] = $this->value->asString($shipping['service'] ?? '');
            $row['shipping_price'] = $this->value->asString($shipping['price'] ?? '');
        }
        unset($row);

        return $this->writer->rowsToCsv($rows);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed>|null $shipping
     */
    private function writeItem(\XMLWriter $writer, array $row, ?array $shipping, bool $useCdata): void
    {
        $writer->startElement('item');
        $this->writer->writeElement($writer, 'title', $this->value->asString($row['title'] ?? ''), $useCdata);
        $this->writer->writeElement($writer, 'link', $this->value->asString($row['link'] ?? ''), $useCdata);
        $this->writer->writeElement($writer, 'description', $this->value->asString($row['description'] ?? ''), $useCdata);

        foreach ($row as $key => $value) {
            $this->writeGoogleElement($writer, (string)$key, $this->value->fieldValue($value), $useCdata);
        }

        if ($shipping !== null) {
            $writer->startElement('g:shipping');
            $this->writeGoogleElement($writer, 'country', $this->value->asString($shipping['country'] ?? ''), $useCdata);
            $this->writeGoogleElement($writer, 'service', $this->value->asString($shipping['service'] ?? ''), $useCdata);
            $this->writeGoogleElement($writer, 'price', $this->value->asString($shipping['price'] ?? ''), $useCdata);
            $writer->endElement();
        }

        $writer->endElement();
    }

    private function writeGoogleElement(\XMLWriter $writer, string $name, string $value, bool $useCdata): void
    {
        if ($value === '') {
            return;
        }

        $this->writer->writeElement($writer, 'g:' . $name, $value, $useCdata);
    }
}
