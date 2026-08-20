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

use Psr\Log\LoggerInterface;
use VTinnovations\LocalFonts\Config\ProductProfile;
use VTinnovations\LocalFonts\Http\VtOneGateway;

/**
 * Orchestrates activation, administrator refresh, removal and the
 * server-pushed update against the one authoritative {@see EntitlementStore}.
 * A failed network call, timeout or denial never erases a previously valid
 * local package.
 */
final class ActivationService
{
    /** Lazily re-verify a stale package this often (administrator refresh / cron). */
    private const STALE_AFTER_SECONDS = 86400;

    public function __construct(
        private readonly VtOneGateway $gateway,
        private readonly EntitlementVerifier $verifier,
        private readonly EntitlementStore $store,
        private readonly EntitlementEvaluator $evaluator,
        private readonly DomainInventory $domains,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function currentState(): EntitlementEvaluation
    {
        return $this->evaluator->evaluate();
    }

    /**
     * @return array{ok: bool, messageKey: string}
     */
    public function activate(string $licenseKey): array
    {
        $licenseKey = trim($licenseKey);

        if ('' === $licenseKey || \strlen($licenseKey) > 190) {
            return ['ok' => false, 'messageKey' => 'no_key_entered'];
        }

        $domain = $this->domains->resolveVerificationDomain();

        if (null === $domain) {
            return ['ok' => false, 'messageKey' => 'no_trusted_domain'];
        }

        $payload = [
            'action' => 'activate',
            'project' => ProductProfile::PROJECT,
            'project_slug' => ProductProfile::PROJECT_SLUG,
            'product_id' => ProductProfile::PRODUCT_ID,
            'license_key' => $licenseKey,
            'domain' => $domain,
            'request_id' => $this->generateRequestId(),
            'timestamp' => time(),
            'nonce' => $this->generateNonce(),
        ];

        return $this->exchange($payload, $domain, null);
    }

    /**
     * @return array{ok: bool, messageKey: string}
     */
    public function refresh(?string $replacementKey = null): array
    {
        $current = $this->store->current();

        if (null === $current) {
            return ['ok' => false, 'messageKey' => 'no_license_activated'];
        }

        $doc = json_decode($current['raw'], true);
        $storedKey = \is_array($doc) ? (string) ($doc['license_key'] ?? '') : '';
        $storedVersion = \is_array($doc) ? (int) ($doc['license_version'] ?? 0) : 0;
        $key = trim((string) ($replacementKey ?? $storedKey));

        if ('' === $key) {
            return ['ok' => false, 'messageKey' => 'no_key_stored'];
        }

        $domain = $this->domains->resolveVerificationDomain();

        if (null === $domain) {
            return ['ok' => false, 'messageKey' => 'no_trusted_domain'];
        }

        $payload = [
            'action' => 'refresh',
            'project' => ProductProfile::PROJECT,
            'project_slug' => ProductProfile::PROJECT_SLUG,
            'product_id' => ProductProfile::PRODUCT_ID,
            'license_key' => $key,
            'domain' => $domain,
            'current_license_version' => $storedVersion,
            'request_id' => $this->generateRequestId(),
            'timestamp' => time(),
            'nonce' => $this->generateNonce(),
        ];

        return $this->exchange($payload, $domain, $storedVersion);
    }

    public function refreshIfStale(): void
    {
        $current = $this->store->current();

        if (null === $current) {
            return;
        }

        $verifiedAt = (int) (json_decode($current['raw'], true)['license_verified_at'] ?? 0);

        if ($verifiedAt > 0 && time() - $verifiedAt < self::STALE_AFTER_SECONDS) {
            return;
        }

        $this->refresh();
    }

    /**
     * @return array{ok: bool, messageKey: string}
     */
    public function remove(): array
    {
        $this->store->remove();

        return ['ok' => true, 'messageKey' => 'removed'];
    }

    /**
     * Applies a server-initiated push. Returns the applied version so the
     * caller can build the `updated`/`already_processed` response and the
     * replay ledger can be updated by the caller after success.
     *
     * @param array<string, mixed> $body
     * @param array<string, mixed> $envelope
     *
     * @return array{ok: bool, version: int|null, reason: string|null}
     */
    public function applyPushedPackage(array $body, array $envelope, string $payloadB64): array
    {
        $current = $this->store->current();
        $previousVersion = null;

        if (null !== $current) {
            $previousDoc = json_decode($current['raw'], true);
            $previousVersion = \is_array($previousDoc) ? (int) ($previousDoc['license_version'] ?? 0) : null;
        }

        $result = $this->verifier->openEnvelope($envelope, $payloadB64, time());

        if (!$result->ok || null === $result->record || null === $result->rawBytes) {
            return ['ok' => false, 'version' => null, 'reason' => $result->reasonCategory];
        }

        if (null !== $previousVersion && $result->record->licenseVersion < $previousVersion) {
            return ['ok' => false, 'version' => null, 'reason' => 'rollback_rejected'];
        }

        $domainError = $this->verifier->checkDomainBinding($result->record, (string) ($body['domain'] ?? ''), $this->domains->trustedInventory());

        if (null !== $domainError) {
            return ['ok' => false, 'version' => null, 'reason' => $domainError];
        }

        // The push path enforces the selected licence model exactly like
        // activation and refresh do; a signed but model-incompatible package
        // must never reach the authoritative store.
        $modelError = $this->verifier->checkLifetimeFreeModel($result->record, time());

        if (null !== $modelError) {
            return ['ok' => false, 'version' => null, 'reason' => $modelError];
        }

        try {
            $this->store->activate($result->rawBytes, $envelope);
        } catch (\Throwable) {
            return ['ok' => false, 'version' => null, 'reason' => 'atomic_activation_failed'];
        }

        return ['ok' => true, 'version' => $result->record->licenseVersion, 'reason' => null];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{ok: bool, messageKey: string}
     */
    private function exchange(array $payload, string $domain, ?int $previousVersion): array
    {
        $response = $this->gateway->verify($payload);
        $body = $response['body'];

        if (null === $body || $response['status_code'] >= 500) {
            $this->logger?->notice('vtinnovations_local_fonts.exchange_unavailable', ['operation' => $payload['action'], 'status' => $response['status_code']]);

            return ['ok' => false, 'messageKey' => 'server_unavailable'];
        }

        if ((string) ($body['request_id'] ?? '') !== $payload['request_id']) {
            return ['ok' => false, 'messageKey' => 'uncorrelated_response'];
        }

        if ('valid' !== ($body['status'] ?? null) || !isset($body['license_payload_b64'], $body['integrity']) || !\is_array($body['integrity'])) {
            return ['ok' => false, 'messageKey' => 'key_rejected'];
        }

        $result = $this->verifier->openEnvelope($body['integrity'], (string) $body['license_payload_b64'], (int) ($body['server_time'] ?? time()));

        if (!$result->ok || null === $result->record || null === $result->rawBytes) {
            $this->logger?->notice('vtinnovations_local_fonts.exchange_verification_failed', ['operation' => $payload['action'], 'result' => $result->reasonCategory]);

            return ['ok' => false, 'messageKey' => 'verification_failed'];
        }

        if (null !== $previousVersion && $result->record->licenseVersion < $previousVersion) {
            return ['ok' => false, 'messageKey' => 'stale_response'];
        }

        $domainError = $this->verifier->checkDomainBinding($result->record, $domain, $this->domains->trustedInventory());

        if (null !== $domainError) {
            return ['ok' => false, 'messageKey' => 'domain_not_authorized'];
        }

        $modelError = $this->verifier->checkLifetimeFreeModel($result->record, time());

        if (null !== $modelError) {
            return ['ok' => false, 'messageKey' => 'model_incompatible'];
        }

        try {
            $this->store->activate($result->rawBytes, $body['integrity']);
        } catch (\Throwable) {
            return ['ok' => false, 'messageKey' => 'save_failed'];
        }

        return ['ok' => true, 'messageKey' => 'activated'];
    }

    private function generateRequestId(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function generateNonce(): string
    {
        return bin2hex(random_bytes(16));
    }
}
