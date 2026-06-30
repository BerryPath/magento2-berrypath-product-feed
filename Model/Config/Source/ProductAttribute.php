<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Model\Config\Source;

use BerryPath\ProductFeed\Model\Feed\DefaultAttributeCodes;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

class ProductAttribute implements OptionSourceInterface
{
    public function __construct(
        private readonly CollectionFactory $attributeCollectionFactory
    ) {
    }

    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase|string}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        $attributes = $this->attributeCollectionFactory->create();
        $attributes->setOrder('attribute_code', 'ASC');

        foreach ($attributes as $attribute) {
            $attributeCode = (string)$attribute->getAttributeCode();
            if ($attributeCode === '' || DefaultAttributeCodes::contains($attributeCode)) {
                continue;
            }

            $label = trim((string)$attribute->getDefaultFrontendLabel());
            if ($label === '') {
                $label = trim((string)$attribute->getFrontendLabel());
            }
            if ($label === '') {
                $label = $attributeCode;
            }

            $options[] = [
                'value' => $attributeCode,
                'label' => __('%1 (%2)', $label, $attributeCode),
            ];
        }

        usort(
            $options,
            static fn (array $first, array $second): int => strcasecmp((string)$first['label'], (string)$second['label'])
        );

        return $options;
    }
}
