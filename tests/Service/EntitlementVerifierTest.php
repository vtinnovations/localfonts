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

namespace VTinnovations\LocalFonts\Tests\Service;

use PHPUnit\Framework\TestCase;
use VTinnovations\LocalFonts\Service\Ed25519Signatures;
use VTinnovations\LocalFonts\Service\EntitlementVerifier;
use VTinnovations\LocalFonts\Tests\Fixture\SignedPackageFactory;

// Not autoloaded when this suite runs under a consuming project (Composer
// ignores a dependency's autoload-dev unless it is the root package).
require_once __DIR__ . '/../Fixture/SignedPackageFactory.php';

/**
 * The signed-trust core: envelope signature, exact-byte MD5, document
 * signature, schema/date/domain/package shape, and the Lifetime Free model.
 * Every negative case here corresponds to a security-review question in
 * `references/security-controls.md`.
 */
final class EntitlementVerifierTest extends TestCase
{
    private SignedPackageFactory $factory;
    private EntitlementVerifier $verifier;
    private int $now;

    protected function setUp(): void
    {
        if (!\function_exists('sodium_crypto_sign_keypair')) {
            self::markTestSkipped('ext-sodium is required for these tests.');
        }

        $this->factory = new SignedPackageFactory();
        $this->verifier = new EntitlementVerifier($this->factory->keyRing, new Ed25519Signatures());
        $this->now = time();
    }

    // ── Happy path ───────────────────────────────────────────────────────────

    public function testAcceptsAFullyValidPackage(): void
    {
        [$payloadB64, $envelope] = $this->factory->validPackage($this->now);

        $result = $this->verifier->openEnvelope($envelope, $payloadB64, $this->now);

        self::assertTrue($result->ok);
        self::assertNotNull($result->record);
        self::assertSame('free', $result->record->licensePackage);
        self::assertNull($result->reasonCategory);
    }

    // ── Envelope-level tamper resistance ────────────────────────────────────

    public function testRejectsATamperedPayloadViaMd5Mismatch(): void
    {
        [$payloadB64, $envelope] = $this->factory->validPackage($this->now);
        $tampered = base64_encode(base64_decode($payloadB64, true) . 'x');

        $result = $this->verifier->openEnvelope($envelope, $tampered, $this->now);

        self::assertFalse($result->ok);
        self::assertSame('integrity_mismatch', $result->reasonCategory);
    }

    public function testRecalculatingMd5OverTamperedBytesCannotForgeTheEnvelope(): void
    {
        // The security-review question this answers: "Can a modified licence
        // be accepted by recalculating MD5?" An attacker without the private
        // key cannot re-sign the envelope to match a new MD5.
        [$payloadB64, $envelope] = $this->factory->validPackage($this->now);
        $tamperedRaw = base64_decode($payloadB64, true) . 'x';
        $envelope['license_md5'] = md5($tamperedRaw);

        $result = $this->verifier->openEnvelope($envelope, base64_encode($tamperedRaw), $this->now);

        self::assertFalse($result->ok);
        self::assertSame('envelope_signature_invalid', $result->reasonCategory);
    }

    public function testRejectsAnEnvelopeWithAForgedSignature(): void
    {
        [$payloadB64, $envelope] = $this->factory->validPackage($this->now);
        $envelope['signature'] = base64_encode(str_repeat('a', 64));

        $result = $this->verifier->openEnvelope($envelope, $payloadB64, $this->now);

        self::assertSame('envelope_signature_invalid', $result->reasonCategory);
    }

    public function testRejectsAnEnvelopeMissingRequiredFields(): void
    {
        [$payloadB64, $envelope] = $this->factory->validPackage($this->now);
        unset($envelope['license_md5']);

        $result = $this->verifier->openEnvelope($envelope, $payloadB64, $this->now);

        self::assertSame('malformed_envelope', $result->reasonCategory);
    }

