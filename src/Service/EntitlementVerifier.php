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

use VTinnovations\LocalFonts\Config\ProductProfile;
use VTinnovations\LocalFonts\Config\TrustedSigningKeys;

/**
 * Canonical-signature and exact-byte trust checks for one licence package.
 * Deliberately does not touch persistence, transport or domain-inventory
 * resolution — those live in {@see EntitlementStore}, {@see VTinnovations\LocalFonts\Http\VtOneGateway}
 * and {@see DomainInventory}.
 */
final class EntitlementVerifier
{
    private const MAX_PAYLOAD_BYTES = 65536;

    public function __construct(
        private readonly TrustedSigningKeys $keys,
        private readonly Ed25519Signatures $signatures,
    ) {
    }

    /**
     * Opens and authenticates a signed integrity envelope plus its Base64
     * payload: envelope signature, exact-byte MD5, document signature and
     * the fixed project/schema/date/package shape. Domain binding is a
     * separate step because it depends on request-specific context.
     *
     * @param array<string, mixed> $envelope
     */
    public function openEnvelope(array $envelope, string $payloadB64, int $now): EntitlementVerificationResult
    {
        if (
            !isset($envelope['project'], $envelope['project_slug'], $envelope['license_version'], $envelope['license_md5'], $envelope['generated_at'], $envelope['key_id'], $envelope['signature_algorithm'], $envelope['signature'])
            || !\is_string($envelope['project'])
            || !\is_string($envelope['project_slug'])
            || !\is_string($envelope['license_md5'])
            || !\is_string($envelope['key_id'])
            || !\is_string($envelope['signature_algorithm'])
            || !\is_string($envelope['signature'])
        ) {
            return EntitlementVerificationResult::failure('malformed_envelope');
        }

        if (ProductProfile::PROJECT !== $envelope['project'] || ProductProfile::PROJECT_SLUG !== $envelope['project_slug']) {
            return EntitlementVerificationResult::failure('project_mismatch');
        }

        if ($this->keys->isEmpty()) {
            return EntitlementVerificationResult::failure('signing_key_store_empty');
        }

        $keyEntry = $this->keys->resolve((string) $envelope['key_id'], (string) $envelope['signature_algorithm'], 'envelope', $now);

        if (null === $keyEntry) {
            return EntitlementVerificationResult::failure('unknown_signing_key');
        }

        $publicKeyBytes = $this->keys->rawPublicKeyBytes($keyEntry);

        if (null === $publicKeyBytes || !$this->signatures->isSupported()) {
            return EntitlementVerificationResult::failure('signing_key_store_empty');
        }

        $envelopeMessage = CanonicalJson::encode($envelope, ['signature']);

        if (!$this->signatures->verifyDetached($envelopeMessage, (string) $envelope['signature'], $publicKeyBytes)) {
            return EntitlementVerificationResult::failure('envelope_signature_invalid');
        }

        if (\strlen($payloadB64) > (int) (self::MAX_PAYLOAD_BYTES * 1.4)) {
            return EntitlementVerificationResult::failure('payload_too_large');
        }

        $rawBytes = base64_decode($payloadB64, true);

        if (false === $rawBytes || '' === $rawBytes || \strlen($rawBytes) > self::MAX_PAYLOAD_BYTES) {
            return EntitlementVerificationResult::failure('invalid_payload_encoding');
        }

        if (!hash_equals(strtolower((string) $envelope['license_md5']), md5($rawBytes))) {
            return EntitlementVerificationResult::failure('integrity_mismatch');
        }

        $doc = json_decode($rawBytes, true);

        if (!\is_array($doc)) {
            return EntitlementVerificationResult::failure('invalid_document_json');
        }

        if (!isset($doc['signature']) || !\is_string($doc['signature'])) {
            return EntitlementVerificationResult::failure('malformed_document');
        }

        $documentMessage = CanonicalJson::encode($doc, ['signature']);
        $documentVerified = false;

        foreach ($this->keys->usableForPurpose('document', $now) as $entry) {
            $documentKeyBytes = $this->keys->rawPublicKeyBytes($entry);

            if (null !== $documentKeyBytes && $this->signatures->verifyDetached($documentMessage, (string) $doc['signature'], $documentKeyBytes)) {
                $documentVerified = true;

                break;
            }
        }

        if (!$documentVerified) {
            return EntitlementVerificationResult::failure('document_signature_invalid');
        }

        $shapeError = $this->validateDocumentShape($doc, (int) $envelope['license_version']);

        if (null !== $shapeError) {
            return EntitlementVerificationResult::failure($shapeError);
        }

        return EntitlementVerificationResult::success(EntitlementRecord::fromDecodedDocument($doc), $rawBytes);
    }

    /**
     * Signed exact-host binding: the operation domain must equal
     * `license_domain`, belong to `license_domains`, and the trusted
     * configured inventory must intersect that same signed set.
     *
     * @param list<string> $trustedInventory
     */
    public function checkDomainBinding(EntitlementRecord $record, string $expectedDomain, array $trustedInventory): ?string
    {
        if ('' === $expectedDomain || $record->licenseDomain !== $expectedDomain) {
            return 'domain_request_mismatch';
        }

        if (!\in_array($record->licenseDomain, $record->licenseDomains, true)) {
            return 'domain_not_bound';
        }

        if (null === $this->resolveMatchedDomain($record, $trustedInventory, null)) {
            return 'domain_inventory_no_intersection';
        }

        return null;
    }

