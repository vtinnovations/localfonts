<?php

declare(strict_types=1);

namespace VTinnovations\LocalFonts\Service;

final class CssImportCleaner
{
    public function __construct(
        private readonly FontStorage $storage,
        private readonly string $projectDir,
        private readonly ContaoFileRegistry $fileRegistry,
    ) {
    }

    public function containsGoogleFonts(string $css): bool
    {
        return str_contains($css, 'fonts.googleapis.com') || str_contains($css, 'fonts.gstatic.com');
    }

    public function clean(string $css): string
    {
        $patterns = [
            '~@import\s+(?:url\()?["\']?[^"\'\);]*(?:fonts\.googleapis\.com|fonts\.gstatic\.com)[^"\'\);]*["\']?\)?\s*;?~i',
            '~<[^>]+(?:fonts\.googleapis\.com|fonts\.gstatic\.com)[^>]*>\s*~i',
        ];

        foreach ($patterns as $pattern) {
            $css = preg_replace($pattern, '', $css) ?? $css;
        }

        return $css;
    }

    public function writeCleanCopy(string $sourceUrl, string $css): string
    {
        $dir = $this->storage->getPublicDir() . '/cleaned-css';

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $this->storage->makeWebAccessible();

        $filename = 'cleaned-' . substr(sha1($sourceUrl), 0, 16) . '.css';
        $target = $dir . '/' . $filename;
        file_put_contents($target, $this->clean($css));
        $this->fileRegistry->registerPublicPath($target, $this->projectDir);

        return '/files/localfonts/cleaned-css/' . $filename;
    }
}