    public function testRejectsAnEnvelopeForADifferentProject(): void
    {
        [$payloadB64, $envelope] = $this->factory->validPackage($this->now);
        $envelope['project_slug'] = 'someone-else';

        $result = $this->verifier->openEnvelope($envelope, $payloadB64, $this->now);

        self::assertSame('project_mismatch', $result->reasonCategory);
    }

    public function testRejectsAnEmptySigningKeyRing(): void
    {
        $emptyRing = new \VTinnovations\LocalFonts\Config\TrustedSigningKeys([]);
        $verifier = new EntitlementVerifier($emptyRing, new Ed25519Signatures());
        [$payloadB64, $envelope] = $this->factory->validPackage($this->now);

        $result = $verifier->openEnvelope($envelope, $payloadB64, $this->now);

        self::assertSame('signing_key_store_empty', $result->reasonCategory);
    }

    public function testRejectsAnUnknownKeyId(): void
    {
        [$payloadB64, $envelope] = $this->factory->validPackage($this->now);
        $envelope['key_id'] = 'not-in-the-ring';

        $result = $this->verifier->openEnvelope($envelope, $payloadB64, $this->now);

        self::assertSame('unknown_signing_key', $result->reasonCategory);
    }

    public function testRejectsInvalidBase64Payload(): void
    {
        [, $envelope] = $this->factory->validPackage($this->now);

        $result = $this->verifier->openEnvelope($envelope, '***not base64***', $this->now);

        self::assertSame('invalid_payload_encoding', $result->reasonCategory);
    }

    public function testRejectsAnOversizedPayload(): void
    {
        [, $envelope] = $this->factory->validPackage($this->now);
        $huge = base64_encode(str_repeat('x', 200000));

        $result = $this->verifier->openEnvelope($envelope, $huge, $this->now);

        self::assertFalse($result->ok);
        self::assertSame('payload_too_large', $result->reasonCategory);
    }

    // ── Document-level signature and shape ──────────────────────────────────

    public function testRejectsADocumentWithAForgedSignature(): void
    {
        $document = $this->factory->baseDocument($this->now);
        $document['signature'] = base64_encode(str_repeat('a', 64));
        [$payloadB64, $envelope] = $this->factory->wrap($document, $this->now);

        $result = $this->verifier->openEnvelope($envelope, $payloadB64, $this->now);

        self::assertSame('document_signature_invalid', $result->reasonCategory);
    }

    public function testRejectsAnUnsupportedSchemaVersion(): void
    {
        $this->assertDocumentRejected(['schema_version' => 1], 'unsupported_schema_version');
    }

    public function testRejectsAVersionMismatchBetweenEnvelopeAndDocument(): void
    {
        $document = $this->factory->signDocument($this->factory->baseDocument($this->now));
        [$payloadB64, $envelope] = $this->factory->wrap($document, $this->now, version: 999);

        $result = $this->verifier->openEnvelope($envelope, $payloadB64, $this->now);

        self::assertSame('version_mismatch', $result->reasonCategory);
    }

    public function testRejectsAnEmptyLicenseDomain(): void
    {
        $this->assertDocumentRejected(['license_domain' => ''], 'malformed_domain');
    }

    public function testRejectsAWildcardDomainEntry(): void
    {
        $this->assertDocumentRejected(['license_domains' => ['*.example.com']], 'invalid_domain_entry');
    }

    public function testRejectsAnEmptyDomainList(): void
    {
        $this->assertDocumentRejected(['license_domains' => []], 'malformed_domain_list');
    }

    public function testRejectsDuplicateDomains(): void
    {
        $this->assertDocumentRejected(['license_domains' => ['example.com', 'example.com']], 'duplicate_domains');
    }

    public function testRejectsAnUnsortedDomainList(): void
    {
        $this->assertDocumentRejected(['license_domains' => ['zzz.com', 'aaa.com'], 'license_domain' => 'aaa.com'], 'unsorted_domains');
    }

