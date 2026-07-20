# Local Fonts — V&T Innovations

Contao-5-Bundle, das die veröffentlichte Website nach Google Fonts durchsucht, die
gefundenen Schriftdateien lokal herunterlädt und ein lokales Stylesheet unter
`files/localfonts/localfonts.css` erzeugt und automatisch im Frontend einbindet.

Damit werden keine Verbindungen mehr zu `fonts.googleapis.com` / `fonts.gstatic.com`
aufgebaut — DSGVO-freundlich und schneller.

- Paket: `vtinnovations/localfonts`
- Namespace: `VTinnovations\LocalFonts`
- Lizenz-Produktcode: `vt-localfonts` (Lizenzserver: https://www.v-t.one)

## Ablauf im Backend (Layout → Local Fonts)

Drei getrennte Schritte — es wird nichts geschrieben oder eingebunden, solange
nicht geklickt wurde:

1. **Website scannen** — durchsucht alle veröffentlichten Seiten nach Google Fonts
   (auch `@import` in lokalen CSS-Dateien). Zeigt nur an, was gefunden wurde.
2. **Schriften lokal laden** — lädt die `.woff2`-Dateien nach `files/localfonts/`,
   erzeugt `localfonts.css` (inklusive `unicode-range` je Subset) und legt den
   Symlink in den Web-Root. Wieder entfernbar über „Lokale Schriften entfernen".
3. **Einbinden** — zwei Möglichkeiten:
   - **Automatisch**: Das Stylesheet wird in jede Seite eingebunden.
   - **Selbst einbinden**: Nichts wird injiziert, das Backend zeigt den
     `<link>`-Code zum Kopieren (z. B. fürs Layout unter „Zusätzliche
     `<head>`-Tags").

   Unabhängig davon lassen sich externe Verweise auf `fonts.googleapis.com` /
   `fonts.gstatic.com` aus dem Frontend entfernen.

## Weiteres

- CLI: `vendor/bin/contao-console localfonts:scan` (nur scannen),
  mit `--download` auch herunterladen
- Täglicher Cronjob zur Lizenz-Nachprüfung
- Ohne DNS am Startpunkt erzeugt Contao auf der Konsole `localhost`-URLs; der
  Scan meldet das. Im Backend tritt das nicht auf.

## Lizenzierung

Kostenpflichtiges Plugin. Ohne gültige Lizenz:

- werden im Backend keine Scan-Aktionen ausgeführt,
- wird im Frontend nichts eingebunden oder umgeschrieben.

Lizenzschlüssel wird im Backend-Modul eingetragen und gegen
`https://www.v-t.one/api/v1/verify` geprüft (Produkt `vt-localfonts`).
Das Ergebnis liegt zwischengespeichert in `var/localfonts/license.json`,
Kulanzfenster 7 Tage, tägliche Nachprüfung per Cron.

### Entwickler-Bypass

Nur für lokale Entwicklung/Staging — niemals in Produktion:

```yaml
environment:
  - LOCALFONTS_LICENSE_BYPASS=1
```

## Speicherorte

```text
files/localfonts/            # heruntergeladene Fonts + generiertes CSS
var/localfonts/state.json    # Scan-Status
var/localfonts/license.json  # zwischengespeicherter Lizenzstatus
```

## Installation (Composer path repo)

```json
{
    "repositories": [{ "type": "path", "url": "packages/localfonts" }],
    "require": { "vtinnovations/localfonts": "*" }
}
```
