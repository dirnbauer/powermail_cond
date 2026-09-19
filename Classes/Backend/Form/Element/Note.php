<?php

declare(strict_types=1);

namespace In2code\PowermailCond\Backend\Form\Element;

use In2code\Powermail\Domain\Model\Field;
use In2code\Powermail\Domain\Model\Page;
use Throwable;
use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Show a note in the Condition Container in the Backend if the number of fields in the chosen form exceeds a threshold.
 */
class Note extends AbstractFormElement
{
    protected const FIELD_LIMIT = 30;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * @throws Throwable
     */
    public function render(): array
    {
        $content = '';
        if ($this->formHasTooManyFields()) {
            $languageService = $this->getLanguageService();
            $title = $languageService->sL(
                'LLL:EXT:powermail_cond/Resources/Private/Language/locallang_db.xlf:tx_powermailcond_conditioncontainer.note.title'
            );
            $description = $languageService->sL(
                'LLL:EXT:powermail_cond/Resources/Private/Language/locallang_db.xlf:tx_powermailcond_conditioncontainer.note.description'
            );
            $content = '<div class="alert alert-warning"><strong>' . $title . '</strong>' . $description . '</div>';
        }
        return ['html' => $content];
    }

    /**
     * @throws Throwable
     */
    protected function formHasTooManyFields(): bool
    {
        $formUid = isset($this->data['databaseRow']['form'][0]) ? (int)$this->data['databaseRow']['form'][0] : 0;
        return $formUid > 0 && $this->getNumberOfFormFields($formUid) > self::FIELD_LIMIT;
    }

    /**
     * @throws Throwable
     */
    protected function getNumberOfFormFields(int $formUid): int
    {
        $query = $this->connectionPool->getQueryBuilderForTable(Field::TABLE_NAME);
        $query->getRestrictions()->removeAll();
        $query->count('f.uid')
              ->from(Field::TABLE_NAME, 'f')
              ->leftJoin('f', Page::TABLE_NAME, 'p', 'f.page = p.uid')
              ->leftJoin('p', 'tx_powermail_domain_model_form', 'form', 'p.form = form.uid')
              ->where($query->expr()->eq('form.uid', $query->createNamedParameter($formUid)))
              ->andWhere($query->expr()->eq('f.hidden', $query->createNamedParameter(0)))
              ->andWhere($query->expr()->eq('f.deleted', $query->createNamedParameter(0)))
              ->andWhere($query->expr()->eq('p.hidden', $query->createNamedParameter(0)))
              ->andWhere($query->expr()->eq('p.deleted', $query->createNamedParameter(0)));
        return (int)$query->executeQuery()->fetchOne();
    }
}
