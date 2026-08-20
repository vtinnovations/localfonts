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

namespace VTinnovations\LocalFonts\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use VTinnovations\LocalFonts\Config\TrustedSigningKeys;
use VTinnovations\LocalFonts\Service\CanonicalJson;
use VTinnovations\LocalFonts\Service\Ed25519Signatures;

/**
 * Release/CI gate. Fails when the shipped build could never authenticate a
 * real v-t.one response: an empty or unusable key ring, a key whose bytes do
 * not match its published fingerprint, missing crypto support, or a broken
 * canonical-encoding/verification path. Run this against the built artefact,
 * not only the source tree.
 */
#[AsCommand(
    name: 'localfonts:verify-trust-anchor',
    description: 'Verifies the pinned verification keys and signing path are release-ready.',
)]
final class VerifyTrustAnchorCommand extends Command
{
    /**
     * Published SHA-256 fingerprint prefixes for the approved keys. A key
     * whose reconstructed bytes do not hash to its declared prefix is treated
     * as tampered-with, not merely misconfigured.
     */
    private const EXPECTED_FINGERPRINT_PREFIXES = [
        'vtone-2026a' => 'edcd614e70c59ce0',
    ];

    public function __construct(
        private readonly TrustedSigningKeys $keys,
        private readonly Ed25519Signatures $signatures,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = time();

        if (!$this->signatures->isSupported()) {
            $io->error('libsodium is unavailable: signed licence workflows could never verify.');

            return Command::FAILURE;
        }

        try {
            $usableKeyIds = $this->keys->assertProductionReady($now);
        } catch (\RuntimeException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $rows = [];

        foreach ($usableKeyIds as $keyId) {
            $entry = $this->keys->resolve($keyId, 'ed25519', 'envelope', $now)
                ?? $this->keys->resolve($keyId, 'ed25519', 'document', $now);

            if (null === $entry) {
                $io->error(sprintf('Key "%s" is advertised as usable but cannot be resolved.', $keyId));

                return Command::FAILURE;
            }

            $raw = $this->keys->rawPublicKeyBytes($entry);

            if (null === $raw) {
                $io->error(sprintf('Key "%s" does not decode to a raw 32-byte Ed25519 public key.', $keyId));

                return Command::FAILURE;
            }

            $fingerprint = hash('sha256', $raw);
            $expected = self::EXPECTED_FINGERPRINT_PREFIXES[$keyId] ?? null;

            if (null !== $expected && !str_starts_with($fingerprint, $expected)) {
                $io->error(sprintf('Key "%s" does not match its published fingerprint %s.', $keyId, $expected));

                return Command::FAILURE;
            }

            $rows[] = [$keyId, $entry['algorithm'], implode(', ', $entry['purposes']), substr($fingerprint, 0, 16), null !== $expected ? 'pinned' : 'no published prefix'];
        }

        $io->table(['key_id', 'algorithm', 'purposes', 'fingerprint', 'fingerprint check'], $rows);

        if (!$this->verifyCanonicalPath()) {
            $io->error('The canonical-encoding and signature-verification path failed its self-test.');

            return Command::FAILURE;
        }

        $io->success(sprintf('Trust anchor is release-ready (%d usable key(s)).', \count($usableKeyIds)));
        $io->note('A positive fixed vector signed by the real v-t.one private key must still be supplied by V-T.ONE to prove full cross-system interoperability.');

        return Command::SUCCESS;
    }

    /**
     * Proves canonicalization and detached verification work through the same
     * production code the real packets use, including negative cases. Uses a
     * throwaway keypair because the genuine private key exists only on
     * V-T.ONE infrastructure.
     */
    private function verifyCanonicalPath(): bool
    {
        if ('{"a":["z","y"],"b":1,"n":{"a":2,"z":1}}' !== CanonicalJson::encode(['b' => 1, 'a' => ['z', 'y'], 'signature' => 'x', 'n' => ['z' => 1, 'a' => 2]])) {
            return false;
        }

        $keypair = sodium_crypto_sign_keypair();
        $secret = sodium_crypto_sign_secretkey($keypair);
        $public = sodium_crypto_sign_publickey($keypair);

        $message = CanonicalJson::encode(['project' => 'Local Fonts', 'v' => 1]);
        $signature = base64_encode(sodium_crypto_sign_detached($message, $secret));

        return $this->signatures->verifyDetached($message, $signature, $public)
            && !$this->signatures->verifyDetached($message . 'tampered', $signature, $public);
    }
}
