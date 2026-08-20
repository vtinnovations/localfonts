<?php

declare(strict_types=1);

/*
 * Local Fonts
 *
 * Package: vtinnovations/localfonts
 * Copyright: V&T Innovations
 * Licence: LGPL-3.0-or-later
 * Website: https://www.v-t.one
 */

namespace VTinnovations\LocalFonts\Command;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use VTinnovations\LocalFonts\Http\VtOneGateway;
use VTinnovations\LocalFonts\Service\ActivationService;
use VTinnovations\LocalFonts\Service\EntitlementEvaluator;
use VTinnovations\LocalFonts\Service\FontCrawler;
use VTinnovations\LocalFonts\Service\FontInstaller;

// The command name/description are a Symfony attribute, evaluated before any
// request/console context exists, so they cannot come from a language file —
// the one unavoidable exception, matching the AsCommand contract itself.
#[AsCommand(name: 'localfonts:scan', description: 'Scans the website for Google Fonts and stores them locally.')]
final class ScanLocalFontsCommand extends Command
{
    public function __construct(
        private readonly FontCrawler $crawler,
        private readonly FontInstaller $installer,
        private readonly ActivationService $activation,
        private readonly EntitlementEvaluator $evaluator,
        private readonly VtOneGateway $gateway,
        private readonly ContaoFramework $framework,
    ) {
        parent::__construct();
    }

    /**
     * `configure()` runs during command *registration* (e.g. for every
     * command whenever `bin/console list` builds the whole command list),
     * not per invocation — booting the Contao framework there just to
     * translate one `--help` line would initialize it for unrelated
     * commands too. Console metadata stays in English, same as the
     * `#[AsCommand]` attribute above.
     */
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
        $this->framework->initialize();
        System::loadLanguageFile('local_fonts');
        $lang = &$GLOBALS['TL_LANG']['local_fonts'];

        $this->activation->refreshIfStale();
        $evaluation = $this->evaluator->evaluate();

        if (!$evaluation->active) {
            $output->writeln('<error>' . \sprintf($lang['cli_requires_license'], 'https://www.v-t.one') . '</error>');

            return Command::FAILURE;
        }

        // CLI/worker context: use the persisted authenticated matched domain, never an ambient header.
        if (null !== $evaluation->matchedDomain) {
            $this->gateway->sendInvocationSignal($evaluation->matchedDomain);
        }

        $this->crawler->scan();
        $output->writeln('<info>' . $lang['cli_scan_finished'] . '</info>');

        if (!$input->getOption('download')) {
            $output->writeln($lang['cli_run_with_download']);

            return Command::SUCCESS;
        }

        $this->installer->install();
        $output->writeln('<info>' . $lang['cli_fonts_installed'] . '</info>');

        return Command::SUCCESS;
    }
}
