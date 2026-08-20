🇬🇧 [English version](Security.en.md)

# Sicherheitsmodell — Local Fonts

Dieses Dokument beschreibt das Sicherheits- und Vertrauensmodell von **Local Fonts** auf Administratorebene: welche Zusicherungen gelten, welches Verhalten von Umgebungsbedingungen abhängt und wie sich das Bundle im Fehlerfall verhält. Es beschreibt bewusst **nicht**, wie die Prüfungen intern implementiert sind — das ist kein technisches Implementierungshandbuch.

## Geltungsbereich

Das Sicherheitsmodell betrifft zwei getrennte Bereiche:

1. **Lizenzierung/Berechtigung** — Aktivierung, Aktualisierung, Entfernung und die serverseitige Zustellung einer aktualisierten Lizenz durch V&T Innovations.
2. **Kern-Funktion** (Scan, Download, Einbindung) — läuft ausschließlich, wenn Bereich 1 eine aktive Lizenz bestätigt.

## Zugesichertes Verhalten

Die folgenden Zusicherungen sind durch die Implementierung und die begleitende automatisierte Testsuite abgedeckt:

* **Signierter, authentifizierter Austausch.** Jede Lizenzoperation (Aktivierung, Aktualisierung sowie eine serverseitig ausgelöste Zustellung) wird als kryptographisch signierte Nachricht ausgetauscht. Eine Antwort, die nicht zur ursprünglichen Anfrage gehört oder deren Signatur nicht zu einem hinterlegten, vertrauenswürdigen Schlüssel von V&T Innovations passt, wird verworfen und wirkt sich nicht auf den bestehenden Lizenzstatus aus.
* **Kein Rückschritt.** Eine neu empfangene Lizenz wird nur übernommen, wenn ihre Version nicht älter ist als die zuvor aktive. Eine ältere oder inkonsistente Antwort wird abgelehnt.
* **Domainbindung.** Eine Lizenz gilt ausschließlich für die Domain(s), für die sie signiert wurde, und nur, wenn diese mit einer für diese Installation tatsächlich vertrauenswürdig konfigurierten Domain übereinstimmt.
* **Atomare Übernahme mit Sicherung.** Beim Aktivieren einer neu geprüften Lizenz wird zunächst die vorherige gültige Lizenz gesichert, dann die neue geschrieben und anschließend erneut gelesen und verglichen. Weicht das Ergebnis ab, wird automatisch auf die gesicherte, zuvor gültige Lizenz zurückgesetzt. Dieses Verhalten ist durch automatisierte Tests abgedeckt.
* **Fail-closed.** Jeder unerwartete Zustand — nicht erreichbarer Server, fehlerhafte Antwort, fehlende Kryptographie-Unterstützung, beschädigte lokale Daten — führt dazu, dass die geschützte Funktion als „nicht lizenziert" behandelt wird. Er führt nie zu einem Serverfehler der Website und nie dazu, dass eine zuvor gültige Lizenz stillschweigend als weiterhin gültig angenommen wird, wenn eine erneute Prüfung tatsächlich fehlschlägt.
* **Authentifizierter Update-Endpunkt.** Der öffentlich erreichbare Endpunkt, über den V&T Innovations eine aktualisierte Lizenz zustellen kann, verarbeitet ausschließlich Anfragen mit gültiger kryptographischer Signatur. Herkunftsangaben wie eine behauptete Absenderadresse werden nicht als Vertrauensnachweis herangezogen. Bereits verarbeitete Anfragen werden erkannt und nicht doppelt angewendet (Wiederholungsschutz).
* **Private Ablage.** Sämtliche Lizenzdaten liegen außerhalb des öffentlich erreichbaren Web-Verzeichnisses (`var/localfonts/entitlement/`), nicht unter `files/` oder `public/`.
* **Geschwärzte Protokollierung.** Protokolliert werden ausschließlich generische, sichere Diagnosecodes. Lizenzschlüssel, Anfrage- oder Antwortinhalte, Signaturen und Prüfsummen werden nie protokolliert.

## Bedingtes Verhalten (abhängig von der Umgebung)

* Die Lizenzprüfung setzt die PHP-Erweiterung `ext-sodium` voraus. Ist sie nicht installiert, kann grundsätzlich keine Lizenz aktiviert werden; das Bundle bleibt dauerhaft im unlizenzierten Zustand.
* Das automatische Anlegen des Symlinks nach `files/localfonts/` in den Webroot hängt von den Dateisystemrechten der jeweiligen Installation ab. Schlägt es fehl, informiert das Backend darüber und verweist auf den manuellen Contao-Konsolenbefehl.
* Die tägliche automatische Neuprüfung einer bestehenden Lizenz erfolgt nur, wenn die letzte erfolgreiche Prüfung länger als 24 Stunden zurückliegt; ein vorübergehend nicht erreichbarer Server verzögert lediglich die nächste Prüfung, ohne die aktuell gültige Lizenz zu beeinträchtigen.

## Best-Effort-Verhalten

* Ein knappes, betriebsbezogenes Signal je relevantem Frontend-Aufruf sowie ein einmaliges Signal je Backend-Sitzung an V&T Innovations sind bewusst **best effort**: Ein Fehlschlag dieses Signals wirkt sich nie auf die Gültigkeit der Lizenz, auf die Auslieferung der Website oder auf die Backend-Bedienung aus.

## Ausdrücklich nicht zugesichert

* Es wird nicht zugesichert, dass das Lizenzierungs- oder Sicherheitssystem grundsätzlich nicht umgangen oder nachgebildet werden kann. Diese Dokumentation macht dazu bewusst keine Aussage und beschreibt keine internen Prüfmechanismen, Schlüssel, Signaturformate oder Implementierungsdetails.
* Es besteht keine Garantie für die Zustellung der in diesem Abschnitt genannten Best-Effort-Signale.

## Weitere Dokumente

* [Deutsches README](../README.md)
* [English security model](Security.en.md)
