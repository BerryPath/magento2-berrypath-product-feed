<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Model\Feed\Formatter;

class FeedValue
{
    public function asString(mixed $value): string
    {
        if (!is_scalar($value) && !$value instanceof \Stringable) {
            return '';
        }

        return trim((string)$value);
    }

    public function fieldValue(mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', array_filter(array_map([$this, 'fieldValue'], $value)));
        }

        return $this->asString($value);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function channelTitle(array $config): string
    {
        $feedName = $this->asString($config['feed_name'] ?? '');
        if ($feedName !== '') {
            return $feedName;
        }

        $storeCode = $this->asString($config['store_code'] ?? '');

        return $storeCode !== '' ? 'Product Feed - ' . $storeCode : 'Product Feed';
    }

    /**
     * @param array<string, mixed> $product
     */
    public function productType(array $product): string
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

    public function priceWithCurrency(string $amount, string $currency): string
    {
        if ($currency === '') {
            return $amount;
        }

        return number_format($this->amountValue($amount), 2, '.', '') . ' ' . $currency;
    }

    public function amountValue(string $amount): float
    {
        return is_numeric($amount) ? (float)$amount : 0.0;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function filterEmptyRow(array $row): array
    {
        return array_filter(
            $row,
            static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []
        );
    }
}
