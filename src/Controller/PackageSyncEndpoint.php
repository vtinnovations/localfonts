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

namespace VTinnovations\LocalFonts\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use VTinnovations\LocalFonts\Config\ProductProfile;
use VTinnovations\LocalFonts\Service\ActivationService;
use VTinnovations\LocalFonts\Service\ReplayLedger;
use VTinnovations\LocalFonts\Service\UpdaterRequestAuthenticator;

/**
 * The exact, independent-of-backend-login server-to-server path V-T.ONE uses
 * to push a newer package. Public by design; trust comes only from the
 * `vt-one/request-sig-v1` signature, never from a claimed origin. Kept thin:
 * request-shape limits here, everything else in
 * {@see UpdaterRequestAuthenticator} and {@see ActivationService}.
 */
final class PackageSyncEndpoint
{
    public function __construct(
        private readonly UpdaterRequestAuthenticator $authenticator,
        private readonly ActivationService $activation,
        private readonly ReplayLedger $replay,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    #[Route(ProductProfile::UPDATER_PATH, name: 'vtinnovations_localfonts_license_updater', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $now = time();
        $auth = $this->authenticator->authenticate($request, $now);

        if ('rejected' === $auth->outcome) {
            $this->logger?->notice('vtinnovations_local_fonts.updater_rejected', ['result' => $auth->reasonCategory, 'status' => $auth->httpStatus]);

            return new JsonResponse(['status' => 'error'], $auth->httpStatus);
        }

        if ('already_processed' === $auth->outcome) {
            return new JsonResponse([
                'status' => 'already_processed',
                'request_id' => $auth->requestId,
                'license_version' => $auth->alreadyProcessedVersion,
            ]);
        }

        $body = $auth->body ?? [];
        $outcome = $this->activation->applyPushedPackage($body, (array) ($body['integrity'] ?? []), (string) ($body['license_payload_b64'] ?? ''));

        if (!$outcome['ok']) {
            $this->logger?->notice('vtinnovations_local_fonts.updater_apply_failed', ['result' => $outcome['reason']]);

            return new JsonResponse(['status' => 'error'], 'rollback_rejected' === $outcome['reason'] ? 409 : 401);
        }

        $this->replay->record((string) $auth->requestId, (string) $auth->nonce, (string) $auth->bodySha256, (int) $outcome['version'], $now);

        return new JsonResponse([
            'status' => 'updated',
            'request_id' => $auth->requestId,
            'license_version' => $outcome['version'],
        ]);
    }
}
