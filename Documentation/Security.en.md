🇩🇪 [Deutsche Version](Sicherheit.md) (Standardsprache / default language)

# Security model — Local Fonts

This document describes the security and trust model of **Local Fonts** at an administrator level: which guarantees hold, which behaviour depends on environment conditions, and how the bundle behaves on failure. It deliberately does **not** describe how the checks are implemented internally — this is not a technical implementation manual.

## Scope

The security model covers two separate areas:

1. **Licensing/entitlement** — activation, refresh, removal, and the server-side delivery of an updated licence by V&T Innovations.
2. **Core feature** (scan, download, embed) — runs only once area 1 confirms an active licence.

## Guaranteed behaviour

The following guarantees are backed by the implementation and its accompanying automated test suite:

* **Signed, authenticated exchange.** Every licensing operation (activation, refresh, and a server-initiated delivery) is exchanged as a cryptographically signed message. A response that does not correlate with the original request, or whose signature does not match a pinned, trusted V&T Innovations key, is discarded and never affects the existing licence state.
* **No rollback.** A newly received licence is only applied if its version is not older than the previously active one. An older or inconsistent response is rejected.
* **Domain binding.** A licence only applies to the domain(s) it was signed for, and only when that domain actually matches a domain genuinely configured as trusted for this installation.
* **Atomic activation with backup.** When activating a newly verified licence, the previous valid licence is backed up first, the new one is then written, and the result is re-read and compared. On any mismatch, the backed-up, previously valid licence is restored automatically. This behaviour is covered by automated tests.
* **Fail-closed.** Any unexpected condition — an unreachable server, a malformed response, missing cryptographic support, corrupted local data — causes the protected feature to be treated as "unlicensed". It never turns into a site-wide server error, and it never causes a previously valid licence to be silently assumed still valid when a genuine re-check actually fails.
* **Authenticated update endpoint.** The publicly reachable endpoint through which V&T Innovations can deliver an updated licence only ever processes requests carrying a valid cryptographic signature. A claimed sender address is never treated as proof of trust. Already-processed requests are recognised and never applied twice (replay protection).
* **Private storage.** All licence data lives outside the publicly reachable web directory (`var/localfonts/entitlement/`), never under `files/` or `public/`.
* **Redacted logging.** Only generic, safe diagnostic codes are logged. Licence keys, request or response bodies, signatures and checksums are never logged.

## Conditional behaviour (environment-dependent)

* Licence verification requires the PHP extension `ext-sodium`. If it is not installed, no licence can be activated at all; the bundle remains permanently in its unlicensed state.
* Automatically creating the symlink into the web root for `files/localfonts/` depends on the filesystem permissions of the specific installation. If it fails, the backend reports this and points to the manual Contao console command.
* The daily automatic re-check of an existing licence only runs once the last successful check is more than 24 hours old; a temporarily unreachable server only delays the next check without affecting the currently valid licence.

## Best-effort behaviour

* A small, operational signal sent to V&T Innovations per relevant front-end request, and a one-time signal per backend session, are deliberately **best effort**: a failure of this signal never affects licence validity, website delivery, or backend operation.

## Explicitly not guaranteed

* No claim is made that the licensing or security system can never be bypassed or reproduced. This documentation deliberately makes no statement on that point and does not describe internal verification mechanisms, keys, signature formats, or implementation details.
* Delivery of the best-effort signals mentioned above is not guaranteed.

## Further documents

* [English README](../README.en.md)
* [Deutsches Sicherheitsmodell](Sicherheit.md)
