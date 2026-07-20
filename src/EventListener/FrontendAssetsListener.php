<?php

declare(strict_types=1);

namespace VTinnovations\LocalFonts\EventListener;

use Symfony\Component\HttpFoundation\RequestStack;
use VTinnovations\LocalFonts\Security\LicenseGuard;
use VTinnovations\LocalFonts\Service\CssImportCleaner;
use VTinnovations\LocalFonts\Service\FontCrawler;
use VTinnovations\LocalFonts\Service\LocalFontsManager;
use VTinnovations\LocalFonts\Service\StateStore;

final class FrontendAssetsListener
{
    public function __construct(
        private readonly StateStore $stateStore,
        private readonly CssImportCleaner $cssImportCleaner,
        private readonly LicenseGuard $licenseGuard,
        private readonly RequestStack $requestStack,
        private readonly string $projectDir,
    ) {
    }

    public function onModifyFrontendPage(string $buffer, string $template = ''): string
    {
        // Paid-only: without a valid license the page is left untouched.
        if (!$this->licenseGuard->isLicensed()) {
            return $buffer;
        }

        // Leave the crawler's own view of the page alone, see FontCrawler::SCAN_HEADER.
        if (true === $this->requestStack->getCurrentRequest()?->headers->has(FontCrawler::SCAN_HEADER)) {
            return $buffer;
        }

        $state = $this->stateStore->load();

        if (empty($state['settings']['enabled'])) {
            return $buffer;
        }

        if (!empty($state['settings']['removeExternalGoogleFonts'])) {
            $buffer = $this->replaceLinkedStylesheetsWithCleanCopies($buffer);
            $buffer = $this->removeExternalGoogleFonts($buffer);
            $buffer = $this->replaceCleanedCss($buffer, $state);
        }

        $buffer = $this->addActiveMarker($buffer);

        // Manual mode: the operator embeds the stylesheet themselves, so the tag
        // is never added automatically.
        if (LocalFontsManager::MODE_MANUAL === ($state['settings']['injectMode'] ?? LocalFontsManager::MODE_AUTO)) {
            return $buffer;
        }

        if (!$this->localCssExists()) {
            return $buffer;
        }

        if (str_contains($buffer, '/files/localfonts/localfonts.css')) {
            return $buffer;
        }

        $tag = '<link rel="stylesheet" href="/files/localfonts/localfonts.css">';

        if (false !== stripos($buffer, '</head>')) {
            return preg_replace('~</head>~i', $tag . "\n</head>", $buffer, 1) ?? $buffer;
        }

        return $tag . "\n" . $buffer;
    }

    private function addActiveMarker(string $buffer): string
    {
        if (str_contains($buffer, 'Local Fonts active')) {
            return $buffer;
        }

        if (false !== stripos($buffer, '</head>')) {
            return preg_replace('~</head>~i', "<!-- Local Fonts active -->\n</head>", $buffer, 1) ?? $buffer;
        }

        return "<!-- Local Fonts active -->\n" . $buffer;
    }

    private function localCssExists(): bool
    {
        return is_file($this->projectDir . '/files/localfonts/localfonts.css')
            || is_file($this->projectDir . '/public/files/localfonts/localfonts.css');
    }

    private function removeExternalGoogleFonts(string $buffer): string
    {
        $patterns = [
            '~<[^>]+(?:fonts\.googleapis\.com|fonts\.gstatic\.com)[^>]*>\s*~i',
            '~<link\b(?=[^>]*\bhref=["\'](?:https?:)?//fonts\.googleapis\.com/[^"\']+["\'])[^>]*>\s*~i',
            '~<link\b(?=[^>]*\bhref=["\'](?:https?:)?//fonts\.gstatic\.com/[^"\']+["\'])[^>]*>\s*~i',
            '~@import\s+(?:url\()?["\']?[^"\'\);]*(?:fonts\.googleapis\.com|fonts\.gstatic\.com)[^"\'\);]*["\']?\)?\s*;?~i',
            '~https?:?//fonts\.googleapis\.com/[^"\'<>\s)]+~i',
            '~https?:?//fonts\.gstatic\.com/[^"\'<>\s)]+~i',
        ];

        foreach ($patterns as $pattern) {
            $buffer = preg_replace($pattern, '', $buffer) ?? $buffer;
        }

        return $buffer;
    }

    private function replaceLinkedStylesheetsWithCleanCopies(string $buffer): string
    {
        return preg_replace_callback(
            '~<link\b(?=[^>]*\brel=["\'][^"\']*stylesheet[^"\']*["\'])(?=[^>]*\bhref=["\'](?P<href>[^"\']+)["\'])[^>]*>~i',
            function (array $match): string {
                $tag = $match[0];
                $href = html_entity_decode((string) $match['href'], ENT_QUOTES);

                if (str_contains($href, 'fonts.googleapis.com') || str_contains($href, 'fonts.gstatic.com')) {
                    return '';
                }

                $localFile = $this->resolveLocalCssFile($href);

                if (null === $localFile || !is_file($localFile)) {
                    return $tag;
                }

                $css = (string) file_get_contents($localFile);

                if (!$this->cssImportCleaner->containsGoogleFonts($css)) {
                    return $tag;
                }

                $replacement = $this->cssImportCleaner->writeCleanCopy($href, $css);

                return str_replace($match['href'], htmlspecialchars($replacement, ENT_QUOTES), $tag);
            },
            $buffer
        ) ?? $buffer;
    }

    private function resolveLocalCssFile(string $href): ?string
    {
        $path = (string) (parse_url($href, PHP_URL_PATH) ?: $href);
        $path = ltrim($path, '/');

        if ('' === $path || str_contains($path, '..')) {
            return null;
        }

        $candidates = [
            $this->projectDir . '/public/' . $path,
            $this->projectDir . '/web/' . $path,
            $this->projectDir . '/' . $path,
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $state
     */
    private function replaceCleanedCss(string $buffer, array $state): string
    {
        foreach (($state['cleanedCss'] ?? []) as $entry) {
            if (!is_array($entry) || empty($entry['source']) || empty($entry['replacement'])) {
                continue;
            }

            $source = (string) $entry['source'];
            $replacement = (string) $entry['replacement'];
            $path = (string) (parse_url($source, PHP_URL_PATH) ?: '');
            $relativePath = ltrim($path, '/');
            $query = (string) (parse_url($source, PHP_URL_QUERY) ?: '');
            $pathWithQuery = '' !== $query ? $path . '?' . $query : $path;
            $relativePathWithQuery = ltrim($pathWithQuery, '/');

            foreach (array_filter([$source, $pathWithQuery, $relativePathWithQuery, $path, $relativePath]) as $needle) {
                $buffer = str_replace(htmlspecialchars($needle, ENT_QUOTES), htmlspecialchars($replacement, ENT_QUOTES), $buffer);
                $buffer = str_replace($needle, $replacement, $buffer);
            }
        }

        return $buffer;
    }
}
