<?php
declare(strict_types = 1);
namespace B13\Warmup\Command;

/*
 * This file is part of TYPO3 CMS-based extension "warmup" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use B13\Warmup\Service\PageWarmupService;
use B13\Warmup\Service\RootlineV8Service;
use B13\Warmup\Service\RootlineWarmupService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

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

        if (version_compare(TYPO3_branch, '9.5') === -1) {
            $io->writeln('Warming up the rootline for all pages. If it is there this will go fast.');
            $rootlineService = new RootlineV8Service();
            $errors = $rootlineService->warmUp($io);
        } else {
#            $io->writeln('Warming up the rootline for all pages. If it is there this will go fast.');
#            $rootlineService = new RootlineWarmupService();
#            $errors = $rootlineService->warmUp($io);
            $io->writeln('Calling all pages.');
            $pageService = new PageWarmupService();
            $pageService->warmUp($io);
        }
        if (!empty($errors)) {
            $io->error('Error while warming up the rootline cache.');
            $io->listing($errors);
            return 1;
        }
        $io->success('All done');
        return 0;
    }

    private function getWarmupService(string $type): iterable
    {
        if (version_compare(TYPO3_branch, '9.5') === -1) {
            new RootlineV8Service();
    }
}
