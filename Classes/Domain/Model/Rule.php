<?php

declare(strict_types=1);

namespace In2code\PowermailCond\Domain\Model;

use In2code\Powermail\Domain\Model\Field;
use In2code\Powermail\Domain\Model\Form;
use In2code\Powermail\Domain\Model\Page;
use In2code\PowermailCond\Domain\Comparator\Comparison;
use In2code\PowermailCond\Event\EvaluateRuleEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Rule extends AbstractEntity
{
    public const OPERATOR_IS_SET = 0;
    public const OPERATOR_NOT_IS_SET = 1;
    public const OPERATOR_CONTAINS_VALUE = 2;
    public const OPERATOR_NOT_CONTAINS_VALUE = 3;
    public const OPERATOR_IS = 4;
    public const OPERATOR_NOT_IS = 5;
    public const OPERATOR_GREATER_THAN = 6;
    public const OPERATOR_LESS_THAN = 7;
    public const OPERATOR_CONTAINS_VALUE_FROM_FIELD = 8;
    public const OPERATOR_NOT_CONTAINS_VALUE_FROM_FIELD = 9;

    /**
     * Operator values from here upwards belong to extensions that add their own operators.
     * They are evaluated by a listener to {@see EvaluateRuleEvent} instead of by {@see Comparison}.
     */
    public const OPERATOR_THIRD_PARTY_OFFSET = 100;

    protected string $title = '';

    protected ?Field $startField = null;

    protected int $ops = self::OPERATOR_IS_SET;

    protected string $condString = '';

    protected ?Field $equalField = null;

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getStartField(): ?Field
    {
        return $this->startField;
    }

    public function setStartField(Field $startField): void
    {
        $this->startField = $startField;
    }

    public function getOps(): int
    {
        return $this->ops;
    }

    public function setOps(int $ops): void
    {
        $this->ops = $ops;
    }

    public function getCondString(): string
    {
        return $this->condString;
    }

    public function setCondString(string $condString): void
    {
        $this->condString = $condString;
    }

    public function getEqualField(): ?Field
    {
        return $this->equalField;
    }

    public function setEqualField(Field $equalField): void
    {
        $this->equalField = $equalField;
    }

    /**
     * @throws Throwable
     */
    public function applies(Form $form): bool
    {
        /** @var Page $page */
        foreach ($form->getPages() as $page) {
            /** @var Field $field */
            foreach ($page->getFields() as $field) {
                if (
                    isset($field, $this->startField)
                    && $field->getUid() === $this->startField->getUid()
                ) {
                    if ($this->ops >= self::OPERATOR_THIRD_PARTY_OFFSET) {
                        if ($this->evaluateThirdPartyOperator($form, $field)) {
                            return true;
                        }
                        continue;
                    }
                    $comparison = new Comparison($this->ops);
                    if ($comparison->evaluate($field, $this->condString, $this->equalField)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Hand an operator this extension does not know about to whoever registered it.
     */
    protected function evaluateThirdPartyOperator(Form $form, Field $startField): bool
    {
        $event = new EvaluateRuleEvent($this, $form, $startField, $this->condString, $this->equalField);
        GeneralUtility::makeInstance(EventDispatcherInterface::class)->dispatch($event);

        return $event->getResult() ?? false;
    }
}
