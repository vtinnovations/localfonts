<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/localfonts
 * @author    V&T Innovations
 * @license   LGPL-3.0-or-later
 * @copyright V&T Innovations 2026
 */

namespace VTinnovations\LocalFonts\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use VTinnovations\LocalFonts\Security\LicenseManager;
use VTinnovations\LocalFonts\Service\FontCrawler;
use VTinnovations\LocalFonts\Service\FontInstaller;

#[AsCommand(name: 'localfonts:scan', description: 'Scans the website for Google Fonts and stores them locally.')]
final class ScanLocalFontsCommand extends Command
{
    public function __construct(
        private readonly FontCrawler $crawler,
        private readonly FontInstaller $installer,
        private readonly LicenseManager $licenseManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'download',
            'd',
            InputOption::VALUE_NONE,
            'Also download the detected fonts and generate the stylesheet (same as the backend button).'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->licenseManager->refreshIfStale($this->licenseManager->getDomain());

        if (!$this->licenseManager->isLicensed()) {
            $output->writeln('<error>This plugin requires a valid V&T Innovations license. Get yours at https://www.v-t.one.</error>');

            return Command::FAILURE;
        }

        $this->crawler->scan();
        $output->writeln('<info>Local Fonts scan finished.</info>');

        if (!$input->getOption('download')) {
            $output->writeln('Run again with --download to store the fonts locally.');

            return Command::SUCCESS;
        }

        $this->installer->install();
        $output->writeln('<info>Fonts downloaded and stylesheet generated.</info>');

        return Command::SUCCESS;
    }
}
