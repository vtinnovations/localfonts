🇩🇪 [Deutsche Version](README.md) (Standardsprache / default language)

# Local Fonts

Contao bundle by **V&T Innovations** that scans your own website for embedded Google Fonts, downloads the found font files locally into the Contao upload directory, and generates a local stylesheet. This removes the need for the browser to contact `fonts.googleapis.com` and `fonts.gstatic.com` at all — privacy-friendly (GDPR/DSGVO) and independent of Google's servers on the front end.

## 1. Project overview

Local Fonts works in three deliberately separate, administrator-triggered steps:

1. **Scan website** — scans every published regular page for embedded Google Fonts stylesheets. Nothing is stored yet.
2. **Download fonts locally** — downloads the font files found during the scan, stores them under `files/localfonts/`, and generates the matching `@font-face` stylesheet.
3. **Embed** — decides whether the local stylesheet is embedded automatically on every page, or whether the operator embeds the generated CSS code themselves. Remaining external Google Fonts references can optionally be removed from the front end.

Every step requires an explicit click in the backend (or the corresponding CLI call) — nothing is ever written to the website automatically in the background.

Using the bundle requires an activated, free V&T Innovations licence (see section 14). Without activation, the bundle runs no protected feature and the website behaves exactly as if the bundle were not installed.

## 2. Implementation status

Complete, native Contao 5 bundle, version **1.0.0**. Every feature described in this document is production-ready; this is not a placeholder, an unfinished port, or a planned feature.

## 3. Supported framework and runtime versions

| Component | Supported version |
|---|---|
| PHP | ^8.2 |
| Contao Core Bundle | ^5.3 |
| Symfony (config, console, dependency-injection, http-client, http-foundation, http-kernel) | ^6.4 \|\| ^7.0 |
| PHP extension `ext-json` | required |
| PHP extension `ext-sodium` | required (Ed25519 signature verification for licensing) |

## 4. System requirements

* A working Contao 5 installation (Managed Edition or Composer-based setup) matching the versions above.
* Outbound HTTPS connections from the server to `fonts.googleapis.com`, `fonts.gstatic.com`, and `www.v-t.one` (see section 19).
* Write access for the web server to the Contao upload directory (`files/`) and to `var/`.
* For automatic symlink creation: the Contao console must be executable.

## 5. Installation

```bash
composer require vtinnovations/localfonts
```

In a Contao Managed Edition the bundle is registered automatically via `ContaoManager\Plugin`, including its own routes. In a classic Composer setup without Contao Manager, the bundle must be registered in the kernel like any other Symfony bundle.

After installing:

```bash
vendor/bin/contao-console cache:clear
vendor/bin/contao-console contao:symlinks
```

## 6. Composer / package manager setup

