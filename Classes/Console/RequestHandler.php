<?php
namespace CMSExperts\Warmup\Console;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use TYPO3\CMS\Core\Console\RequestHandlerInterface;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Database\DatabaseConnection;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use TYPO3\CMS\Frontend\Page\PageRepository;

/**
 * Can be run via typo3/cli_dispatch.phpsh cache:warmup
 */
class RequestHandler implements RequestHandlerInterface
{
    /**
     * Instance of the current TYPO3 bootstrap
     * @var Bootstrap
     */
    protected $bootstrap;

    /**
     * Constructor handing over the bootstrap
     *
     * @param Bootstrap $bootstrap
     */
    public function __construct(Bootstrap $bootstrap)
    {
        $this->bootstrap = $bootstrap;
    }

    /**
     * Handles any commandline request
     *
     * @param InputInterface $input
     * @return void
     */
    public function handleRequest(InputInterface $input)
    {
        $this->bootstrap->loadExtensionTables();
        // Make sure output is not buffered, so command-line output and interaction can take place
        GeneralUtility::flushOutputBuffers();

        /** @var ConsoleOutput $output */
        $output = GeneralUtility::makeInstance(ConsoleOutput::class);
        $output->writeln('Welcome to the Cache Warmup');

        $exitCode = 0;

        try {
            $output->writeln('Warming up the rootline for all pages. If it is there this will go fast.');
            $this->warmupRootline();
        } catch (\RuntimeException $e) {
            $output->write('Error while warming up the rootline cache');
            $exitCode = 1;
        }

        $output->write('All done');
        exit($exitCode);
    }

    /**
     * This request handler can handle any CLI request.
     *
     * @param InputInterface $input
     * @return bool Always TRUE
     */
    public function canHandleRequest(InputInterface $input)
    {
        return $input->getFirstArgument() === 'cache:warmup';
    }

    /**
     * Returns the priority - how eager the handler is to actually handle the request.
     *
     * @return int The priority of the request handler.
     */
    public function getPriority()
    {
        return 80;
    }

    /**
     * Runs through all pages and pages_language_overlay records
     * and builds the rootline for each page.
     *
     * The Rootline Utility does the rest by storing this data to the cache_rootline cache
     * if it has not happened yet.
     */
    protected function warmupRootline()
    {
        $pageRepository = $this->initializePageRepository();

        // fetch all pages which are not deleted and in live workspace
        $pageRecords = $this->getDatabaseConnection()->exec_SELECTgetRows(
            'uid',
            'pages',
            'deleted=0'
        );
        foreach ($pageRecords as $pageRecord) {
            $this->buildRootlineForPage(
                $pageRecord['uid'],
                0,
                $pageRepository
            );
        }

        $pageTranslations = $this->getDatabaseConnection()->exec_SELECTgetRows(
            'pid, sys_language_uid',
            'pages_language_overlay',
            'deleted=0'
        );
        foreach ($pageTranslations as $pageTranslationRecord) {
            $this->buildRootlineForPage(
                $pageTranslationRecord['pid'],
                $pageTranslationRecord['sys_language_uid'],
                $pageRepository
            );
        }
    }

    /**
     * Calls the Rootline Utility and build the rootline for a specific page in a specific language
     *
     * @param int $pageUid
     * @param int $languageUid
     * @param PageRepository $pageRepository
     */
    protected function buildRootlineForPage($pageUid, $languageUid, PageRepository $pageRepository)
    {
        /** @var RootlineUtility $rootlineUtility */
        $pageRepository->sys_language_uid = (int)$languageUid;
        $rootlineUtility = GeneralUtility::makeInstance(RootlineUtility::class, $pageUid, '', $pageRepository);
        $rootlineUtility->get();
    }

    /**
     * Sets up the PageRepository object
     *
     * @return PageRepository
     */
    protected function initializePageRepository()
    {
        /** @var PageRepository $pageRepository */
        $pageRepository = GeneralUtility::makeInstance(PageRepository::class);
        $pageRepository->versioningPreview = false;
        $pageRepository->sys_language_uid = 0;
        $pageRepository->versioningWorkspaceId = 0;
        $pageRepository->init(false);
        return $pageRepository;
    }

    /**
     * @return DatabaseConnection
     */
    protected function getDatabaseConnection()
    {
        return $GLOBALS['TYPO3_DB'];
    }
}
