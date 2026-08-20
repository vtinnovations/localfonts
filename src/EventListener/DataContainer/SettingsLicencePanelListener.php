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

namespace VTinnovations\LocalFonts\EventListener\DataContainer;

use Contao\Config;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\DataContainer;
use Contao\Date;
use Contao\System;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use VTinnovations\LocalFonts\Config\ProductProfile;
use VTinnovations\LocalFonts\Service\EntitlementEvaluation;
use VTinnovations\LocalFonts\Service\EntitlementEvaluator;
use VTinnovations\LocalFonts\Service\EntitlementRecord;
use VTinnovations\LocalFonts\Service\SessionEntrySignal;

/**
 * Renders the sole global licensing surface for this package: a section on
 * Contao's own "Settings" screen. Registered as the `tl_settings`
 * `vtinnovations_localfonts_licence` `input_field_callback` — see
 * `contao/dca/tl_settings.php`. This is the module's authoritative entry
 * point, so it also fires the mandatory once-per-session module-entry
 * signal.
 *
 * The section deliberately mirrors the sibling V-T.ONE products' licence
 * sections on the same Settings page (a coloured status headline, one dense
 * "·"-separated detail line, the key field, then the actions in a single row)
 * so all packages present one visual language.
 *
 * Everything is server-rendered from the state resolved on this request, and
 * the three controls are plain submit buttons that use HTML's own `formaction`
 * to re-point the surrounding settings form at this package's licence route.
 * A nested `<form>` would be invalid HTML here and is silently dropped by
 * every browser, which would leave the buttons submitting Contao's settings
 * form instead of the action they name — hence no nested form and no custom
 * JavaScript.
 */
final class SettingsLicencePanelListener
{
    public function __construct(
        private readonly EntitlementEvaluator $evaluator,
        private readonly SessionEntrySignal $sessionSignal,
        private readonly UrlGeneratorInterface $router,
        // Contao's own cookie-based manager — the generic Symfony CSRF interface
        // autowires to session-based `security.csrf.token_manager`, which the
        // blanket RequestTokenListener never checks against.
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly string $csrfTokenName,
    ) {
    }

    /**
     * The whole section body. Contao passes the data container and an extra
     * label fragment; neither is needed, but the signature must tolerate them.
     */
    public function render(?DataContainer $dc = null, string $xlabel = ''): string
    {
        System::loadLanguageFile('local_fonts');

        $this->sessionSignal->notifyOnFirstEntry();

        $evaluation = $this->evaluator->evaluate();
        // Contao's own RequestTokenListener validates every backend POST against this
        // one canonical token name — a package-specific token id would never validate.
        $token = $this->csrfTokenManager->getToken($this->csrfTokenName)->getValue();

        return '<div class="widget vtinnovations-localfonts-licence" style="max-width:640px">'
            . '<h3>' . $this->esc($GLOBALS['TL_LANG']['tl_settings']['vtinnovations_localfonts_licence'][0] ?? 'Local Fonts') . '</h3>'
            . '<div style="padding:12px 15px;border:1px solid var(--content-border);border-radius:4px;background:var(--content-bg)">'
            . $this->renderStatus($evaluation)
            . $this->renderControls($evaluation, $token)
            . '</div></div>';
    }

    private function renderStatus(EntitlementEvaluation $evaluation): string
    {
        $lang = &$GLOBALS['TL_LANG']['local_fonts'];

        // A stored licence that no longer verifies is presented exactly like
        // "never activated" — the backend never exposes actionable detail
        // about why verification failed.
        if (!$evaluation->active || null === $evaluation->record) {
            $link = '<a href="https://www.v-t.one" target="_blank" rel="noopener">v-t.one</a>';

            return $this->renderHeadline('var(--red)', $lang['licence_headline_unlicensed'])
                . '<div class="tl_gray" style="font-size:12px;line-height:1.7">'
                . \sprintf($lang['licence_unlicensed_notice'], $this->esc(ProductProfile::PROJECT), $link)
                . '</div>';
        }

        return $this->renderHeadline('var(--green)', $lang['licence_headline_active'])
            . $this->renderDetailLine($evaluation->record);
    }