* Composer package name: `vtinnovations/localfonts`
* Package type: `contao-bundle`
* PSR-4 namespace: `VTinnovations\LocalFonts\` → `src/`
* Bundle class and routing are registered through a dedicated `contao-manager-plugin` (`extra.contao-manager-plugin` in `composer.json`), compatible with `contao/manager-plugin` ^2.0.

## 7. Required executables and configuration

No additional configuration file is required. The bundle resolves its working directory automatically from the Contao project directory (`var/localfonts/`, see section 18) and needs no further environment variables.

## 8. Filesystem permissions

The web server user needs write access to:

* `var/localfonts/` — state file and the locally stored licence data (see section 18).
* `files/localfonts/` — downloaded font files, generated stylesheet, cleaned CSS copies.

The bundle creates both directories itself when needed. If the automatic symlink for `files/localfonts/` into the web root cannot be created, the backend reports this and points to:

```bash
vendor/bin/contao-console contao:symlinks
```

## 9. Backend and administration access

* The operating module lives under **Layout › Local Fonts** and is available to any backend user with access to that module group.
* Without an activated licence, the module shows only a notice with a link to the licence management screen; none of the three steps is visible or usable.
* Licence management itself lives under **System › Settings › Local Fonts Licence management** and — like the whole Contao Settings screen — is reserved for administrators. Activating, refreshing and removing the licence are additionally restricted server-side to administrator accounts and protected by Contao's regular CSRF token.

## 10. Front-end integration

Once a licence is active and the feature is enabled in the settings (default: enabled), the bundle affects every delivered front-end page as follows:

* **Automatic mode** (default): the local stylesheet (`/files/localfonts/localfonts.css`) is embedded automatically before `</head>` on every page, once it has been generated.
* **Manual mode**: nothing is embedded automatically; the operator copies the CSS code shown in the backend into their own layout (e.g. under "Additional `<head>` tags").
* **Block external Google Fonts** (optional, disabled by default): remaining `<link>`, `@import` and URL references to `fonts.googleapis.com`/`fonts.gstatic.com` are removed from the delivered page; third-party stylesheets already embedded that themselves reference Google Fonts are replaced with cleaned local copies.
* Delivered pages receive the response header `X-Local-Fonts: active` while the feature is actively in effect.

Without an active licence, output stays unchanged — the website behaves exactly as if the bundle were not installed, even if fonts had previously been stored locally.

## 11. Navigation modules

| Location | Label | Purpose |
|---|---|---|
| Layout | **Local Fonts** | The three-step workflow: scan, download, embed |
| System › Settings | **Local Fonts Licence management** | Activate, refresh, remove the licence; view status |

## 12. Verified features

* **Website scan**: scans every published regular page (including linked, non-Google stylesheets) for Google Fonts references and lists the detected font families with variants and file counts.
* **Local download**: downloads each detected font file exactly once, stores it under `files/localfonts/<font>/`, and generates a complete `@font-face` stylesheet (including `unicode-range` per subset).
* **CLI command** `localfonts:scan` (option `--download`/`-d`): runs the scan and, optionally, the download outside the backend, e.g. for a scheduled cron run.
* **Automatic or manual embedding**, including switching between the two modes.
* **Blocking external Google Fonts**, including cleanup of third-party stylesheets embedded via `<link>`.
* **Removal** of the locally stored fonts and the generated stylesheet (reset to the original state).
* **Licence management**: activation, refresh and removal directly in the Contao settings, including a status display (domain, package, version).
* **Daily automatic licence check** (Contao Scheduler/cron) that re-verifies a stale local licence against the licence server without administrator intervention.

## 13. Permissions and access control

| Action | Requirement |
|---|---|
| Open the "Local Fonts" operating module | Backend access to the "Layout" module group |
| Trigger scan/download/embed | additionally: an activated licence |
| Activate/refresh/remove the licence | Backend administrator, valid CSRF token |
| View the licence status | Access to the global settings screen (administrator-only) |
| Licence update endpoint (server-to-server) | Publicly reachable, but only usable with a valid cryptographic signature from V&T Innovations (see [security model](Documentation/Security.en.md)) |

## 14. Licensing and entitlement behaviour

Local Fonts follows V&T Innovations' **"Lifetime Free"** model: the product is free of charge, but always requires a successfully activated, signed licence — there is no anonymous or unlicensed usage mode and no paid tier.

Effective states that actually occur:

| State | Effect |
|---|---|
| No licence activated | No protected feature runs; the front end stays unchanged; the backend module shows only the activation notice |
| Licence active (Lifetime Free) | Every feature described in this document is available |
| A previously activated licence no longer passes validation (e.g. after a domain change) | Displayed identically to "no licence activated" in the backend — there is no separate error display that would expose actionable details |

Activation, refresh and removal happen exclusively through the licence management screen in the Contao settings (section 9). Details of the verification itself are covered in the [security model](Documentation/Security.en.md).

## 15. Feature status table

| Feature | Status |
|---|---|
| Website scan for Google Fonts | Conditional (requires an active licence) |
| Local download of font files | Conditional (requires an active licence) |
| Automatic embedding of the local stylesheet | Conditional (requires an active licence) |
| Manual copy-and-paste embedding | Conditional (requires an active licence) |
| Blocking external Google Fonts calls | Conditional (requires an active licence) |
| CLI command `localfonts:scan` | Conditional (requires an active licence) |
| Daily automatic licence check | Available |
| Licence activation/management | Available |
| Paid tier | Not applicable (a pure Lifetime Free product) |

## 16. Security model

A detailed, administrator-level description of the security and trust model (activation, integrity checks, failure behaviour) is available in [`Documentation/Security.en.md`](Documentation/Security.en.md).

Summary:

* Every licensing operation is exchanged as a cryptographically signed message and re-verified before it is ever applied.
* Licence data lives outside the web root (`var/localfonts/entitlement/`).
* An unreachable licence server or a failed verification never alters a previously valid, already-activated licence.
* Verification failures only ever disable the protected feature — they never turn into a site-wide server error.

## 17. Operational safety

* Writes to the licence data are exclusively locked and preceded by a backup of the previous valid data.
* Every write is re-read and re-verified immediately afterwards; on any mismatch, the previous backup is restored automatically.
* The publicly reachable update endpoint only ever processes requests carrying a valid signature; unsigned or incorrectly signed requests are rejected without further processing.

## 18. Runtime directories

| Path | Contents |
|---|---|
| `var/localfonts/state.json` | Scan/installation status, settings (embed mode, blocking on/off, etc.) |
| `var/localfonts/entitlement/` | Locally stored, signed licence data including a backup copy and a replay journal for the update endpoint — never commit or publish |
| `files/localfonts/` | Downloaded font files, generated stylesheet `localfonts.css`, cleaned CSS copies |

## 19. External communication

The bundle communicates with the following external destinations, always over TLS-verified HTTPS connections with a fixed target address (no redirects followed, bounded timeouts):

| Destination | Purpose |
|---|---|
| `fonts.googleapis.com`, `fonts.gstatic.com` | Reading the Google Fonts stylesheets and downloading the font files during scan and download |
| `www.v-t.one` | Licence activation, refresh and the daily automatic check; a small, operational signal per relevant page request or per backend session |
| the website's own published pages | The scan requests the site's own pages to find embedded Google Fonts stylesheets |

Conversely, this bundle's signed licence update endpoint is publicly reachable, so V&T Innovations can deliver an updated licence server-side; it only ever processes cryptographically signed requests.

## 20. Logging and redaction of sensitive data

* Only generic, safe diagnostic codes are logged (e.g. "server unavailable", "verification failed") — never the licence key, a request or response body, a signature or a checksum value.
* Backend messages (success/failure of activation, refresh, removal) use the same general, translated text, never raw internal data.

## 21. Deployment

```bash
composer require vtinnovations/localfonts
vendor/bin/contao-console cache:clear
vendor/bin/contao-console contao:symlinks
```

After any deployment that changes embedded Google Fonts references in the layout, running a fresh website scan is recommended.

## 22. Cache clearing

```bash
vendor/bin/contao-console cache:clear
```

## 23. Testing

The bundle ships a PHPUnit test suite (`tests/`) but has no `vendor/` directory of its own — it is a Contao library, not a standalone application. The suite is run from a project that has the bundle installed as a dependency:

```bash
vendor/bin/phpunit --bootstrap vendor/autoload.php -c vendor/vtinnovations/localfonts/phpunit.xml.dist
```

The PHP extension `ext-sodium` is required for the signature-related tests; without it, the affected tests are skipped automatically.

## 24. Troubleshooting

| Symptom | Cause / fix |
|---|---|
| The backend module shows only the licence notice | No active licence — activate it under System › Settings › Local Fonts Licence management |
| Scan reports "Page URLs were generated as 'localhost'" | The scan ran outside a web request (e.g. via cron) and the root page has no DNS entry; set the domain on the website's root page or configure `framework.router.request_context.host` |
| Font files are not served under `/files/localfonts/…` | The symlink is missing; run `vendor/bin/contao-console contao:symlinks` |
| "Block external Google Fonts" is on, but fonts are missing on the front end | Manual embed mode is active without the CSS code having been embedded — switch to automatic mode or embed the displayed code |
| A repeated scan suddenly finds no fonts | "Block external Google Fonts" already removes the references the scan is looking for from earlier, cached page responses — clear the page cache before scanning |
| The licence server does not respond | The previous valid licence state is preserved unchanged; a later retry (automatically once a day, or manually via "Update Licence") will succeed once the server is reachable again |

## 25. Known limitations

* Only the Lifetime Free licensing model is supported; there is no trial period, no expiry date and no paid tier for this product.
* Only fonts embedded via the public Google Fonts CSS2 service (`fonts.googleapis.com`) are detected; self-hosted fonts or fonts loaded from other providers are not detected.
* The scan requires an internet-reachable, DNS-resolved domain; without a real hostname on the root page, a scan run via cron/CLI produces no usable results.
* Licence domain binding is exact (hostname match); subdomains or alternative spellings count as separate domains.
* Without the PHP extension `ext-sodium`, no licence can be activated at all — the bundle then remains permanently in its unlicensed state.
* Removing the licence does not delete already-downloaded font files; they remain on the server but are neither served nor manageable through the backend module until the licence is reactivated.

## 26. Licence and copyright information

* Licence: **LGPL-3.0-or-later**
* Copyright: **V&T Innovations**
* Website: [https://www.v-t.one](https://www.v-t.one)

## 27. Further documents

* [German README](README.md) (default language)
* [Security model](Documentation/Security.en.md) / [Sicherheitsmodell](Documentation/Sicherheit.md)
