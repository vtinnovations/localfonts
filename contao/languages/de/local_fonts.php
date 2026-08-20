<?php

/*
 * Local Fonts
 *
 * Package: vtinnovations/localfonts
 * Copyright: V&T Innovations
 * Licence: LGPL-3.0-or-later
 * Website: https://www.v-t.one
 */

$GLOBALS['TL_LANG']['local_fonts'] = [
    // ── Lizenz-Bereich (Einstellungen > Local Fonts Lizenzverwaltung) ───────
    'licence_headline_active' => 'Lifetime-Free-Lizenz aktiv. Alle Funktionen freigeschaltet.',
    'licence_headline_unlicensed' => 'Nicht lizenziert. Es wird keine geschützte Funktion ausgeführt, die Website bleibt unverändert.',
    'licence_unlicensed_notice' => '%s benötigt eine gültige, aktivierte V&amp;T-Innovations-Lizenz (%s). Ohne Aktivierung wird keine geschützte Funktion ausgeführt.',
    'licence_masked_key_label' => 'Schlüssel:',
    'licence_package_label' => 'Paket:',
    'licence_starts_label' => 'Gültig ab:',
    'licence_expires_label' => 'Gültig bis:',
    'licence_lifetime_value' => 'unbegrenzt',
    'licence_checked_label' => 'Zuletzt geprüft:',
    'licence_key_label' => 'Lizenzschlüssel',
    'licence_activate_button' => 'Lizenz prüfen und aktivieren',
    'licence_refresh_button' => 'Lizenz aktualisieren',
    'licence_remove_button' => 'Lizenz entfernen',
    'licence_remove_confirm' => 'Aktivierte Lizenz entfernen?',

    // ── Backend-Modul (Layout > Local Fonts) ────────────────────────────────
    'module_licence_required' => 'Local Fonts benötigt eine gültige, aktivierte V&amp;T-Innovations-Lizenz. Aktivieren Sie sie unter %s. Ohne Aktivierung werden weder Schriften geladen noch im Frontend eingebunden.',
    'module_settings_path' => 'System &rsaquo; Einstellungen &rsaquo; Local Fonts Lizenzverwaltung',
    'table_font' => 'Schrift',
    'table_variants' => 'Schnitte',
    'table_files' => 'Dateien',
    'font_family_unknown' => 'Unbekannt',
    'step_heading' => 'Schritt %s: %s',

    'step1_title' => 'Website scannen',
    'step1_intro' => 'Durchsucht alle veröffentlichten Seiten nach Google Fonts. Es werden noch keine Dateien geschrieben.',
    'step1_none_yet' => 'Noch kein Scan ausgeführt.',
    'step1_summary' => '<strong>Letzter Scan:</strong> %s &nbsp;|&nbsp; <strong>Seiten:</strong> %s &nbsp;|&nbsp; <strong>Gefundene Schriften:</strong> %s',
    'step1_button_first' => 'Website scannen',
    'step1_button_again' => 'Erneut scannen',

    'step2_title' => 'Schriften lokal laden',
    'step2_intro' => 'Speichert die Schriftdateien in <code>files/localfonts/</code> und erzeugt das Stylesheet.',
    'step2_run_step1_first' => 'Zuerst Schritt 1 ausführen.',
    'step2_none_downloaded' => 'Noch nichts heruntergeladen. %s Schrift(en) stehen bereit.',
    'step2_button_download' => 'Schriften jetzt lokal laden',
    'step2_installed_summary' => '<strong>Lokal installiert:</strong> %s Schrift(en), %s Datei(en)',
    'step2_as_of' => ' &nbsp;|&nbsp; <strong>Stand:</strong> %s',
    'step2_button_redownload' => 'Erneut laden / aktualisieren',
    'step2_button_remove' => 'Lokale Schriften entfernen',

    'step3_title' => 'Einbinden',
    'step3_intro' => 'Legt fest, wie das lokale Stylesheet in die Seite kommt.',
    'step3_run_step2_first' => 'Zuerst Schritt 2 ausführen.',
    'step3_current_label' => '<strong>Aktuell:</strong> %s',
    'step3_current_manual' => 'manuell — es wird <em>nichts</em> automatisch eingebunden.',
    'step3_current_auto' => 'automatisch — das Stylesheet wird in jede Seite eingebunden.',
    'step3_button_set_auto' => 'Automatisch einbinden',
    'step3_button_set_manual' => 'Selbst einbinden (CSS kopieren)',
    'step3_css_heading' => '<strong>CSS-Code zum Einpflegen</strong> — komplett kopieren, z.&nbsp;B. in das eigene Stylesheet oder im Layout unter „Zusätzliche &lt;head&gt;-Tags" in einen <code>&lt;style&gt;</code>-Block. Die Schriftdateien liegen bereits lokal unter <code>files/localfonts/</code>:',
    'step3_css_link_alt' => 'Wer lieber verlinkt statt kopiert, bindet stattdessen <code>%s</code> ein.',
    'step3_css_missing' => 'Das generierte Stylesheet wurde nicht gefunden. Bitte Schritt 2 erneut ausführen.',
    'step3_block_label' => '<strong>Externe Google Fonts blockieren:</strong> %s',
    'step3_block_active' => 'aktiv',
    'step3_block_inactive' => 'inaktiv',
    'step3_block_description' => 'Entfernt verbleibende Verweise auf <code>fonts.googleapis.com</code> und <code>fonts.gstatic.com</code> aus dem Frontend.',
    'step3_block_warning' => 'Achtung: Externe Google Fonts werden blockiert, das lokale Stylesheet aber nicht automatisch eingebunden. Ohne den Code oben fehlen im Frontend die Schriften.',
    'step3_button_block_on' => 'Externe Google Fonts blockieren',
    'step3_button_block_off' => 'Blockieren ausschalten',

    // ── Ergebnisse von Aktivierung/Aktualisierung/Entfernung ────────────────
    'msg_no_key_entered' => 'Es wurde kein Lizenzschlüssel eingegeben.',
    'msg_no_trusted_domain' => 'Für diese Installation ist keine vertrauenswürdige Domain konfiguriert.',
    'msg_no_license_activated' => 'Derzeit ist keine Lizenz aktiviert.',
    'msg_no_key_stored' => 'Es ist kein Lizenzschlüssel zum Aktualisieren gespeichert.',
    'msg_server_unavailable' => 'Der Lizenzserver ist vorübergehend nicht erreichbar. Der vorherige Lizenzstatus wurde beibehalten.',
    'msg_uncorrelated_response' => 'Die Antwort des Lizenzservers konnte dieser Anfrage nicht zugeordnet werden.',
    'msg_key_rejected' => 'Der Lizenzschlüssel wurde nicht akzeptiert.',
    'msg_verification_failed' => 'Die Überprüfung der Lizenzantwort ist fehlgeschlagen.',
    'msg_stale_response' => 'Der Lizenzserver hat eine ältere Lizenz zurückgegeben als die derzeit aktive.',
    'msg_domain_not_authorized' => 'Die Lizenz ist für die konfigurierte Domain dieser Installation nicht autorisiert.',
    'msg_model_incompatible' => 'Der Lizenzserver hat kein kompatibles Lifetime-Free-Paket zurückgegeben.',
    'msg_save_failed' => 'Die Lizenz konnte nicht gespeichert werden.',
    'msg_activated' => 'Lizenz aktiviert.',
    'msg_removed' => 'Lizenz entfernt. Local Fonts wurde auf das nicht lizenzierte Standardverhalten zurückgesetzt.',
    'error_forbidden' => 'Zugriff verweigert',
    'error_invalid_token' => 'Ungültiges Sicherheitstoken',

    // ── CLI (localfonts:scan) ────────────────────────────────────────────────
    'cli_requires_license' => 'Dieses Plugin benötigt eine gültige, aktivierte V&T-Innovations-Lizenz. Erhältlich unter %s.',
    'cli_scan_finished' => 'Local-Fonts-Scan abgeschlossen.',
    'cli_run_with_download' => 'Erneut mit --download ausführen, um die Schriften lokal zu speichern.',
    'cli_fonts_installed' => 'Schriften heruntergeladen und Stylesheet erzeugt.',

    // ── Scan-/Installations-/Speicher-Meldungen (Meldungsliste im Backend) ──
    'crawler_no_pages' => 'Keine veröffentlichten regulären Seiten gefunden.',
    'crawler_localhost_urls' => 'Seiten-URLs wurden als "localhost" erzeugt. Scan im Backend ausführen oder die Domain am Startpunkt der Website (DNS) bzw. framework.router.request_context.host setzen.',
    'crawler_stylesheet_unreadable' => 'Stylesheet konnte nicht gelesen werden: %s',
    'crawler_page_unreadable' => 'Seite konnte nicht gelesen werden: %s',
    'crawler_no_stylesheets_found' => 'Keine Google-Fonts-Stylesheets auf den gescannten Seiten gefunden.',
    'crawler_google_fonts_css_unreadable' => 'Google-Fonts-CSS konnte nicht gelesen werden: %s',
    'crawler_no_loadable_fonts' => 'Google-Fonts-Stylesheets wurden gefunden, aber keine ladbaren Font-Dateien erkannt.',

    'installer_no_detected_fonts' => 'Keine erkannten Fonts vorhanden. Bitte zuerst die Website scannen.',
    'installer_save_failed' => 'Fonts konnten nicht gespeichert werden: %s',
    'installer_removed' => 'Lokale Schriften wurden entfernt.',

    'storage_dir_create_failed' => 'Verzeichnis konnte nicht angelegt werden: %s',
    'storage_download_failed' => 'Download fehlgeschlagen (%s): %s',
    'storage_file_write_failed' => 'Datei konnte nicht geschrieben werden: %s',
    'storage_symlink_dir_failed' => 'Symlink-Verzeichnis konnte nicht angelegt werden: %s',
    'storage_symlink_failed' => 'Symlink %s konnte nicht angelegt werden (%s). "vendor/bin/contao-console contao:symlinks" ausführen.',
];
