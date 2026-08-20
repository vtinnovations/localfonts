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

namespace VTinnovations\LocalFonts\Http;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use VTinnovations\LocalFonts\Config\ProductProfile;

/**
 * The only class allowed to speak to v-t.one. Fixed hosts, no redirects, TLS
 * verification always on, bounded timeouts and a capped response size.
 * Never logs a packet body, a nonce or a licence key.
 */
/**
 * Not `final`: this is the sole outbound transport collaborator {@see ActivationService}
 * depends on, and it needs a PHPUnit test double in unit tests that must
 * never contact the live v-t.one endpoints. `final` buys nothing here —
 * nothing security-sensitive depends on this class being unextendable.
 */
class VtOneGateway
{
    private const MAX_RESPONSE_BYTES = 65536;

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{status_code: int, body: array<string, mixed>|null}
     */
    public function verify(array $payload): array
    {
        try {
            $response = $this->client->request('POST', ProductProfile::VERIFY_ENDPOINT, [
                'json' => $payload,
                'timeout' => 6,
                'max_duration' => 10,
                'max_redirects' => 0,
                'verify_peer' => true,
                'verify_host' => true,
                'headers' => ['Content-Type' => 'application/json'],
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);

            if (\strlen($content) > self::MAX_RESPONSE_BYTES) {
                $this->logger?->warning('vtinnovations_local_fonts.verify_response_too_large', ['operation' => $payload['action'] ?? 'unknown']);

                return ['status_code' => $statusCode, 'body' => null];
            }

            $contentType = strtolower($response->getHeaders(false)['content-type'][0] ?? '');

            if (!str_contains($contentType, 'application/json')) {
                return ['status_code' => $statusCode, 'body' => null];
            }

            $decoded = json_decode($content, true);

            return ['status_code' => $statusCode, 'body' => \is_array($decoded) ? $decoded : null];
        } catch (ExceptionInterface $exception) {
            $this->logger?->notice('vtinnovations_local_fonts.verify_transport_error', ['operation' => $payload['action'] ?? 'unknown', 'result' => 'transport_error']);

            return ['status_code' => 0, 'body' => null];
        }
    }

    /**
     * Per-invocation signal. Best effort; failure must never surface to the
     * caller or affect entitlement.
     */
    public function sendInvocationSignal(string $domain): void
    {
        $this->fireAndForget(['project' => ProductProfile::PROJECT, 'domain' => $domain]);
    }

    /**
     * Mandatory once-per-session module-entry signal. `key` must already be
     * authenticated by the caller; this class never validates it.
     */
    public function sendSessionEntrySignal(string $domain, string $key): void
    {
        $this->fireAndForget(['domain' => $domain, 'key' => $key]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function fireAndForget(array $payload): void
    {
        try {
            $this->client->request('POST', ProductProfile::SIGNAL_ENDPOINT, [
                'json' => $payload,
                'timeout' => 3,
                'max_duration' => 5,
                'max_redirects' => 0,
                'verify_peer' => true,
                'verify_host' => true,
                'headers' => ['Content-Type' => 'application/json'],
            ]);
        } catch (ExceptionInterface) {
            // Silent by design: a signal never affects licence validity or rendering.
        }
    }
}
