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

namespace VTinnovations\LocalFonts\Controller\Backend;

use Contao\BackendModule;
use Contao\Environment;
use Contao\Input;
use Contao\System;
use VTinnovations\LocalFonts\Service\EntitlementEvaluator;
use VTinnovations\LocalFonts\Service\LocalFontsManager;

/**
 * Backend module under "Layout > Local Fonts". Walks the operator through the
 * three steps — scan, download, embed — and never writes files or touches the
 * front end without an explicit click. Licensing itself is managed on the
 * global "Settings" screen (see `contao/dca/tl_settings.php`); this module
 * only reads the resulting entitlement state.
 */
final class LocalFontsModule extends BackendModule
{
    public function generate(): string
    {
        System::loadLanguageFile('local_fonts');

        $formSubmit = (string) Input::post('FORM_SUBMIT');
        $action = (string) Input::post('localfonts_action');
        $evaluation = $this->getEntitlementEvaluator()->evaluate();

        if (
            'POST' === ($_SERVER['REQUEST_METHOD'] ?? '')
            && 'tl_localfonts' === $formSubmit
            && '' !== $action
        ) {
            if (!$evaluation->active) {
                $this->redirect($this->getRedirectUrl('no_license'));
            }

            System::getContainer()->get(LocalFontsManager::class)->handleBackendAction($action);
            $this->redirect($this->getRedirectUrl($action));
        }

        $manager = System::getContainer()->get(LocalFontsManager::class);
        [$tokenName, $token] = $this->getRequestToken();

        return $this->renderMarkup($manager->getState(), $manager->getGeneratedCss(), $evaluation->active, $tokenName, $token);
    }

    protected function compile(): void
    {
    }

