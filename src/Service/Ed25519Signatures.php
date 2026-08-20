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
 * Detached-signature verification primitive. Kept separate from key
 * resolution and canonicalization so no single class holds the whole
 * signed-trust flow. Fails closed (returns false) on any malformed input or
 * a missing crypto extension instead of throwing past the caller.
 */
final class Ed25519Signatures
{
    public function isSupported(): bool
    {
        return \function_exists('sodium_crypto_sign_verify_detached');
    }

    public function verifyDetached(string $message, string $signatureBase64, string $publicKeyBytes): bool
    {
        if (!$this->isSupported() || 32 !== \strlen($publicKeyBytes)) {
            return false;
        }

        $signature = base64_decode($signatureBase64, true);

        if (false === $signature || 64 !== \strlen($signature)) {
            return false;
        }

        try {
            return sodium_crypto_sign_verify_detached($signature, $message, $publicKeyBytes);
        } catch (\SodiumException) {
            return false;
        }
    }
}
