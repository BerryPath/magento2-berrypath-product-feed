<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Ui\Component\Listing\Column;

use BerryPath\ProductFeed\Model\Feed\FileStorage;
use BerryPath\ProductFeed\Model\ProfileFactory;
use Magento\Framework\Escaper;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class File extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly FileStorage $fileStorage,
        private readonly ProfileFactory $profileFactory,
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
            $profileId = (int)($item['entity_id'] ?? 0);
            if ($profileId <= 0) {
                continue;
            }

            $profile = $this->profileFactory->create();
            $profile->setData($item);
            $profile->setId($profileId);

            if (!$this->fileStorage->exists($profile)) {
                $item[$this->getData('name')] = $this->escaper->escapeHtml(__('Not generated'));
                continue;
            }

            $item[$this->getData('name')] = sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                $this->escaper->escapeUrl($this->fileStorage->getUrl($profile)),
                $this->escaper->escapeHtml(__('Open feed'))
            );
        }
        unset($item);

        return $dataSource;
    }
}
