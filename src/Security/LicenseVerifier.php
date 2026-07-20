<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/localfonts
 * @author    V&T Innovations
 * @license   LGPL-3.0-or-later
 * @copyright V&T Innovations 2026
 */

namespace VTinnovations\LocalFonts\Security;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Thin HTTP wrapper around the V&T Innovations verify endpoint (v-t.one).
 * Stateless: it makes the call and normalises the response; persistence and
 * grace logic live in {@see LicenseManager}. Same license server and contract
 * as the Migrator and Schema.org bundles — only the product code differs.
 */
final class LicenseVerifier
{
    // Canonical host: v-t.one 301-redirects to www.v-t.one, and Symfony HttpClient downgrades a
    // redirected POST to GET (dropping the JSON body), so hit the final host directly.
    private const ENDPOINT = 'https://www.v-t.one/api/v1/verify';

    // This plugin's product code on the v-t.one license server.
    private const PRODUCT = 'vt-localfonts';

    // Must match `vt_license_api_secret` on the v-t.one license server; sent as the X-VT-Api-Key header.
    private const API_SECRET = 'X-VT-API';

    public function __construct(private readonly HttpClientInterface $client)
    {
    }

    /**
     * @return array{valid: bool, server_error: bool, expires_at: int|null, package: string|null, message: string}
     */
    public function verify(string $licenseKey, string $domain): array
    {
        $payload = ['key' => $licenseKey, 'domain' => $domain, 'product' => self::PRODUCT];

        try {
            $response = $this->client->request('POST', self::ENDPOINT, [
                'headers' => ['X-VT-Api-Key' => self::API_SECRET],
                'json' => $payload,
                'timeout' => 5,
            ]);
            $statusCode = $response->getStatusCode();
            $data = $response->toArray(false);

            if ($statusCode >= 500) {
                return $this->serverError('License server temporarily unavailable.');
            }

            return [
                'valid' => true === ($data['valid'] ?? false),
                'server_error' => false,
                'expires_at' => isset($data['expires_at']) ? (int) $data['expires_at'] : null,
                'package' => isset($data['package']) ? (string) $data['package'] : null,
                'message' => (string) ($data['message'] ?? ''),
            ];
        } catch (TransportExceptionInterface) {
            return $this->serverError('Could not connect to the license server.');
        } catch (\Throwable) {
            return $this->serverError('Unexpected error during verification.');
        }
    }

    /**
     * @return array{valid: false, server_error: true, expires_at: null, package: null, message: string}
     */
    private function serverError(string $message): array
    {
        return ['valid' => false, 'server_error' => true, 'expires_at' => null, 'package' => null, 'message' => $message];
    }
}
