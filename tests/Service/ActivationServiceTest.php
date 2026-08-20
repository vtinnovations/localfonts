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
use VTinnovations\LocalFonts\Http\VtOneGateway;
use VTinnovations\LocalFonts\Service\ActivationService;
use VTinnovations\LocalFonts\Service\DomainInventory;
use VTinnovations\LocalFonts\Service\Ed25519Signatures;
use VTinnovations\LocalFonts\Service\EntitlementEvaluator;
use VTinnovations\LocalFonts\Service\EntitlementStore;
use VTinnovations\LocalFonts\Service\EntitlementVerifier;
use VTinnovations\LocalFonts\Tests\Fixture\SignedPackageFactory;

// Not autoloaded when this suite runs under a consuming project (Composer
// ignores a dependency's autoload-dev unless it is the root package).
require_once __DIR__ . '/../Fixture/SignedPackageFactory.php';

/**
 * Orchestration: request building, early local rejections (no network call
 * needed), response correlation, rollback prevention, and "a failed exchange
 * never erases a previously valid licence".
 */
final class ActivationServiceTest extends TestCase
{
    private SignedPackageFactory $factory;
    private string $scratchDir;
    private EntitlementStore $store;
    private EntitlementVerifier $verifier;
    private int $now;

    protected function setUp(): void
    {
        if (!\function_exists('sodium_crypto_sign_keypair')) {
            self::markTestSkipped('ext-sodium is required for these tests.');
        }

        $this->factory = new SignedPackageFactory();
        $this->scratchDir = sys_get_temp_dir() . '/lf_activation_test_' . bin2hex(random_bytes(6));
        $this->store = new EntitlementStore(new Paths($this->scratchDir));
        $this->verifier = new EntitlementVerifier($this->factory->keyRing, new Ed25519Signatures());
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

    public function testActivateRejectsAnEmptyKeyWithoutCallingTheGateway(): void
    {
        $gateway = $this->createMock(VtOneGateway::class);
        $gateway->expects(self::never())->method('verify');

        $service = $this->buildService($gateway, inventory: ['example.com']);
        $result = $service->activate('');

        self::assertFalse($result['ok']);
        self::assertSame('no_key_entered', $result['messageKey']);
    }

    public function testActivateRejectsAnOverlongKeyWithoutCallingTheGateway(): void
    {
        $gateway = $this->createMock(VtOneGateway::class);
        $gateway->expects(self::never())->method('verify');

        $service = $this->buildService($gateway, inventory: ['example.com']);
        $result = $service->activate(str_repeat('x', 200));

        self::assertFalse($result['ok']);
        self::assertSame('no_key_entered', $result['messageKey']);
    }

    public function testActivateRejectsWhenNoTrustedDomainIsConfigured(): void
    {
        $gateway = $this->createMock(VtOneGateway::class);
        $gateway->expects(self::never())->method('verify');

        $service = $this->buildService($gateway, inventory: []);
        $result = $service->activate('LF-KEY');

        self::assertFalse($result['ok']);
        self::assertSame('no_trusted_domain', $result['messageKey']);
    }

    public function testActivateSucceedsAndPersistsAValidResponse(): void
    {
        [$payloadB64, $envelope] = $this->factory->validPackage($this->now, ['license_domain' => 'example.com', 'license_domains' => ['example.com']]);
        $gateway = $this->gatewayReturning($this->successBody($payloadB64, $envelope));

        $service = $this->buildService($gateway, inventory: ['example.com']);
        $result = $service->activate('LF-KEY');

        self::assertTrue($result['ok']);
        self::assertSame('activated', $result['messageKey']);
        self::assertNotNull($this->store->current());
    }

    public function testActivateFailsWhenTheServerReturns5xxAndPreservesNoPriorState(): void
    {
        $gateway = $this->createMock(VtOneGateway::class);
        $gateway->method('verify')->willReturn(['status_code' => 503, 'body' => null]);

        $service = $this->buildService($gateway, inventory: ['example.com']);
        $result = $service->activate('LF-KEY');

        self::assertFalse($result['ok']);
        self::assertSame('server_unavailable', $result['messageKey']);
        self::assertNull($this->store->current());
    }

    public function testActivateFailsWhenResponseRequestIdDoesNotCorrelate(): void
    {
        [$payloadB64, $envelope] = $this->factory->validPackage($this->now);
        $gateway = $this->gatewayReturning([
            'request_id' => 'a-completely-different-id',
            'status' => 'valid',
            'license_payload_b64' => $payloadB64,
            'integrity' => $envelope,
            'server_time' => $this->now,
        ]);

        $service = $this->buildService($gateway, inventory: ['example.com']);
        $result = $service->activate('LF-KEY');

        self::assertFalse($result['ok']);
        self::assertSame('uncorrelated_response', $result['messageKey']);
    }

    public function testActivateFailsWhenTheServerDeniesTheKey(): void
    {
        $gateway = $this->createMock(VtOneGateway::class);
        $gateway->method('verify')->willReturnCallback(fn (array $payload) => [
            'status_code' => 200,
            'body' => ['request_id' => $payload['request_id'], 'status' => 'invalid'],
        ]);

        $service = $this->buildService($gateway, inventory: ['example.com']);
        $result = $service->activate('LF-KEY');

        self::assertFalse($result['ok']);
        self::assertSame('key_rejected', $result['messageKey']);
    }

    public function testActivateFailsClosedOnACompletelyInvalidResponsePacket(): void
    {
        $gateway = $this->createMock(VtOneGateway::class);
        $gateway->method('verify')->willReturnCallback(fn (array $payload) => [
            'status_code' => 200,
            'body' => ['request_id' => $payload['request_id'], 'status' => 'valid', 'license_payload_b64' => 'garbage', 'integrity' => []],
        ]);

        $service = $this->buildService($gateway, inventory: ['example.com']);
        $result = $service->activate('LF-KEY');

        self::assertFalse($result['ok']);
        self::assertSame('verification_failed', $result['messageKey']);
    }

    public function testActivateRejectsADomainNotInTheTrustedInventory(): void
    {
        // The fixture's signed package is bound to example.com, but this
        // installation's only trusted configured host is a different one —
        // the exact intersection required for activation does not exist.
        [$payloadB64, $envelope] = $this->factory->validPackage($this->now, ['license_domain' => 'example.com', 'license_domains' => ['example.com']]);
        $gateway = $this->gatewayReturning($this->successBody($payloadB64, $envelope));

        $service = $this->buildService($gateway, inventory: ['other-trusted-host.com']);
        $result = $service->activate('LF-KEY');

        self::assertFalse($result['ok']);
        self::assertSame('domain_not_authorized', $result['messageKey']);
    }

    public function testActivateRejectsAModelIncompatiblePackage(): void
    {
        [$payloadB64, $envelope] = $this->factory->validPackage($this->now, [
            'license_domain' => 'example.com',
            'license_domains' => ['example.com'],
            'license_package' => 'pro',
        ]);
        $gateway = $this->gatewayReturning($this->successBody($payloadB64, $envelope));

        $service = $this->buildService($gateway, inventory: ['example.com']);
        $result = $service->activate('LF-KEY');

        self::assertFalse($result['ok']);
        self::assertSame('model_incompatible', $result['messageKey']);
    }

    public function testRefreshFailsWhenNothingIsActivated(): void
    {
        $service = $this->buildService($this->createMock(VtOneGateway::class), inventory: ['example.com']);

        $result = $service->refresh();

        self::assertFalse($result['ok']);
        self::assertSame('no_license_activated', $result['messageKey']);
    }

    public function testRefreshSendsActionRefreshWithTheStoredVersion(): void
    {
        $this->activateFixture(['license_domain' => 'example.com', 'license_domains' => ['example.com'], 'license_version' => 3]);

        $capturedPayload = null;
        [$newPayloadB64, $newEnvelope] = $this->factory->validPackage($this->now, ['license_domain' => 'example.com', 'license_domains' => ['example.com'], 'license_version' => 4]);
        $gateway = $this->createMock(VtOneGateway::class);
        $gateway->method('verify')->willReturnCallback(function (array $payload) use (&$capturedPayload, $newPayloadB64, $newEnvelope) {
            $capturedPayload = $payload;

            return ['status_code' => 200, 'body' => ['request_id' => $payload['request_id'], 'status' => 'valid', 'license_payload_b64' => $newPayloadB64, 'integrity' => $newEnvelope, 'server_time' => $this->now]];
        });

        $service = $this->buildService($gateway, inventory: ['example.com']);
        $result = $service->refresh();

        self::assertTrue($result['ok']);
        self::assertSame('refresh', $capturedPayload['action']);
        self::assertSame(3, $capturedPayload['current_license_version']);
    }

    public function testRefreshRejectsAnOlderVersionThanCurrentlyStored(): void
    {
        $this->activateFixture(['license_domain' => 'example.com', 'license_domains' => ['example.com'], 'license_version' => 5]);

        [$olderPayloadB64, $olderEnvelope] = $this->factory->validPackage($this->now, ['license_domain' => 'example.com', 'license_domains' => ['example.com'], 'license_version' => 2]);
        $gateway = $this->gatewayReturning($this->successBody($olderPayloadB64, $olderEnvelope));

        $service = $this->buildService($gateway, inventory: ['example.com']);
        $result = $service->refresh();

        self::assertFalse($result['ok']);
        self::assertSame('stale_response', $result['messageKey']);

        // The previously valid (version 5) package must survive the rejected refresh.
        $current = json_decode($this->store->current()['raw'], true);
        self::assertSame(5, $current['license_version']);
    }

    public function testRemoveClearsStateAndReturnsSuccess(): void
    {
        $this->activateFixture(['license_domain' => 'example.com', 'license_domains' => ['example.com']]);

        $service = $this->buildService($this->createMock(VtOneGateway::class), inventory: ['example.com']);
        $result = $service->remove();

        self::assertTrue($result['ok']);
        self::assertSame('removed', $result['messageKey']);
        self::assertNull($this->store->current());
    }

    public function testApplyPushedPackageRejectsARollback(): void
    {
        $this->activateFixture(['license_domain' => 'example.com', 'license_domains' => ['example.com'], 'license_version' => 10]);

        [$olderPayloadB64, $olderEnvelope] = $this->factory->validPackage($this->now, ['license_domain' => 'example.com', 'license_domains' => ['example.com'], 'license_version' => 3]);
        $service = $this->buildService($this->createMock(VtOneGateway::class), inventory: ['example.com']);

        $outcome = $service->applyPushedPackage(['domain' => 'example.com'], $olderEnvelope, $olderPayloadB64);

        self::assertFalse($outcome['ok']);
        self::assertSame('rollback_rejected', $outcome['reason']);
    }

    public function testApplyPushedPackageSucceedsForANewerVersion(): void
    {
        $this->activateFixture(['license_domain' => 'example.com', 'license_domains' => ['example.com'], 'license_version' => 1]);

        [$newerPayloadB64, $newerEnvelope] = $this->factory->validPackage($this->now, ['license_domain' => 'example.com', 'license_domains' => ['example.com'], 'license_version' => 2]);
        $service = $this->buildService($this->createMock(VtOneGateway::class), inventory: ['example.com']);

        $outcome = $service->applyPushedPackage(['domain' => 'example.com'], $newerEnvelope, $newerPayloadB64);

        self::assertTrue($outcome['ok']);
        self::assertSame(2, $outcome['version']);
    }

    public function testApplyPushedPackageRejectsAModelIncompatiblePush(): void
    {
        [$payloadB64, $envelope] = $this->factory->validPackage($this->now, [
            'license_domain' => 'example.com',
            'license_domains' => ['example.com'],
            'license_package' => 'pro',
        ]);
        $service = $this->buildService($this->createMock(VtOneGateway::class), inventory: ['example.com']);

        $outcome = $service->applyPushedPackage(['domain' => 'example.com'], $envelope, $payloadB64);

        self::assertFalse($outcome['ok']);
        self::assertSame('package_not_accepted', $outcome['reason']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function successBody(string $payloadB64, array $envelope): array
    {
        return [
            'request_id' => null, // filled in by gatewayReturning()'s callback
            'status' => 'valid',
            'license_payload_b64' => $payloadB64,
            'integrity' => $envelope,
            'server_time' => $this->now,
        ];
    }

    private function gatewayReturning(array $body): VtOneGateway
    {
        $gateway = $this->createMock(VtOneGateway::class);
        $gateway->method('verify')->willReturnCallback(static function (array $payload) use ($body) {
            $body['request_id'] = $body['request_id'] ?? $payload['request_id'];
            // When the test wants an uncorrelated response, it sets request_id explicitly.
            if (\array_key_exists('request_id', $body) && null === $body['request_id']) {
                $body['request_id'] = $payload['request_id'];
            }

            return ['status_code' => 200, 'body' => $body];
        });

        return $gateway;
    }

    /**
     * @param list<string> $inventory
     */
    private function buildService(VtOneGateway $gateway, array $inventory): ActivationService
    {
        $domains = $this->createMock(DomainInventory::class);
        $domains->method('resolveVerificationDomain')->willReturn($inventory[0] ?? null);
        $domains->method('trustedInventory')->willReturn($inventory);

        $evaluator = new EntitlementEvaluator($this->store, $this->verifier, $domains);

        return new ActivationService($gateway, $this->verifier, $this->store, $evaluator, $domains);
    }

    private function activateFixture(array $documentOverrides): void
    {
        [$payloadB64, $envelope] = $this->factory->validPackage($this->now, $documentOverrides);
        $this->store->activate(base64_decode($payloadB64, true), $envelope);
    }
}
