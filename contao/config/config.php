<?php

/*
 * Local Fonts
 *
 * Package: vtinnovations/localfonts
 * Copyright: V&T Innovations
 * Licence: LGPL-3.0-or-later
 * Website: https://www.v-t.one
 */

use VTinnovations\LocalFonts\Controller\Backend\LocalFontsModule;

$GLOBALS['BE_MOD']['design']['local_fonts'] = [
    'callback' => LocalFontsModule::class,
];
