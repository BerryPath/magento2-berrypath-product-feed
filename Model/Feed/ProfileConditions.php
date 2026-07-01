<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Model\Feed;

use BerryPath\ProductFeed\Model\Profile;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\CatalogRule\Model\Rule;
use Magento\CatalogRule\Model\RuleFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Rule\Model\Condition\AbstractCondition;

class ProfileConditions
{
    public const FORM_NAME = 'edit_form';

    /**
     * @var array<string, Rule>
     */
    private array $rulesByConditions = [];

    public function __construct(
        private readonly RuleFactory $ruleFactory,
        private readonly Json $serializer
    ) {
    }

    public function createRule(Profile $profile): Rule
    {
        $rule = $this->ruleFactory->create();
        $rule->setId((int)$profile->getId());

        $conditions = trim((string)$profile->getData('conditions_serialized'));
        if ($conditions !== '') {
            $rule->setConditionsSerialized($conditions);
        }

        return $rule;
    }

    public function getConditionsFieldSetId(Profile $profile, string $formName = self::FORM_NAME): string
    {
        return $this->createRule($profile)->getConditionsFieldSetId($formName);
    }

    /**
     * @param array<string, mixed> $postData
     */
    public function serializeFromPost(array $postData, string $currentConditions = ''): string
    {
        $conditions = null;
        if (isset($postData['rule']) && is_array($postData['rule'])) {
            $conditions = $postData['rule']['conditions'] ?? null;
        } elseif (isset($postData['conditions'])) {
            $conditions = $postData['conditions'];
        }

        if (!is_array($conditions)) {
            return $currentConditions;
        }

        $rule = $this->ruleFactory->create();
        $rule->loadPost(['conditions' => $conditions]);
        $conditionsArray = $rule->getConditions()->asArray();

        return $this->hasNestedConditions($conditionsArray)
            ? $this->serializer->serialize($conditionsArray)
            : '';
    }

    public function collectValidatedAttributes(?Profile $profile, ProductCollection $collection): void
    {
        $rule = $this->getValidationRule($profile);
        if ($rule === null) {
            return;
        }

        $rule->setCollectedAttributes([]);
        $rule->getConditions()->collectValidatedAttributes($collection);
    }

    public function validate(?Profile $profile, Product $product): bool
    {
        $rule = $this->getValidationRule($profile);

        return $rule === null || (bool)$rule->validate($product);
    }

    public function setConditionFormName(AbstractCondition $conditions, string $formName, string $jsFormName): void
    {
        $conditions->setFormName($formName);
        $conditions->setJsFormObject($jsFormName);

        if ($conditions->getConditions() && is_array($conditions->getConditions())) {
            foreach ($conditions->getConditions() as $condition) {
                if ($condition instanceof AbstractCondition) {
                    $this->setConditionFormName($condition, $formName, $jsFormName);
                }
            }
        }
    }

    private function getValidationRule(?Profile $profile): ?Rule
    {
        if ($profile === null || !$profile->getId()) {
            return null;
        }

        $conditions = trim((string)$profile->getData('conditions_serialized'));
        if ($conditions === '') {
            return null;
        }

        $cacheKey = sha1($conditions);
        if (!isset($this->rulesByConditions[$cacheKey])) {
            $rule = $this->ruleFactory->create();
            $rule->setConditionsSerialized($conditions);
            $rule->getConditions();
            $this->rulesByConditions[$cacheKey] = $rule;
        }

        return $this->rulesByConditions[$cacheKey];
    }

    /**
     * @param array<string, mixed> $conditions
     */
    private function hasNestedConditions(array $conditions): bool
    {
        return isset($conditions['conditions'])
            && is_array($conditions['conditions'])
            && $conditions['conditions'] !== [];
    }
}
