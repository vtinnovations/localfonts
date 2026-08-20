<?php

/*
 * Local Fonts
 *
 * Package: vtinnovations/localfonts
 * Copyright: V&T Innovations
 * Licence: LGPL-3.0-or-later
 * Website: https://www.v-t.one
 */

/*
 * All user-facing text for this bundle lives here (and in the German
 * counterpart under languages/de/). Diagnostic/log category codes such as
 * `domain_not_bound` are deliberately NOT translated here — those are safe,
 * generic identifiers for operational logs, not UI text (see
 * Documentation/Security.en.md, logging and privacy).
 */

$GLOBALS['TL_LANG']['local_fonts'] = [
    // ── Licence panel (Settings > Local Fonts Licence management) ──────────
    'licence_headline_active' => 'Lifetime Free licence active. All features unlocked.',
    'licence_headline_unlicensed' => 'Not licensed. No protected feature runs, the website stays unchanged.',
    'licence_unlicensed_notice' => '%s requires a free, activated V&amp;T Innovations licence (%s). Without activation, no protected feature runs.',
    'licence_masked_key_label' => 'Key:',
    'licence_package_label' => 'Package:',
    'licence_starts_label' => 'Valid from:',
    'licence_expires_label' => 'Valid until:',
    'licence_lifetime_value' => 'unlimited',
    'licence_checked_label' => 'Last verified:',
    'licence_key_label' => 'Licence key',
    'licence_activate_button' => 'Verify and Activate Licence',
    'licence_refresh_button' => 'Update Licence',
    'licence_remove_button' => 'Remove Licence',
    'licence_remove_confirm' => 'Remove the activated licence?',

    // ── Backend module (Layout > Local Fonts) ───────────────────────────────
    'module_licence_required' => 'Local Fonts requires a free, activated V&amp;T Innovations licence. Activate it under %s. Without activation, no fonts are downloaded or embedded on the front end.',
    'module_settings_path' => 'System &rsaquo; Settings &rsaquo; Local Fonts Licence management',
    'table_font' => 'Font',
    'table_variants' => 'Variants',
    'table_files' => 'Files',
    'font_family_unknown' => 'Unknown',
    'step_heading' => 'Step %s: %s',

    'step1_title' => 'Scan website',
    'step1_intro' => 'Scans all published pages for Google Fonts. No files are written yet.',
    'step1_none_yet' => 'No scan has been run yet.',
    'step1_summary' => '<strong>Last scan:</strong> %s &nbsp;|&nbsp; <strong>Pages:</strong> %s &nbsp;|&nbsp; <strong>Fonts found:</strong> %s',
    'step1_button_first' => 'Scan website',
    'step1_button_again' => 'Scan again',

    'step2_title' => 'Download fonts locally',
    'step2_intro' => 'Saves the font files under <code>files/localfonts/</code> and generates the stylesheet.',
    'step2_run_step1_first' => 'Run step 1 first.',
    'step2_none_downloaded' => 'Nothing downloaded yet. %s font(s) ready.',
    'step2_button_download' => 'Download fonts now',
    'step2_installed_summary' => '<strong>Installed locally:</strong> %s font(s), %s file(s)',
    'step2_as_of' => ' &nbsp;|&nbsp; <strong>As of:</strong> %s',
    'step2_button_redownload' => 'Download again / update',
    'step2_button_remove' => 'Remove local fonts',

    'step3_title' => 'Embed',
    'step3_intro' => 'Determines how the local stylesheet gets onto the page.',
    'step3_run_step2_first' => 'Run step 2 first.',
    'step3_current_label' => '<strong>Currently:</strong> %s',
    'step3_current_manual' => 'manual — <em>nothing</em> is embedded automatically.',
    'step3_current_auto' => 'automatic — the stylesheet is embedded on every page.',
    'step3_button_set_auto' => 'Embed automatically',
    'step3_button_set_manual' => 'Embed myself (copy CSS)',
    'step3_css_heading' => '<strong>CSS code to embed</strong> — copy in full, e.&nbsp;g. into your own stylesheet or under "Additional &lt;head&gt; tags" in the layout, as a <code>&lt;style&gt;</code> block. The font files are already stored locally under <code>files/localfonts/</code>:',
    'step3_css_link_alt' => 'If you prefer to link instead of copying, embed <code>%s</code> instead.',
    'step3_css_missing' => 'The generated stylesheet was not found. Please run step 2 again.',
    'step3_block_label' => '<strong>Block external Google Fonts:</strong> %s',
    'step3_block_active' => 'active',
    'step3_block_inactive' => 'inactive',
    'step3_block_description' => 'Removes remaining references to <code>fonts.googleapis.com</code> and <code>fonts.gstatic.com</code> from the front end.',
    'step3_block_warning' => 'Warning: external Google Fonts are being blocked, but the local stylesheet is not embedded automatically. Without the code above, fonts will be missing on the front end.',
    'step3_button_block_on' => 'Block external Google Fonts',
    'step3_button_block_off' => 'Turn off blocking',

    // ── Activation/refresh/remove outcomes (ActivationService message keys) ─
    'msg_no_key_entered' => 'No licence key was entered.',
    'msg_no_trusted_domain' => 'No trusted domain is configured for this installation.',
    'msg_no_license_activated' => 'No licence is currently activated.',
    'msg_no_key_stored' => 'No licence key is stored to refresh.',
    'msg_server_unavailable' => 'The licence server is temporarily unavailable. The previous licence state was preserved.',
    'msg_uncorrelated_response' => 'The licence server response could not be correlated with this request.',
    'msg_key_rejected' => 'The licence key was not accepted.',
    'msg_verification_failed' => 'The licence response failed verification.',
    'msg_stale_response' => 'The licence server returned an older licence than the one currently active.',
    'msg_domain_not_authorized' => 'The licence is not authorised for this installation\'s configured domain.',
    'msg_model_incompatible' => 'The licence server did not return a compatible Lifetime Free package.',
    'msg_save_failed' => 'The licence could not be saved.',
    'msg_activated' => 'Licence activated.',
    'msg_removed' => 'Licence removed. Local Fonts has returned to its default, unlicensed behaviour.',
    'error_forbidden' => 'Forbidden',
    'error_invalid_token' => 'Invalid security token',

    // ── CLI (localfonts:scan) ───────────────────────────────────────────────
    'cli_requires_license' => 'This plugin requires a free, activated V&T Innovations license. Get yours at %s.',
    'cli_scan_finished' => 'Local Fonts scan finished.',
    'cli_run_with_download' => 'Run again with --download to store the fonts locally.',
    'cli_fonts_installed' => 'Fonts downloaded and stylesheet generated.',

    // ── Scan/install/storage messages (rendered in the backend messages list) ─
    'crawler_no_pages' => 'No published regular pages were found.',
    'crawler_localhost_urls' => 'Page URLs were generated as "localhost". Run the scan from the backend, or set the domain on the website\'s root page (DNS) or via framework.router.request_context.host.',
    'crawler_stylesheet_unreadable' => 'Could not read stylesheet: %s',
    'crawler_page_unreadable' => 'Could not read page: %s',
    'crawler_no_stylesheets_found' => 'No Google Fonts stylesheets were found on the scanned pages.',
    'crawler_google_fonts_css_unreadable' => 'Could not read Google Fonts CSS: %s',
    'crawler_no_loadable_fonts' => 'Google Fonts stylesheets were found, but no loadable font files were detected.',

    'installer_no_detected_fonts' => 'No detected fonts. Please scan the website first.',
    'installer_save_failed' => 'Fonts could not be saved: %s',
    'installer_removed' => 'Local fonts have been removed.',

    'storage_dir_create_failed' => 'Could not create directory: %s',
    'storage_download_failed' => 'Download failed (%s): %s',
    'storage_file_write_failed' => 'Could not write file: %s',
    'storage_symlink_dir_failed' => 'Could not create symlink directory: %s',
    'storage_symlink_failed' => 'Could not create symlink %s (%s). Run "vendor/bin/contao-console contao:symlinks".',
];
