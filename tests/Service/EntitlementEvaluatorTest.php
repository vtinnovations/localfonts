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
use VTinnovations\LocalFonts\Service\DomainInventory;
use VTinnovations\LocalFonts\Service\Ed25519Signatures;
use VTinnovations\LocalFonts\Service\EntitlementEvaluation;
use VTinnovations\LocalFonts\Service\EntitlementEvaluator;
use VTinnovations\LocalFonts\Service\EntitlementStore;
use VTinnovations\LocalFonts\Service\EntitlementVerifier;
use VTinnovations\LocalFonts\Tests\Fixture\SignedPackageFactory;

// Not autoloaded when this suite runs under a consuming project (Composer
// ignores a dependency's autoload-dev unless it is the root package).
require_once __DIR__ . '/../Fixture/SignedPackageFactory.php';

/**
 * The shared evaluation used by every feature boundary. It must never throw
 * (a licensing fault degrades to "unlicensed", never a 500 on the frontend
 * request path — see DomainInventory's blank-dns regression) and it must
 * resolve the matched domain from the real inventory ∩ license_domains
 * intersection, not merely echo `license_domain`.
 */
final class EntitlementEvaluatorTest extends TestCase
{
    private SignedPackageFactory $factory;
    private string $scratchDir;
    private int $now;

    protected function setUp(): void
    {
        if (!\function_exists('sodium_crypto_sign_keypair')) {
            self::markTestSkipped('ext-sodium is required for these tests.');
        }

        $this->factory = new SignedPackageFactory();
        $this->scratchDir = sys_get_temp_dir() . '/lf_eval_test_' . bin2hex(random_bytes(6));
        $this->now = time();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->scratchDir)) {
            $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->scratchDir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($items as $item) {
                $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
            }
            @rmdir($this->scratchDir);
        }
    }

    public function testUnlicensedWhenNothingIsStored(): void
    {
        $evaluator = $this->buildEvaluator(inventory: ['example.com']);

        $evaluation = $evaluator->evaluate();

        self::assertFalse($evaluation->active);
        self::assertSame(EntitlementEvaluation::STATUS_UNLICENSED, $evaluation->status);
    }

    public function testActiveForAFullyValidStoredPackage(): void
    {
        $evaluator = $this->buildEvaluator(inventory: ['example.com']);
        $this->activate($evaluator, ['license_domain' => 'example.com', 'license_domains' => ['example.com']]);

        $evaluation = $evaluator->evaluate();

        self::assertTrue($evaluation->active);
        self::assertSame('example.com', $evaluation->matchedDomain);
    }

    public function testInvalidWhenNoConfiguredDomainIntersectsTheSignedSet(): void
    {
        $evaluator = $this->buildEvaluator(inventory: ['unrelated.com']);
        $this->activate($evaluator, ['license_domain' => 'example.com', 'license_domains' => ['example.com']]);

        $evaluation = $evaluator->evaluate();

        self::assertFalse($evaluation->active);
        self::assertSame(EntitlementEvaluation::STATUS_INVALID, $evaluation->status);
    }

    public function testMatchedDomainIsTheIntersectionNotJustLicenseDomain(): void
    {
        // license_domain records the *last verification* host; the evaluator
        // must derive the authoritative host from the current inventory
        // instead of trusting that field blindly.
        $evaluator = $this->buildEvaluator(inventory: ['staging.example.com']);
        $this->activate($evaluator, [
            'license_domain' => 'example.com',
            'license_domains' => ['example.com', 'staging.example.com'],
        ]);

        $evaluation = $evaluator->evaluate();

        self::assertTrue($evaluation->active);
        self::assertSame('staging.example.com', $evaluation->matchedDomain);
    }

    public function testInvalidForAPackageNotYetValid(): void
    {
        $evaluator = $this->buildEvaluator(inventory: ['example.com']);
        $this->activate($evaluator, [
            'license_domain' => 'example.com',
            'license_domains' => ['example.com'],
            'license_starts_at' => $this->now + 3600,
        ]);

        self::assertFalse($evaluator->evaluate()->active);
    }

    public function testUnlicensedWhenTheStoredEnvelopeHasBeenTamperedWith(): void
    {
        $store = new EntitlementStore(new \VTinnovations\LocalFonts\Config\Paths($this->scratchDir));
        $verifier = new EntitlementVerifier($this->factory->keyRing, new Ed25519Signatures());
        $domains = $this->createMock(DomainInventory::class);
        $domains->method('trustedInventory')->willReturn(['example.com']);
        $evaluator = new EntitlementEvaluator($store, $verifier, $domains);

        [$payloadB64, $envelope] = $this->factory->validPackage($this->now, ['license_domain' => 'example.com', 'license_domains' => ['example.com']]);
        $store->activate(base64_decode($payloadB64, true), $envelope);

        // Tamper with the persisted bytes directly, bypassing the store's own API.
        $store->activate(base64_decode($payloadB64, true) . 'tampered', $envelope);

        self::assertFalse($evaluator->evaluate()->active);
    }

    public function testNeverThrowsWhenTheDomainInventoryCollaboratorFails(): void
    {
        // Proves the fail-closed contract: any collaborator exception degrades
        // to "unlicensed" rather than propagating into the frontend request.
        $store = new EntitlementStore(new \VTinnovations\LocalFonts\Config\Paths($this->scratchDir));
        [$payloadB64, $envelope] = $this->factory->validPackage($this->now, ['license_domain' => 'example.com', 'license_domains' => ['example.com']]);
        $store->activate(base64_decode($payloadB64, true), $envelope);

        $verifier = new EntitlementVerifier($this->factory->keyRing, new Ed25519Signatures());
        $domains = $this->createMock(DomainInventory::class);
        $domains->method('trustedInventory')->willThrowException(new \RuntimeException('database unavailable'));

        $evaluator = new EntitlementEvaluator($store, $verifier, $domains);

        $evaluation = $evaluator->evaluate();

        self::assertFalse($evaluation->active);
        self::assertSame(EntitlementEvaluation::STATUS_UNLICENSED, $evaluation->status);
    }

    /**
     * @param list<string> $inventory
     */
    private function buildEvaluator(array $inventory): EntitlementEvaluator
    {
        $store = new EntitlementStore(new \VTinnovations\LocalFonts\Config\Paths($this->scratchDir));
        $verifier = new EntitlementVerifier($this->factory->keyRing, new Ed25519Signatures());
        $domains = $this->createMock(DomainInventory::class);
        $domains->method('trustedInventory')->willReturn($inventory);

        return new EntitlementEvaluator($store, $verifier, $domains);
    }

    private function activate(EntitlementEvaluator $evaluator, array $documentOverrides): void
    {
        $ref = new \ReflectionProperty($evaluator, 'store');
        $ref->setAccessible(true);
        /** @var EntitlementStore $store */
        $store = $ref->getValue($evaluator);

        [$payloadB64, $envelope] = $this->factory->validPackage($this->now, $documentOverrides);
        $store->activate(base64_decode($payloadB64, true), $envelope);
    }
}
