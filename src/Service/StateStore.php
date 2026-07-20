<?php

declare(strict_types=1);

namespace VTinnovations\LocalFonts\Service;

final class StateStore
{
    public function __construct(private readonly string $projectDir)
    {
    }

    /**
     * @return array<string,mixed>
     */
    public function load(): array
    {
        $file = $this->getStateFile();

        if (!is_file($file)) {
            return $this->defaultState();
        }

        $data = json_decode((string) file_get_contents($file), true);

        if (!is_array($data)) {
            return $this->defaultState();
        }

        return array_replace_recursive($this->defaultState(), $data);
    }

    /**
     * @param array<string,mixed> $state
     */
    public function save(array $state): void
    {
        $dir = dirname($this->getStateFile());

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($this->getStateFile(), json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function getStateFile(): string
    {
        return $this->projectDir . '/var/localfonts/state.json';
    }

    /**
     * @return array<string,mixed>
     */
    private function defaultState(): array
    {
        return [
            'lastScan' => null,
            'lastDownload' => null,
            'localCss' => '/files/localfonts/localfonts.css',
            // What the last scan found (incl. the source URLs), not yet downloaded.
            'detected' => [],
            // What is actually installed locally.
            'fonts' => [],
            'cleanedCss' => [],
            'pages' => [],
            'messages' => [],
            'settings' => [
                'enabled' => true,
                'fontDisplay' => 'swap',
                'removeExternalGoogleFonts' => false,
                // auto = stylesheet is injected; manual = operator embeds it himself.
                'injectMode' => 'auto',
            ],
        ];
    }
}
