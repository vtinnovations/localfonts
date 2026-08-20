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

namespace VTinnovations\LocalFonts\Service;

use Contao\System;

/**
 * Second step of the workflow: takes what {@see FontCrawler} detected and
 * actually downloads the font files plus the generated stylesheet. Kept apart
 * from the scan so the operator decides when files are written, and so a scan
 * can be repeated without touching what is already installed.
 */
final class FontInstaller
{
    public function __construct(
        private readonly FontStorage $storage,
        private readonly CssGenerator $cssGenerator,
        private readonly StateStore $stateStore,
    ) {
    }

    public function install(): void
    {
        System::loadLanguageFile('local_fonts');
        $lang = &$GLOBALS['TL_LANG']['local_fonts'];

        $state = $this->stateStore->load();
        $detected = $state['detected'] ?? [];
        $messages = [];

        if ([] === $detected) {
            $state['messages'] = [$lang['installer_no_detected_fonts']];
            $this->stateStore->save($state);

            return;
        }

        try {
            $summary = $this->storage->downloadFonts($detected);

            foreach ($this->storage->getFailures() as $failure) {
                $messages[] = $failure;
            }

            $this->cssGenerator->generate($detected, (string) ($state['settings']['fontDisplay'] ?? 'swap'));
        } catch (\Throwable $exception) {
            $summary = [];
            $messages[] = sprintf($lang['installer_save_failed'], $exception->getMessage());
        }

        $state['fonts'] = array_values($summary);
        $state['lastDownload'] = [] !== $summary ? date('Y-m-d H:i:s') : ($state['lastDownload'] ?? null);
        $state['messages'] = $messages;

        $this->stateStore->save($state);
    }

    /**
     * The generated @font-face rules, for operators who embed the CSS by hand
     * instead of letting the bundle inject the stylesheet.
     */
    public function getGeneratedCss(): string
    {
        $file = $this->storage->getPublicDir() . '/localfonts.css';

        return is_file($file) ? (string) file_get_contents($file) : '';
    }

    /**
     * Removes the generated stylesheet and every downloaded font file, so the
     * operator can go back to the original state.
     */
    public function remove(): void
    {
        System::loadLanguageFile('local_fonts');

        $dir = $this->storage->getPublicDir();

        if (is_dir($dir)) {
            $this->deleteRecursive($dir);
        }

        $state = $this->stateStore->load();
        $state['fonts'] = [];
        $state['lastDownload'] = null;
        $state['messages'] = [$GLOBALS['TL_LANG']['local_fonts']['installer_removed']];

        $this->stateStore->save($state);
    }

    private function deleteRecursive(string $dir): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($dir);
    }
}
