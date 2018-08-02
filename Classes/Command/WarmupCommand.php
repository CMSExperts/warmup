<?php
declare(strict_types = 1);
namespace CMSExperts\Warmup\Command;

/*
 * This file is part of the TYPO3 Warmup Extension.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use TYPO3\CMS\Frontend\Page\PageRepository;

/**
 * Called via cache:warmup
 */
class WarmupCommand extends Command
{
    /**
     * Configure the command by defining the name, options and arguments
     */
    public function configure()
    {
        $this->setDescription('Warms up some basic caches for frontend rendering.');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Welcome to the Cache Warmup');

        $io->writeln('Warming up the rootline for all pages. If it is there this will go fast.');
        $errors = $this->warmupRootline($io);
        if (!empty($errors)) {
            $io->error('Error while warming up the rootline cache.');
            $io->listing($errors);
            return 1;
        }
        $io->writeln('All done');
        return 0;
    }

    /**
     * Runs through all pages and pages_language_overlay records
     * and builds the rootline for each page.
     *
     * The Rootline Utility does the rest by storing this data to the cache_rootline cache
     * if it has not happened yet.
     *
     * @param SymfonyStyle $io
     * @return array the page Ids with errors
     */
    protected function warmupRootline(SymfonyStyle $io): array
    {
        $erroredPageIds = [];
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
                $erroredPageIds[] = 'Page ID: ' . $pageRecord['uid'];
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
                $erroredPageIds[] = 'Page ID: ' . $pageTranslationRecord['pid'] . ' (Language: ' . $pageTranslationRecord['sys_language_uid'] . ')';
            }
        }

        return $erroredPageIds;
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