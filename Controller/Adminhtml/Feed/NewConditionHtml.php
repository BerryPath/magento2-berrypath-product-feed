<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\CatalogRule\Model\RuleFactory;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Rule\Model\Condition\AbstractCondition;
use Magento\Rule\Model\ConditionFactory;

class NewConditionHtml extends Action implements HttpGetActionInterface, HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'BerryPath_ProductFeed::feeds';

    public function __construct(
        Context $context,
        private readonly RawFactory $rawFactory,
        private readonly ConditionFactory $conditionFactory,
        private readonly RuleFactory $ruleFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): Raw
    {
        $result = $this->rawFactory->create();
        $type = $this->getConditionType();
        if ($type === '' || !$this->isAllowedConditionType($type)) {
            return $result->setContents('');
        }

        try {
            $condition = $this->conditionFactory->create($type);
        } catch (\Throwable) {
            return $result->setContents('');
        }

        $condition
            ->setId((string)$this->getRequest()->getParam('id'))
            ->setType($type)
            ->setRule($this->ruleFactory->create())
            ->setPrefix('conditions');

        $attribute = $this->getConditionAttribute();
        if ($attribute !== '') {
            $condition->setAttribute($attribute);
        }

        if ($condition instanceof AbstractCondition) {
            $condition->setJsFormObject((string)$this->getRequest()->getParam('form'));
            $condition->setFormName((string)$this->getRequest()->getParam('form_namespace'));

            return $result->setContents($condition->asHtmlRecursive());
        }

        return $result->setContents('');
    }

    /**
     * Restrict instantiation to Magento rule condition classes, preventing arbitrary class creation.
     */
    private function isAllowedConditionType(string $type): bool
    {
        $type = ltrim($type, '\\');

        foreach (['Magento\\CatalogRule\\Model\\Rule\\Condition\\', 'Magento\\Rule\\Model\\Condition\\'] as $prefix) {
            if (str_starts_with($type, $prefix) && is_subclass_of($type, AbstractCondition::class)) {
                return true;
            }
        }

        return false;
    }

    private function getConditionType(): string
    {
        $types = $this->getTypeParts();

        return $types[0] ?? '';
    }

    private function getConditionAttribute(): string
    {
        $types = $this->getTypeParts();

        return $types[1] ?? '';
    }

    /**
     * @return array<int, string>
     */
    private function getTypeParts(): array
    {
        return explode('|', str_replace('-', '/', (string)$this->getRequest()->getParam('type', '')));
    }
}
