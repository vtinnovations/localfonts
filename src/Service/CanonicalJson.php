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

/**
 * Deterministic `vt-one/canonical-json-v1` encoding shared by the licence
 * document and integrity-envelope signatures: strip the excluded top-level
 * fields, recursively sort map keys in ascending bytewise order, preserve
 * list order exactly, and encode UTF-8 JSON without pretty printing or
 * escaped slashes/Unicode. Scalar types are never coerced.
 */
final class CanonicalJson
{
    /**
     * @param array<string, mixed> $document
     * @param list<string>         $excludeTopLevelKeys
     */
    public static function encode(array $document, array $excludeTopLevelKeys = ['signature']): string
    {
        foreach ($excludeTopLevelKeys as $key) {
            unset($document[$key]);
        }

        $json = json_encode(self::canonicalizeValue($document), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (false === $json) {
            throw new \RuntimeException('Unable to produce canonical JSON encoding.');
        }

        return $json;
    }

    private static function canonicalizeValue(mixed $value): mixed
    {
        if (!\is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(self::canonicalizeValue(...), $value);
        }

        $keys = array_keys($value);
        usort($keys, static fn (int|string $a, int|string $b): int => strcmp((string) $a, (string) $b));

        $sorted = [];

        foreach ($keys as $key) {
            $sorted[$key] = self::canonicalizeValue($value[$key]);
        }

        return $sorted;
    }
}
