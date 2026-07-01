<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Model\Feed;

class CronExpression
{
    public function isValid(string $expression): bool
    {
        $expressions = $this->expressions($expression);
        if ($expressions === []) {
            return false;
        }

        foreach ($expressions as $singleExpression) {
            $parts = $this->parts($singleExpression);
            if ($parts === []
                || $this->fieldValues($parts[0], 0, 59) === []
                || $this->fieldValues($parts[1], 0, 23) === []
                || $this->fieldValues($parts[2], 1, 31) === []
                || $this->fieldValues($parts[3], 1, 12) === []
                || $this->fieldValues($parts[4], 0, 7) === []
            ) {
                return false;
            }
        }

        return true;
    }

    public function isDue(string $expression, ?string $generatedAt, ?string $createdAt, ?int $now = null): bool
    {
        $expressions = $this->expressions($expression);
        if ($expressions === []) {
            return false;
        }

        foreach ($expressions as $singleExpression) {
            if ($this->singleExpressionIsDue($singleExpression, $generatedAt, $createdAt, $now)) {
                return true;
            }
        }

        return false;
    }

    private function singleExpressionIsDue(
        string $expression,
        ?string $generatedAt,
        ?string $createdAt,
        ?int $now = null
    ): bool {
        $parts = $this->parts($expression);
        if ($parts === []) {
            return false;
        }

        $now = $now ?? time();
        $nowMinute = intdiv($now, 60) * 60;
        $generatedTimestamp = $this->timestamp($generatedAt);
        $createdTimestamp = $this->timestamp($createdAt);
        $since = max($generatedTimestamp, $createdTimestamp, $nowMinute - 86400);

        for ($timestamp = $nowMinute; $timestamp > $since; $timestamp -= 60) {
            if ($this->matches($parts, $timestamp)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function expressions(string $expression): array
    {
        return preg_split('/[;\r\n]+/', trim($expression), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * @return array<int, string>
     */
    private function parts(string $expression): array
    {
        $parts = preg_split('/\s+/', trim($expression), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return count($parts) === 5 ? array_values($parts) : [];
    }

    /**
     * @param array<int, string> $parts
     */
    private function matches(array $parts, int $timestamp): bool
    {
        $minute = (int)gmdate('i', $timestamp);
        $hour = (int)gmdate('G', $timestamp);
        $dayOfMonth = (int)gmdate('j', $timestamp);
        $month = (int)gmdate('n', $timestamp);
        $dayOfWeek = (int)gmdate('w', $timestamp);

        $domMatches = $this->fieldMatches($parts[2], $dayOfMonth, 1, 31);
        $dowMatches = $this->fieldMatches($parts[4], $dayOfWeek, 0, 7);
        $dayMatches = $parts[2] !== '*' && $parts[4] !== '*'
            ? $domMatches || $dowMatches
            : $domMatches && $dowMatches;

        return $this->fieldMatches($parts[0], $minute, 0, 59)
            && $this->fieldMatches($parts[1], $hour, 0, 23)
            && $dayMatches
            && $this->fieldMatches($parts[3], $month, 1, 12);
    }

    private function fieldMatches(string $field, int $value, int $min, int $max): bool
    {
        $values = $this->fieldValues($field, $min, $max);

        return isset($values[$value]) || ($max === 7 && $value === 0 && isset($values[7]));
    }

    /**
     * @return array<int, true>
     */
    private function fieldValues(string $field, int $min, int $max): array
    {
        $values = [];
        foreach (explode(',', $field) as $part) {
            $part = trim($part);
            if ($part === '') {
                return [];
            }

            $step = 1;
            if (str_contains($part, '/')) {
                [$part, $stepPart] = explode('/', $part, 2);
                if (!ctype_digit($stepPart) || (int)$stepPart < 1) {
                    return [];
                }
                $step = (int)$stepPart;
            }

            if ($part === '*') {
                $start = $min;
                $end = $max;
            } elseif (str_contains($part, '-')) {
                [$startPart, $endPart] = explode('-', $part, 2);
                if (!ctype_digit($startPart) || !ctype_digit($endPart)) {
                    return [];
                }
                $start = (int)$startPart;
                $end = (int)$endPart;
            } elseif (ctype_digit($part)) {
                $start = (int)$part;
                $end = $start;
            } else {
                return [];
            }

            if ($start < $min || $end > $max || $start > $end) {
                return [];
            }

            for ($value = $start; $value <= $end; $value += $step) {
                $values[$value] = true;
            }
        }

        return $values;
    }

    private function timestamp(?string $value): int
    {
        if ($value === null || trim($value) === '') {
            return 0;
        }

        $timestamp = strtotime($value);

        return $timestamp !== false ? $timestamp : 0;
    }
}