    /**
     * The deterministic authenticated host for this installation: the exact
     * intersection of the trusted configured inventory and the signed
     * `license_domains`. The current trusted host wins when it is itself a
     * member of that intersection, otherwise the lowest-sorted member is
     * used so background work and the session signal stay stable regardless
     * of which host served the request. Null means this installation is not
     * authorized at all.
     *
     * @param list<string> $trustedInventory
     */
    public function resolveMatchedDomain(EntitlementRecord $record, array $trustedInventory, ?string $currentHost): ?string
    {
        $intersection = array_values(array_intersect($trustedInventory, $record->licenseDomains));

        if ([] === $intersection) {
            return null;
        }

        if (null !== $currentHost && \in_array($currentHost, $intersection, true)) {
            return $currentHost;
        }

        sort($intersection, SORT_STRING);

        return $intersection[0];
    }

    /**
     * Applies the mandatory Lifetime Free entitlement contract: only the
     * approved Free package, with a signed lifetime flag and null expiry,
     * and only once its signed start instant has actually passed.
     */
    public function checkLifetimeFreeModel(EntitlementRecord $record, int $now): ?string
    {
        if (ProductProfile::ACCEPTED_PACKAGE !== $record->licensePackage) {
            return 'package_not_accepted';
        }

        if (!$record->licenseLifetime || null !== $record->licenseExpiresAt) {
            return 'not_lifetime_package';
        }

        if ('valid' !== $record->validationStatus) {
            return 'validation_status_not_valid';
        }

        if ($record->licenseStartsAt > $now) {
            return 'not_yet_valid';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $doc
     */
    private function validateDocumentShape(array $doc, int $envelopeVersion): ?string
    {
        if (2 !== (int) ($doc['schema_version'] ?? 0)) {
            return 'unsupported_schema_version';
        }

        if (ProductProfile::PROJECT !== ($doc['project'] ?? null) || ProductProfile::PROJECT_SLUG !== ($doc['project_slug'] ?? null)) {
            return 'project_mismatch';
        }

        if (!\is_int($doc['license_version'] ?? null) || (int) $doc['license_version'] !== $envelopeVersion) {
            return 'version_mismatch';
        }

        if (!\is_string($doc['license_domain'] ?? null) || '' === $doc['license_domain']) {
            return 'malformed_domain';
        }

        // A signed record with no key could never satisfy refresh or the
        // mandatory session-entry signal, both of which read it from here.
        if (!\is_string($doc['license_key'] ?? null) || '' === trim((string) $doc['license_key'])) {
            return 'malformed_key';
        }

        if (!\is_bool($doc['license_lifetime'] ?? null) || !\is_bool($doc['free_available'] ?? null)) {
            return 'malformed_flags';
        }

        $domains = $doc['license_domains'] ?? null;

        if (!\is_array($domains) || [] === $domains || !array_is_list($domains)) {
            return 'malformed_domain_list';
        }

        $normalized = array_map('strval', $domains);

        if ($normalized !== array_unique($normalized)) {
            return 'duplicate_domains';
        }

        $sorted = $normalized;
        sort($sorted, SORT_STRING);

        if ($sorted !== $normalized) {
            return 'unsorted_domains';
        }

        foreach ($normalized as $hostname) {
            if (!$this->isCanonicalHostname($hostname)) {
                return 'invalid_domain_entry';
            }
        }

        if (!\is_int($doc['license_max_domains'] ?? null) || (int) $doc['license_max_domains'] < 1) {
            return 'invalid_max_domains';
        }

        if (!\is_string($doc['license_package'] ?? null) || '' === $doc['license_package']) {
            return 'malformed_package';
        }

        if (!\is_int($doc['license_issued_at'] ?? null) || !\is_int($doc['license_starts_at'] ?? null)) {
            return 'malformed_dates';
        }

        $lifetime = true === ($doc['license_lifetime'] ?? null);
        $expiresAt = $doc['license_expires_at'] ?? null;

        if ($lifetime) {
            if (null !== $expiresAt) {
                return 'lifetime_with_expiry';
            }
        } else {
            if (!\is_int($expiresAt) || $expiresAt <= (int) $doc['license_starts_at']) {
                return 'missing_expiry';
            }
        }

        if (!\is_string($doc['validation_status'] ?? null) || '' === $doc['validation_status']) {
            return 'malformed_status';
        }

        return null;
    }

    private function isCanonicalHostname(string $hostname): bool
    {
        if ('' === $hostname || \strlen($hostname) > 253) {
            return false;
        }

        if (str_contains($hostname, '*') || str_ends_with($hostname, '.')) {
            return false;
        }

        return 1 === preg_match('/^(?!-)[a-z0-9-]{1,63}(?<!-)(\.(?!-)[a-z0-9-]{1,63}(?<!-))*$/', $hostname);
    }
}
