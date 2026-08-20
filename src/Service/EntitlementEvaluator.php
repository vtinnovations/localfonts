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
 * Re-authenticates the persisted package on every read (envelope signature,
 * exact-byte MD5 and document signature are re-checked, not just trusted
 * from the last write) and applies the Lifetime Free model plus the exact
 * signed-domain intersection against the current trusted inventory. This is
 * the shared decision point every feature boundary consults, but it is not
 * itself the enforcement — each caller still gates its own operation.
 */
final class EntitlementEvaluator
{
    public function __construct(
        private readonly EntitlementStore $store,
        private readonly EntitlementVerifier $verifier,
        private readonly DomainInventory $domains,
    ) {
    }

    /**
     * Never throws. This runs on the frontend response path, so a storage,
     * configuration or crypto fault must degrade to "unlicensed" — which
     * restores stock Contao behaviour — rather than turning a licensing
     * problem into a site-wide 500.
     */
    public function evaluate(): EntitlementEvaluation
    {
        try {
            return $this->doEvaluate();
        } catch (\Throwable) {
            return EntitlementEvaluation::unlicensed();
        }
    }

    private function doEvaluate(): EntitlementEvaluation
    {
        $current = $this->store->current();

        if (null === $current) {
            return EntitlementEvaluation::unlicensed();
        }

        $result = $this->verifier->openEnvelope($current['envelope'], base64_encode($current['raw']), time());

        if (!$result->ok || null === $result->record) {
            return EntitlementEvaluation::unlicensed();
        }

        $record = $result->record;

        if (null !== $this->verifier->checkLifetimeFreeModel($record, time())) {
            return EntitlementEvaluation::invalid($record);
        }

        // The authoritative match is an exact intersection between this
        // installation's trusted configured hosts and the signed domain set.
        // `license_domain` alone is not enough: it records the host used for
        // the last verification operation, which may belong to a different
        // installation the same signed package was issued for.
        $matchedDomain = $this->verifier->resolveMatchedDomain(
            $record,
            $this->domains->trustedInventory(),
            $this->domains->currentTrustedHost(),
        );

        if (null === $matchedDomain) {
            return EntitlementEvaluation::invalid($record);
        }

        return EntitlementEvaluation::valid($record, $matchedDomain);
    }
}
