<?php
declare(strict_types=1);

namespace B13\Warmup;

/*
 * This file is part of TYPO3 CMS-based extension "warmup" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */


use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Error\Http\PageNotFoundException;
use TYPO3\CMS\Core\Http\ImmediateResponseException;
use TYPO3\CMS\Core\Http\MiddlewareDispatcher;
use TYPO3\CMS\Core\Http\MiddlewareStackResolver;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Service\DependencyOrderingService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Exception\RequiredArgumentMissingException;
use TYPO3\CMS\Extbase\Service\EnvironmentService;
use TYPO3\CMS\Extbase\SignalSlot\Dispatcher;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Frontend\Http\RequestHandler;

class FrontendRequestBuilder
{
    private $originalUser;

    private $backedUpEnvironment = [];

    private $originalEnvironmentService;

    private function prepare()
    {
        $this->originalUser = $GLOBALS['BE_USER'];
        $this->backupEnvironment();
        $this->initializeEnvironmentForNonCliCall(GeneralUtility::getApplicationContext());

        $this->originalEnvironmentService = GeneralUtility::makeInstance(EnvironmentService::class);
        $environmentService = new class extends EnvironmentService {
            public function isEnvironmentInFrontendMode()
            {
                return true;
            }
            public function isEnvironmentInBackendMode()
            {
                return false;
            }
            public function isEnvironmentInCliMode()
            {
                return false;
            }
        };
        GeneralUtility::setSingletonInstance(EnvironmentService::class, $environmentService);
        GeneralUtility::setSingletonInstance(Dispatcher::class, new Dispatcher());

        $GLOBALS['BE_USER'] = null;
        unset($GLOBALS['TSFE']);
    }

    private function restore()
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_userauth.php']['postUserLookUp']['frontendrequestbuilder'];
        $GLOBALS['BE_USER'] = $this->originalUser;
        GeneralUtility::setSingletonInstance(EnvironmentService::class, $this->originalEnvironmentService);
        unset($GLOBALS['TSFE']);
        $this->restoreEnvironment();
    }


    public function buildRequestForPage(UriInterface $uri, $frontendUserGroups = []): ?ResponseInterface
    {
        $this->prepare();
        $request = new ServerRequest($uri, 'GET');

        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_userauth.php']['postUserLookUp']['frontendrequestbuilder'] = function($parameters, $parentObject) use ($frontendUserGroups) {
            if (!empty($frontendUserGroups) && $parentObject instanceof FrontendUserAuthentication) {
                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                    ->getQueryBuilderForTable('fe_users');
                $parentObject->user = $queryBuilder
                    ->select('*')
                    ->from('fe_users')
                    ->where(
                        $queryBuilder->expr()->eq('usergroup', $queryBuilder->createNamedParameter(implode(',', $frontendUserGroups)))
                    )
                    ->setMaxResults(1)
                    ->execute()
                    ->fetch();
            }
        };

        $response = null;
        try {
            $response = $this->executeFrontendRequest($request);
            #var_dump($response->getBody()->getContents());
        } catch (ImmediateResponseException $e) {
            $response = $e->getResponse();
            #var_dump($response->getBody()->getContents());
            var_dump($response->getReasonPhrase());
        } catch (RequiredArgumentMissingException $e) {
            // @todo: log
        } catch (PageNotFoundException $e) {
            // @todo: log
        } catch (\Throwable $e) {
            var_dump(get_class($e));
        }

        $this->restore();

        return $response;
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function executeFrontendRequest(ServerRequestInterface $request): ResponseInterface
    {
        $dispatcher = $this->buildDispatcher();
        return $dispatcher->handle($request);
    }

    /**
     * @return MiddlewareDispatcher
     * @throws \TYPO3\CMS\Core\Cache\Exception\InvalidDataException
     * @throws \TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException
     * @throws \TYPO3\CMS\Core\Exception
     */
    private function buildDispatcher()
    {
        $requestHandler = GeneralUtility::makeInstance(RequestHandler::class);
        $resolver = new MiddlewareStackResolver(
            GeneralUtility::makeInstance(PackageManager::class),
            GeneralUtility::makeInstance(DependencyOrderingService::class),
            GeneralUtility::makeInstance(CacheManager::class)->getCache('cache_core')
        );
        $middlewares = $resolver->resolve('frontend');
        return new MiddlewareDispatcher($requestHandler, $middlewares);
    }

    private function initializeEnvironmentForNonCliCall(ApplicationContext $applicationContext): void
    {
        Environment::initialize(
            $applicationContext,
            false,
            Environment::isComposerMode(),
            Environment::getProjectPath(),
            Environment::getPublicPath(),
            Environment::getVarPath(),
            Environment::getConfigPath(),
            Environment::getCurrentScript(),
            Environment::isWindows() ? 'WINDOWS' : 'UNIX'
        );
    }

    /**
     * Helper method used in setUp() if $this->backupEnvironment is true
     * to back up current state of the Environment::class
     */
    private function backupEnvironment(): void
    {
        $this->backedUpEnvironment['context'] = Environment::getContext();
        $this->backedUpEnvironment['isCli'] = Environment::isCli();
        $this->backedUpEnvironment['composerMode'] = Environment::isComposerMode();
        $this->backedUpEnvironment['projectPath'] = Environment::getProjectPath();
        $this->backedUpEnvironment['publicPath'] = Environment::getPublicPath();
        $this->backedUpEnvironment['varPath'] = Environment::getVarPath();
        $this->backedUpEnvironment['configPath'] = Environment::getConfigPath();
        $this->backedUpEnvironment['currentScript'] = Environment::getCurrentScript();
        $this->backedUpEnvironment['isOsWindows'] = Environment::isWindows();
    }

    /**
     * Helper method used in tearDown() if $this->backupEnvironment is true
     * to reset state of Environment::class
     */
    private function restoreEnvironment(): void
    {
        Environment::initialize(
            $this->backedUpEnvironment['context'],
            $this->backedUpEnvironment['isCli'],
            $this->backedUpEnvironment['composerMode'],
            $this->backedUpEnvironment['projectPath'],
            $this->backedUpEnvironment['publicPath'],
            $this->backedUpEnvironment['varPath'],
            $this->backedUpEnvironment['configPath'],
            $this->backedUpEnvironment['currentScript'],
            $this->backedUpEnvironment['isOsWindows'] ? 'WINDOWS' : 'UNIX'
        );
    }
}
