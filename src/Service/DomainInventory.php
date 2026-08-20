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

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\PageModel;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Trusted, normalized domain inventory for this global (instance-wide)
 * module. The inventory is the union of every configured root-page host in
 * this Contao installation — never an untrusted request header on its own.
 */
/**
 * Not `final`: this is the I/O boundary collaborator {@see EntitlementEvaluator}
 * and {@see ActivationService} depend on, and it needs a PHPUnit test double
 * in their unit tests. `final` buys nothing here — nothing security-sensitive
 * depends on this class being unextendable, unlike the crypto/persistence
 * classes that stay `final`.
 */
class DomainInventory
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @return list<string>
     */
    public function trustedInventory(): array
    {
        $this->framework->initialize();

        /** @var class-string<PageModel> $pageModelClass */
        $pageModelClass = PageModel::class;
        $adapter = $this->framework->getAdapter($pageModelClass);

        $hosts = [];
        $roots = $adapter->findByType('root');

        if (null !== $roots) {
            foreach ($roots as $root) {
                $dns = $this->normalize((string) ($root->dns ?? ''));

                if (null !== $dns) {
                    $hosts[$dns] = true;
                }
            }
        }

        $hosts = array_keys($hosts);
        sort($hosts, SORT_STRING);

        return $hosts;
    }

    /**
     * Deterministic verification domain: the current trusted request host
     * when it belongs to the inventory, otherwise the primary configured
     * host. Returns null when no trusted domain is configured at all.
     */
    public function resolveVerificationDomain(): ?string
    {
        $inventory = $this->trustedInventory();

        if ([] === $inventory) {
            return null;
        }

        $currentHost = $this->currentTrustedHost();

        if (null !== $currentHost && \in_array($currentHost, $inventory, true)) {
            return $currentHost;
        }

        return $inventory[0];
    }

    /**
     * The current request host in canonical form, or null outside a request
     * (CLI, worker, cron) or when it cannot be normalized. Membership in the
     * trusted inventory is the caller's decision — this value alone never
     * authorizes anything.
     */
    public function currentTrustedHost(): ?string
    {
        return $this->normalize((string) ($this->requestStack->getMainRequest()?->getHost() ?? ''));
    }

    /**
     * Representation-only normalization: lowercase, one trailing dot, an
     * approved port and consistent IDN/Punycode. It never strips `www`,
     * collapses to a registrable domain or otherwise widens scope, and it
     * returns null rather than throwing for anything unusable — a blank
     * root-page `dns` (the Contao default for a single-site install) simply
     * contributes no exact host to the inventory.
     */
    private function normalize(string $host): ?string
    {
        $host = strtolower(trim($host));
        $host = rtrim($host, '.');

        // Strip a scheme/path if one was pasted into the root-page dns field, and drop an approved port.
        $host = (string) preg_replace('~^[a-z][a-z0-9+.-]*://~', '', $host);
        $host = explode('/', $host, 2)[0];

        if ('' === $host || str_contains($host, '@')) {
            return null;
        }

        if (false !== ($colon = strrpos($host, ':')) && !str_contains($host, '[')) {
            $port = substr($host, $colon + 1);

            if (ctype_digit($port)) {
                $host = substr($host, 0, $colon);
            }
        }

        if ('' === $host) {
            return null;
        }

        // idn_to_ascii() raises a ValueError on an empty string and returns
        // false for anything it cannot convert, so both are guarded here.
        if (\function_exists('idn_to_ascii')) {
            $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

            if (\is_string($ascii) && '' !== $ascii) {
                $host = $ascii;
            }
        }

        return $this->isCanonicalHostname($host) ? $host : null;
    }

    private function isCanonicalHostname(string $host): bool
    {
        if ('' === $host || \strlen($host) > 253 || str_contains($host, '*')) {
            return false;
        }

        return 1 === preg_match('/^(?!-)[a-z0-9-]{1,63}(?<!-)(\.(?!-)[a-z0-9-]{1,63}(?<!-))*$/', $host);
    }
}
