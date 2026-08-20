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

namespace VTinnovations\LocalFonts\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use VTinnovations\LocalFonts\Service\ActivationService;

/**
 * Daily administrator-style refresh so a revoked or updated package takes
 * effect without anyone re-entering the key. Only re-checks a stale
 * package; a transient server error preserves the previously valid state.
 */
#[AsCronJob('daily')]
final class EntitlementRefreshCron
{
    public function __construct(private readonly ActivationService $activation)
    {
    }

    public function __invoke(): void
    {
        $this->activation->refreshIfStale();
    }
}
