<?php
defined('TYPO3_MODE') or die();

if (TYPO3_REQUESTTYPE & TYPO3_REQUESTTYPE_CLI) {
    // Register the Warmup request handler
    \TYPO3\CMS\Core\Core\Bootstrap::getInstance()->registerRequestHandlerImplementation(
        \CMSExperts\Warmup\Console\RequestHandler::class
    );
}
