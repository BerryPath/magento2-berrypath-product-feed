<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Block\Adminhtml\Feed\Edit\Tab;

use BerryPath\ProductFeed\Model\Config\Source\ScheduleStatus;
use Magento\Backend\Block\Widget\Form as WidgetForm;

class Schedule extends AbstractTab
{
    private const DEFAULT_DAY = '*';
    private const DEFAULT_TIME = '02:00';

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Data\FormFactory $formFactory,
        private readonly ScheduleStatus $scheduleStatusSource,
        array $data = []
    ) {
        parent::__construct($context, $registry, $formFactory, $data);
    }

    public function getTabLabel(): string
    {
        return (string)__('Schedule');
    }

    protected function _prepareForm(): WidgetForm
    {
        $form = $this->_formFactory->create();
        $fieldset = $form->addFieldset('schedule_fieldset', ['legend' => __('Schedule')]);

        $fieldset->addField('schedule_enabled', 'select', [
            'name' => 'schedule_enabled',
            'label' => __('Feed Generation'),
            'title' => __('Feed Generation'),
            'values' => $this->scheduleStatusSource->toOptionArray(),
            'note' => __('Choose whether Magento should refresh this feed automatically.'),
        ]);
        $fieldset->addField('schedule_day', 'multiselect', [
            'name' => 'schedule_day',
            'label' => __('Refresh Days'),
            'title' => __('Refresh Days'),
            'values' => $this->getDayOptions(),
        ]);
        $fieldset->addField('schedule_time', 'multiselect', [
            'name' => 'schedule_time',
            'label' => __('Refresh Time'),
            'title' => __('Refresh Time'),
            'values' => $this->getTimeOptions(),
            'note' => __('Select one or more times. The generated feed file is replaced when Magento cron runs.'),
        ]);

        $profile = $this->getProfile();
        if ($profile->getData('generated_at')) {
            $fieldset->addField('generated_at', 'label', [
                'label' => __('Last Generated'),
                'value' => (string)$profile->getData('generated_at'),
            ]);
        }
        if ($profile->getData('last_generation_error')) {
            $fieldset->addField('last_generation_error', 'label', [
                'label' => __('Last Generation Error'),
                'value' => (string)$profile->getData('last_generation_error'),
            ]);
        }

        $form->setValues($this->getScheduleData());
        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * @return array<string, mixed>
     */
    private function getScheduleData(): array
    {
        $data = $this->getProfileData();
        [$day, $time] = $this->parseCronExpression((string)($data['schedule_cron_expression'] ?? ''));
        $data['schedule_enabled'] = isset($data['schedule_enabled']) ? (int)$data['schedule_enabled'] : 1;
        $data['schedule_day'] = $day;
        $data['schedule_time'] = $time;

        return $data;
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function parseCronExpression(string $expression): array
    {
        $expressions = preg_split('/[;\r\n]+/', trim($expression), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($expressions === []) {
            return [$this->getDefaultDays(), [self::DEFAULT_TIME]];
        }

        $days = [];
        $times = [];
        foreach ($expressions as $singleExpression) {
            $parts = preg_split('/\s+/', trim($singleExpression), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if (count($parts) !== 5 || $parts[2] !== '*' || $parts[3] !== '*') {
                return [$this->getDefaultDays(), [self::DEFAULT_TIME]];
            }

            $expressionDay = (string)$parts[4];
            if ($expressionDay === '7') {
                $expressionDay = '0';
            }
            if ($expressionDay === '*') {
                $days = $this->getDefaultDays();
            } elseif (!ctype_digit($expressionDay) || (int)$expressionDay < 0 || (int)$expressionDay > 6) {
                return [$this->getDefaultDays(), [self::DEFAULT_TIME]];
            } else {
                $days[] = $expressionDay;
            }

            $minutes = $this->parseCronList((string)$parts[0], [0, 30], 0, 59);
            $hours = $this->parseCronList((string)$parts[1], range(0, 23), 0, 23);
            if (count($minutes) !== 1 || count($hours) !== 1) {
                return [$this->getDefaultDays(), [self::DEFAULT_TIME]];
            }

            $times[] = sprintf('%02d:%02d', $hours[0], $minutes[0]);
        }

        $days = array_values(array_unique($days ?: $this->getDefaultDays()));
        sort($days, SORT_NUMERIC);
        $times = array_values(array_unique($times));
        sort($times, SORT_STRING);

        return [$days, $times ?: [self::DEFAULT_TIME]];
    }

    /**
     * @param array<int, int> $allowedValues
     * @return array<int, int>
     */
    private function parseCronList(string $value, array $allowedValues, int $min, int $max): array
    {
        $values = [];
        foreach (explode(',', $value) as $part) {
            $part = trim($part);
            if (!ctype_digit($part)) {
                return [];
            }

            $number = (int)$part;
            if ($number < $min || $number > $max || !in_array($number, $allowedValues, true)) {
                return [];
            }

            $values[] = $number;
        }

        $values = array_values(array_unique($values));
        sort($values, SORT_NUMERIC);

        return $values;
    }

    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    private function getDayOptions(): array
    {
        return [
            ['value' => '0', 'label' => __('Sunday')],
            ['value' => '1', 'label' => __('Monday')],
            ['value' => '2', 'label' => __('Tuesday')],
            ['value' => '3', 'label' => __('Wednesday')],
            ['value' => '4', 'label' => __('Thursday')],
            ['value' => '5', 'label' => __('Friday')],
            ['value' => '6', 'label' => __('Saturday')],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function getDefaultDays(): array
    {
        return ['0', '1', '2', '3', '4', '5', '6'];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function getTimeOptions(): array
    {
        $options = [];
        for ($hour = 0; $hour < 24; $hour++) {
            foreach ([0, 30] as $minute) {
                $value = sprintf('%02d:%02d', $hour, $minute);
                $options[] = ['value' => $value, 'label' => $value];
            }
        }

        return $options;
    }
}
