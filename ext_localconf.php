<?php

declare(strict_types=1);

use In2code\Powermail\Domain\Validator\InputValidator;
use In2code\PowermailCond\Backend\Form\Element\Note;
use In2code\PowermailCond\Controller\ConditionController;
use In2code\PowermailCond\Domain\Validator\ConditionAwareValidator;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die('Access denied.');

(static function (): void {
    // Page TSconfig is auto-loaded from Configuration/page.tsconfig since TYPO3 v13.

    ExtensionUtility::configurePlugin(
        'powermail_cond',
        'Pi1',
        [ConditionController::class => 'buildCondition'],
        [ConditionController::class => 'buildCondition'],
    );

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1588153418] = [
        'nodeName' => 'powermailCondShowNote',
        'priority' => 50,
        'class' => Note::class,
    ];

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][InputValidator::class] = [
        'className' => ConditionAwareValidator::class,
    ];
})();
