<?php

declare(strict_types=1);

namespace In2code\PowermailCond\ViewHelpers;

use In2code\Powermail\Domain\Model\Form;
use In2code\PowermailCond\Service\ConditionService;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Renders the condition state of a form as JSON, so the frontend JavaScript knows
 * which fields and pages start out hidden.
 */
final class ConditionsViewHelper extends AbstractViewHelper
{
    protected ConditionService $conditionService;

    public function injectConditionService(ConditionService $conditionService): void
    {
        $this->conditionService = $conditionService;
    }

    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('form', Form::class, 'Form', true);
    }

    public function render(): string
    {
        /** @var Form $form */
        $form = $this->arguments['form'];

        $params = ['mail' => ['form' => $form->getUid()]];
        if ($this->renderingContext->hasAttribute(ServerRequestInterface::class)) {
            $request = $this->renderingContext->getAttribute(ServerRequestInterface::class);
            $parsedBody = $request instanceof ServerRequestInterface ? $request->getParsedBody() : null;
            if (is_array($parsedBody) && isset($parsedBody['tx_powermail_pi1'])) {
                $params = $parsedBody['tx_powermail_pi1'];
            }
        }

        return json_encode($this->conditionService->getArguments($params), JSON_THROW_ON_ERROR);
    }
}
