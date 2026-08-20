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
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class FontCrawler
{
    /**
     * Marks the crawler's own requests. With "block external Google Fonts"
     * enabled the front end listeners strip exactly the tags the crawler is
     * looking for, so a rescan would find nothing and wipe the font list. The
     * listeners leave responses to this header untouched.
     */
    public const SCAN_HEADER = 'X-Local-Fonts-Scan';

    public function __construct(
        private readonly PageUrlProvider $pageUrlProvider,
        private readonly GoogleFontsParser $parser,
        private readonly CssImportCleaner $cssImportCleaner,
        private readonly StateStore $stateStore,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function scan(): void
    {
        System::loadLanguageFile('local_fonts');
        $lang = &$GLOBALS['TL_LANG']['local_fonts'];

        $state = $this->stateStore->load();
        $pages = $this->pageUrlProvider->getPublishedUrls();
        $stylesheetUrls = [];
        $cleanedCss = [];
        $messages = [];

        if ([] === $pages) {
            $messages[] = $lang['crawler_no_pages'];
        }

        // Without a request context (cron, contao-console) and without a DNS entry
        // on the root page Contao generates "localhost" URLs, which usually hit the
        // wrong site or a redirect loop instead of the real front end.
        foreach ($pages as $pageUrl) {
            $host = (string) (parse_url($pageUrl, PHP_URL_HOST) ?: '');

            if ('localhost' === $host || '127.0.0.1' === $host) {
                $messages[] = $lang['crawler_localhost_urls'];

                break;
            }
        }

        foreach ($pages as $pageUrl) {
            try {
                $html = $this->httpClient->request('GET', $pageUrl, [
                    'headers' => [
                        'User-Agent' => 'VT Local Fonts Scanner',
                        self::SCAN_HEADER => '1',
                    ],
                ])->getContent(false);

                foreach ($this->parser->extractStylesheetUrls($html) as $stylesheetUrl) {
                    $stylesheetUrls[] = $stylesheetUrl;
                }

                foreach ($this->parser->extractLinkedStylesheets($html) as $linkedStylesheet) {
                    $linkedStylesheetUrl = $this->resolveUrl($pageUrl, $linkedStylesheet);

                    if (null === $linkedStylesheetUrl || str_contains($linkedStylesheetUrl, 'fonts.googleapis.com')) {
                        continue;
                    }

                    try {
                        $linkedCss = $this->httpClient->request('GET', $linkedStylesheetUrl, [
                            'headers' => [
                                'User-Agent' => 'VT Local Fonts Scanner',
                                self::SCAN_HEADER => '1',
                            ],
                        ])->getContent(false);

                        foreach ($this->parser->extractStylesheetUrls($linkedCss) as $stylesheetUrl) {
                            $stylesheetUrls[] = $stylesheetUrl;
                        }

                        if ($this->cssImportCleaner->containsGoogleFonts($linkedCss)) {
                            $cleanedCss[] = [
                                'source' => $linkedStylesheetUrl,
                                'replacement' => $this->cssImportCleaner->writeCleanCopy($linkedStylesheetUrl, $linkedCss),
                            ];
                        }
                    } catch (\Throwable) {
                        $messages[] = sprintf($lang['crawler_stylesheet_unreadable'], $linkedStylesheetUrl);
                    }
                }
            } catch (\Throwable $exception) {
                $messages[] = sprintf($lang['crawler_page_unreadable'], $pageUrl);
            }
        }

        if ([] === $stylesheetUrls && [] !== $pages) {
            $messages[] = $lang['crawler_no_stylesheets_found'];
        }

        $fonts = [];

        foreach (array_values(array_unique($stylesheetUrls)) as $stylesheetUrl) {
            try {
                $css = $this->httpClient->request('GET', $stylesheetUrl, [
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0 AppleWebKit/537.36 Chrome/120 Safari/537.36',
                    ],
                ])->getContent();

                foreach ($this->parser->parseFontCss($css) as $key => $font) {
                    $fonts[$key] ??= [
                        'family' => $font['family'],
                        'variants' => [],
                        'files' => [],
                    ];

                    $fonts[$key]['variants'] = array_values(array_unique(array_merge($fonts[$key]['variants'], $font['variants'])));
                    $fonts[$key]['files'] = array_merge($fonts[$key]['files'], $font['files']);
                }
            } catch (\Throwable $exception) {
                $messages[] = sprintf($lang['crawler_google_fonts_css_unreadable'], $stylesheetUrl);
            }
        }

        if ([] === $fonts && [] !== $stylesheetUrls) {
            $messages[] = $lang['crawler_no_loadable_fonts'];
        }

        foreach ($fonts as $key => $font) {
            $uniqueFiles = [];

            foreach ($font['files'] as $file) {
                $uniqueFiles[$file['url'] . '|' . $file['style'] . '|' . $file['weight']] = $file;
            }

            $fonts[$key]['files'] = array_values($uniqueFiles);
        }

        // The scan only reports what it found. Downloading is a separate, explicit
        // step (FontInstaller) so nothing is written to the site behind the
        // operator's back.
        $state['lastScan'] = date('Y-m-d H:i:s');
        $state['pages'] = $pages;
        $state['detected'] = $fonts;
        $state['cleanedCss'] = $cleanedCss;
        $state['messages'] = $messages;

        $this->stateStore->save($state);
    }

    private function resolveUrl(string $baseUrl, string $url): ?string
    {
        if (str_starts_with($url, 'data:') || str_starts_with($url, 'mailto:') || str_starts_with($url, 'tel:')) {
            return null;
        }

        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        if (preg_match('~^https?://~i', $url)) {
            return $url;
        }

        $base = parse_url($baseUrl);

        if (!isset($base['scheme'], $base['host'])) {
            return null;
        }

        if (str_starts_with($url, '/')) {
            return $base['scheme'] . '://' . $base['host'] . $url;
        }

        $path = isset($base['path']) ? dirname($base['path']) : '';
        $path = '/' === $path ? '' : $path;

        return $base['scheme'] . '://' . $base['host'] . $path . '/' . $url;
    }
}
