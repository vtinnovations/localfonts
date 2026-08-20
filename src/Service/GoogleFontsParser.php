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

final class GoogleFontsParser
{
    /**
     * @return list<string>
     */
    public function extractStylesheetUrls(string $html): array
    {
        preg_match_all('~https://fonts\.googleapis\.com/css2?[^"\'<\s)]+~i', $html, $matches);

        return array_values(array_unique(array_map('html_entity_decode', $matches[0] ?? [])));
    }

    /**
     * @return list<string>
     */
    public function extractLinkedStylesheets(string $html): array
    {
        preg_match_all('~<link[^>]+(?:rel=["\'][^"\']*stylesheet[^"\']*["\'][^>]+href=["\'](?P<href1>[^"\']+)["\']|href=["\'](?P<href2>[^"\']+)["\'][^>]+rel=["\'][^"\']*stylesheet[^"\']*["\'])[^>]*>~i', $html, $matches);

        $hrefs = [];

        foreach (($matches['href1'] ?? []) as $href) {
            if ('' !== $href) {
                $hrefs[] = html_entity_decode($href);
            }
        }

        foreach (($matches['href2'] ?? []) as $href) {
            if ('' !== $href) {
                $hrefs[] = html_entity_decode($href);
            }
        }

        return array_values(array_unique($hrefs));
    }

    /**
     * @return array<string,array{family:string, variants:list<string>, files:list<array{url:string, weight:string, style:string, unicodeRange:string}>}>
     */
    public function parseFontCss(string $css): array
    {
        $fonts = [];

        preg_match_all('~@font-face\s*\{(?P<body>.*?)\}~is', $css, $blocks);

        foreach ($blocks['body'] ?? [] as $body) {
            $family = $this->extractProperty($body, 'font-family');
            $style = $this->extractProperty($body, 'font-style') ?: 'normal';
            $weight = $this->extractProperty($body, 'font-weight') ?: '400';
            $url = $this->extractFontUrl($body);

            if (null === $family || null === $url) {
                continue;
            }

            $family = trim($family, '\'" ');
            $key = strtolower(preg_replace('~[^a-z0-9]+~i', '-', $family) ?? $family);
            $variant = $style . '-' . $weight;

            $fonts[$key] ??= [
                'family' => $family,
                'variants' => [],
                'files' => [],
            ];

            if (!in_array($variant, $fonts[$key]['variants'], true)) {
                $fonts[$key]['variants'][] = $variant;
            }

            $fonts[$key]['files'][] = [
                'url' => $url,
                'weight' => $weight,
                'style' => $style,
                // Google ships one @font-face per subset, distinguished solely by
                // unicode-range. Dropping it would leave several identical rules
                // per weight and the browser would simply use the last one — which
                // may well be the Vietnamese or Cyrillic subset.
                'unicodeRange' => (string) ($this->extractProperty($body, 'unicode-range') ?? ''),
            ];
        }

        return $fonts;
    }

    private function extractProperty(string $body, string $property): ?string
    {
        if (!preg_match('~' . preg_quote($property, '~') . '\s*:\s*([^;]+)~i', $body, $match)) {
            return null;
        }

        return trim($match[1]);
    }

    private function extractFontUrl(string $body): ?string
    {
        if (!preg_match('~url\((?P<quote>[\'"]?)(?P<url>https://fonts\.gstatic\.com/[^)\'"]+)(?P=quote)\)~i', $body, $match)) {
            return null;
        }

        return $match['url'];
    }
}
