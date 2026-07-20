<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/localfonts
 * @author    V&T Innovations
 * @license   LGPL-3.0-or-later
 * @copyright V&T Innovations 2026
 */

namespace VTinnovations\LocalFonts\Service;

final class LocalFontsManager
{
    /** Stylesheet is injected into every page automatically. */
    public const MODE_AUTO = 'auto';

    /** Nothing is injected; the operator embeds the snippet themselves. */
    public const MODE_MANUAL = 'manual';

    public function __construct(
        private readonly FontCrawler $crawler,
        private readonly FontInstaller $installer,
        private readonly StateStore $stateStore,
    ) {
    }

    public function handleBackendAction(string $action): void
    {
        switch ($action) {
            case 'scan':
                $this->crawler->scan();
                break;

            case 'download':
                $this->installer->install();
                break;

            case 'remove':
                $this->installer->remove();
                break;

            case 'set_mode_auto':
                $this->setSetting('injectMode', self::MODE_AUTO);
                break;

            case 'set_mode_manual':
                $this->setSetting('injectMode', self::MODE_MANUAL);
                break;

            case 'toggle_remove_external':
                $state = $this->stateStore->load();
                $this->setSetting('removeExternalGoogleFonts', empty($state['settings']['removeExternalGoogleFonts']));
                break;
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function getState(): array
    {
        return $this->stateStore->load();
    }

    public function isInstalled(): bool
    {
        return [] !== ($this->stateStore->load()['fonts'] ?? []);
    }

    public function getGeneratedCss(): string
    {
        return $this->installer->getGeneratedCss();
    }

    private function setSetting(string $key, mixed $value): void
    {
        $state = $this->stateStore->load();
        $state['settings'][$key] = $value;
        $this->stateStore->save($state);
    }
}
