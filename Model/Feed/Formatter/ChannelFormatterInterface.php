<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Model\Feed\Formatter;

interface ChannelFormatterInterface
{
    /**
     * @param array<string, mixed> $feed
     * @param array<string, mixed> $options
     */
    public function toXml(array $feed, array $options = []): string;

    /**
     * @param array<string, mixed> $feed
     * @param array<string, mixed> $options
     */
    public function toJson(array $feed, array $options = []): string;

    /**
     * @param array<string, mixed> $feed
     * @param array<string, mixed> $options
     */
    public function toCsv(array $feed, array $options = []): string;
}
