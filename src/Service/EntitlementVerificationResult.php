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
 * Outcome of a verification step. `reasonCategory` is a safe, generic
 * diagnostic code — never a raw error message, stack trace or packet dump —
 * suitable for operational logs.
 */
final class EntitlementVerificationResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly ?EntitlementRecord $record,
        public readonly ?string $rawBytes,
        public readonly ?string $reasonCategory,
    ) {
    }

    public static function success(EntitlementRecord $record, string $rawBytes): self
    {
        return new self(true, $record, $rawBytes, null);
    }

    public static function failure(string $reasonCategory): self
    {
        return new self(false, null, null, $reasonCategory);
    }
}
