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

namespace VTinnovations\LocalFonts\Config;

/**
 * Pinned public-key ring for the shared v-t.one 2026a signing policy. This is
 * verification material only — no private key ever lives in this class or in
 * any distributed product. Keys are indexed by key_id and constrained by an
 * explicit algorithm allowlist; a production ring must never be empty.
 */
final class TrustedSigningKeys
{
    private const ALGORITHM_ALLOWLIST = ['ed25519'];

    /**
     * @var array<string, array{algorithm: string, public_key_base64: string, purposes: list<string>, activated_at: int, retired_at: int|null}>
     */
    private array $ring;

    /**
     * Structurally invalid material is rejected at construction time rather
     * than at verification time, so a misconfigured ring can never silently
     * degrade into "no usable key". An empty ring is constructible only so
     * negative tests can assert the fail-closed path; use
     * {@see assertProductionReady()} to gate a release artefact.
     *
     * @param array<string, array{algorithm: string, public_key_base64: string, purposes: list<string>, activated_at: int, retired_at: int|null}> $ring
     */
    public function __construct(array $ring = self::DEFAULT_RING)
    {
        foreach ($ring as $keyId => $entry) {
            if (!\is_string($keyId) || '' === $keyId) {
                throw new \InvalidArgumentException('Signing key entries must be indexed by a non-empty key ID.');
            }

            if (!\in_array($entry['algorithm'] ?? null, self::ALGORITHM_ALLOWLIST, true)) {
                throw new \InvalidArgumentException(sprintf('Signing key "%s" declares an unsupported algorithm.', $keyId));
            }

            if ([] === ($entry['purposes'] ?? [])) {
                throw new \InvalidArgumentException(sprintf('Signing key "%s" declares no purpose.', $keyId));
            }

            $decoded = base64_decode((string) ($entry['public_key_base64'] ?? ''), true);

            if (false === $decoded || 32 !== \strlen($decoded)) {
                throw new \InvalidArgumentException(sprintf('Signing key "%s" is not a valid raw 32-byte Ed25519 public key.', $keyId));
            }
        }

        $this->ring = $ring;
    }

    /**
     * Release/configuration gate: a distributable production build must pin at
     * least one usable verification key. Returns the usable key IDs so a build
     * step can record exactly what was shipped.
     *
     * @return list<string>
     */
    public function assertProductionReady(int $now): array
    {
        if ($this->isEmpty()) {
            throw new \RuntimeException('Refusing a production build with an empty signing key ring.');
        }

        $usable = [];

        foreach (['document', 'envelope', 'request'] as $purpose) {
            foreach (array_keys($this->usableForPurpose($purpose, $now)) as $keyId) {
                $usable[$keyId] = true;
            }
        }

        if ([] === $usable) {
            throw new \RuntimeException('Refusing a production build: no pinned signing key is currently usable.');
        }

        return array_keys($usable);
    }

    private const DEFAULT_RING = [
        'vtone-2026a' => [
            'algorithm' => 'ed25519',
            'public_key_base64' => 'qllgm+66FUVBFJ3O68ICFG8b37dR+9jMfr1+4/pSygE=',
            'purposes' => ['document', 'envelope', 'request'],
            'activated_at' => 0,
            'retired_at' => null,
        ],
    ];

    public function isEmpty(): bool
    {
        return [] === $this->ring;
    }

    /**
     * @return array{algorithm: string, public_key_base64: string, purposes: list<string>, activated_at: int, retired_at: int|null}|null
     */
    public function resolve(string $keyId, string $algorithm, string $purpose, int $now): ?array
    {
        $entry = $this->ring[$keyId] ?? null;

        if (null === $entry) {
            return null;
        }

        if (!\in_array($algorithm, self::ALGORITHM_ALLOWLIST, true) || $entry['algorithm'] !== $algorithm) {
            return null;
        }

        if (!\in_array($purpose, $entry['purposes'], true)) {
            return null;
        }

        if ($entry['activated_at'] > $now) {
            return null;
        }

        if (null !== $entry['retired_at'] && $entry['retired_at'] < $now) {
            return null;
        }

        return $entry;
    }

    /**
     * All currently usable entries for a given purpose, used when the wire
     * format (the licence document) does not name a key_id and every usable
     * key must be tried.
     *
     * @return array<string, array{algorithm: string, public_key_base64: string, purposes: list<string>, activated_at: int, retired_at: int|null}>
     */
    public function usableForPurpose(string $purpose, int $now): array
    {
        $usable = [];

        foreach ($this->ring as $keyId => $entry) {
            if (null !== $this->resolve($keyId, $entry['algorithm'], $purpose, $now)) {
                $usable[$keyId] = $entry;
            }
        }

        return $usable;
    }

    /**
     * Decodes and validates the raw verification-key bytes for one ring
     * entry. Returns null (never throws) so callers can fail closed.
     */
    public function rawPublicKeyBytes(array $entry): ?string
    {
        $bytes = base64_decode($entry['public_key_base64'], true);

        if (false === $bytes || 32 !== \strlen($bytes)) {
            return null;
        }

        return $bytes;
    }
}
