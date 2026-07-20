<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/localfonts
 * @author    V&T Innovations
 * @license   LGPL-3.0-or-later
 * @copyright V&T Innovations 2026
 */

namespace VTinnovations\LocalFonts\Controller\Backend;

use Contao\BackendModule;
use Contao\Environment;
use Contao\Input;
use Contao\System;
use VTinnovations\LocalFonts\Security\LicenseManager;
use VTinnovations\LocalFonts\Service\LocalFontsManager;

/**
 * Backend module under "Layout > Local Fonts". Walks the operator through the
 * three steps — scan, download, embed — and never writes files or touches the
 * front end without an explicit click.
 */
final class LocalFontsModule extends BackendModule
{
    public function generate(): string
    {
        $formSubmit = (string) Input::post('FORM_SUBMIT');
        $action = (string) Input::post('localfonts_action');
        $licenseManager = $this->getLicenseManager();
        $licenseManager->refreshIfStale((string) Environment::get('host'));

        if (
            'POST' === ($_SERVER['REQUEST_METHOD'] ?? '')
            && 'tl_localfonts' === $formSubmit
            && '' !== $action
        ) {
            if ('save_license' === $action) {
                $licenseManager->activate((string) Input::post('license_key'), (string) Environment::get('host'));
                $this->redirect($this->getRedirectUrl($action));
            }

            if (!$licenseManager->isLicensed()) {
                $this->redirect($this->getRedirectUrl('no_license'));
            }

            System::getContainer()->get(LocalFontsManager::class)->handleBackendAction($action);
            $this->redirect($this->getRedirectUrl($action));
        }

        $manager = System::getContainer()->get(LocalFontsManager::class);
        [$tokenName, $token] = $this->getRequestToken();

        return $this->renderMarkup($manager->getState(), $manager->getGeneratedCss(), $licenseManager, $tokenName, $token);
    }

    protected function compile(): void
    {
    }

    private function getLicenseManager(): LicenseManager
    {
        return System::getContainer()->get(LicenseManager::class);
    }

    private function getRedirectUrl(string $action): string
    {
        $request = (string) Environment::get('request');
        $separator = str_contains($request, '?') ? '&' : '?';

        return $request . $separator . 'localfonts_done=' . rawurlencode($action) . '&t=' . time();
    }

    /**
     * @return array{0:string,1:string}
     */
    private function getRequestToken(): array
    {
        $container = System::getContainer();
        $tokenName = 'REQUEST_TOKEN';

        if ($container->has('contao.csrf.token_manager')) {
            $tokenManager = $container->get('contao.csrf.token_manager');

            if (method_exists($tokenManager, 'getDefaultTokenValue')) {
                return [$tokenName, (string) $tokenManager->getDefaultTokenValue()];
            }
        }

        if (class_exists('Contao\RequestToken') && method_exists('Contao\RequestToken', 'get')) {
            return [$tokenName, (string) \Contao\RequestToken::get()];
        }

        return [$tokenName, ''];
    }

    /**
     * @param array<string,mixed> $state
     */
    private function renderMarkup(array $state, string $generatedCss, LicenseManager $licenseManager, string $tokenName, string $token): string
    {
        $isLicensed = $licenseManager->isLicensed();
        $detected = $state['detected'] ?? [];
        $installed = $state['fonts'] ?? [];
        $pages = $state['pages'] ?? [];
        $messages = $state['messages'] ?? [];
        $localCss = (string) ($state['localCss'] ?? '/files/localfonts/localfonts.css');
        $removeExternal = !empty($state['settings']['removeExternalGoogleFonts']);
        $mode = (string) ($state['settings']['injectMode'] ?? LocalFontsManager::MODE_AUTO);
        $isManual = LocalFontsManager::MODE_MANUAL === $mode;
        $tokenField = '<input type="hidden" name="' . $this->esc($tokenName) . '" value="' . $this->esc($token) . '">';

        $html = '<div class="tl_listing_container localfonts-backend">';
        $html .= $this->renderLicensePanel($licenseManager, $isLicensed, $tokenField);

        if (!$isLicensed) {
            return $html . '</div>';
        }

        $html .= $this->renderMessages($messages);
        $html .= $this->renderStepScan($state, $detected, $pages, $tokenField);
        $html .= $this->renderStepDownload($state, $detected, $installed, $tokenField);
        $html .= $this->renderStepEmbed($installed, $isManual, $removeExternal, $localCss, $generatedCss, $tokenField);

        return $html . '</div>';
    }

