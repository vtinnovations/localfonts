<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/localfonts
 * @author    V&T Innovations
 * @license   LGPL-3.0-or-later
 * @copyright V&T Innovations 2026
 */

namespace VTinnovations\LocalFonts\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Symfony\Component\HttpFoundation\RequestStack;
use VTinnovations\LocalFonts\Security\LicenseManager;

/**
 * Daily re-verification against the license server so a revoked or expired key
 * takes effect without the operator re-entering it. Only re-checks when the
 * cache is older than a day; a transient server error keeps the grace window.
 */
#[AsCronJob('daily')]
final class LicenseRefreshCron
{
    public function __construct(
        private readonly LicenseManager $licenseManager,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function __invoke(): void
    {
        if (!$this->licenseManager->isCacheStale()) {
            return;
        }

        $request = $this->requestStack->getMainRequest();
        $domain = $request?->getHost() ?? '';

        $this->licenseManager->refresh($domain);
    }
}
