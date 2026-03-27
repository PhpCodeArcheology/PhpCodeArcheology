# Phase 7 — Developer Experience

> **Ziel:** Das Tool soll sich nahtlos in den Entwickler-Workflow einfügen und Spaß machen.

> **Hinweis:** Vor jeder Umsetzung zuerst in den Plan-Mode wechseln! Betroffenen Code lesen, Abhängigkeiten verstehen, Plan mit dem User abstimmen — erst dann implementieren.

## Prio 1: CLI verbessern — ERLEDIGT (Batch A)

- [x] **Progress-Bar und Echtzeit-Feedback**
  - Fortschrittsbalken mit ETA in Analyzer, Calculator, Predictor, Report
  - Am Ende: Zusammenfassung mit Ergebnis-Überblick (Files, CC, MI, Health, Errors)

- [ ] **`--watch` Mode** — DEFERRED (braucht Incremental-Analysis-Cache)
  - Dateisystem überwachen und bei Änderungen automatisch neu analysieren
  - Nur geänderte Dateien re-analysieren (inkrementell)
  - Live-Update des Reports

- [x] **`--quick` Mode**
  - Nur die wichtigsten Metriken berechnen (CC, LOC, MI)
  - Keine Report-Generierung, nur Terminal-Output
  - Top-10 Files/Classes nach Complexity

- [x] **Farbige Terminal-Ausgabe**
  - CliFormatter: zentrales Farbmanagement
  - Grün/Gelb/Rot für Problem-Level
  - Tabellen-Formatierung im Terminal für `--quick`
  - `--no-color` Flag + NO_COLOR Env + Pipe-Detection

## Prio 2: Konfiguration verbessern — TEILWEISE ERLEDIGT (Batch B + D)

- [x] **`init`-Befehl**
  - `phpcodearcheology init` erstellt eine kommentierte Config-Datei
  - Interaktiver Setup: Welche Verzeichnisse? Report-Dir? Report-Type?
  - Erkennt automatisch `src/`, `app/`, `lib/` Verzeichnisse

- [x] **Config-Validierung mit hilfreichen Fehlermeldungen**
  - "Path 'srx' does not exist. Did you mean 'src'?"
  - Alle ungültigen Optionen auflisten, nicht beim ersten Fehler stoppen
  - reportType gegen bekannte Typen validieren

- [ ] **Metrik-Konfiguration** — OFFEN
  - Eigene Thresholds für Predictions definierbar in YAML
  - Metriken an/ausschalten

- [x] **Schwellenwerte auf Plausibilität geprüft** (Batch D)
  - Review aller 13 Prediction-Thresholds — alle plausibel
  - LowTypeCoverage: jetzt zwei Stufen (<40% Error, <60% Warning)
  - MI-Schwellenwert dynamisiert: großzügiger bei hoher Type-Coverage (>80%)

## Prio 3: Vergleichs-Features — ERLEDIGT (Batch C)

- [x] **Zwei Reports vergleichen**
  - `phpcodearcheology compare report-v1.json report-v2.json`
  - Delta-Ansicht: Metrics Before/After, neue/gelöste Probleme

- [x] **Baseline-Management**
  - `phpcodearcheology baseline create` — Aktuellen Stand als Baseline speichern
  - `phpcodearcheology baseline check` — Nur neue Probleme anzeigen, Exit 1 bei neuen Errors

## Prio 4: Dokumentation & Erklärungen — TEILWEISE ERLEDIGT (Batch D)

- [x] **Metrik-Glossar im Report**
  - Custom HTML-Tooltips auf allen Metrik-Tiles (sofort bei Hover, 95 Tooltips)
  - Glossar-Seite mit allen Metriken gruppiert + Prediction-Schwellenwerten
  - 81 fehlende Metric-Descriptions aufgefüllt (project, class, file, method, package)

- [x] **Markdown-Report teilweise aktualisiert**
  - git.html.twig und glossary.html.twig für Markdown hinzugefügt
  - index.md.twig erweitert um Problems-Zusammenfassung
  - MarkdownReport Template-Fallback (.md.twig → .html.twig) implementiert
  - BEKANNTES ISSUE: Markdown-Templates sind größtenteils HTML-Kopien mit falschen Includes — braucht eigenständiges Refactoring

- [ ] **Vollständige README mit Beispielen** — OFFEN
  - Installation (Composer global, per-project)
  - Quick Start
  - Alle CLI-Optionen dokumentiert (inkl. neue: --quick, --no-color, --fail-on, --generate-claude-md)
  - Config-File-Referenz
  - Subcommands: init, compare, baseline

## Prio 5: Robustheit & Qualität — ERLEDIGT (Batch D)

- [x] **History nur bei Änderungen schreiben**
  - Duplikat-Erkennung via Data-Hash (md5)
  - Bei gleichen Daten: Zeitstempel der letzten Zeile aktualisiert statt neue Zeile

- [x] **Schwellenwerte auf Plausibilität geprüft**
  - Alle 13 Predictions reviewed
  - LowTypeCoverage: zwei Stufen implementiert
  - MI: dynamische Toleranz bei hoher Type-Coverage
  - Duplikat overallAvgCCFile in project.php entfernt

- [x] **Exit-Codes konsistent**
  - `--fail-on=error|warning` (Phase 6)
  - `baseline check` gibt Exit 1 bei neuen Errors (Phase 7C)
  - `compare` gibt Exit 0 (informational)

## Prio 6: Distribution — Spätere Phase

- [ ] **Phar-Distribution**
  - Single-File-Download: `phpcodearcheology.phar`
  - Kein Composer nötig

- [ ] **Docker-Image**
  - `docker run phpcodearcheology /path/to/project`
  - Multi-Arch (amd64, arm64)

- [ ] **Composer Plugin**
  - `composer analyze` als Shortcut

## Spätere Phasen (nicht Phase 7)

- [ ] **Performance — Caching, inkrementelle Analyse**
  - Voraussetzung für --watch Mode
  - Per-File Result Cache in MetricsContainer

- [ ] **Plugin-System — Erweiterbarkeit durch externe Plugins**
  - Custom Visitors, Calculators, Predictions als externe Packages
  - Plugin-Discovery via Composer

- [ ] **Konfigurierbarkeit — Custom Rules, .editorconfig-ähnlich**
  - Eigene Prediction-Rules definierbar
  - Per-Directory Konfiguration

- [ ] **Markdown-Report vollständig refactoren**
  - Templates von HTML-Kopien auf echtes Markdown umstellen
  - Include-Pfade korrigieren (parts/header.html.twig → parts/header.md.twig)
