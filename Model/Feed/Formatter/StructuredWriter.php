<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Model\Feed\Formatter;

class StructuredWriter
{
    /**
     * @var array<string, bool>
     */
    private const CDATA_ELEMENT_NAMES = [
        'age_group' => true,
        'brand' => true,
        'color' => true,
        'description' => true,
        'feed_name' => true,
        'gender' => true,
        'google_product_category' => true,
        'item_group_title' => true,
        'manufacturer' => true,
        'material' => true,
        'name' => true,
        'pattern' => true,
        'product_category' => true,
        'product_type' => true,
        'seller_name' => true,
        'short_description' => true,
        'size' => true,
        'tax_class_id' => true,
        'title' => true,
        'visibility' => true,
    ];

    /**
     * @param array<string, mixed> $feed
     */
    public function feedToXml(array $feed, bool $useCdata = true): string
    {
        $writer = $this->createXmlWriter();
        $writer->startElement('product_feed');

        $this->writeNode($writer, 'config', is_array($feed['config'] ?? null) ? $feed['config'] : [], $useCdata);

        $writer->startElement('products');
        foreach (($feed['products'] ?? []) as $product) {
            if (is_array($product)) {
                $this->writeNode($writer, 'product', $product, $useCdata);
            }
        }
        $writer->endElement();

        $writer->endElement();
        $writer->endDocument();

        return (string)$writer->outputMemory();
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function rowsToXml(array $rows, string $rootName, string $itemName, bool $useCdata = true): string
    {
        $writer = $this->createXmlWriter();
        $writer->startElement($rootName);

        foreach ($rows as $row) {
            $this->writeNode($writer, $itemName, $row, $useCdata);
        }

        $writer->endElement();
        $writer->endDocument();

        return (string)$writer->outputMemory();
    }

    /**
     * @param array<int, mixed> $rows
     */
    public function rowsToCsv(array $rows): string
    {
        $rows = array_values(array_filter($rows, static fn (mixed $row): bool => is_array($row)));
        $headers = [];
        foreach ($rows as $row) {
            foreach (array_keys($row) as $header) {
                $header = (string)$header;
                if (!in_array($header, $headers, true)) {
                    $headers[] = $header;
                }
            }
        }

        if ($headers === []) {
            return '';
        }

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return '';
        }

        fputcsv($stream, $headers, ',', '"', '');
        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $header) {
                $line[] = $this->csvValue($row[$header] ?? '');
            }
            fputcsv($stream, $line, ',', '"', '');
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv !== false ? $csv : '';
    }

    /**
     * @param array<string, mixed> $data
     */
    public function writeNode(\XMLWriter $writer, string $name, array $data, bool $useCdata = true): void
    {
        $writer->startElement($this->sanitizeElementName($name));

        foreach ($data as $key => $value) {
            $key = is_string($key) ? $key : 'item';

            if (is_array($value)) {
                $writer->startElement($this->sanitizeElementName($key));
                foreach ($value as $item) {
                    if (is_array($item)) {
                        $this->writeNode($writer, $this->singularize($key), $item, $useCdata);
                    } else {
                        $writer->startElement($this->singularize($key));
                        $this->writeValue($writer, $item, $this->singularize($key), $useCdata);
                        $writer->endElement();
                    }
                }
                $writer->endElement();
                continue;
            }

            $this->writeElement($writer, $this->sanitizeElementName($key), $value, $useCdata);
        }

        $writer->endElement();
    }

    public function writeElement(\XMLWriter $writer, string $name, mixed $value, bool $useCdata = true): void
    {
        $writer->startElement($name);
        $this->writeValue($writer, $value, $name, $useCdata);
        $writer->endElement();
    }

    public function createXmlWriter(): \XMLWriter
    {
        $writer = new \XMLWriter();
        $writer->openMemory();
        $writer->setIndent(true);
        $writer->setIndentString('  ');
        $writer->startDocument('1.0', 'UTF-8');

        return $writer;
    }

    private function writeValue(\XMLWriter $writer, mixed $value, string $elementName = '', bool $useCdata = true): void
    {
        if (is_bool($value)) {
            $writer->text($value ? 'true' : 'false');
            return;
        }

        if (is_scalar($value) || $value instanceof \Stringable) {
            $text = (string)$value;
            if ($useCdata && $this->shouldUseCdata($elementName, $text)) {
                $this->writeCdata($writer, $text);
                return;
            }

            $writer->text($text);
        }
    }

    private function shouldUseCdata(string $elementName, string $value): bool
    {
        if ($value === '') {
            return false;
        }

        $elementName = strtolower($elementName);
        if (str_contains($elementName, ':')) {
            $elementName = (string)substr($elementName, (int)strrpos($elementName, ':') + 1);
        }

        return isset(self::CDATA_ELEMENT_NAMES[$elementName]);
    }

    private function writeCdata(\XMLWriter $writer, string $value): void
    {
        if (str_contains($value, ']]>')) {
            $writer->text($value);
            return;
        }

        $writer->writeCdata($value);
    }

    private function csvValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value)) {
            $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            return $json !== false ? $json : '';
        }

        return is_scalar($value) || $value instanceof \Stringable ? (string)$value : '';
    }

    private function sanitizeElementName(string $name): string
    {
        $name = (string)preg_replace('/[^A-Za-z0-9_.-]/', '_', $name);
        if ($name === '' || preg_match('/^[A-Za-z_]/', $name) !== 1) {
            $name = 'item_' . $name;
        }

        return $name;
    }

    private function singularize(string $name): string
    {
        return match ($name) {
            'products' => 'product',
            'categories' => 'category',
            'category_ids' => 'category_id',
            default => 'item',
        };
    }
}
