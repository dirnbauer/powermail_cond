<?php

declare(strict_types=1);

namespace In2code\PowermailCond\Event;

use In2code\Powermail\Domain\Model\Field;
use In2code\Powermail\Domain\Model\Form;
use In2code\PowermailCond\Domain\Model\Rule;

/**
 * Dispatched for a rule whose operator is not one of the built-in ones.
 *
 * Operator values from {@see Rule::OPERATOR_THIRD_PARTY_OFFSET} upwards are reserved for
 * extensions that add their own operators. Such an extension registers its operator in the
 * TCA of tx_powermailcond_domain_model_rule and listens to this event to evaluate it.
 *
 * A listener that recognises the operator calls {@see self::setResult()}. A rule whose
 * operator no listener recognises never applies.
 */
final class EvaluateRuleEvent
{
    private ?bool $result = null;

    public function __construct(
        private readonly Rule $rule,
        private readonly Form $form,
        private readonly Field $startField,
        private readonly string $valueToMatch,
        private readonly ?Field $equalField,
    ) {
    }

    public function getRule(): Rule
    {
        return $this->rule;
    }

    public function getOperation(): int
    {
        return $this->rule->getOps();
    }

    /**
     * The whole form, with every field already carrying the value the visitor submitted.
     */
    public function getForm(): Form
    {
        return $this->form;
    }

    public function getStartField(): Field
    {
        return $this->startField;
    }

    public function getValueToMatch(): string
    {
        return $this->valueToMatch;
    }

    public function getEqualField(): ?Field
    {
        return $this->equalField;
    }

    public function getResult(): ?bool
    {
        return $this->result;
    }

    public function setResult(?bool $result): void
    {
        $this->result = $result;
    }

    public function isHandled(): bool
    {
        return $this->result !== null;
    }
}
