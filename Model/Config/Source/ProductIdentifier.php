<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Model\Config\Source;

use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

class ProductIdentifier implements OptionSourceInterface
{
    public function __construct(
        private readonly CollectionFactory $attributeCollectionFactory
    ) {
    }

    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        $attributeOptions = [];

        $attributes = $this->attributeCollectionFactory->create();
        $attributes->setOrder('attribute_code', 'ASC');

        foreach ($attributes as $attribute) {
            $attributeCode = (string)$attribute->getAttributeCode();
            if ($attributeCode === '' || $attributeCode === 'entity_id') {
                continue;
            }

            $label = trim((string)$attribute->getDefaultFrontendLabel());
            if ($label === '') {
                $label = trim((string)$attribute->getFrontendLabel());
            }
            if ($label === '') {
                $label = $attributeCode;
            }

            $attributeOptions[] = [
                'value' => $attributeCode,
                'label' => __('%1 (%2)', $label, $attributeCode),
            ];
        }

        usort(
            $attributeOptions,
            static fn (array $first, array $second): int => strcasecmp((string)$first['label'], (string)$second['label'])
        );

        return array_merge(
            [['value' => 'entity_id', 'label' => __('Product ID')]],
            $attributeOptions
        );
    }
}
