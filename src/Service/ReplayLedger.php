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

namespace VTinnovations\LocalFonts\Service;

use VTinnovations\LocalFonts\Config\Paths;

/**
 * Bounded replay/idempotency journal for the inbound updater endpoint. Keyed
 * by request ID; stores only a nonce digest, a body fingerprint and the
 * applied version — never the packet itself. A single-node file store with
 * exclusive locking; swap for a shared transactional store in a clustered
 * deployment.
 */
final class ReplayLedger
{
    private const RETENTION_SECONDS = 2 * 86400;

    public function __construct(private readonly Paths $paths)
    {
    }

    /**
     * @return array{nonce_digest: string, body_sha256: string, applied_version: int, processed_at: int}|null
     */
    public function find(string $requestId): ?array
    {
        return $this->load()[$requestId] ?? null;
    }

    public function record(string $requestId, string $nonce, string $bodySha256, int $appliedVersion, int $now): void
    {
        $handle = $this->lock();

        try {
            $ledger = $this->load();
            $ledger[$requestId] = [
                'nonce_digest' => hash('sha256', $nonce),
                'body_sha256' => $bodySha256,
                'applied_version' => $appliedVersion,
                'processed_at' => $now,
            ];

            foreach ($ledger as $id => $entry) {
                if (($entry['processed_at'] ?? 0) < $now - self::RETENTION_SECONDS) {
                    unset($ledger[$id]);
                }
            }

            $this->write($ledger);
        } finally {
            $this->unlock($handle);
        }
    }

    public function nonceSeen(string $nonce): bool
    {
        $digest = hash('sha256', $nonce);

        foreach ($this->load() as $entry) {
            if (hash_equals($digest, (string) ($entry['nonce_digest'] ?? ''))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, array{nonce_digest: string, body_sha256: string, applied_version: int, processed_at: int}>
     */
    private function load(): array
    {
        $file = $this->paths->replayLedgerFile();

        if (!is_file($file)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($file), true);

        return \is_array($data) ? $data : [];
    }

    /**
     * @param array<string, array{nonce_digest: string, body_sha256: string, applied_version: int, processed_at: int}> $ledger
     */
    private function write(array $ledger): void
    {
        $json = json_encode($ledger, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (false === $json) {
            return;
        }

        $file = $this->paths->replayLedgerFile();
        $tmp = $file . '.tmp';

        if (false !== file_put_contents($tmp, $json)) {
            @rename($tmp, $file);
        } else {
            @unlink($tmp);
        }
    }

    /**
     * @return resource
     */
    private function lock()
    {
        $this->paths->prepareEntitlementDir();

        $handle = fopen($this->paths->replayLedgerFile() . '.lock', 'c');

        if (false === $handle) {
            throw new \RuntimeException('Unable to open the replay-ledger lock file.');
        }

        flock($handle, LOCK_EX);

        return $handle;
    }

    /**
     * @param resource $handle
     */
    private function unlock($handle): void
    {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