    private function renderHeadline(string $colour, string $label): string
    {
        return \sprintf(
            '<div style="font-size:15px;font-weight:bold;color:%s;margin-bottom:4px">%s</div>',
            $colour,
            $this->esc($label),
        );
    }

    /**
     * The five facts every V-T.ONE section on this screen shows and no more:
     * which licence, which package, since when, until when, last verified when.
     * The bound domain, the signed domain set, the allowance and the document
     * version were dropped — record internals nobody acts on from this screen.
     *
     * The key appears masked at both ends only. That is enough to tell one
     * stored licence from another, which is all this line is for; the full key
     * is never rendered.
     */
    private function renderDetailLine(EntitlementRecord $record): string
    {
        $lang = &$GLOBALS['TL_LANG']['local_fonts'];

        $parts = [
            $lang['licence_masked_key_label'] . ' ' . $this->maskedKey($record->licenseKey),
            $lang['licence_package_label'] . ' ' . strtoupper($record->licensePackage),
            $lang['licence_starts_label'] . ' ' . $this->moment($record->licenseStartsAt),
            $lang['licence_expires_label'] . ' ' . ($record->licenseLifetime ? $lang['licence_lifetime_value'] : $this->moment($record->licenseExpiresAt)),
            $lang['licence_checked_label'] . ' ' . $this->moment($record->licenseVerifiedAt),
        ];

        return '<div class="tl_gray" style="font-size:12px;line-height:1.7">' . $this->esc(implode(' · ', $parts)) . '</div>';
    }

    /**
     * Four leading and four trailing characters around a fixed-width mask. A key
     * too short to keep both ends recognisable is masked whole: half of a short
     * key is not a hint, it is the key.
     */
    private function maskedKey(string $key): string
    {
        $key = trim($key);
        $mask = str_repeat('•', 8);

        if ('' === $key) {
            return '—';
        }

        if (mb_strlen($key) <= 8) {
            return $mask;
        }

        return mb_substr($key, 0, 4) . $mask . mb_substr($key, -4);
    }

    private function moment(?int $timestamp): string
    {
        if (null === $timestamp || $timestamp <= 0) {
            return '—';
        }

        return Date::parse((string) Config::get('datimFormat'), $timestamp);
    }

    /**
     * The key field plus the three actions in a single row. Refresh and remove
     * only appear once there is an active licence to act on.
     */
    private function renderControls(EntitlementEvaluation $evaluation, string $token): string
    {
        $lang = &$GLOBALS['TL_LANG']['local_fonts'];

        $html = '<label for="vtinnovations_localfonts_license_key" style="display:block;margin:12px 0 4px"><strong>'
            . $this->esc($lang['licence_key_label']) . '</strong></label>'
            . '<input type="text" name="license_key" id="vtinnovations_localfonts_license_key"'
            . ' autocomplete="off" spellcheck="false" maxlength="190" value=""'
            . ' style="width:100%;padding:6px;box-sizing:border-box"'
            . ' placeholder="XXXXX-XXXXX-XXXXX-XXXXX">';

        $html .= '<div class="vtinnovations-localfonts-licence-actions" style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">'
            . $this->renderButton('activate', $lang['licence_activate_button'], $token);

        if ($evaluation->active) {
            $html .= $this->renderButton('refresh', $lang['licence_refresh_button'], $token)
                . $this->renderButton('remove', $lang['licence_remove_button'], $token, $lang['licence_remove_confirm']);
        }

        return $html . '</div>';
    }

    private function renderButton(string $action, string $label, string $token, ?string $confirm = null): string
    {
        // The token rides on the button itself as well as on the surrounding
        // settings form, so the POST carries a valid one either way.
        return \sprintf(
            '<button type="submit" class="tl_submit" name="REQUEST_TOKEN" value="%s" formmethod="post" formaction="%s"%s>%s</button>',
            $this->esc($token),
            $this->esc($this->actionUrl($action)),
            null !== $confirm ? ' onclick="return confirm(' . $this->esc(json_encode($confirm, JSON_THROW_ON_ERROR)) . ')"' : '',
            $this->esc($label),
        );
    }

    private function actionUrl(string $action): string
    {
        return $this->router->generate('vtinnovations_localfonts_licence_action', ['action' => $action]);
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
