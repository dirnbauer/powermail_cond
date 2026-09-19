<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'tx_powermailcond_domain_model_conditioncontainer' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:powermail_cond/Resources/Public/Icons/tx_powermailcond_domain_model_conditioncontainer.svg',
    ],
    'tx_powermailcond_domain_model_condition' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:powermail_cond/Resources/Public/Icons/tx_powermailcond_domain_model_condition.svg',
    ],
    'tx_powermailcond_domain_model_rule' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:powermail_cond/Resources/Public/Icons/tx_powermailcond_domain_model_rule.svg',
    ],
];
