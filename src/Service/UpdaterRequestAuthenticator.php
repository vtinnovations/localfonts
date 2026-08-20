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

use Symfony\Component\HttpFoundation\Request;
use VTinnovations\LocalFonts\Config\ProductProfile;
use VTinnovations\LocalFonts\Config\TrustedSigningKeys;

/**
 * Authenticates an inbound `vt-one/request-sig-v1` request for the
 * server-initiated updater. A claimed web origin proves nothing; only this
 * signature check does. CORS/Origin/Referer/User-Agent are never consulted.
 */
final class UpdaterRequestAuthenticator
{
    private const MAX_BODY_BYTES = 65536;
    private const TIMESTAMP_WINDOW_SECONDS = 300;

    public function __construct(
        private readonly TrustedSigningKeys $keys,
        private readonly Ed25519Signatures $signatures,
        private readonly ReplayLedger $replay,
    ) {
    }

    public function authenticate(Request $request, int $now): UpdaterAuthResult
    {
        $body = (string) $request->getContent();

        if (\strlen($body) > self::MAX_BODY_BYTES) {
            return UpdaterAuthResult::rejected('payload_too_large', 413);
        }

        $contentType = strtolower((string) $request->headers->get('Content-Type', ''));

        if (!str_contains($contentType, 'application/json')) {
            return UpdaterAuthResult::rejected('unsupported_media_type', 415);
        }

        $requestId = (string) $request->headers->get('X-VT-Request-ID', '');
        $timestampHeader = (string) $request->headers->get('X-VT-Timestamp', '');
        $nonce = (string) $request->headers->get('X-VT-Nonce', '');
        $keyId = (string) $request->headers->get('X-VT-Key-ID', '');
        $signature = (string) $request->headers->get('X-VT-Signature', '');

        if ('' === $requestId || '' === $timestampHeader || '' === $nonce || '' === $keyId || '' === $signature || !ctype_digit($timestampHeader)) {
            return UpdaterAuthResult::rejected('malformed_request', 401);
        }

        $timestamp = (int) $timestampHeader;

        if (abs($now - $timestamp) > self::TIMESTAMP_WINDOW_SECONDS) {
            return UpdaterAuthResult::rejected('stale_or_future_timestamp', 401);
        }

        if ($this->keys->isEmpty()) {
            return UpdaterAuthResult::rejected('signing_key_store_empty', 401);
        }

        // Ed25519-only algorithm policy for this profile; the algorithm itself is not carried on the wire.
        $keyEntry = $this->keys->resolve($keyId, 'ed25519', 'request', $now);

        if (null === $keyEntry) {
            return UpdaterAuthResult::rejected('unknown_signing_key', 401);
        }

        $publicKeyBytes = $this->keys->rawPublicKeyBytes($keyEntry);

        if (null === $publicKeyBytes) {
            return UpdaterAuthResult::rejected('signing_key_store_empty', 401);
        }

        $bodyHash = hash('sha256', $body);
        $message = implode("\n", [
            strtoupper($request->getMethod()),
            $request->getPathInfo(),
            $requestId,
            (string) $timestamp,
            $nonce,
            $bodyHash,
        ]);

        if (!$this->signatures->verifyDetached($message, $signature, $publicKeyBytes)) {
            return UpdaterAuthResult::rejected('request_signature_invalid', 403);
        }

        $decoded = json_decode($body, true);

        if (!\is_array($decoded)) {
            return UpdaterAuthResult::rejected('malformed_request', 401);
        }

        if (
            (string) ($decoded['request_id'] ?? '') !== $requestId
            || (int) ($decoded['timestamp'] ?? -1) !== $timestamp
            || (string) ($decoded['nonce'] ?? '') !== $nonce
        ) {
            return UpdaterAuthResult::rejected('header_body_mismatch', 401);
        }

        if (
            ProductProfile::PROJECT !== ($decoded['project'] ?? null)
            || ProductProfile::PROJECT_SLUG !== ($decoded['project_slug'] ?? null)
            || ProductProfile::PRODUCT_ID !== ($decoded['product_id'] ?? null)
            || 'license_update' !== ($decoded['action'] ?? null)
        ) {
            return UpdaterAuthResult::rejected('product_mismatch', 401);
        }

        $existing = $this->replay->find($requestId);

        if (null !== $existing) {
            if (hash_equals($existing['body_sha256'], $bodyHash)) {
                return UpdaterAuthResult::alreadyProcessed($existing['applied_version'], $requestId, $decoded);
            }

            return UpdaterAuthResult::rejected('duplicate_request_id_conflict', 409);
        }

        if ($this->replay->nonceSeen($nonce)) {
            return UpdaterAuthResult::rejected('nonce_replayed', 401);
        }

        return UpdaterAuthResult::accepted($requestId, $nonce, $bodyHash, $decoded);
    }
}
