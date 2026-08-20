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

namespace VTinnovations\LocalFonts\Tests\Service;

use PHPUnit\Framework\TestCase;
use VTinnovations\LocalFonts\Config\Paths;
use VTinnovations\LocalFonts\Service\ReplayLedger;

final class ReplayLedgerTest extends TestCase
{
    private string $scratchDir;
    private ReplayLedger $ledger;

    protected function setUp(): void
    {
        $this->scratchDir = sys_get_temp_dir() . '/lf_ledger_test_' . bin2hex(random_bytes(6));
        $this->ledger = new ReplayLedger(new Paths($this->scratchDir));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->scratchDir)) {
            $this->removeRecursive($this->scratchDir);
        }
    }

    public function testFindReturnsNullForAnUnknownRequestId(): void
    {
        self::assertNull($this->ledger->find('never-seen'));
    }

    public function testRecordedEntryCanBeFoundAgain(): void
    {
        $now = time();
        $this->ledger->record('req-1', 'nonce-1', 'sha256-of-body', 7, $now);

        $entry = $this->ledger->find('req-1');

        self::assertNotNull($entry);
        self::assertSame('sha256-of-body', $entry['body_sha256']);
        self::assertSame(7, $entry['applied_version']);
        self::assertSame($now, $entry['processed_at']);
    }

    public function testRecordNeverStoresTheRawNonce(): void
    {
        $this->ledger->record('req-1', 'super-secret-nonce', 'body-hash', 1, time());

        $raw = file_get_contents((new Paths($this->scratchDir))->replayLedgerFile());

        self::assertStringNotContainsString('super-secret-nonce', $raw);
    }

    public function testNonceSeenIsTrueOnlyAfterRecording(): void
    {
        self::assertFalse($this->ledger->nonceSeen('n1'));

        $this->ledger->record('req-1', 'n1', 'hash', 1, time());

        self::assertTrue($this->ledger->nonceSeen('n1'));
        self::assertFalse($this->ledger->nonceSeen('n2'));
    }

    public function testOldEntriesArePrunedOnTheNextWrite(): void
    {
        $old = time() - (3 * 86400); // outside the retention window
        $this->ledger->record('old-req', 'old-nonce', 'hash', 1, $old);

        self::assertNotNull($this->ledger->find('old-req'));

        $this->ledger->record('new-req', 'new-nonce', 'hash', 2, time());

        self::assertNull($this->ledger->find('old-req'));
        self::assertNotNull($this->ledger->find('new-req'));
    }

    public function testEntriesWithinRetentionSurviveAWrite(): void
    {
        $recent = time() - 3600;
        $this->ledger->record('recent-req', 'n1', 'hash', 1, $recent);
        $this->ledger->record('another-req', 'n2', 'hash', 2, time());

        self::assertNotNull($this->ledger->find('recent-req'));
    }

    private function removeRecursive(string $dir): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($dir);
    }
}
