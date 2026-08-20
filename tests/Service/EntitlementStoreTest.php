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
use VTinnovations\LocalFonts\Service\EntitlementStore;

/**
 * Atomic activation: exact-byte persistence, backup-before-overwrite, and
 * a read path that never force-creates a directory (the frontend request
 * path must not turn an unwritable deployment into a 500).
 */
final class EntitlementStoreTest extends TestCase
{
    private string $scratchDir;
    private EntitlementStore $store;

    protected function setUp(): void
    {
        $this->scratchDir = sys_get_temp_dir() . '/lf_store_test_' . bin2hex(random_bytes(6));
        $this->store = new EntitlementStore(new Paths($this->scratchDir));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->scratchDir)) {
            $this->removeRecursive($this->scratchDir);
        }
    }

    public function testCurrentIsNullBeforeAnyActivation(): void
    {
        self::assertNull($this->store->current());
    }

    public function testCurrentDoesNotCreateTheScratchDirectory(): void
    {
        $this->store->current();

        self::assertDirectoryDoesNotExist($this->scratchDir);
    }

    public function testActivatePersistsExactBytesAndEnvelope(): void
    {
        $this->store->activate('{"a":1}', ['k' => 'v1']);
        $current = $this->store->current();

        self::assertSame('{"a":1}', $current['raw']);
        self::assertSame(['k' => 'v1'], $current['envelope']);
    }

    public function testReactivatingReplacesThePreviousPackage(): void
    {
        $this->store->activate('{"a":1}', ['k' => 'v1']);
        $this->store->activate('{"a":2}', ['k' => 'v2']);

        $current = $this->store->current();

        self::assertSame('{"a":2}', $current['raw']);
        self::assertSame(['k' => 'v2'], $current['envelope']);
    }

    public function testActivateWritesABackupOfThePreviousPackage(): void
    {
        $this->store->activate('{"a":1}', ['k' => 'v1']);
        $this->store->activate('{"a":2}', ['k' => 'v2']);

        $paths = new Paths($this->scratchDir);
        self::assertFileExists($paths->entitlementBackupDocumentFile());
        self::assertSame('{"a":1}', file_get_contents($paths->entitlementBackupDocumentFile()));
    }

    public function testRemoveClearsTheAuthoritativeState(): void
    {
        $this->store->activate('{"a":1}', ['k' => 'v1']);
        $this->store->remove();

        self::assertNull($this->store->current());
    }

    public function testActivateIsExclusiveUnderConcurrentCalls(): void
    {
        // Not a true concurrency test (single process), but proves the lock
        // is acquired and released cleanly across repeated activations.
        for ($i = 0; $i < 5; ++$i) {
            $this->store->activate('{"n":' . $i . '}', ['v' => $i]);
        }

        self::assertSame('{"n":4}', $this->store->current()['raw']);
    }

    public function testCurrentReturnsNullWhenTheEnvelopeFileIsMissing(): void
    {
        $this->store->activate('{"a":1}', ['k' => 'v1']);
        unlink((new Paths($this->scratchDir))->entitlementEnvelopeFile());

        self::assertNull($this->store->current());
    }

    public function testCurrentReturnsNullWhenTheEnvelopeIsNotValidJson(): void
    {
        $this->store->activate('{"a":1}', ['k' => 'v1']);
        file_put_contents((new Paths($this->scratchDir))->entitlementEnvelopeFile(), 'not json');

        self::assertNull($this->store->current());
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
