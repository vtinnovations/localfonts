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

final class Ed25519SignaturesTest extends TestCase
{
    protected function setUp(): void
    {
        if (!\function_exists('sodium_crypto_sign_keypair')) {
            self::markTestSkipped('ext-sodium is required for these tests.');
        }
    }

    public function testVerifiesAGenuineSignature(): void
    {
        [$secret, $public] = $this->keypair();
        $message = 'hello canonical world';
        $signature = base64_encode(sodium_crypto_sign_detached($message, $secret));

        self::assertTrue((new Ed25519Signatures())->verifyDetached($message, $signature, $public));
    }

    public function testRejectsATamperedMessage(): void
    {
        [$secret, $public] = $this->keypair();
        $signature = base64_encode(sodium_crypto_sign_detached('original', $secret));

        self::assertFalse((new Ed25519Signatures())->verifyDetached('tampered', $signature, $public));
    }

    public function testRejectsASignatureFromADifferentKey(): void
    {
        [$secret] = $this->keypair();
        [, $otherPublic] = $this->keypair();
        $signature = base64_encode(sodium_crypto_sign_detached('message', $secret));

        self::assertFalse((new Ed25519Signatures())->verifyDetached('message', $signature, $otherPublic));
    }

    public function testRejectsMalformedBase64WithoutThrowing(): void
    {
        [, $public] = $this->keypair();

        self::assertFalse((new Ed25519Signatures())->verifyDetached('message', 'not-valid-base64!!!', $public));
    }

    public function testRejectsAWrongLengthSignatureWithoutThrowing(): void
    {
        [, $public] = $this->keypair();

        self::assertFalse((new Ed25519Signatures())->verifyDetached('message', base64_encode('too-short'), $public));
    }

    public function testRejectsAWrongLengthPublicKeyWithoutThrowing(): void
    {
        [$secret] = $this->keypair();
        $signature = base64_encode(sodium_crypto_sign_detached('message', $secret));

        self::assertFalse((new Ed25519Signatures())->verifyDetached('message', $signature, 'too-short'));
    }

    public function testReportsSodiumSupport(): void
    {
        self::assertTrue((new Ed25519Signatures())->isSupported());
    }

    /**
     * @return array{0: string, 1: string} [secretKey, publicKey] raw bytes
     */
    private function keypair(): array
    {
        $keypair = sodium_crypto_sign_keypair();

        return [sodium_crypto_sign_secretkey($keypair), sodium_crypto_sign_publickey($keypair)];
    }
}
