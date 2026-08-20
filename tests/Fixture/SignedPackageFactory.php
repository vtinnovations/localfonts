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

namespace VTinnovations\LocalFonts\Tests\Fixture;

use VTinnovations\LocalFonts\Config\TrustedSigningKeys;
use VTinnovations\LocalFonts\Service\CanonicalJson;

/**
 * Builds self-signed licence packages with a throwaway Ed25519 keypair, so
 * tests can exercise the real verification path end-to-end without the
 * genuine v-t.one private key, which exists only on V-T.ONE infrastructure.
 */
final class SignedPackageFactory
{
    public readonly string $secretKey;
    public readonly string $publicKey;
    public readonly TrustedSigningKeys $keyRing;

    public function __construct(public readonly string $keyId = 'test-key')
    {
        $keypair = sodium_crypto_sign_keypair();
        $this->secretKey = sodium_crypto_sign_secretkey($keypair);
        $this->publicKey = sodium_crypto_sign_publickey($keypair);

        $this->keyRing = new TrustedSigningKeys([
            $this->keyId => [
                'algorithm' => 'ed25519',
                'public_key_base64' => base64_encode($this->publicKey),
                'purposes' => ['document', 'envelope', 'request'],
                'activated_at' => 0,
                'retired_at' => null,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function baseDocument(int $now, array $overrides = []): array
    {
        return array_merge([
            'schema_version' => 2,
            'project' => 'Local Fonts',
            'project_slug' => 'localfonts',
            'license_key' => 'LF-TEST-0001',
            'license_domain' => 'example.com',
            'license_domains' => ['example.com', 'staging.example.com'],
            'license_max_domains' => 3,
            'license_package' => 'free',
            'license_features' => [],
            'license_version' => 1,
            'license_issued_at' => $now - 100,
            'license_starts_at' => $now - 100,
            'license_expires_at' => null,
            'license_lifetime' => true,
            'license_verified_at' => $now,
            'free_available' => true,
            'validation_status' => 'valid',
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $document
     *
     * @return array<string, mixed> the document with `signature` set
     */
    public function signDocument(array $document): array
    {
        $message = CanonicalJson::encode($document, ['signature']);
        $document['signature'] = base64_encode(sodium_crypto_sign_detached($message, $this->secretKey));

        return $document;
    }

    /**
     * @param array<string, mixed> $document a document already carrying `signature`
     *
     * @return array{0: string, 1: array<string, mixed>} [payloadB64, envelope]
     */
    public function wrap(array $document, int $now, ?int $version = null): array
    {
        $raw = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $envelope = [
            'project' => 'Local Fonts',
            'project_slug' => 'localfonts',
            'license_version' => $version ?? $document['license_version'],
            'license_md5' => md5($raw),
            'generated_at' => $now,
            'key_id' => $this->keyId,
            'signature_algorithm' => 'ed25519',
        ];
        $message = CanonicalJson::encode($envelope, ['signature']);
        $envelope['signature'] = base64_encode(sodium_crypto_sign_detached($message, $this->secretKey));

        return [base64_encode($raw), $envelope];
    }

    /**
     * Convenience: build, sign and wrap a complete valid package in one call.
     *
     * @param array<string, mixed> $documentOverrides
     *
     * @return array{0: string, 1: array<string, mixed>, 2: array<string, mixed>} [payloadB64, envelope, document]
     */
    public function validPackage(int $now, array $documentOverrides = []): array
    {
        $document = $this->signDocument($this->baseDocument($now, $documentOverrides));
        [$payloadB64, $envelope] = $this->wrap($document, $now);

        return [$payloadB64, $envelope, $document];
    }
}
