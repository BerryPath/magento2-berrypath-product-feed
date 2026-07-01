<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class Actions extends Column
{
    private const URL_PATH_EDIT = 'berrypath/feed/edit';

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
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
            $profileId = (int)($item['entity_id'] ?? 0);
            if ($profileId <= 0) {
                continue;
            }

            $item[$this->getData('name')] = [
                'edit' => [
                    'href' => $this->urlBuilder->getUrl(self::URL_PATH_EDIT, ['id' => $profileId]),
                    'label' => __('Edit'),
                ],
            ];
        }
        unset($item);

        return $dataSource;
    }
}
