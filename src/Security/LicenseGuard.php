<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/localfonts
 * @author    V&T Innovations
 * @license   LGPL-3.0-or-later
 * @copyright V&T Innovations 2026
 */

namespace VTinnovations\LocalFonts\Security;

/**
 * Single-gate helper injected where the paid feature is guarded (the backend
 * module actions, the scan command and the front end injection). Paid-only
 * product, so there is one gate: isLicensed().
 */
final class LicenseGuard
{
    public function __construct(private readonly LicenseManager $licenseManager)
    {
    }

    public function isLicensed(): bool
    {
        return $this->licenseManager->isLicensed();
    }
}
