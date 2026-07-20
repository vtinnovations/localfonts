<?php

declare(strict_types=1);

namespace VTinnovations\LocalFonts\Service;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Dbafs;

final class ContaoFileRegistry
{
    public function __construct(private readonly ContaoFramework $framework)
    {
    }

    public function registerPublicPath(string $absolutePath, string $projectDir): void
    {
        if (!is_file($absolutePath)) {
            return;
        }

        $relativePath = $this->toFilesPath($absolutePath, $projectDir);

        if (null === $relativePath || !class_exists(Dbafs::class)) {
            return;
        }

        try {
            $this->framework->initialize();
            $this->framework->getAdapter(Dbafs::class)->addResource($relativePath);
        } catch (\Throwable) {
        }
    }

    /**
     * Only files inside the Contao upload dir (<project>/files) belong in the
     * DBAFS. On installs where public/files is a real directory rather than a
     * symlink the fonts are written there instead — registering those would
     * create tl_files rows for paths that do not exist in the upload dir, so
     * they are skipped.
     */
    private function toFilesPath(string $absolutePath, string $projectDir): ?string
    {
        $path = str_replace('\\', '/', $absolutePath);
        $projectDir = str_replace('\\', '/', rtrim($projectDir, '/\\'));

        $filesBase = $projectDir . '/files/';

        if (str_starts_with($path, $filesBase)) {
            return 'files/' . ltrim(substr($path, strlen($filesBase)), '/');
        }

        return null;
    }
}
