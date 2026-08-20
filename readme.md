🇬🇧 [English version](README.en.md)

# Local Fonts

Contao-Bundle für **V&T Innovations**, das die eigene Website automatisch nach eingebundenen Google Fonts durchsucht, die gefundenen Schriftdateien lokal im Contao-Uploadverzeichnis speichert und ein lokales Stylesheet erzeugt. Damit lassen sich externe Aufrufe von `fonts.googleapis.com` und `fonts.gstatic.com` vermeiden — datenschutzfreundlich (DSGVO/GDPR) und ohne Abhängigkeit von Google-Servern im Frontend.

## 1. Projektübersicht

Local Fonts arbeitet in drei bewusst getrennten, vom Administrator ausgelösten Schritten:

1. **Website scannen** — durchsucht alle veröffentlichten regulären Seiten nach eingebundenen Google-Fonts-Stylesheets. Es wird noch nichts gespeichert.
2. **Schriften lokal laden** — lädt die beim Scan gefundenen Schriftdateien herunter, legt sie unter `files/localfonts/` ab und erzeugt das passende `@font-face`-Stylesheet.
3. **Einbinden** — legt fest, ob das lokale Stylesheet automatisch in jede Seite eingebunden wird oder ob der Betreiber den generierten CSS-Code selbst einpflegt. Optional können verbleibende externe Google-Fonts-Verweise aus dem Frontend entfernt werden.

Jeder Schritt erfordert einen expliziten Klick im Backend (oder den passenden CLI-Aufruf) — es wird nie automatisch im Hintergrund in die Website geschrieben.

Die Nutzung des Bundles setzt eine aktivierte, kostenlose V&T-Innovations-Lizenz voraus (siehe Abschnitt 14). Ohne Aktivierung führt das Bundle keine geschützte Funktion aus und die Website verhält sich wie ohne installiertes Bundle.

## 2. Implementierungsstatus

Vollständig implementiertes, natives Contao-5-Bundle, Version **1.0.0**. Alle in dieser Dokumentation beschriebenen Funktionen sind produktiv einsetzbar; es handelt sich nicht um einen Platzhalter, einen unfertigen Port oder eine geplante Funktion.

## 3. Unterstützte Framework- und Laufzeitversionen

| Komponente | Unterstützte Version |
|---|---|
| PHP | ^8.2 |
| Contao Core-Bundle | ^5.3 |
| Symfony (config, console, dependency-injection, http-client, http-foundation, http-kernel) | ^6.4 \|\| ^7.0 |
| PHP-Erweiterung `ext-json` | erforderlich |
| PHP-Erweiterung `ext-sodium` | erforderlich (Ed25519-Signaturprüfung für die Lizenzierung) |

## 4. Systemvoraussetzungen

* Eine lauffähige Contao-5-Installation (Managed Edition oder Composer-basiertes Setup) mit den oben genannten Versionen.
* Ausgehende HTTPS-Verbindungen des Servers zu `fonts.googleapis.com`, `fonts.gstatic.com` sowie zu `www.v-t.one` (siehe Abschnitt 19).
* Schreibzugriff des Webservers auf das Contao-Upload-Verzeichnis (`files/`) und auf `var/`.
* Für die automatische Symlink-Erstellung: die Contao-Konsole muss ausführbar sein.

## 5. Installation

```bash
composer require vtinnovations/localfonts
```

In einer Contao Managed Edition wird das Bundle über den `ContaoManager\Plugin` automatisch registriert, inklusive der eigenen Routen. In einem klassischen Composer-Setup ohne Contao Manager muss das Bundle wie jedes andere Symfony-Bundle im Kernel registriert werden.

Nach der Installation:

```bash
vendor/bin/contao-console cache:clear
vendor/bin/contao-console contao:symlinks
```

## 6. Composer-Einrichtung

