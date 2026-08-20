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
 * Outcome of authenticating one inbound updater request.
 */
final class UpdaterAuthResult
{
    /**
     * @param array<string, mixed>|null $body
     */
    private function __construct(
        public readonly string $outcome,
        public readonly int $httpStatus,
        public readonly ?string $reasonCategory,
        public readonly ?string $requestId,
        public readonly ?string $nonce,
        public readonly ?string $bodySha256,
        public readonly ?array $body,
        public readonly ?int $alreadyProcessedVersion,
    ) {
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function accepted(string $requestId, string $nonce, string $bodySha256, array $body): self
    {
        return new self('accepted', 200, null, $requestId, $nonce, $bodySha256, $body, null);
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function alreadyProcessed(int $version, string $requestId, array $body): self
    {
        return new self('already_processed', 200, null, $requestId, null, null, $body, $version);
    }

    public static function rejected(string $reasonCategory, int $httpStatus): self
    {
        return new self('rejected', $httpStatus, $reasonCategory, null, null, null, null, null);
    }
}
