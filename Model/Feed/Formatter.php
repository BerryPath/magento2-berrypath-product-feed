<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Model\Feed;

class Formatter
{
    private const GOOGLE_NAMESPACE = 'http://base.google.com/ns/1.0';

    /**
     * @param array<string, mixed> $feed
     */
    public function toXml(array $feed): string
    {
        $writer = new \XMLWriter();
        $writer->openMemory();
        $writer->setIndent(true);
        $writer->setIndentString('  ');
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('product_feed');

        $this->writeNode($writer, 'config', $feed['config'] ?? []);

        $writer->startElement('products');
        foreach (($feed['products'] ?? []) as $product) {
            if (is_array($product)) {
                $this->writeNode($writer, 'product', $product);
            }
        }
        $writer->endElement();

        $writer->endElement();
        $writer->endDocument();

        return (string)$writer->outputMemory();
    }

    /**
     * @param array<string, mixed> $feed
     * @param array{condition?: string, shipping?: array{country: string, service: string, price: string}|null} $options
     */
    public function toGoogleRss(array $feed, array $options = []): string
    {
        $writer = new \XMLWriter();
        $writer->openMemory();
        $writer->setIndent(true);
        $writer->setIndentString('  ');
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('rss');
        $writer->writeAttribute('version', '2.0');
        $writer->writeAttribute('xmlns:g', self::GOOGLE_NAMESPACE);
        $writer->startElement('channel');

        $config = is_array($feed['config'] ?? null) ? $feed['config'] : [];
        $currency = $this->asString($config['currency'] ?? '');
        $writer->writeElement('title', $this->buildChannelTitle($config));
        $writer->writeElement('link', $this->asString($config['store_url'] ?? ''));
        $writer->writeElement('description', 'Magento product feed');

        foreach (($feed['products'] ?? []) as $product) {
            if (!is_array($product)) {
                continue;
            }

            $this->writeGoogleItem($writer, $product, $currency, $options);
        }

        $writer->endElement();
        $writer->endElement();
        $writer->endDocument();

        return (string)$writer->outputMemory();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeNode(\XMLWriter $writer, string $name, array $data): void
    {
        $writer->startElement($this->sanitizeElementName($name));

        foreach ($data as $key => $value) {
            $key = is_string($key) ? $key : 'item';

            if (is_array($value)) {
                $writer->startElement($this->sanitizeElementName($key));
                foreach ($value as $item) {
                    if (is_array($item)) {
                        $this->writeNode($writer, $this->singularize($key), $item);
                    } else {
                        $writer->startElement($this->singularize($key));
                        $this->writeValue($writer, $item);
                        $writer->endElement();
                    }
                }
                $writer->endElement();
                continue;
            }

            $writer->startElement($this->sanitizeElementName($key));
            $this->writeValue($writer, $value);
            $writer->endElement();
        }

        $writer->endElement();
    }

    private function writeValue(\XMLWriter $writer, mixed $value): void
    {
        if (is_bool($value)) {
            $writer->text($value ? 'true' : 'false');
            return;
        }

        if (is_scalar($value) || $value instanceof \Stringable) {
            $writer->text((string)$value);
        }
    }

    /**
     * @param array<string, mixed> $product
     * @param array{condition?: string, shipping?: array{country: string, service: string, price: string}|null} $options
     */
    private function writeGoogleItem(\XMLWriter $writer, array $product, string $currency, array $options): void
    {
        $title = $this->asString($product['name'] ?? '');
        $description = $this->asString($product['description'] ?? $product['short_description'] ?? $title);
        $link = $this->asString($product['url'] ?? '');
        $price = $this->asString($product['price'] ?? '');
        $finalPrice = $this->asString($product['final_price'] ?? $price);

        $writer->startElement('item');
        $writer->writeElement('title', $title);
        $writer->writeElement('link', $link);
        $writer->writeElement('description', $description);
        $this->writeGoogleElement($writer, 'id', $this->asString($product['id'] ?? ''));
        $this->writeGoogleElement($writer, 'title', $title);
        $this->writeGoogleElement($writer, 'description', $description);
        $this->writeGoogleElement($writer, 'link', $link);
        $this->writeGoogleElement($writer, 'image_link', $this->asString($product['image'] ?? ''));
        $this->writeGoogleElement($writer, 'availability', !empty($product['is_salable']) ? 'in_stock' : 'out_of_stock');
        $this->writeGoogleElement($writer, 'condition', $this->asString($options['condition'] ?? 'new'));
        $this->writeGoogleElement($writer, 'price', $this->formatGooglePrice($price, $currency));

        if ($this->amountValue($finalPrice) > 0.0 && $this->amountValue($finalPrice) < $this->amountValue($price)) {
            $this->writeGoogleElement($writer, 'sale_price', $this->formatGooglePrice($finalPrice, $currency));
        }

        $productType = $this->buildProductType($product);
        if ($productType !== '') {
            $this->writeGoogleElement($writer, 'product_type', $productType);
        }

        if (is_array($options['shipping'] ?? null)) {
            $shipping = $options['shipping'];
            $writer->startElement('g:shipping');
            $this->writeGoogleElement($writer, 'country', $this->asString($shipping['country'] ?? ''));
            $this->writeGoogleElement($writer, 'service', $this->asString($shipping['service'] ?? ''));
            $this->writeGoogleElement($writer, 'price', $this->asString($shipping['price'] ?? ''));
            $writer->endElement();
        }

        $writer->endElement();
    }

    private function writeGoogleElement(\XMLWriter $writer, string $name, string $value): void
    {
        if ($value === '') {
            return;
        }

        $writer->writeElement('g:' . $name, $value);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function buildChannelTitle(array $config): string
    {
        $storeCode = $this->asString($config['store_code'] ?? '');

        return $storeCode !== '' ? 'Product Feed - ' . $storeCode : 'Product Feed';
    }

    /**
     * @param array<string, mixed> $product
     */
    private function buildProductType(array $product): string
    {
        if (!is_array($product['categories'] ?? null)) {
            return '';
        }

        $categoryNames = [];
        foreach ($product['categories'] as $category) {
            if (!is_array($category)) {
                continue;
            }

            $name = $this->asString($category['name'] ?? '');
            if ($name !== '') {
                $categoryNames[] = $name;
            }
        }

        return implode(' > ', array_values(array_unique($categoryNames)));
    }

    private function formatGooglePrice(string $amount, string $currency): string
    {
        if ($currency === '') {
            return $amount;
        }

        return number_format($this->amountValue($amount), 2, '.', '') . ' ' . $currency;
    }

    private function amountValue(string $amount): float
    {
        return is_numeric($amount) ? (float)$amount : 0.0;
    }

    private function asString(mixed $value): string
    {
        if (!is_scalar($value) && !$value instanceof \Stringable) {
            return '';
        }

        return trim((string)$value);
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
