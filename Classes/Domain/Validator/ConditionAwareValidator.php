<?php

declare(strict_types=1);

namespace In2code\PowermailCond\Domain\Validator;

use In2code\Powermail\Domain\Model\Field;
use In2code\Powermail\Domain\Model\Page;
use In2code\Powermail\Domain\Validator\InputValidator;
use In2code\Powermail\Utility\ConfigurationUtility;
use In2code\PowermailCond\Domain\Model\Condition;
use Throwable;

/**
 * powermail's input validator, minus the mandatory check for fields a condition has hidden.
 *
 * Replaces In2code\Powermail\Domain\Validator\InputValidator (see ext_localconf.php). It has
 * no constructor of its own: TYPO3 v14 creates Extbase validators without arguments and hands them
 * their options through setOptions() afterwards (ValidatorResolver::createValidator()), and
 * powermail's AbstractValidator constructor takes none either — it only loads the TypoScript
 * settings.
 */
class ConditionAwareValidator extends InputValidator
{
    /**
     * Validate a single field, unless a condition has hidden it
     *
     * @param mixed $value
     * @throws Throwable
     */
    protected function isValidFieldInMandatoryValidation(Field $field, $value): void
    {
        $parentPage = $field->getPage();
        $form = $parentPage?->getForm();
        if ($parentPage === null || $form === null) {
            return;
        }

        // With the element browser a field is not tied to one page, so look for it on each of them.
        $pageUids = [(int)$parentPage->getUid()];
        if (ConfigurationUtility::isReplaceIrreWithElementBrowserActive()) {
            $pageUids = [];
            /** @var Page $page */
            foreach ($form->getPages() as $page) {
                $pageUids[] = (int)$page->getUid();
            }
        }

        $arguments = $GLOBALS['TYPO3_REQUEST']->getAttribute('frontend.user')->getSessionData('tx_powermail_cond');
        $actions = is_array($arguments) ? ($arguments[Condition::INDEX_TODO][(int)$form->getUid()] ?? []) : [];
        foreach ($pageUids as $pageUid) {
            if (($actions[$pageUid][$field->getMarker()][Condition::INDEX_ACTION] ?? null) === Condition::ACTION_HIDE_STRING) {
                return;
            }
        }

        parent::isValidFieldInMandatoryValidation($field, $value);
    }
}
