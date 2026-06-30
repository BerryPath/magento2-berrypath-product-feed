<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class LocaleCode implements OptionSourceInterface
{
    /**
     * Keep this list aligned with BerryPath application FlowLocalizationService::LANGUAGE_CHOICES.
     *
     * @var array<string, string>
     */
    private const LOCALE_LABELS = [
        'en' => 'English',
        'nl' => 'Dutch',
        'bg' => 'Bulgarian',
        'cs' => 'Czech',
        'da' => 'Danish',
        'de' => 'German',
        'el' => 'Greek',
        'fr' => 'French',
        'es' => 'Spanish',
        'et' => 'Estonian',
        'fi' => 'Finnish',
        'hr' => 'Croatian',
        'hu' => 'Hungarian',
        'it' => 'Italian',
        'lt' => 'Lithuanian',
        'lv' => 'Latvian',
        'no' => 'Norwegian',
        'pl' => 'Polish',
        'pt' => 'Portuguese',
        'ro' => 'Romanian',
        'sk' => 'Slovak',
        'sl' => 'Slovenian',
        'sv' => 'Swedish',
    ];

    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        $options = [
            ['value' => '', 'label' => __('Magento store locale')],
        ];

        foreach (self::LOCALE_LABELS as $code => $label) {
            $options[] = [
                'value' => $code,
                'label' => __('%1 (%2)', __($label), $code),
            ];
        }

        return $options;
    }

    public static function normalizeLocaleCode(string $locale): string
    {
        $locale = strtolower(str_replace('_', '-', trim($locale)));
        $locale = preg_replace('/[^a-z0-9-]/', '', $locale) ?? '';
        $locale = trim($locale, '-');
        if ($locale === '') {
            return '';
        }

        if (isset(self::LOCALE_LABELS[$locale])) {
            return $locale;
        }

        $primary = strtok($locale, '-') ?: $locale;

        return isset(self::LOCALE_LABELS[$primary]) ? $primary : '';
    }
}
