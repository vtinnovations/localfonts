<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/localfonts
 * @author    V&T Innovations
 * @license   LGPL-3.0-or-later
 * @copyright V&T Innovations 2026
 */

namespace VTinnovations\LocalFonts\Config;

/**
 * Resolves the bundle's working paths under the configured scratch dir
 * (default var/localfonts). Holds the crawl state and the cached license
 * state — never commit this tree.
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

    public function licenseFile(): string
    {
        return $this->scratch() . '/license.json';
    }

    private function ensure(string $dir): string
    {
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Could not create directory "%s".', $dir));
        }

        return $dir;
    }
}
