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
use Symfony\Component\HttpFoundation\Request;
use VTinnovations\LocalFonts\Config\Paths;
use VTinnovations\LocalFonts\Service\Ed25519Signatures;
use VTinnovations\LocalFonts\Service\ReplayLedger;
use VTinnovations\LocalFonts\Service\UpdaterRequestAuthenticator;
use VTinnovations\LocalFonts\Tests\Fixture\SignedPackageFactory;

// Not autoloaded when this suite runs under a consuming project (Composer
// ignores a dependency's autoload-dev unless it is the root package).
require_once __DIR__ . '/../Fixture/SignedPackageFactory.php';

/**
 * `vt-one/request-sig-v1`: method, path, request ID, timestamp, nonce and
 * raw-body SHA-256, joined by newlines. `X-VT-Key-ID` selects the key but is
 * deliberately not part of the signed message.
 */
final class UpdaterRequestAuthenticatorTest extends TestCase
{
    private SignedPackageFactory $factory;
    private ReplayLedger $ledger;
    private UpdaterRequestAuthenticator $authenticator;
    private string $scratchDir;
    private int $now;

    protected function setUp(): void
    {
        if (!\function_exists('sodium_crypto_sign_keypair')) {
            self::markTestSkipped('ext-sodium is required for these tests.');
        }

        $this->factory = new SignedPackageFactory('vtone-2026a');
        $this->scratchDir = sys_get_temp_dir() . '/lf_updater_test_' . bin2hex(random_bytes(6));
        $this->ledger = new ReplayLedger(new Paths($this->scratchDir));
        $this->authenticator = new UpdaterRequestAuthenticator($this->factory->keyRing, new Ed25519Signatures(), $this->ledger);
        $this->now = time();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->scratchDir)) {
            $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->scratchDir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($items as $item) {
                $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
            }
            @rmdir($this->scratchDir);
        }
    }

    public function testAcceptsAProperlySignedRequest(): void
    {
        $result = $this->authenticator->authenticate($this->buildRequest(), $this->now);

        self::assertSame('accepted', $result->outcome);
        self::assertNull($result->reasonCategory);
    }

    public function testRejectsAnOversizedBody(): void
    {
        $body = json_encode(array_merge($this->baseBody(), ['padding' => str_repeat('x', 70000)]));
        $request = $this->buildRequest(bodyOverride: $body);

        $result = $this->authenticator->authenticate($request, $this->now);

        self::assertSame('rejected', $result->outcome);
        self::assertSame('payload_too_large', $result->reasonCategory);
        self::assertSame(413, $result->httpStatus);
    }

    public function testRejectsAnUnsupportedMediaType(): void
    {
        $result = $this->authenticator->authenticate($this->buildRequest(contentType: 'text/plain'), $this->now);

        self::assertSame('unsupported_media_type', $result->reasonCategory);
        self::assertSame(415, $result->httpStatus);
    }

    public function testRejectsAMissingSignatureHeader(): void
    {
        $result = $this->authenticator->authenticate($this->buildRequest(omitHeader: 'X-VT-Signature'), $this->now);

        self::assertSame('malformed_request', $result->reasonCategory);
        self::assertSame(401, $result->httpStatus);
    }

    public function testRejectsANonNumericTimestampHeader(): void
    {
        $result = $this->authenticator->authenticate($this->buildRequest(headerOverrides: ['X-VT-Timestamp' => 'not-a-number']), $this->now);

        self::assertSame('malformed_request', $result->reasonCategory);
    }

    public function testRejectsAStaleTimestamp(): void
    {
        $result = $this->authenticator->authenticate($this->buildRequest(timestamp: $this->now - 1000), $this->now);

        self::assertSame('stale_or_future_timestamp', $result->reasonCategory);
        self::assertSame(401, $result->httpStatus);
    }

    public function testRejectsAFutureTimestamp(): void
    {
        $result = $this->authenticator->authenticate($this->buildRequest(timestamp: $this->now + 1000), $this->now);

        self::assertSame('stale_or_future_timestamp', $result->reasonCategory);
    }

    public function testRejectsAnEmptySigningKeyRing(): void
    {
        $emptyLedger = new ReplayLedger(new Paths($this->scratchDir . '_empty'));
        $authenticator = new UpdaterRequestAuthenticator(new \VTinnovations\LocalFonts\Config\TrustedSigningKeys([]), new Ed25519Signatures(), $emptyLedger);

        $result = $authenticator->authenticate($this->buildRequest(), $this->now);

        self::assertSame('signing_key_store_empty', $result->reasonCategory);
    }

    public function testRejectsAnUnknownKeyId(): void
    {
        $result = $this->authenticator->authenticate($this->buildRequest(headerOverrides: ['X-VT-Key-ID' => 'not-in-the-ring']), $this->now);

        self::assertSame('unknown_signing_key', $result->reasonCategory);
    }

    public function testRejectsAForgedSignature(): void
    {
        $result = $this->authenticator->authenticate($this->buildRequest(signatureOverride: base64_encode(str_repeat('a', 64))), $this->now);

        self::assertSame('request_signature_invalid', $result->reasonCategory);
        self::assertSame(403, $result->httpStatus);
    }

    public function testKeyIdHeaderIsNotPartOfTheSignedMessage(): void
    {
        // Changing X-VT-Key-ID after signing must not affect a signature that
        // was computed without it, as long as the (now-different) key still
        // exists in the ring and can verify the same six-line message.
        $secondFactory = new SignedPackageFactory('vtone-2026a-second');
        $ring = $this->mergeRings($this->factory, $secondFactory);
        $ledger = new ReplayLedger(new Paths($this->scratchDir . '_merged'));
        $authenticator = new UpdaterRequestAuthenticator($ring, new Ed25519Signatures(), $ledger);

        // Signed with the first key but the header claims the second key: must fail (signatures don't match under the second key).
        $result = $authenticator->authenticate($this->buildRequest(headerOverrides: ['X-VT-Key-ID' => 'vtone-2026a-second']), $this->now);

        self::assertSame('rejected', $result->outcome);
        self::assertSame('request_signature_invalid', $result->reasonCategory);
    }

    public function testRejectsHeaderBodyMismatchOnRequestId(): void
    {
        // A validly signed request (the header's request_id is what's actually
        // signed and matches X-VT-Request-ID) whose body payload nonetheless
        // carries a different request_id — signature verification alone
        // cannot catch this; the explicit field-equality check must.
        $body = $this->baseBody();
        $body['request_id'] = 'a-different-request-id-inside-the-body';
        $request = $this->buildRequest(bodyOverride: json_encode($body), signedRequestId: 'req-fixture-1');

        $result = $this->authenticator->authenticate($request, $this->now);

        self::assertSame('rejected', $result->outcome);
        self::assertSame('header_body_mismatch', $result->reasonCategory);
    }

    public function testRejectsAProductMismatch(): void
    {
        $body = $this->baseBody();
        $body['project_slug'] = 'someone-else';
        $request = $this->buildRequest(bodyOverride: json_encode($body));

        $result = $this->authenticator->authenticate($request, $this->now);

        self::assertSame('product_mismatch', $result->reasonCategory);
    }

    public function testExactRetryOfAnAlreadyProcessedRequestIsIdempotent(): void
    {
        $request = $this->buildRequest(requestId: 'req-1', nonce: 'nonce-1');
        $this->ledger->record('req-1', 'nonce-1', hash('sha256', (string) $request->getContent()), 5, $this->now);

        $result = $this->authenticator->authenticate($request, $this->now);

        self::assertSame('already_processed', $result->outcome);
        self::assertSame(5, $result->alreadyProcessedVersion);
    }

    public function testSameRequestIdWithDifferentContentIsRejectedAsConflict(): void
    {
        $this->ledger->record('req-1', 'nonce-1', hash('sha256', 'a-totally-different-body'), 5, $this->now);

        $result = $this->authenticator->authenticate($this->buildRequest(requestId: 'req-1', nonce: 'nonce-1'), $this->now);

        self::assertSame('rejected', $result->outcome);
        self::assertSame('duplicate_request_id_conflict', $result->reasonCategory);
        self::assertSame(409, $result->httpStatus);
    }

    public function testRejectsANonceThatWasAlreadyUsedUnderADifferentRequestId(): void
    {
        $this->ledger->record('some-other-request', 'reused-nonce', 'unrelated-hash', 1, $this->now);

        $result = $this->authenticator->authenticate($this->buildRequest(nonce: 'reused-nonce', requestId: 'brand-new-request'), $this->now);

        self::assertSame('nonce_replayed', $result->reasonCategory);
    }

    // ── Fixture construction ─────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function baseBody(): array
    {
        return [
            'action' => 'license_update',
            'project' => 'Local Fonts',
            'project_slug' => 'localfonts',
            'product_id' => 'vt-localfonts',
            'domain' => 'example.com',
            'request_id' => 'req-fixture-1',
            'timestamp' => $this->now,
            'nonce' => 'nonce-fixture-1',
        ];
    }

    private function buildRequest(
        ?string $bodyOverride = null,
        string $contentType = 'application/json',
        ?string $omitHeader = null,
        array $headerOverrides = [],
        ?string $signatureOverride = null,
        ?int $timestamp = null,
        ?string $requestId = null,
        ?string $nonce = null,
        // Independent of the body's own request_id: the production authenticator
        // signs/verifies against the HEADER value, so a genuinely mismatched
        // (but still validly signed) request needs the header pinned separately
        // from whatever the body payload happens to contain.
        ?string $signedRequestId = null,
    ): Request {
        $body = $this->baseBody();

        if (null !== $timestamp) {
            $body['timestamp'] = $timestamp;
        }

        if (null !== $requestId) {
            $body['request_id'] = $requestId;
        }

        if (null !== $nonce) {
            $body['nonce'] = $nonce;
        }

        $rawBody = $bodyOverride ?? json_encode($body);
        $decoded = json_decode($rawBody, true) ?? $body;
        $headerRequestId = $signedRequestId ?? (string) $decoded['request_id'];

        $method = 'POST';
        $path = '/rest/api/v1/localfonts-license-updater';
        $message = implode("\n", [
            strtoupper($method),
            $path,
            $headerRequestId,
            (string) $decoded['timestamp'],
            (string) $decoded['nonce'],
            hash('sha256', $rawBody),
        ]);
        $signature = $signatureOverride ?? base64_encode(sodium_crypto_sign_detached($message, $this->factory->secretKey));

        $headers = array_merge([
            'X-VT-Request-ID' => $headerRequestId,
            'X-VT-Timestamp' => (string) $decoded['timestamp'],
            'X-VT-Nonce' => (string) $decoded['nonce'],
            'X-VT-Key-ID' => $this->factory->keyId,
            'X-VT-Signature' => $signature,
        ], $headerOverrides);

        if (null !== $omitHeader) {
            unset($headers[$omitHeader]);
        }

        $server = ['CONTENT_TYPE' => $contentType];

        foreach ($headers as $name => $value) {
            $server['HTTP_' . str_replace('-', '_', strtoupper($name))] = $value;
        }

        return Request::create('https://example.com' . $path, $method, [], [], [], $server, $rawBody);
    }

    private function mergeRings(SignedPackageFactory ...$factories): \VTinnovations\LocalFonts\Config\TrustedSigningKeys
    {
        $entries = [];

        foreach ($factories as $factory) {
            $entries[$factory->keyId] = [
                'algorithm' => 'ed25519',
                'public_key_base64' => base64_encode($factory->publicKey),
                'purposes' => ['document', 'envelope', 'request'],
                'activated_at' => 0,
                'retired_at' => null,
            ];
        }

        return new \VTinnovations\LocalFonts\Config\TrustedSigningKeys($entries);
    }
}