* Composer-Paketname: `vtinnovations/localfonts`
* Pakettyp: `contao-bundle`
* PSR-4-Namespace: `VTinnovations\LocalFonts\` → `src/`
* Registrierung von Bundle-Klasse und Routing erfolgt über einen eigenen `contao-manager-plugin` (`extra.contao-manager-plugin` in `composer.json`), kompatibel mit `contao/manager-plugin` ^2.0.

## 7. Erforderliche ausführbare Programme und Konfiguration

Es ist keine zusätzliche Konfigurationsdatei erforderlich. Das Bundle liest seinen Arbeitsordner automatisch aus dem Contao-Projektverzeichnis ab (`var/localfonts/`, siehe Abschnitt 18) und benötigt keine weiteren Umgebungsvariablen.

## 8. Dateisystemberechtigungen

Der Webserver-Benutzer benötigt Schreibrechte auf:

* `var/localfonts/` — Zustandsdatei sowie die lokal gespeicherten Lizenzdaten (siehe Abschnitt 18).
* `files/localfonts/` — heruntergeladene Schriftdateien, generiertes Stylesheet, bereinigte CSS-Kopien.

Das Bundle legt beide Verzeichnisse bei Bedarf selbst an. Kann der automatische Symlink von `files/localfonts/` in den Webroot nicht angelegt werden, meldet das Backend dies und verweist auf:

```bash
vendor/bin/contao-console contao:symlinks
```

## 9. Backend- und Administrationszugriff

* Das Bedienmodul befindet sich unter **Layout › Local Fonts** und steht jedem Backend-Benutzer mit Zugriff auf diese Modulgruppe zur Verfügung.
* Ohne aktivierte Lizenz zeigt das Modul ausschließlich einen Hinweis mit Link zur Lizenzverwaltung; keiner der drei Schritte ist dann sichtbar oder ausführbar.
* Die Lizenzverwaltung selbst befindet sich unter **System › Einstellungen › Local Fonts Lizenzverwaltung** und ist — wie die gesamte Contao-Einstellungsseite — ausschließlich Administratoren vorbehalten. Aktivieren, Aktualisieren und Entfernen der Lizenz sind serverseitig zusätzlich auf Administratorkonten beschränkt und durch das reguläre Contao-CSRF-Token abgesichert.

## 10. Frontend-Integration

Ist eine Lizenz aktiv und die Funktion in den Einstellungen aktiviert (Standard: aktiviert), wirkt sich das Bundle wie folgt auf jede ausgelieferte Frontend-Seite aus:

* **Automatischer Modus** (Standard): Das lokale Stylesheet (`/files/localfonts/localfonts.css`) wird automatisch vor `</head>` in jede Seite eingebunden, sofern es bereits erzeugt wurde.
* **Manueller Modus**: Es wird nichts automatisch eingebunden; der Betreiber kopiert den im Backend angezeigten CSS-Code selbst in sein Layout (z.&nbsp;B. unter „Zusätzliche `<head>`-Tags").
* **Externe Google Fonts blockieren** (optional, standardmäßig deaktiviert): Verbleibende `<link>`-, `@import`- und URL-Verweise auf `fonts.googleapis.com`/`fonts.gstatic.com` werden aus der ausgelieferten Seite entfernt; bereits lokal eingebundene Fremd-Stylesheets, die ihrerseits Google-Fonts-Bezüge enthalten, werden durch bereinigte lokale Kopien ersetzt.
* Ausgelieferte Seiten erhalten den Antwort-Header `X-Local-Fonts: active`, solange die Funktion aktiv greift.

Ohne aktive Lizenz bleibt die Ausgabe unverändert — die Website verhält sich exakt wie ohne installiertes Bundle, auch wenn zuvor bereits Schriften lokal gespeichert wurden.

## 11. Navigationsmodule

| Ort | Bezeichnung | Zweck |
|---|---|---|
| Layout | **Local Fonts** | Die drei Arbeitsschritte Scannen, Laden, Einbinden |
| System › Einstellungen | **Local Fonts Lizenzverwaltung** | Lizenz aktivieren, aktualisieren, entfernen; Status anzeigen |

## 12. Verifizierte Funktionen

* **Website-Scan**: Durchsucht alle veröffentlichten regulären Seiten (inkl. verlinkter, nicht von Google stammender Stylesheets) nach Google-Fonts-Referenzen und listet gefundene Schriftfamilien mit Schnitten und Dateianzahl auf.
* **Lokales Laden**: Lädt jede gefundene Schriftdatei genau einmal herunter, legt sie unter `files/localfonts/<schrift>/` ab und erzeugt ein vollständiges `@font-face`-Stylesheet (inkl. `unicode-range` je Subset).
* **CLI-Kommando** `localfonts:scan` (Option `--download`/`-d`): führt Scan und optional Download außerhalb des Backends aus, z.&nbsp;B. für einen geplanten Cron-Lauf.
* **Automatische oder manuelle Einbindung** inkl. Umschalten zwischen beiden Modi.
* **Blockieren externer Google Fonts** inkl. Bereinigung fremder, per `<link>` eingebundener Stylesheets.
* **Entfernen** der lokal gespeicherten Schriften und des generierten Stylesheets (Zurücksetzen auf den Ausgangszustand).
* **Lizenzverwaltung**: Aktivierung, Aktualisierung und Entfernung einer Lizenz direkt in den Contao-Einstellungen inklusive Statusanzeige (Domain, Paket, Version).
* **Tägliche automatische Lizenzprüfung** (Contao Scheduler/Cron), die eine veraltete lokale Lizenz erneut mit dem Lizenzserver abgleicht, ohne dass ein Administrator eingreifen muss.

## 13. Berechtigungen und Zugriffskontrolle

| Aktion | Voraussetzung |
|---|---|
| Bedienmodul „Local Fonts" öffnen | Backend-Zugriff auf die Modulgruppe „Layout" |
| Scan/Download/Einbinden auslösen | zusätzlich: aktivierte Lizenz |
| Lizenz aktivieren/aktualisieren/entfernen | Backend-Administrator, gültiges CSRF-Token |
| Lizenzstatus einsehen | Zugriff auf die globale Einstellungsseite (administratorpflichtig) |
| Lizenz-Update-Endpunkt (server-seitig) | öffentlich erreichbar, aber nur mit gültiger kryptographischer Signatur von V&T Innovations verwertbar (siehe [Sicherheitsmodell](Documentation/Sicherheit.md)) |

## 14. Lizenz- und Berechtigungsverhalten

Local Fonts folgt dem **„Lifetime Free"**-Modell von V&T Innovations: Das Produkt ist kostenlos, benötigt aber in jedem Fall eine erfolgreich aktivierte, signierte Lizenz — es gibt keinen anonymen oder unlizenzierten Nutzungsmodus und keine kostenpflichtige Zusatzstufe.

Tatsächlich vorkommende Zustände:

| Zustand | Auswirkung |
|---|---|
| Keine Lizenz aktiviert | Keine geschützte Funktion läuft; Frontend bleibt unverändert; Backend-Modul zeigt nur den Aktivierungshinweis |
| Lizenz aktiv (Lifetime Free) | Alle in dieser Dokumentation beschriebenen Funktionen stehen zur Verfügung |
| Aktivierte Lizenz besteht die Prüfung nicht mehr (z.&nbsp;B. nach einem Domainwechsel) | Wird im Backend identisch zu „keine Lizenz aktiviert" angezeigt — es gibt keine gesonderte Fehleranzeige, die verwertbare Details preisgeben würde |

Aktivierung, Aktualisierung und Entfernung erfolgen ausschließlich über die Lizenzverwaltung in den Contao-Einstellungen (Abschnitt 9). Details zur Prüfung selbst stehen im [Sicherheitsmodell](Documentation/Sicherheit.md).

## 15. Funktionsstatus-Tabelle

| Funktion | Status |
|---|---|
| Website-Scan nach Google Fonts | Bedingt (erfordert aktive Lizenz) |
| Lokales Laden der Schriftdateien | Bedingt (erfordert aktive Lizenz) |
| Automatische Einbindung des lokalen Stylesheets | Bedingt (erfordert aktive Lizenz) |
| Manuelle Einbindung per Copy-&-Paste | Bedingt (erfordert aktive Lizenz) |
| Blockieren externer Google-Fonts-Aufrufe | Bedingt (erfordert aktive Lizenz) |
| CLI-Kommando `localfonts:scan` | Bedingt (erfordert aktive Lizenz) |
| Tägliche automatische Lizenzprüfung | Verfügbar |
| Lizenzaktivierung/-verwaltung | Verfügbar |
| Kostenpflichtige Zusatzstufe | Nicht zutreffend (reines Lifetime-Free-Produkt) |

## 16. Sicherheitsmodell

Eine ausführliche, administratorgerechte Beschreibung des Sicherheits- und Vertrauensmodells (Aktivierung, Integritätsprüfung, Ausfallverhalten) befindet sich in [`Documentation/Sicherheit.md`](Documentation/Sicherheit.md).

Kurzfassung:

* Jede Lizenzoperation wird kryptographisch signiert ausgetauscht und vor dem Übernehmen erneut geprüft.
* Lizenzdaten liegen außerhalb des Webroots (`var/localfonts/entitlement/`).
* Ein nicht erreichbarer Lizenzserver oder ein Prüffehler verändert niemals eine zuvor gültige, bereits aktivierte Lizenz.
* Fehlschläge bei der Prüfung deaktivieren ausschließlich die geschützte Funktion — sie führen nie zu einem Serverfehler auf der Website.

## 17. Betriebssicherheit

* Schreibvorgänge auf die Lizenzdaten erfolgen exklusiv gesperrt und mit vorheriger Sicherung der vorherigen gültigen Daten.
* Nach jedem Schreibvorgang wird das Ergebnis erneut gelesen und geprüft; weicht es ab, wird automatisch auf die vorherige Sicherung zurückgesetzt.
* Der öffentlich erreichbare Update-Endpunkt verarbeitet nur Anfragen mit gültiger Signatur; unsignierte oder falsch signierte Anfragen werden ohne Verarbeitung abgelehnt.

## 18. Laufzeitverzeichnisse

| Pfad | Inhalt |
|---|---|
| `var/localfonts/state.json` | Scan-/Installationsstatus, Einstellungen (Einbindungsmodus, Blockieren aktiv/inaktiv usw.) |
| `var/localfonts/entitlement/` | Lokal gespeicherte, signierte Lizenzdaten inkl. Sicherungskopie und Ablaufjournal für den Update-Endpunkt — niemals versionieren oder veröffentlichen |
| `files/localfonts/` | Heruntergeladene Schriftdateien, generiertes Stylesheet `localfonts.css`, bereinigte CSS-Kopien |

## 19. Externe Kommunikation

Das Bundle kommuniziert mit folgenden externen Zielen, jeweils ausschließlich über TLS-verifizierte HTTPS-Verbindungen mit fester Ziel-Adresse (keine Weiterleitungen, begrenzte Zeitlimits):

| Ziel | Zweck |
|---|---|
| `fonts.googleapis.com`, `fonts.gstatic.com` | Auslesen der Google-Fonts-Stylesheets und Herunterladen der Schriftdateien während Scan bzw. Download |
| `www.v-t.one` | Aktivierung, Aktualisierung und automatische tägliche Prüfung der Lizenz; ein knappes, betriebsbezogenes Signal je relevantem Seitenaufruf bzw. je Backend-Sitzung |
| eigene, veröffentlichte Seiten der Website | Der Scan ruft die eigenen Seiten der Website selbst auf, um eingebundene Google-Fonts-Stylesheets zu finden |

Der signierte Lizenz-Update-Endpunkt dieses Bundles ist umgekehrt öffentlich erreichbar, damit V&T Innovations eine aktualisierte Lizenz serverseitig zustellen kann; er verarbeitet ausschließlich kryptographisch signierte Anfragen.

## 20. Protokollierung und Schwärzung vertraulicher Daten

* Es werden ausschließlich generische, sichere Diagnosecodes protokolliert (z.&nbsp;B. „Server nicht erreichbar", „Prüfung fehlgeschlagen") — niemals der Lizenzschlüssel, ein Anfrage- oder Antwortinhalt, eine Signatur oder ein Prüfsummenwert.
* Backend-Meldungen (Erfolg/Fehler bei Aktivierung, Aktualisierung, Entfernung) verwenden dieselben allgemeinen, übersetzten Texte, keine internen Rohdaten.

## 21. Deployment

```bash
composer require vtinnovations/localfonts
vendor/bin/contao-console cache:clear
vendor/bin/contao-console contao:symlinks
```

Nach jedem Deployment mit geänderten Abhängigkeiten empfiehlt sich zusätzlich ein erneuter Website-Scan, falls sich eingebundene Google-Fonts-Referenzen im Layout geändert haben.

## 22. Cache-Leerung

```bash
vendor/bin/contao-console cache:clear
```

## 23. Tests

Das Bundle liefert eine PHPUnit-Testsuite (`tests/`) mit, besitzt aber kein eigenes `vendor/`-Verzeichnis — es ist eine Contao-Bibliothek, keine eigenständige Anwendung. Die Suite wird aus einem Projekt heraus ausgeführt, in dem das Bundle als Abhängigkeit installiert ist:

```bash
vendor/bin/phpunit --bootstrap vendor/autoload.php -c vendor/vtinnovations/localfonts/phpunit.xml.dist
```

Für die Signatur-bezogenen Tests wird die PHP-Erweiterung `ext-sodium` benötigt; ohne sie werden die betroffenen Tests automatisch übersprungen.

## 24. Fehlerbehebung

| Symptom | Ursache / Lösung |
|---|---|
| Backend-Modul zeigt nur den Lizenzhinweis | Keine aktive Lizenz — unter System › Einstellungen › Local Fonts Lizenzverwaltung aktivieren |
| Scan meldet „Seiten-URLs wurden als ‚localhost' erzeugt" | Scan wurde außerhalb eines Web-Requests (z.&nbsp;B. per Cron) ausgeführt und die Root-Seite hat keinen DNS-Eintrag; Domain am Startpunkt der Website setzen oder `framework.router.request_context.host` konfigurieren |
| Schriftdateien werden nicht unter `/files/localfonts/…` ausgeliefert | Symlink fehlt; `vendor/bin/contao-console contao:symlinks` ausführen |
| „Externe Google Fonts blockieren" aktiv, aber Schriften fehlen im Frontend | Manueller Einbindungsmodus aktiv, ohne dass der CSS-Code eingebunden wurde — automatischen Modus wählen oder den angezeigten Code einbinden |
| Ein erneuter Scan findet plötzlich keine Schriften mehr | „Externe Google Fonts blockieren" entfernt bereits vor dem eigentlichen Scan die zu findenden Referenzen aus früheren, im Cache liegenden Seitenaufrufen — Seiten-Cache vor dem Scan leeren |
| Lizenzserver antwortet nicht | Vorheriger gültiger Lizenzstatus bleibt unverändert erhalten; späterer erneuter Versuch (automatisch täglich oder manuell über „Lizenz aktualisieren") |

## 25. Bekannte Einschränkungen

* Es wird ausschließlich das Lifetime-Free-Lizenzmodell unterstützt; es existieren keine Testphase, keine Ablaufdaten und keine kostenpflichtige Zusatzstufe für dieses Produkt.
* Erkannt werden nur Schriften, die über den öffentlichen Google-Fonts-CSS2-Dienst (`fonts.googleapis.com`) eingebunden sind; selbst gehostete oder über andere Anbieter geladene Schriften werden nicht erkannt.
* Der Scan benötigt eine über das Internet erreichbare, per DNS aufgelöste Domain; ohne echten Hostnamen auf der Root-Seite liefert ein per Cron/CLI ausgeführter Scan keine brauchbaren Ergebnisse.
* Domainbindung der Lizenz erfolgt exakt (Hostname-genau); Subdomains oder alternative Schreibweisen zählen als eigenständige Domains.
* Ohne die PHP-Erweiterung `ext-sodium` kann keine Lizenz aktiviert werden — das Bundle bleibt dann dauerhaft im unlizenzierten Zustand.
* Das Entfernen der Lizenz löscht nicht die bereits heruntergeladenen Schriftdateien; diese bleiben auf dem Server, werden aber bis zur erneuten Aktivierung weder ausgeliefert noch über das Backend-Modul verwaltet.

## 26. Lizenz- und Urheberrechtsinformationen

* Lizenz: **LGPL-3.0-or-later**
* Copyright: **V&T Innovations**
* Website: [https://www.v-t.one](https://www.v-t.one)

## 27. Weitere Dokumente

* [English README](README.en.md)
* [Sicherheitsmodell](Documentation/Sicherheit.md) / [Security model](Documentation/Security.en.md)
