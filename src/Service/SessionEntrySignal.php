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

use Symfony\Component\HttpFoundation\RequestStack;
use VTinnovations\LocalFonts\Config\ProductProfile;
use VTinnovations\LocalFonts\Http\VtOneGateway;

/**
 * Mandatory once-per-authenticated-backend-session module-entry signal. Must
 * be invoked only from the authoritative Settings licence section's own
 * render path — never from a generic bootstrap, listener or frontend
 * request. Global module: deduplicated by session + project slug only.
 */
final class SessionEntrySignal
{
    private const SESSION_KEY = 'vtinnovations_local_fonts_session_signal_sent';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly VtOneGateway $gateway,
        private readonly EntitlementEvaluator $evaluator,
    ) {
    }

    public function notifyOnFirstEntry(): void
    {
        try {
            $session = $this->requestStack->getSession();
        } catch (\Throwable) {
            // No backend session available (e.g. CLI context) — never send from here.
            return;
        }

        if ($session->get(self::SESSION_KEY, false)) {
            return;
        }

        $evaluation = $this->evaluator->evaluate();

        if (null === $evaluation->record || '' === $evaluation->record->licenseKey || null === $evaluation->matchedDomain) {
            return;
        }

        // Claim before deferred delivery so a timeout/failure is never retried this session.
        $session->set(self::SESSION_KEY, true);

        $this->gateway->sendSessionEntrySignal($evaluation->matchedDomain, $evaluation->record->licenseKey);
    }
}
