<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Controller\Adminhtml\Feed;

use BerryPath\ProductFeed\Model\Config\Source\FeedType;
use BerryPath\ProductFeed\Model\Config\Source\LocaleCode;
use BerryPath\ProductFeed\Model\Config\Source\OutputFormat;
use BerryPath\ProductFeed\Model\Config\Source\ProductCondition;
use BerryPath\ProductFeed\Model\Feed\CronExpression;
use BerryPath\ProductFeed\Model\Feed\FileStorage;
use BerryPath\ProductFeed\Model\Feed\ProfileConditions;
use BerryPath\ProductFeed\Model\ProfileFactory;
use BerryPath\ProductFeed\Model\ProfileRepository;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'BerryPath_ProductFeed::feeds';
    private const DEFAULT_SCHEDULE_DAYS = ['0', '1', '2', '3', '4', '5', '6'];
    private const DEFAULT_SCHEDULE_TIME = '02:00';

    public function __construct(
        Context $context,
        private readonly ProfileFactory $profileFactory,
        private readonly ProfileRepository $profileRepository,
        private readonly FileStorage $fileStorage,
        private readonly CronExpression $cronExpression,
        private readonly ProfileConditions $profileConditions
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $request = $this->getRequest();
        $profileId = (int)$request->getParam('entity_id');

        try {
            $postData = $request->getPostValue();
            if (!is_array($postData)) {
                throw new LocalizedException(__('No feed options were submitted.'));
            }

            $profile = $profileId > 0
                ? $this->profileRepository->getById($profileId)
                : $this->profileFactory->create();
            $profile->addData($this->normalizeData($postData, (string)$profile->getData('conditions_serialized')));
            $this->profileRepository->save($profile);
            if ($this->fileStorage->exists($profile)) {
                $this->messageManager->addSuccessMessage(
                    __('The feed options have been saved. The existing live feed file remains available.')
                );
            } else {
                $this->messageManager->addSuccessMessage(
                    __('The feed options have been saved. Generate the feed to create the live file.')
                );
            }

            if ($request->getParam('back')) {
                return $this->resultRedirectFactory->create()->setPath('berrypath/feed/edit', ['id' => $profile->getId()]);
            }

            return $this->resultRedirectFactory->create()->setPath('berrypath/feed/index');
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());

            return $this->resultRedirectFactory->create()->setPath(
                'berrypath/feed/edit',
                $profileId > 0 ? ['id' => $profileId] : []
            );
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeData(array $data, string $currentConditions): array
    {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            throw new LocalizedException(__('Please enter a feed name.'));
        }

        $storeId = (int)($data['store_id'] ?? 0);
        if ($storeId <= 0) {
            throw new LocalizedException(__('Please select a store view.'));
        }

        $extraAttributes = $data['extra_attributes'] ?? [];
        if (is_array($extraAttributes)) {
            $extraAttributes = implode(',', array_filter(array_map('strval', $extraAttributes)));
        }

        return [
            'name' => $name,
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'feed_type' => $this->normalizeFeedType((string)($data['feed_type'] ?? '')),
            'output_format' => $this->normalizeOutputFormat((string)($data['output_format'] ?? '')),
            'use_cdata' => !empty($data['use_cdata']) ? 1 : 0,
            'store_id' => $storeId,
            'market_code' => $this->normalizeMarketCode((string)($data['market_code'] ?? '')),
            'locale_code' => $this->normalizeLocaleCode((string)($data['locale_code'] ?? '')),
            'product_identifier' => $this->normalizeAttributeCode((string)($data['product_identifier'] ?? 'entity_id')),
            'active_products_only' => !empty($data['active_products_only']) ? 1 : 0,
            'visible_products_only' => !empty($data['visible_products_only']) ? 1 : 0,
            'salable_products_only' => !empty($data['salable_products_only']) ? 1 : 0,
            'skip_child_products_of_inactive_parents' => !empty($data['skip_child_products_of_inactive_parents']) ? 1 : 0,
            'include_not_visible' => empty($data['visible_products_only']) ? 1 : 0,
            'extra_attributes' => (string)$extraAttributes,
            'conditions_serialized' => $this->profileConditions->serializeFromPost($data, $currentConditions),
            'google_condition' => $this->normalizeCondition((string)($data['google_condition'] ?? 'new')),
            'google_include_shipping' => !empty($data['google_include_shipping']) ? 1 : 0,
            'google_shipping_country' => $this->normalizeCountry((string)($data['google_shipping_country'] ?? '')),
            'google_shipping_service' => trim((string)($data['google_shipping_service'] ?? '')),
            'google_shipping_price' => $this->normalizePrice((string)($data['google_shipping_price'] ?? '')),
            'schedule_enabled' => (int)($data['schedule_enabled'] ?? 1) === 1 ? 1 : 0,
            'schedule_cron_expression' => $this->buildCronExpression(
                $data['schedule_day'] ?? self::DEFAULT_SCHEDULE_DAYS,
                $data['schedule_time'] ?? [self::DEFAULT_SCHEDULE_TIME]
            ),
        ];
    }

    private function normalizeFeedType(string $feedType): string
    {
        return in_array($feedType, FeedType::values(), true) ? $feedType : FeedType::PRODUCT;
    }

    private function normalizeOutputFormat(string $format): string
    {
        $format = trim($format);

        return in_array($format, [OutputFormat::XML, OutputFormat::CSV, OutputFormat::JSON], true)
            ? $format
            : OutputFormat::XML;
    }

    private function normalizeCondition(string $condition): string
    {
        return in_array(
            $condition,
            [ProductCondition::NEW, ProductCondition::REFURBISHED, ProductCondition::USED],
            true
        ) ? $condition : ProductCondition::NEW;
    }

    private function normalizeAttributeCode(string $attributeCode): string
    {
        $attributeCode = trim($attributeCode);

        return preg_match('/^[a-z][a-z0-9_]{0,254}$/', $attributeCode) === 1 ? $attributeCode : 'entity_id';
    }

    private function normalizeMarketCode(string $marketCode): ?string
    {
        $marketCode = strtolower(trim(str_replace('_', '-', $marketCode)));
        $marketCode = (string)preg_replace('/[^a-z0-9-]+/', '-', $marketCode);
        $marketCode = trim((string)preg_replace('/-+/', '-', $marketCode), '-');

        return preg_match('/^[a-z0-9](?:[a-z0-9-]{0,38}[a-z0-9])?$/', $marketCode) === 1
            ? $marketCode
            : null;
    }

    private function normalizeLocaleCode(string $localeCode): ?string
    {
        $localeCode = LocaleCode::normalizeLocaleCode($localeCode);

        return $localeCode !== '' ? $localeCode : null;
    }

    private function normalizeCountry(string $country): ?string
    {
        $country = strtoupper(trim($country));

        return preg_match('/^[A-Z]{2}$/', $country) === 1 ? $country : null;
    }

    private function normalizePrice(string $price): ?string
    {
        $price = str_replace(',', '.', trim($price));

        return is_numeric($price) && (float)$price >= 0.0 ? number_format((float)$price, 4, '.', '') : null;
    }

    /**
     * @param string|array<int|string, mixed> $days
     * @param string|array<int|string, mixed> $times
     */
    private function buildCronExpression(string|array $days, string|array $times): string
    {
        $expressions = [];
        foreach ($this->normalizeScheduleDays($days) as $day) {
            foreach ($this->normalizeScheduleTimes($times) as [$hour, $minute]) {
                $expressions[] = sprintf('%d %d * * %s', $minute, $hour, $day);
            }
        }

        $expression = implode("\n", $expressions);
        if (!$this->cronExpression->isValid($expression)) {
            throw new LocalizedException(__('Please select a valid feed schedule.'));
        }

        return $expression;
    }

    /**
     * @param string|array<int|string, mixed> $days
     * @return array<int, string>
     */
    private function normalizeScheduleDays(string|array $days): array
    {
        if (!is_array($days)) {
            $days = [$days];
        }

        $normalized = [];
        foreach ($days as $day) {
            if (!is_scalar($day)) {
                continue;
            }

            $day = trim((string)$day);
            if (ctype_digit($day) && (int)$day >= 0 && (int)$day <= 6) {
                $normalized[] = (string)(int)$day;
            }
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized, SORT_NUMERIC);

        return $normalized !== [] ? $normalized : self::DEFAULT_SCHEDULE_DAYS;
    }

    /**
     * @param string|array<int|string, mixed> $times
     * @return array<int, array{0: int, 1: int}>
     */
    private function normalizeScheduleTimes(string|array $times): array
    {
        if (!is_array($times)) {
            $times = [$times];
        }

        $normalized = [];
        foreach ($times as $time) {
            if (!is_scalar($time)) {
                continue;
            }

            $normalized[] = $this->normalizeScheduleTime((string)$time);
        }

        $normalized = array_values(array_unique($normalized, SORT_REGULAR));

        return $normalized !== [] ? $normalized : [$this->normalizeScheduleTime(self::DEFAULT_SCHEDULE_TIME)];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function normalizeScheduleTime(string $time): array
    {
        $time = trim($time);
        if (preg_match('/^([01][0-9]|2[0-3]):(00|30)$/', $time, $matches) !== 1) {
            $time = self::DEFAULT_SCHEDULE_TIME;
            preg_match('/^([01][0-9]|2[0-3]):(00|30)$/', $time, $matches);
        }

        return [(int)$matches[1], (int)$matches[2]];
    }
}
