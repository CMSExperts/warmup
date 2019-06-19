<?php
declare(strict_types=1);
namespace B13\Warmup\Authentication;

/*
 * This file is part of TYPO3 CMS-based extension "warmup" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use TYPO3\CMS\Core\Authentication\AbstractAuthenticationService;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Magic logic to add user groups injected into $this->>info['alwaysActiveGroups']
 */
class FrontendUserGroupInjector extends AbstractAuthenticationService
{
    /**
     * @param $user
     * @param $knownGroups
     * @return array
     */
    public function getGroups($user, $knownGroups)
    {
        $groupIds = $this->info['alwaysActiveGroups'] ?? [];
        if (!empty($groupIds)) {
            // Also request a user
            // @todo: should fetch the whole record, probably in a separate hook
            if ($this->info['requestUser'] ?? false) {
                $this->pObj->user['uid'] = (int)$this->info['requestUser'];
            }
            return $this->fetchGroupsFromDatabase($groupIds);
        }
        $this->logger->warning(self::class . ' was activated, but no user groups were set');
        return [];
    }

    private function fetchGroupsFromDatabase(array $groupUids): array
    {
        $groupRecords = [];
        $this->logger->debug('Get usergroups with id: ' . implode(',', $groupUids));
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('fe_groups');

        $res = $queryBuilder->select('*')
            ->from('fe_groups')
            ->where(
                $queryBuilder->expr()->in(
                    'uid',
                    $queryBuilder->createNamedParameter($groupUids, Connection::PARAM_INT_ARRAY)
                )
            )
            ->execute();

        while ($row = $res->fetch()) {
            $groupRecords[$row['uid']] = $row;
        }
        return $groupRecords;
    }
}
