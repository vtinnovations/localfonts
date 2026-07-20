<?php

declare(strict_types=1);

namespace VTinnovations\LocalFonts\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use VTinnovations\LocalFonts\Security\LicenseGuard;
use VTinnovations\LocalFonts\Service\FontCrawler;
use VTinnovations\LocalFonts\Service\StateStore;

final class FrontendResponseListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly StateStore $stateStore,
        private readonly FrontendAssetsListener $assetsListener,
        private readonly LicenseGuard $licenseGuard,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -512],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // Paid-only: without a valid license nothing is injected or rewritten.
        if (!$this->licenseGuard->isLicensed()) {
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
    }
}
