<?php

declare(strict_types=1);

namespace In2code\PowermailCond\Domain\Repository;

use In2code\PowermailCond\Domain\Model\ConditionContainer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\Repository;

class ConditionContainerRepository extends Repository
{
    public function initializeObject(): void
    {
        $querySettings = GeneralUtility::makeInstance(Typo3QuerySettings::class);
        $querySettings->setRespectStoragePage(false);
        $querySettings->setRespectSysLanguage(false);
        $this->setDefaultQuerySettings($querySettings);
    }

    /**
     * The container configured for a form, wherever it is stored.
     *
     * This was findOneByForm() until TYPO3 v14 removed Extbase's magic repository finders.
     */
    public function findOneByFormUid(int $formUid): ?ConditionContainer
    {
        $container = $this->findOneBy(['form' => $formUid]);

        return $container instanceof ConditionContainer ? $container : null;
    }
}