    public function testAcceptsInstanceBoundMaxDomainsOf9999WithoutTreatingItAsAWildcard(): void
    {
        $document = $this->factory->signDocument($this->factory->baseDocument($this->now, ['license_max_domains' => 9999]));
        [$payloadB64, $envelope] = $this->factory->wrap($document, $this->now);

        $result = $this->verifier->openEnvelope($envelope, $payloadB64, $this->now);

        self::assertTrue($result->ok);
        self::assertSame(9999, $result->record->licenseMaxDomains);

        // 9999 must still only authorize exact members of license_domains —
        // never an unrelated host merely because the allowance is large.
        self::assertSame('example.com', $this->verifier->resolveMatchedDomain($result->record, ['example.com', 'evil.com'], null));
        self::assertNull($this->verifier->resolveMatchedDomain($result->record, ['evil.com'], null));
    }

    public function testRejectsANonPositiveMaxDomains(): void
    {
        $this->assertDocumentRejected(['license_max_domains' => 0], 'invalid_max_domains');
    }

    public function testRejectsAnEmptyLicensePackage(): void
    {
        $this->assertDocumentRejected(['license_package' => ''], 'malformed_package');
    }

    public function testRejectsLifetimeTrueWithANonNullExpiry(): void
    {
        $this->assertDocumentRejected(['license_expires_at' => $this->now + 1000], 'lifetime_with_expiry');
    }

    public function testRejectsANonLifetimePackageWithoutAnExpiry(): void
    {
        $this->assertDocumentRejected(['license_lifetime' => false, 'license_expires_at' => null], 'missing_expiry');
    }

    public function testRejectsAnExpiryNotAfterTheStartDate(): void
    {
        $this->assertDocumentRejected([
            'license_lifetime' => false,
            'license_issued_at' => $this->now,
            'license_starts_at' => $this->now,
            'license_expires_at' => $this->now,
        ], 'missing_expiry');
    }

    public function testRejectsAnEmptyLicenseKey(): void
    {
        $this->assertDocumentRejected(['license_key' => ''], 'malformed_key');
    }

    public function testRejectsAWhitespaceOnlyLicenseKey(): void
    {
        $this->assertDocumentRejected(['license_key' => '   '], 'malformed_key');
    }

    public function testRejectsANonBooleanLifetimeFlag(): void
    {
        $this->assertDocumentRejected(['license_lifetime' => 'true'], 'malformed_flags');
    }

    public function testRejectsANonBooleanFreeAvailableFlag(): void
    {
        $this->assertDocumentRejected(['free_available' => 1], 'malformed_flags');
    }

    public function testRejectsAnEmptyValidationStatus(): void
    {
        $this->assertDocumentRejected(['validation_status' => ''], 'malformed_status');
    }

    // ── Domain binding ───────────────────────────────────────────────────────

    public function testDomainBindingSucceedsOnExactIntersection(): void
    {
        [, , $document] = $this->factory->validPackage($this->now);
        $record = $this->openRecord($document);

        self::assertNull($this->verifier->checkDomainBinding($record, 'example.com', ['example.com']));
    }

    public function testDomainBindingRejectsARequestDomainNotEqualToLicenseDomain(): void
    {
        [, , $document] = $this->factory->validPackage($this->now);
        $record = $this->openRecord($document);

        self::assertSame('domain_request_mismatch', $this->verifier->checkDomainBinding($record, 'staging.example.com', ['staging.example.com']));
    }

    public function testDomainBindingRejectsAnApexLicenceForAWwwHost(): void
    {
        [, , $document] = $this->factory->validPackage($this->now, ['license_domains' => ['example.com'], 'license_domain' => 'example.com']);
        $record = $this->openRecord($document);

        self::assertSame('domain_request_mismatch', $this->verifier->checkDomainBinding($record, 'www.example.com', ['www.example.com']));
    }

