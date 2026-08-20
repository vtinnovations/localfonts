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

use Contao\CoreBundle\Util\SymlinkUtil;
use Contao\System;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class FontStorage
{
    /** @var list<string> */
    private array $failures = [];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $projectDir,
        private readonly ContaoFileRegistry $fileRegistry,
        private readonly string $uploadPath = 'files',
    ) {
    }

    /**
     * @param array<string,array{family:string, variants:list<string>, files:list<array{url:string, weight:string, style:string, unicodeRange:string}>}> $fonts
     *
     * @return array<string,array{family:string, variants:list<string>, files:int}>
     */
    public function downloadFonts(array $fonts): array
    {
        System::loadLanguageFile('local_fonts');
        $lang = &$GLOBALS['TL_LANG']['local_fonts'];

        $summary = [];
        $this->failures = [];

        foreach ($fonts as $key => $font) {
            $fontDir = $this->getPublicDir() . '/' . $key;

            if (!is_dir($fontDir) && !@mkdir($fontDir, 0775, true) && !is_dir($fontDir)) {
                $this->failures[] = sprintf($lang['storage_dir_create_failed'], $fontDir);

                continue;
            }

            $this->makeWebAccessible();

            $this->fileRegistry->registerPublicPath($this->getPublicDir(), $this->projectDir);
            $this->fileRegistry->registerPublicPath($fontDir, $this->projectDir);

            $count = 0;

            foreach ($font['files'] as $file) {
                $extension = pathinfo(parse_url($file['url'], PHP_URL_PATH) ?: '', PATHINFO_EXTENSION) ?: 'woff2';
                $filename = $key . '-' . $file['style'] . '-' . $file['weight'] . '-' . substr(sha1($file['url']), 0, 8) . '.' . $extension;
                $target = $fontDir . '/' . $filename;

                if (!is_file($target)) {
                    try {
                        $response = $this->httpClient->request('GET', $file['url']);
                        @file_put_contents($target, $response->getContent());
                    } catch (\Throwable $exception) {
                        $this->failures[] = sprintf($lang['storage_download_failed'], $file['url'], $exception->getMessage());

                        continue;
                    }
                }

                // Only count what is really on disk — a failed write must not be
                // reported back to the backend as a downloaded font.
                if (!is_file($target) || 0 === filesize($target)) {
                    $this->failures[] = sprintf($lang['storage_file_write_failed'], $target);

                    continue;
                }

                $this->fileRegistry->registerPublicPath($target, $this->projectDir);

                ++$count;
            }

            if (0 === $count) {
                continue;
            }

            $summary[$key] = [
                'family' => $font['family'],
                'variants' => $font['variants'],
                'files' => $count,
            ];
        }

        return $summary;
    }

    /**
     * Problems from the last downloadFonts() run, surfaced in the backend so a
     * failed write is visible instead of being reported as a success.
     *
     * @return list<string>
     */
    public function getFailures(): array
    {
        return $this->failures;
    }

    /**
     * Everything lives in the Contao upload directory (files/localfonts) so the
     * fonts show up in the file manager and in the DBAFS. Contao does not expose
     * that directory wholesale — it symlinks each folder carrying a .public
     * marker into the web root, which is what makeWebAccessible() takes care of.
     */
    public function getPublicDir(): string
    {
        return $this->projectDir . '/' . trim($this->uploadPath, '/') . '/localfonts';
    }

    /**
     * Writes the .public marker and mirrors Contao's contao:symlinks behaviour
     * for our folder, so /files/localfonts/… is served right after a scan
     * instead of only after the next symlink run.
     */
    public function makeWebAccessible(): void
    {
        System::loadLanguageFile('local_fonts');
        $lang = &$GLOBALS['TL_LANG']['local_fonts'];

        $dir = $this->getPublicDir();

        if (!is_dir($dir)) {
            return;
        }

        $marker = $dir . '/.public';

        if (!is_file($marker)) {
            @file_put_contents($marker, '');
        }

        $relative = trim($this->uploadPath, '/') . '/localfonts';
        $link = $this->getWebDir() . '/' . $relative;

        if (is_link($link) || is_dir($link)) {
            return;
        }

        if (!is_dir(\dirname($link)) && !@mkdir(\dirname($link), 0775, true) && !is_dir(\dirname($link))) {
            $this->failures[] = sprintf($lang['storage_symlink_dir_failed'], \dirname($link));

            return;
        }

        try {
            SymlinkUtil::symlink($relative, $this->getWebDirName() . '/' . $relative, $this->projectDir);
        } catch (\Throwable $exception) {
            $this->failures[] = sprintf(
                $lang['storage_symlink_failed'],
                $link,
                $exception->getMessage()
            );
        }
    }

    private function getWebDirName(): string
    {
        return is_dir($this->projectDir . '/public') ? 'public' : 'web';
    }

    private function getWebDir(): string
    {
        return $this->projectDir . '/' . $this->getWebDirName();
    }
}
