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

namespace VTinnovations\LocalFonts\Config;

/**
 * Resolves the bundle's working paths under the configured scratch dir
 * (default var/localfonts). Holds the crawl state and the authenticated
 * entitlement state — never commit this tree.
 */
final class Paths
{
    private readonly string $scratchDir;

    public function __construct(string $scratchDir)
    {
        $this->scratchDir = rtrim($scratchDir, '/\\');
    }

    public function scratch(): string
    {
        return $this->ensure($this->scratchDir);
    }

    /**
     * Path only — deliberately does not create anything. Reads happen on the
     * frontend request path, where forcing a mkdir on a read-only or
     * unwritable deployment would turn a licensing concern into a site-wide
     * failure. Writers call {@see prepareEntitlementDir()} first.
     */
    private function entitlementDir(): string
    {
        return $this->scratchDir . '/entitlement';
    }

    public function prepareEntitlementDir(): string
    {
        return $this->ensure($this->ensure($this->scratchDir) . '/entitlement');
    }

    public function entitlementDocumentFile(): string
    {
        return $this->entitlementDir() . '/package.bin';
    }

    public function entitlementEnvelopeFile(): string
    {
        return $this->entitlementDir() . '/package.envelope.json';
    }

    public function entitlementBackupDocumentFile(): string
    {
        return $this->entitlementDir() . '/package.bin.bak';
    }

    public function entitlementBackupEnvelopeFile(): string
    {
        return $this->entitlementDir() . '/package.envelope.json.bak';
    }

    public function entitlementLockFile(): string
    {
        return $this->entitlementDir() . '/.lock';
    }

    public function replayLedgerFile(): string
    {
        return $this->entitlementDir() . '/exchange-journal.json';
    }

    private function ensure(string $dir): string
    {
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Could not create directory "%s".', $dir));
        }

        return $dir;
    }
}