    public function testDomainBindingRejectsWhenNoConfiguredDomainIntersects(): void
    {
        [, , $document] = $this->factory->validPackage($this->now);
        $record = $this->openRecord($document);

        self::assertSame('domain_inventory_no_intersection', $this->verifier->checkDomainBinding($record, 'example.com', ['unrelated.com']));
    }

    // ── resolveMatchedDomain ─────────────────────────────────────────────────

    public function testResolveMatchedDomainPrefersTheCurrentTrustedHostWhenInIntersection(): void
    {
        [, , $document] = $this->factory->validPackage($this->now);
        $record = $this->openRecord($document);

        self::assertSame('staging.example.com', $this->verifier->resolveMatchedDomain($record, ['example.com', 'staging.example.com'], 'staging.example.com'));
    }

    public function testResolveMatchedDomainIgnoresACurrentHostOutsideTheIntersection(): void
    {
        [, , $document] = $this->factory->validPackage($this->now, ['license_domains' => ['example.com']]);
        $record = $this->openRecord($document);

        self::assertSame('example.com', $this->verifier->resolveMatchedDomain($record, ['example.com'], 'evil.com'));
    }

    public function testResolveMatchedDomainFallsBackToTheLowestSortedIntersectionMember(): void
    {
        [, , $document] = $this->factory->validPackage($this->now);
        $record = $this->openRecord($document);

        self::assertSame('example.com', $this->verifier->resolveMatchedDomain($record, ['staging.example.com', 'example.com'], null));
    }

    public function testResolveMatchedDomainIsNullWithoutAnyIntersection(): void
    {
        [, , $document] = $this->factory->validPackage($this->now);
        $record = $this->openRecord($document);

        self::assertNull($this->verifier->resolveMatchedDomain($record, ['unrelated.com'], null));
    }

    // ── Lifetime Free model ──────────────────────────────────────────────────

    public function testLifetimeFreeModelAcceptsTheApprovedPackage(): void
    {
        [, , $document] = $this->factory->validPackage($this->now);
        $record = $this->openRecord($document);

        self::assertNull($this->verifier->checkLifetimeFreeModel($record, $this->now));
    }

    public function testLifetimeFreeModelRejectsAProPackage(): void
    {
        [, , $document] = $this->factory->validPackage($this->now, ['license_package' => 'pro']);
        $record = $this->openRecord($document);

        self::assertSame('package_not_accepted', $this->verifier->checkLifetimeFreeModel($record, $this->now));
    }

    public function testLifetimeFreeModelRejectsAFutureStartDate(): void
    {
        [, , $document] = $this->factory->validPackage($this->now, ['license_starts_at' => $this->now + 3600]);
        $record = $this->openRecord($document);

        self::assertSame('not_yet_valid', $this->verifier->checkLifetimeFreeModel($record, $this->now));
    }

    public function testLifetimeFreeModelRejectsANonValidValidationStatus(): void
    {
        [, , $document] = $this->factory->validPackage($this->now, ['validation_status' => 'revoked']);
        $record = $this->openRecord($document);

        self::assertSame('validation_status_not_valid', $this->verifier->checkLifetimeFreeModel($record, $this->now));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function assertDocumentRejected(array $overrides, string $expectedCategory): void
    {
        $document = $this->factory->signDocument($this->factory->baseDocument($this->now, $overrides));
        [$payloadB64, $envelope] = $this->factory->wrap($document, $this->now);

        $result = $this->verifier->openEnvelope($envelope, $payloadB64, $this->now);

        self::assertFalse($result->ok);
        self::assertSame($expectedCategory, $result->reasonCategory);
    }

    /**
     * @param array<string, mixed> $document
     */
    private function openRecord(array $document): \VTinnovations\LocalFonts\Service\EntitlementRecord
    {
        [$payloadB64, $envelope] = $this->factory->wrap($document, $this->now);
        $result = $this->verifier->openEnvelope($envelope, $payloadB64, $this->now);

        self::assertTrue($result->ok, 'Fixture package must open successfully for this test to be meaningful.');

        return $result->record;
    }
}
