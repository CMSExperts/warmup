<?php

use B13\Warmup\Authentication\FrontendUserGroupInjector;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

ExtensionManagementUtility::addService(
    'warmup',
    'auth',
    FrontendUserGroupInjector::class,
    [
        'title' => 'Add Frontend Groups based on CLI Request Builder',
        'description' => 'Adds frontend usergroups by verifying data from the Frontend Request Builder.',
        'subtype' => 'getGroupsFE',
        'available' => false,
        'priority' => 90,
        'quality' => 90,
        'className' => FrontendUserGroupInjector::class,
    ]
);