    private function renderLicensePanel(LicenseManager $licenseManager, bool $isLicensed, string $tokenField): string
    {
        $expiresAt = $licenseManager->getExpiresAt();

        $status = $isLicensed
            ? '<span style="color:#5aa354;">aktiv</span>' . ($licenseManager->isBypassed() ? ' (Entwickler-Bypass)' : '')
            : '<span style="color:#c33;">nicht aktiv</span>';

        $notice = $isLicensed
            ? ''
            : '<p class="tl_error" style="margin-top:12px;">Dieses Plugin benötigt eine gültige Lizenz von V&amp;T Innovations '
                . '(<a href="https://www.v-t.one" target="_blank" rel="noopener">v-t.one</a>). '
                . 'Ohne Lizenz werden weder Schriften geladen noch im Frontend eingebunden.</p>';

        return '
  <div style="margin:0 0 20px;padding:14px;border:1px solid #3a3f49;border-radius:4px;">
    <form method="post">
      <input type="hidden" name="FORM_SUBMIT" value="tl_localfonts">
      ' . $tokenField . '
      <input type="hidden" name="localfonts_action" value="save_license">
      <label for="localfonts_license_key"><strong>Lizenzschlüssel (V&amp;T Innovations)</strong></label>
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:8px;">
        <input id="localfonts_license_key" type="text" name="license_key" value="' . $this->esc($licenseManager->getLicenseKey()) . '" class="tl_text" style="min-width:320px;">
        <button type="submit" class="tl_submit">Lizenz speichern</button>
      </div>
    </form>
    <p style="margin-top:10px;"><strong>Status:</strong> ' . $status
            . ' &nbsp;|&nbsp; <strong>Paket:</strong> ' . ($this->esc($licenseManager->getPackage()) ?: '-')
            . ' &nbsp;|&nbsp; <strong>Domain:</strong> ' . ($this->esc($licenseManager->getDomain()) ?: '-')
            . ' &nbsp;|&nbsp; <strong>Gültig bis:</strong> ' . (null !== $expiresAt && $expiresAt > 0 ? date('d.m.Y', $expiresAt) : 'unbegrenzt') . '</p>
    ' . $notice . '
  </div>';
    }

    /**
     * @param list<string> $messages
     */
    private function renderMessages(array $messages): string
    {
        if ([] === $messages) {
            return '';
        }

        $items = '';

        foreach ($messages as $message) {
            $items .= '<li>' . $this->esc((string) $message) . '</li>';
        }

        return '<div class="tl_info" style="margin-bottom:18px;"><ul style="margin:0;padding-left:18px;">' . $items . '</ul></div>';
    }

    /**
     * @param array<string,mixed> $state
     * @param array<string,mixed> $detected
     * @param list<string>        $pages
     */
    private function renderStepScan(array $state, array $detected, array $pages, string $tokenField): string
    {
        $lastScan = $state['lastScan'] ?? null;
        $rows = '';

        foreach ($detected as $font) {
            $rows .= '<tr><td>' . $this->esc((string) ($font['family'] ?? 'Unbekannt'))
                . '</td><td>' . $this->esc(implode(', ', $font['variants'] ?? []))
                . '</td><td>' . count($font['files'] ?? []) . '</td></tr>';
        }

        $table = '' !== $rows
            ? '<table class="tl_listing" style="margin-top:12px;"><thead><tr><th>Schrift</th><th>Schnitte</th><th>Dateien</th></tr></thead><tbody>' . $rows . '</tbody></table>'
            : '';

        $summary = null === $lastScan
            ? '<p>Noch kein Scan ausgeführt.</p>'
            : '<p><strong>Letzter Scan:</strong> ' . $this->esc((string) $lastScan)
                . ' &nbsp;|&nbsp; <strong>Seiten:</strong> ' . count($pages)
                . ' &nbsp;|&nbsp; <strong>Gefundene Schriften:</strong> ' . count($detected) . '</p>';

        return $this->step(
            1,
            'Website scannen',
            'Durchsucht alle veröffentlichten Seiten nach Google Fonts. Es werden noch keine Dateien geschrieben.',
            $summary . $table,
            $this->button('scan', null === $lastScan ? 'Website scannen' : 'Erneut scannen', $tokenField)
        );
    }

    /**
     * @param array<string,mixed> $state
     * @param array<string,mixed> $detected
     * @param array<string,mixed> $installed
     */
    private function renderStepDownload(array $state, array $detected, array $installed, string $tokenField): string
    {
        if ([] === $detected && [] === $installed) {
            return $this->step(2, 'Schriften lokal laden', 'Lädt die gefundenen Schriftdateien herunter und erzeugt das lokale Stylesheet.', '<p>Zuerst Schritt 1 ausführen.</p>', '');
        }

        $rows = '';
        $totalFiles = 0;

        foreach ($installed as $font) {
            $totalFiles += (int) ($font['files'] ?? 0);
            $rows .= '<tr><td>' . $this->esc((string) ($font['family'] ?? 'Unbekannt'))
                . '</td><td>' . $this->esc(implode(', ', $font['variants'] ?? []))
                . '</td><td>' . $this->esc((string) ($font['files'] ?? 0)) . '</td></tr>';
        }

        if ([] === $installed) {
            $body = '<p>Noch nichts heruntergeladen. ' . count($detected) . ' Schrift(en) stehen bereit.</p>';
            $buttons = $this->button('download', 'Schriften jetzt lokal laden', $tokenField);
        } else {
            $body = '<p><strong>Lokal installiert:</strong> ' . count($installed) . ' Schrift(en), ' . $totalFiles . ' Datei(en)'
                . (isset($state['lastDownload']) && null !== $state['lastDownload'] ? ' &nbsp;|&nbsp; <strong>Stand:</strong> ' . $this->esc((string) $state['lastDownload']) : '') . '</p>'
                . '<table class="tl_listing" style="margin-top:12px;"><thead><tr><th>Schrift</th><th>Schnitte</th><th>Dateien</th></tr></thead><tbody>' . $rows . '</tbody></table>';
            $buttons = $this->button('download', 'Erneut laden / aktualisieren', $tokenField)
                . $this->button('remove', 'Lokale Schriften entfernen', $tokenField);
        }

        return $this->step(2, 'Schriften lokal laden', 'Speichert die Schriftdateien in <code>files/localfonts/</code> und erzeugt das Stylesheet.', $body, $buttons);
    }

