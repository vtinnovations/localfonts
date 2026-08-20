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

namespace VTinnovations\LocalFonts\Tests\Config;

use PHPUnit\Framework\TestCase;
use VTinnovations\LocalFonts\Config\TrustedSigningKeys;

/**
 * The integration-readiness gate: a production ring must be non-empty,
 * structurally valid, and every entry must resolve exactly by key_id +
 * algorithm + purpose + activation window — never guessed or defaulted.
 */
final class TrustedSigningKeysTest extends TestCase
{
    public function testDefaultRingPinsTheApprovedSharedKey(): void
    {
        $ring = new TrustedSigningKeys();

        self::assertFalse($ring->isEmpty());

        $entry = $ring->resolve('vtone-2026a', 'ed25519', 'envelope', time());

        self::assertNotNull($entry);
        self::assertSame('ed25519', $entry['algorithm']);
    }

    public function testDefaultKeyMatchesItsPublishedFingerprint(): void
    {
        $ring = new TrustedSigningKeys();
        $entry = $ring->resolve('vtone-2026a', 'ed25519', 'document', time());
        $raw = $ring->rawPublicKeyBytes($entry);

        self::assertNotNull($raw);
        self::assertSame(32, \strlen($raw));
        self::assertStringStartsWith('edcd614e70c59ce0', hash('sha256', $raw));
    }

    public function testConstructorRejectsAPlaceholderKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new TrustedSigningKeys(['x' => $this->entry(['public_key_base64' => 'REPLACE_ME'])]);
    }

    public function testConstructorRejectsAWrongLengthKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new TrustedSigningKeys(['x' => $this->entry(['public_key_base64' => base64_encode('too-short')])]);
    }

    public function testConstructorRejectsAnUnsupportedAlgorithm(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new TrustedSigningKeys(['x' => $this->entry(['algorithm' => 'rsa-sha256'])]);
    }

    public function testConstructorRejectsAKeyWithNoPurpose(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new TrustedSigningKeys(['x' => $this->entry(['purposes' => []])]);
    }

    public function testConstructorRejectsAnEmptyKeyId(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new TrustedSigningKeys(['' => $this->entry()]);
    }

    public function testEmptyRingIsConstructibleForNegativeTests(): void
    {
        $ring = new TrustedSigningKeys([]);

        self::assertTrue($ring->isEmpty());
        self::assertNull($ring->resolve('anything', 'ed25519', 'envelope', time()));
    }

    public function testResolveFailsForAnUnknownKeyId(): void
    {
        $ring = new TrustedSigningKeys(['known' => $this->entry()]);

        self::assertNull($ring->resolve('unknown', 'ed25519', 'envelope', time()));
    }

    public function testResolveFailsForAnAlgorithmMismatch(): void
    {
        $ring = new TrustedSigningKeys(['k' => $this->entry(['algorithm' => 'ed25519'])]);

        self::assertNull($ring->resolve('k', 'ed25519-other', 'envelope', time()));
    }

    public function testResolveFailsForAnUnadvertisedPurpose(): void
    {
        $ring = new TrustedSigningKeys(['k' => $this->entry(['purposes' => ['document']])]);

        self::assertNull($ring->resolve('k', 'ed25519', 'request', time()));
    }

    public function testResolveFailsBeforeActivation(): void
    {
        $now = time();
        $ring = new TrustedSigningKeys(['k' => $this->entry(['activated_at' => $now + 3600])]);

        self::assertNull($ring->resolve('k', 'ed25519', 'envelope', $now));
    }

    public function testResolveSucceedsExactlyAtActivation(): void
    {
        $now = time();
        $ring = new TrustedSigningKeys(['k' => $this->entry(['activated_at' => $now])]);

        self::assertNotNull($ring->resolve('k', 'ed25519', 'envelope', $now));
    }

    public function testResolveFailsAfterRetirement(): void
    {
        $now = time();
        $ring = new TrustedSigningKeys(['k' => $this->entry(['retired_at' => $now - 10])]);

        self::assertNull($ring->resolve('k', 'ed25519', 'envelope', $now));
    }

    public function testUsableForPurposeReturnsOnlyCurrentlyUsableKeys(): void
    {
        $now = time();
        $ring = new TrustedSigningKeys([
            'active' => $this->entry(),
            'retired' => $this->entry(['retired_at' => $now - 10]),
            'wrong-purpose' => $this->entry(['purposes' => ['request']]),
        ]);

        self::assertSame(['active'], array_keys($ring->usableForPurpose('document', $now)));
    }

    public function testAssertProductionReadyRejectsAnEmptyRing(): void
    {
        $this->expectException(\RuntimeException::class);

        (new TrustedSigningKeys([]))->assertProductionReady(time());
    }

    public function testAssertProductionReadyRejectsARingWithNoCurrentlyUsableKey(): void
    {
        $now = time();
        $ring = new TrustedSigningKeys(['retired' => $this->entry(['retired_at' => $now - 10])]);

        $this->expectException(\RuntimeException::class);
        $ring->assertProductionReady($now);
    }

    public function testAssertProductionReadyAcceptsTheDefaultRing(): void
    {
        $ids = (new TrustedSigningKeys())->assertProductionReady(time());

        self::assertSame(['vtone-2026a'], $ids);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array{algorithm: string, public_key_base64: string, purposes: list<string>, activated_at: int, retired_at: int|null}
     */
    private function entry(array $overrides = []): array
    {
        return array_merge([
            'algorithm' => 'ed25519',
            'public_key_base64' => base64_encode(random_bytes(32)),
            'purposes' => ['document', 'envelope', 'request'],
            'activated_at' => 0,
            'retired_at' => null,
        ], $overrides);
    }
}
