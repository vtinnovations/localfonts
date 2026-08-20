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
 * Frozen product parameters registered with V-T.ONE for this bundle. This is
 * the "Lifetime Free" licence model: the product is free of charge but still
 * requires a successfully activated, signed V-T.ONE licence — there is no
 * anonymous or unlicensed mode.
 */
final class ProductProfile
{
    public const PROJECT = 'Local Fonts';

    public const PROJECT_SLUG = 'localfonts';

    public const PRODUCT_ID = 'vt-localfonts';

    /** Only package value accepted under the Lifetime Free model. */
    public const ACCEPTED_PACKAGE = 'free';

    public const UPDATER_PATH = '/rest/api/v1/' . self::PROJECT_SLUG . '-license-updater';

    public const VERIFY_ENDPOINT = 'https://www.v-t.one/api/v1/verify';

    public const SIGNAL_ENDPOINT = 'https://www.v-t.one/rest/api/v1/log-envoke';
}