    /**
     * @param array<string,mixed> $installed
     */
    private function renderStepEmbed(array $installed, bool $isManual, bool $removeExternal, string $localCss, string $generatedCss, string $tokenField): string
    {
        if ([] === $installed) {
            return $this->step(3, 'Einbinden', 'Legt fest, wie das lokale Stylesheet in die Seite kommt.', '<p>Zuerst Schritt 2 ausführen.</p>', '');
        }

        $modeBox = '<p style="margin-bottom:10px;"><strong>Aktuell:</strong> '
            . ($isManual
                ? 'manuell — es wird <em>nichts</em> automatisch eingebunden.'
                : 'automatisch — das Stylesheet wird in jede Seite eingebunden.')
            . '</p>';

        $modeButtons = $isManual
            ? $this->button('set_mode_auto', 'Automatisch einbinden', $tokenField)
            : $this->button('set_mode_manual', 'Selbst einbinden (CSS kopieren)', $tokenField);

        $manualBox = '';

        if ($isManual) {
            $manualBox = '' !== $generatedCss
                ? '
      <p style="margin-top:14px;"><strong>CSS-Code zum Einpflegen</strong> — komplett kopieren, z.&nbsp;B. in das eigene Stylesheet oder im Layout unter „Zusätzliche &lt;head&gt;-Tags" in einen <code>&lt;style&gt;</code>-Block. Die Schriftdateien liegen bereits lokal unter <code>files/localfonts/</code>:</p>
      <textarea readonly class="tl_textarea" rows="18" style="width:100%;font-family:monospace;font-size:12px;white-space:pre;" onclick="this.select()">' . $this->esc($generatedCss) . '</textarea>
      <p style="margin-top:6px;color:#999;">Wer lieber verlinkt statt kopiert, bindet stattdessen <code>' . $this->esc($localCss) . '</code> ein.</p>'
                : '<p class="tl_error" style="margin-top:14px;">Das generierte Stylesheet wurde nicht gefunden. Bitte Schritt 2 erneut ausführen.</p>';
        }

        $blockWarning = $isManual && $removeExternal
            ? '<p class="tl_error" style="margin-top:12px;">Achtung: Externe Google Fonts werden blockiert, das lokale Stylesheet aber nicht automatisch eingebunden. '
                . 'Ohne den Code oben fehlen im Frontend die Schriften.</p>'
            : '';

        $blockBox = '
      <p style="margin-top:16px;"><strong>Externe Google Fonts blockieren:</strong> ' . ($removeExternal ? 'aktiv' : 'inaktiv') . '</p>
      <p style="margin:4px 0 8px;">Entfernt verbleibende Verweise auf <code>fonts.googleapis.com</code> und <code>fonts.gstatic.com</code> aus dem Frontend.</p>'
            . $blockWarning;

        return $this->step(
            3,
            'Einbinden',
            'Legt fest, wie das lokale Stylesheet in die Seite kommt.',
            $modeBox . $manualBox . $blockBox,
            $modeButtons . $this->button('toggle_remove_external', $removeExternal ? 'Blockieren ausschalten' : 'Externe Google Fonts blockieren', $tokenField)
        );
    }

    private function step(int $number, string $title, string $intro, string $body, string $buttons): string
    {
        return '
  <div style="margin-bottom:18px;padding:14px;border:1px solid #3a3f49;border-radius:4px;">
    <h2 style="margin:0 0 4px;">Schritt ' . $number . ': ' . $this->esc($title) . '</h2>
    <p style="margin:0 0 10px;color:#999;">' . $intro . '</p>
    ' . $body . '
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px;">' . $buttons . '</div>
  </div>';
    }

    private function button(string $action, string $label, string $tokenField): string
    {
        return '
      <form method="post" style="margin:0;">
        <input type="hidden" name="FORM_SUBMIT" value="tl_localfonts">
        ' . $tokenField . '
        <input type="hidden" name="localfonts_action" value="' . $this->esc($action) . '">
        <button type="submit" class="tl_submit">' . $this->esc($label) . '</button>
      </form>';
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
