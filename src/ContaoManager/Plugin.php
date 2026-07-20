<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/localfonts
 * @author    V&T Innovations
 * @license   LGPL-3.0-or-later
 * @copyright V&T Innovations 2026
 */

namespace VTinnovations\LocalFonts\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use VTinnovations\LocalFonts\VtinnovationsLocalFontsBundle;

final class Plugin implements BundlePluginInterface
{
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(VtinnovationsLocalFontsBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class]),
        ];
    }
}
