<?php

/*
 * Local Fonts
 *
 * Package: vtinnovations/localfonts
 * Copyright: V&T Innovations
 * Licence: LGPL-3.0-or-later
 * Website: https://www.v-t.one
 */

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use VTinnovations\LocalFonts\EventListener\DataContainer\SettingsLicencePanelListener;

// Global (instance-wide) licence surface for this package, added to the
// existing Contao "Settings" screen so multiple V-T.ONE packages can each
// register their own section here without competing top-level modules.
//
// The legend is prepended and shared: every V-T.ONE package adds its field to
// the same "vtone_licence_legend" group, so all licence sections sit together
// in one fieldset at the top of the Settings screen, above Contao's own
// legends, with the package name shown as the field's own heading instead of
// a separate legend.
$GLOBALS['TL_DCA']['tl_settings']['fields']['vtinnovations_localfonts_licence'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_settings']['vtinnovations_localfonts_licence'],
    'input_field_callback' => [SettingsLicencePanelListener::class, 'render'],
    'eval' => ['tl_class' => 'clr'],
];

PaletteManipulator::create()
    ->addLegend('vtone_licence_legend', null, PaletteManipulator::POSITION_PREPEND)
    ->addField('vtinnovations_localfonts_licence', 'vtone_licence_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_settings')
;
