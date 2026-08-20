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
 * Atomic filesystem persistence for the one authoritative licence package:
 * the exact decoded document bytes plus their signed integrity envelope.
 * Never accepts a caller-supplied path and never touches application
 * source. A single instance guards both files under one exclusive lock so
 * they can never be left mismatched.
 */
final class EntitlementStore
{
    public function __construct(private readonly Paths $paths)
    {
    }

    /**
     * @return array{raw: string, envelope: array<string, mixed>}|null
     */
    public function current(): ?array
    {
        $documentFile = $this->paths->entitlementDocumentFile();
        $envelopeFile = $this->paths->entitlementEnvelopeFile();

        if (!is_file($documentFile) || !is_file($envelopeFile)) {
            return null;
        }

        $raw = file_get_contents($documentFile);
        $envelopeJson = file_get_contents($envelopeFile);

        if (false === $raw || false === $envelopeJson) {
            return null;
        }

        $envelope = json_decode($envelopeJson, true);

        if (!\is_array($envelope)) {
            return null;
        }

        return ['raw' => $raw, 'envelope' => $envelope];
    }

    /**
     * Activates a newly verified package as one logical transaction:
     * back up the previous valid pair, write the new pair to temporary
     * files on the same filesystem, atomically rename both into place,
     * then re-read and let the caller re-validate before committing to it.
     *
     * @param array<string, mixed> $envelope
     */
    public function activate(string $raw, array $envelope): void
    {
        $lock = $this->acquireLock();

        try {
            $documentFile = $this->paths->entitlementDocumentFile();
            $envelopeFile = $this->paths->entitlementEnvelopeFile();

            if (is_file($documentFile) && is_file($envelopeFile)) {
                @copy($documentFile, $this->paths->entitlementBackupDocumentFile());
                @copy($envelopeFile, $this->paths->entitlementBackupEnvelopeFile());
            }

            $envelopeJson = json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if (false === $envelopeJson) {
                throw new \RuntimeException('Unable to encode the integrity envelope.');
            }

            $tmpDocument = $documentFile . '.tmp';
            $tmpEnvelope = $envelopeFile . '.tmp';

            $this->writeExact($tmpDocument, $raw);
            $this->writeExact($tmpEnvelope, $envelopeJson);

            if (!@rename($tmpDocument, $documentFile) || !@rename($tmpEnvelope, $envelopeFile)) {
                @unlink($tmpDocument);
                @unlink($tmpEnvelope);

                throw new \RuntimeException('Unable to activate the licence package atomically.');
            }

            @chmod($documentFile, 0640);
            @chmod($envelopeFile, 0640);

            $reread = $this->current();

            if (null === $reread || $reread['raw'] !== $raw || !hash_equals($envelopeJson, (string) json_encode($reread['envelope'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))) {
                $this->restoreBackupUnlocked();

                throw new \RuntimeException('Post-activation verification failed; rolled back.');
            }
        } finally {
            $this->releaseLock($lock);
        }
    }

    /**
     * Removes the authoritative state and returns the package/scope to the
     * framework default. Does not touch the backup pair (rollback history
     * is only ever consulted by {@see activate()}).
     */
    public function remove(): void
    {
        $lock = $this->acquireLock();

        try {
            @unlink($this->paths->entitlementDocumentFile());
            @unlink($this->paths->entitlementEnvelopeFile());
        } finally {
            $this->releaseLock($lock);
        }
    }

    /**
     * @return resource
     */
    private function acquireLock()
    {
        $this->paths->prepareEntitlementDir();

        $handle = fopen($this->paths->entitlementLockFile(), 'c');

        if (false === $handle) {
            throw new \RuntimeException('Unable to open the entitlement lock file.');
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);

            throw new \RuntimeException('Unable to acquire the entitlement lock.');
        }

        return $handle;
    }

    /**
     * @param resource $handle
     */
    private function releaseLock($handle): void
    {
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    private function writeExact(string $file, string $bytes): void
    {
        $handle = fopen($file, 'wb');

        if (false === $handle) {
            throw new \RuntimeException(sprintf('Unable to open "%s" for writing.', $file));
        }

        if (false === fwrite($handle, $bytes)) {
            fclose($handle);

            throw new \RuntimeException(sprintf('Unable to write "%s".', $file));
        }

        fflush($handle);
        fclose($handle);
    }

    private function restoreBackupUnlocked(): void
    {
        $backupDocument = $this->paths->entitlementBackupDocumentFile();
        $backupEnvelope = $this->paths->entitlementBackupEnvelopeFile();

        if (is_file($backupDocument) && is_file($backupEnvelope)) {
            @copy($backupDocument, $this->paths->entitlementDocumentFile());
            @copy($backupEnvelope, $this->paths->entitlementEnvelopeFile());
        } else {
            @unlink($this->paths->entitlementDocumentFile());
            @unlink($this->paths->entitlementEnvelopeFile());
        }
    }
}
