<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Ui\Component\Listing\Column;

use Magento\Framework\Escaper;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class Executed extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly Escaper $escaper,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @param array<string, mixed> $dataSource
     * @return array<string, mixed>
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items']) || !is_array($dataSource['data']['items'])) {
            return $dataSource;
        }

        foreach ($dataSource['data']['items'] as &$item) {
            $item[$this->getData('name')] = $this->renderExecution($item);
        }
        unset($item);

        return $dataSource;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function renderExecution(array $item): string
    {
        $error = trim((string)($item['last_generation_error'] ?? ''));
        $lastExecutedAt = trim((string)($item['last_executed_at'] ?? ''));
        $generatedAt = trim((string)($item['generated_at'] ?? ''));
        $count = $item['generated_products_count'] ?? null;

        if ($error !== '') {
            $status = __('Failed');
            $class = 'bp-productfeed-executed__status--failed';
            $title = $error;
        } elseif ($lastExecutedAt !== '' || $generatedAt !== '') {
            $status = __('Success');
            $class = 'bp-productfeed-executed__status--success';
            $title = '';
        } else {
            $status = __('Not generated');
            $class = 'bp-productfeed-executed__status--empty';
            $title = '';
        }

        $date = $lastExecutedAt !== '' ? $lastExecutedAt : $generatedAt;
        $date = $date !== '' ? $date : (string)__('No execution yet');
        $products = is_numeric($count) ? (string)__('Products: %1', (int)$count) : (string)__('Products: -');
        $titleAttribute = $title !== '' ? ' title="' . $this->escaper->escapeHtmlAttr($title) . '"' : '';

        return sprintf(
            '<div class="bp-productfeed-executed"%s><strong class="%s">%s</strong><span>%s</span><span>%s</span></div>',
            $titleAttribute,
            $this->escaper->escapeHtmlAttr($class),
            $this->escaper->escapeHtml($status),
            $this->escaper->escapeHtml($products),
            $this->escaper->escapeHtml($date)
        );
    }
}
