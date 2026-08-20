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
 * Central immutable evaluation result. Consumers must still enforce
 * entitlements at their own feature boundary — this value is shared input,
 * not itself a global gate.
 */
final class EntitlementEvaluation
{
    public const STATUS_UNLICENSED = 'unlicensed';
    public const STATUS_VALID = 'valid';
    public const STATUS_INVALID = 'invalid';

    private function __construct(
        public readonly bool $active,
        public readonly string $status,
        public readonly ?EntitlementRecord $record,
        public readonly ?string $matchedDomain,
    ) {
    }

    public static function unlicensed(): self
    {
        return new self(false, self::STATUS_UNLICENSED, null, null);
    }

    public static function invalid(EntitlementRecord $record): self
    {
        return new self(false, self::STATUS_INVALID, $record, null);
    }

    public static function valid(EntitlementRecord $record, string $matchedDomain): self
    {
        return new self(true, self::STATUS_VALID, $record, $matchedDomain);
    }
}
