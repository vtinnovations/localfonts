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

use Contao\BackendUser;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use VTinnovations\LocalFonts\Service\ActivationService;

/**
 * The single authenticated local action for this package's global licence
 * section: activate, refresh (`action=refresh`) and remove. Reachable only
 * for an authenticated Contao backend user with a valid CSRF token; the
 * result always redirects back to the Settings screen so the section
 * re-renders the freshly evaluated state.
 */
#[Route('/vtone-packages/localfonts/{action}', name: 'vtinnovations_localfonts_licence_action', defaults: ['_scope' => 'backend'], requirements: ['action' => 'activate|refresh|remove'], methods: ['POST'])]
final class PackageLicenceActionController
{
    public function __construct(
        private readonly ActivationService $activation,
        // Contao's own cookie-based manager — the generic Symfony CSRF interface
        // autowires to session-based `security.csrf.token_manager`, which the
        // blanket RequestTokenListener never checks against.
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly string $csrfTokenName,
        private readonly UrlGeneratorInterface $router,
        private readonly Security $security,
        private readonly ContaoFramework $framework,
    ) {
    }

    public function __invoke(Request $request, string $action): Response
    {
        $this->framework->initialize();
        System::loadLanguageFile('local_fonts');
        $lang = &$GLOBALS['TL_LANG']['local_fonts'];

        $user = $this->security->getUser();

        // Contao's Settings screen that hosts this section is admin-only, so the
        // action must be too: a plain authenticated back end user must not be
        // able to activate or revoke the instance licence by posting directly.
        if (!$user instanceof BackendUser || !$user->isAdmin) {
            return new Response($lang['error_forbidden'], Response::HTTP_FORBIDDEN);
        }

        // Contao's own RequestTokenListener already validates every backend POST
        // against this same canonical token name; this is an explicit, auditable
        // repeat of that check at the feature boundary, not a competing scheme.
        $token = new CsrfToken($this->csrfTokenName, (string) $request->request->get('REQUEST_TOKEN', ''));

        if (!$this->csrfTokenManager->isTokenValid($token)) {
            return new Response($lang['error_invalid_token'], Response::HTTP_FORBIDDEN);
        }

        $result = match ($action) {
            'activate' => $this->activation->activate((string) $request->request->get('license_key', '')),
            'refresh' => $this->activation->refresh(),
            'remove' => $this->activation->remove(),
        };

        if ($request->hasSession()) {
            $message = $lang['msg_' . $result['messageKey']] ?? $result['messageKey'];

            $flashBag = $request->getSession()->getFlashBag();
            $flashBag->add(true === $result['ok'] ? 'contao.BE.confirm' : 'contao.BE.error', $message);
        }

        return new RedirectResponse($this->router->generate('contao_backend', ['do' => 'settings']));
    }
}
