<?php

/**
 * @package   vtinnovations/localfonts
 * @author    V&T Innovations
 * @license   LGPL-3.0-or-later
 * @copyright V&T Innovations 2026
 */

use VTinnovations\LocalFonts\Controller\Backend\LocalFontsModule;

$GLOBALS['BE_MOD']['design']['local_fonts'] = [
    'callback' => LocalFontsModule::class,
];
