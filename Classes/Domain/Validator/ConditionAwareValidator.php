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
     * Validate a single field
     *
     * @param mixed $value
     * @throws Throwable
     */
    protected function isValidFieldInMandatoryValidation(Field $field, $value): void
    {
        $arguments = $GLOBALS['TYPO3_REQUEST']->getAttribute('frontend.user')->getSessionData('tx_powermail_cond');
        $parentPage = $field->getPage();
        if ($parentPage === null) {
            return;
        }
        $form = $parentPage->getForm();
        $formUid = $form->getUid();
        $pageUid = $parentPage->getUid();
        $marker = $field->getMarker();

        if (ConfigurationUtility::isReplaceIrreWithElementBrowserActive()) {
            /** @var Page $page */
            foreach ($form->getPages() as $page) {
                /** @var Field $field */
                foreach ($page->getFields() as $field) {
                    if (!empty($arguments[$formUid][$pageUid][$marker][Condition::INDEX_ACTION])) {
                        if ($arguments[$formUid][$pageUid][$marker][Condition::INDEX_ACTION] ===
                            Condition::ACTION_HIDE_STRING) {
                            return;
                        }
                    }
                }
            }
        } else {
            if (!empty($arguments[Condition::INDEX_TODO][$formUid][$pageUid][$marker][Condition::INDEX_ACTION])) {
                if ($arguments[Condition::INDEX_TODO][$formUid][$pageUid][$marker][Condition::INDEX_ACTION] ===
                    Condition::ACTION_HIDE_STRING) {
                    return;
                }
            }
        }
        parent::isValidFieldInMandatoryValidation($field, $value);
    }
}
