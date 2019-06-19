<?php
declare(strict_types=1);

namespace B13\Warmup\Service;

/*
 * This file is part of TYPO3 CMS-based extension "warmup" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use TYPO3\CMS\Frontend\Page\PageRepository;

/**
 * Warms up the root line for v8.
 */
class RootlineV8Service
{
    /**
     * Runs through all pages and pages_language_overlay records
     * and builds the rootline for each page.
     *
     * The Rootline Utility does the rest by storing this data to the cache_rootline cache
     * if it has not happened yet.
     */
    public function warmupRootline(SymfonyStyle $io)
    {
        $pageRepository = $this->initializePageRepository();

        // fetch all pages which are not deleted and in live workspace
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $statement = $queryBuilder->select('uid')->from('pages')->execute();
        while ($pageRecord = $statement->fetch()) {
            try {
                $this->buildRootLineForPage(
                    (int)$pageRecord['uid'],
                    0,
                    $pageRepository
                );
            } catch (\RuntimeException $e) {
                $io->error('Rootline Cache for Page ID ' . $pageRecord['uid'] . ' could not be warmed up');
            }
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('pages_language_overlay');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $statement = $queryBuilder->select('pid', 'sys_language_uid')->from('pages_language_overlay')->execute();
        while ($pageTranslationRecord = $statement->fetch()) {
            try {
                $this->buildRootLineForPage(
                    (int)$pageTranslationRecord['pid'],
                    (int)$pageTranslationRecord['sys_language_uid'],
                    $pageRepository
                );
            } catch (\RuntimeException $e) {
                $io->error('Rootline Cache for Page ID ' . $pageRecord['uid'] . '  (Language: ' . $pageTranslationRecord['sys_language_uid'] . ') could not be warmed up');
            }
        }
    }

    /**
     * Calls the Rootline Utility and build the rootline for a specific page in a specific language
     *
     * @param int $pageUid
     * @param int $languageUid
     * @param PageRepository $pageRepository
     */
    protected function buildRootLineForPage(int $pageUid, int $languageUid, PageRepository $pageRepository)
    {
        $pageRepository->sys_language_uid = $languageUid;
        GeneralUtility::makeInstance(RootlineUtility::class, $pageUid, '', $pageRepository)->get();
    }

    /**
     * Sets up the PageRepository object with default language and no workspace functionality
     *
     * @return PageRepository
     */
    protected function initializePageRepository(): PageRepository
    {
        $pageRepository = GeneralUtility::makeInstance(PageRepository::class);
        $pageRepository->versioningPreview = false;
        $pageRepository->sys_language_uid = 0;
        $pageRepository->versioningWorkspaceId = 0;
        $pageRepository->init(false);

        // Due to some ugly code in TYPO3 Core, we must fake a TSFE object
        $GLOBALS['TSFE'] = new \stdClass();
        $GLOBALS['TSFE']->sys_page = $pageRepository;
        $GLOBALS['TSFE']->gr_list = '0,-1';
        return $pageRepository;
    }
}
