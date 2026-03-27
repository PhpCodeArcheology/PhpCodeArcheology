# Phase 6 — AI-Integration & Machine-Readable Output

> **Ziel:** Output-Formate und Features, die es einer KI (und CI/CD-Pipelines) ermöglichen,
> den Code automatisiert zu verstehen und Verbesserungsvorschläge zu machen.

> **Hinweis:** Vor jeder Umsetzung zuerst in den Plan-Mode wechseln! Betroffenen Code lesen, Abhängigkeiten verstehen, Plan mit dem User abstimmen — erst dann implementieren.

## Prio 1: Strukturierter JSON/SARIF-Output

- [ ] **JSON-Report-Format**
  - Vollständiger Report als strukturiertes JSON
  - Schema-Definition (JSON Schema) für Tooling-Integration
  - Enthält alle Metriken, Problems, Predictions, Git-Daten
  - Stabil versioniertes Format (v1, v2, ...)

- [ ] **SARIF-Output (Static Analysis Results Interchange Format)**
  - Industrie-Standard für statische Analyse-Ergebnisse
  - Direkt importierbar in GitHub Code Scanning, VS Code, IDEs
  - Problems als SARIF Results mit Location, Message, Level

- [ ] **Markdown-Report für LLM-Konsum**
  - Optimierter Markdown-Output den eine KI direkt verarbeiten kann
  - Klare Struktur: Zusammenfassung → Top-Probleme → Metriken → Details
  - Token-effizient: Nur relevante Daten, keine Deko
  - Configurable: `--ai-summary` Flag für kompakten Output

## Prio 2: AI-Prompt-Generierung

- [ ] **Automatische Refactoring-Prompts**
  - Pro Problem-Klasse: Vorgefertigter Prompt-Text für eine KI
  - Beispiel: "Die Klasse `UserService` hat CC=45 und LCOM=5. Schlage ein Refactoring vor,
    das die Klasse in kleinere, fokussierte Services aufteilt."
  - Kontext: Relevante Metriken, Abhängigkeiten, betroffene Methoden

- [ ] **CLAUDE.md Generator**
  - Automatisch eine `CLAUDE.md` generieren die den Projekt-Zustand beschreibt
  - Architektur-Überblick aus den Metriken abgeleitet
  - Top-Probleme und Empfehlungen
  - Package-Struktur und Abhängigkeiten
  - Aktualisierbar bei jedem Run

- [ ] **Context-Dateien für AI-Tools**
  - Pro problematische Klasse: Kontextdatei mit allem was eine KI braucht
  - Metriken, Abhängigkeiten, verwendete Klassen, Problem-Beschreibung
  - Direkt als Input für `claude`, `cursor`, `copilot` etc.

## Prio 3: CI/CD-Integration

- [ ] **Exit-Codes nach Konfiguration**
  - Konfigurierbare Quality Gates: "Exit 1 wenn CC > 20 irgendwo"
  - Threshold-Konfiguration in der Config-Datei
  - `--strict` Mode: Jedes Warning ist ein Fehler
  - `--baseline` Mode: Nur neue Probleme sind Fehler

- [ ] **GitHub Actions Integration**
  - Action-Definition (`action.yml`) für einfache Integration
  - PR-Kommentar mit Metrik-Zusammenfassung
  - Annotations an geänderten Zeilen
  - Status-Check mit konfigurierbaren Thresholds

- [ ] **Diff-Mode: Nur geänderte Dateien analysieren**
  - `--diff=main` analysiert nur Dateien die sich geändert haben
  - Deutlich schneller für CI/CD
  - Vergleich: Aktuelle Metriken vs. Base-Branch

## Prio 4: API & Programmatische Nutzung

- [ ] **PHP-API für programmatische Nutzung**
  - Sauberes Interface um Metriken programmatisch abzufragen
  - `$analyzer->analyze($path)->getClass('App\\UserService')->getMetric('cc')`
  - Composer-Library-Nutzung ohne CLI

- [ ] **Webhook/Event-System**
  - Events bei Analyse-Ergebnissen (für Custom-Integrationen)
  - Webhook an externe Services (Slack, Teams, etc.)
  - Plugin-System für eigene Metriken und Predictions