    private function getEntitlementEvaluator(): EntitlementEvaluator
    {
        return System::getContainer()->get(EntitlementEvaluator::class);
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
    private function renderMarkup(array $state, string $generatedCss, bool $isLicensed, string $tokenName, string $token): string
    {
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
        $html .= $this->renderLicenseNotice($isLicensed);

        if (!$isLicensed) {
            return $html . '</div>';
        }

        $html .= $this->renderMessages($messages);
        $html .= $this->renderStepScan($state, $detected, $pages, $tokenField);
        $html .= $this->renderStepDownload($state, $detected, $installed, $tokenField);
        $html .= $this->renderStepEmbed($installed, $isManual, $removeExternal, $localCss, $generatedCss, $tokenField);

        return $html . '</div>';
    }

    private function renderLicenseNotice(bool $isLicensed): string
    {
        if ($isLicensed) {
            return '';
        }

        $lang = &$GLOBALS['TL_LANG']['local_fonts'];
        $path = '<strong>' . $lang['module_settings_path'] . '</strong>';

        return '<p class="tl_error" style="margin:0 0 18px;">' . \sprintf($lang['module_licence_required'], $path) . '</p>';
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
        $lang = &$GLOBALS['TL_LANG']['local_fonts'];
        $lastScan = $state['lastScan'] ?? null;
        $rows = '';

        foreach ($detected as $font) {
            $rows .= '<tr><td>' . $this->esc((string) ($font['family'] ?? $lang['font_family_unknown']))
                . '</td><td>' . $this->esc(implode(', ', $font['variants'] ?? []))
                . '</td><td>' . count($font['files'] ?? []) . '</td></tr>';
        }

        $table = '' !== $rows
            ? '<table class="tl_listing" style="margin-top:12px;"><thead><tr><th>' . $lang['table_font'] . '</th><th>' . $lang['table_variants'] . '</th><th>' . $lang['table_files'] . '</th></tr></thead><tbody>' . $rows . '</tbody></table>'
            : '';

        $summary = null === $lastScan
            ? '<p>' . $lang['step1_none_yet'] . '</p>'
            : '<p>' . \sprintf($lang['step1_summary'], $this->esc((string) $lastScan), count($pages), count($detected)) . '</p>';

        return $this->step(
            1,
            $lang['step1_title'],
            $lang['step1_intro'],
            $summary . $table,
            $this->button('scan', null === $lastScan ? $lang['step1_button_first'] : $lang['step1_button_again'], $tokenField)
        );
    }

    /**
     * @param array<string,mixed> $state
     * @param array<string,mixed> $detected
     * @param array<string,mixed> $installed
     */
    private function renderStepDownload(array $state, array $detected, array $installed, string $tokenField): string
    {
        $lang = &$GLOBALS['TL_LANG']['local_fonts'];

        if ([] === $detected && [] === $installed) {
            return $this->step(2, $lang['step2_title'], $lang['step2_intro'], '<p>' . $lang['step2_run_step1_first'] . '</p>', '');
        }

        $rows = '';
        $totalFiles = 0;

        foreach ($installed as $font) {
            $totalFiles += (int) ($font['files'] ?? 0);
            $rows .= '<tr><td>' . $this->esc((string) ($font['family'] ?? $lang['font_family_unknown']))
                . '</td><td>' . $this->esc(implode(', ', $font['variants'] ?? []))
                . '</td><td>' . $this->esc((string) ($font['files'] ?? 0)) . '</td></tr>';
        }

        if ([] === $installed) {
            $body = '<p>' . \sprintf($lang['step2_none_downloaded'], count($detected)) . '</p>';
            $buttons = $this->button('download', $lang['step2_button_download'], $tokenField);
        } else {
            $body = '<p>' . \sprintf($lang['step2_installed_summary'], count($installed), $totalFiles)
                . (isset($state['lastDownload']) && null !== $state['lastDownload'] ? \sprintf($lang['step2_as_of'], $this->esc((string) $state['lastDownload'])) : '') . '</p>'
                . '<table class="tl_listing" style="margin-top:12px;"><thead><tr><th>' . $lang['table_font'] . '</th><th>' . $lang['table_variants'] . '</th><th>' . $lang['table_files'] . '</th></tr></thead><tbody>' . $rows . '</tbody></table>';
            $buttons = $this->button('download', $lang['step2_button_redownload'], $tokenField)
                . $this->button('remove', $lang['step2_button_remove'], $tokenField);
        }

        return $this->step(2, $lang['step2_title'], $lang['step2_intro'], $body, $buttons);
    }

    /**
     * @param array<string,mixed> $installed
     */
    private function renderStepEmbed(array $installed, bool $isManual, bool $removeExternal, string $localCss, string $generatedCss, string $tokenField): string
    {
        $lang = &$GLOBALS['TL_LANG']['local_fonts'];

        if ([] === $installed) {
            return $this->step(3, $lang['step3_title'], $lang['step3_intro'], '<p>' . $lang['step3_run_step2_first'] . '</p>', '');
        }

        $modeBox = '<p style="margin-bottom:10px;">' . \sprintf($lang['step3_current_label'], $isManual ? $lang['step3_current_manual'] : $lang['step3_current_auto']) . '</p>';

        $modeButtons = $isManual
            ? $this->button('set_mode_auto', $lang['step3_button_set_auto'], $tokenField)
            : $this->button('set_mode_manual', $lang['step3_button_set_manual'], $tokenField);

        $manualBox = '';

        if ($isManual) {
            $manualBox = '' !== $generatedCss
                ? '
      <p style="margin-top:14px;">' . $lang['step3_css_heading'] . '</p>
      <textarea readonly class="tl_textarea" rows="18" style="width:100%;font-family:monospace;font-size:12px;white-space:pre;" onclick="this.select()">' . $this->esc($generatedCss) . '</textarea>
      <p style="margin-top:6px;color:#999;">' . \sprintf($lang['step3_css_link_alt'], $this->esc($localCss)) . '</p>'
                : '<p class="tl_error" style="margin-top:14px;">' . $lang['step3_css_missing'] . '</p>';
        }

        $blockWarning = $isManual && $removeExternal
            ? '<p class="tl_error" style="margin-top:12px;">' . $lang['step3_block_warning'] . '</p>'
            : '';

        $blockBox = '
      <p style="margin-top:16px;">' . \sprintf($lang['step3_block_label'], $removeExternal ? $lang['step3_block_active'] : $lang['step3_block_inactive']) . '</p>
      <p style="margin:4px 0 8px;">' . $lang['step3_block_description'] . '</p>'
            . $blockWarning;

        return $this->step(
            3,
            $lang['step3_title'],
            $lang['step3_intro'],
            $modeBox . $manualBox . $blockBox,
            $modeButtons . $this->button('toggle_remove_external', $removeExternal ? $lang['step3_button_block_off'] : $lang['step3_button_block_on'], $tokenField)
        );
    }

    private function step(int $number, string $title, string $intro, string $body, string $buttons): string
    {
        $lang = &$GLOBALS['TL_LANG']['local_fonts'];

        return '
  <div style="margin-bottom:18px;padding:14px;border:1px solid #3a3f49;border-radius:4px;">
    <h2 style="margin:0 0 4px;">' . \sprintf($lang['step_heading'], $number, $this->esc($title)) . '</h2>
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
