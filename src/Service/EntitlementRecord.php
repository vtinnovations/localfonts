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
 * Immutable representation of one authenticated schema-version-2 licence
 * document. Constructing an instance does not itself imply trust — callers
 * must only build it from bytes that already passed envelope, MD5 and
 * document-signature verification.
 */
final class EntitlementRecord
{
    /**
     * @param list<string> $licenseDomains
     * @param list<string> $licenseFeatures
     */
    private function __construct(
        public readonly string $project,
        public readonly string $projectSlug,
        public readonly string $licenseKey,
        public readonly string $licenseDomain,
        public readonly array $licenseDomains,
        public readonly int $licenseMaxDomains,
        public readonly string $licensePackage,
        public readonly array $licenseFeatures,
        public readonly int $licenseVersion,
        public readonly int $licenseIssuedAt,
        public readonly int $licenseStartsAt,
        public readonly ?int $licenseExpiresAt,
        public readonly bool $licenseLifetime,
        public readonly int $licenseVerifiedAt,
        public readonly bool $freeAvailable,
        public readonly string $validationStatus,
    ) {
    }

    /**
     * @param array<string, mixed> $doc
     */
    public static function fromDecodedDocument(array $doc): self
    {
        return new self(
            project: (string) ($doc['project'] ?? ''),
            projectSlug: (string) ($doc['project_slug'] ?? ''),
            licenseKey: (string) ($doc['license_key'] ?? ''),
            licenseDomain: (string) ($doc['license_domain'] ?? ''),
            licenseDomains: array_values(array_map('strval', (array) ($doc['license_domains'] ?? []))),
            licenseMaxDomains: (int) ($doc['license_max_domains'] ?? 0),
            licensePackage: (string) ($doc['license_package'] ?? ''),
            licenseFeatures: array_values(array_map('strval', (array) ($doc['license_features'] ?? []))),
            licenseVersion: (int) ($doc['license_version'] ?? 0),
            licenseIssuedAt: (int) ($doc['license_issued_at'] ?? 0),
            licenseStartsAt: (int) ($doc['license_starts_at'] ?? 0),
            licenseExpiresAt: isset($doc['license_expires_at']) && null !== $doc['license_expires_at'] ? (int) $doc['license_expires_at'] : null,
            licenseLifetime: true === ($doc['license_lifetime'] ?? false),
            licenseVerifiedAt: (int) ($doc['license_verified_at'] ?? 0),
            freeAvailable: true === ($doc['free_available'] ?? false),
            validationStatus: (string) ($doc['validation_status'] ?? ''),
        );
    }
}
