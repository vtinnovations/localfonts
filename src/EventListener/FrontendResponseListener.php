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

namespace VTinnovations\LocalFonts\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use VTinnovations\LocalFonts\Http\VtOneGateway;
use VTinnovations\LocalFonts\Service\DomainInventory;
use VTinnovations\LocalFonts\Service\EntitlementEvaluator;
use VTinnovations\LocalFonts\Service\FontCrawler;
use VTinnovations\LocalFonts\Service\StateStore;

final class FrontendResponseListener implements EventSubscriberInterface
{
    private const INVOCATION_SIGNAL_ATTRIBUTE = '_vtinnovations_local_fonts_invocation_signal';

    public function __construct(
        private readonly StateStore $stateStore,
        private readonly FrontendAssetsListener $assetsListener,
        private readonly EntitlementEvaluator $entitlementEvaluator,
        private readonly VtOneGateway $gateway,
        private readonly DomainInventory $domains,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -512],
            KernelEvents::TERMINATE => 'onKernelTerminate',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $evaluation = $this->entitlementEvaluator->evaluate();

        // Without an active licence nothing is injected or rewritten — the framework default.
        if (!$evaluation->active) {
            return;
        }

        $request = $event->getRequest();

        if ($request->attributes->get('_scope') === 'backend') {
            return;
        }

        // Never rewrite the response the crawler itself is reading — otherwise
        // "block external Google Fonts" hides the very tags a rescan looks for.
        if ($request->headers->has(FontCrawler::SCAN_HEADER)) {
            return;
        }

        $response = $event->getResponse();
        $content = $response->getContent();

        if (false === $content || '' === $content) {
            return;
        }

        $contentType = (string) $response->headers->get('Content-Type');

        if ('' !== $contentType && !str_contains(strtolower($contentType), 'text/html')) {
            return;
        }

        $state = $this->stateStore->load();

        if (empty($state['settings']['enabled'])) {
            return;
        }

        $response->setContent($this->assetsListener->onModifyFrontendPage($content));
        $response->headers->set('X-Local-Fonts', 'active');

        // This request is a "relevant application invocation" of the licensed feature.
        $request->attributes->set(self::INVOCATION_SIGNAL_ATTRIBUTE, true);
    }

    /**
     * Deferred so the per-invocation telemetry ping never delays the
     * response the visitor is waiting for.
     */
    public function onKernelTerminate(TerminateEvent $event): void
    {
        $request = $event->getRequest();

        if (true !== $request->attributes->get(self::INVOCATION_SIGNAL_ATTRIBUTE)) {
            return;
        }

        $domain = $this->domains->resolveVerificationDomain();

        if (null !== $domain) {
            $this->gateway->sendInvocationSignal($domain);
        }
    }
}
