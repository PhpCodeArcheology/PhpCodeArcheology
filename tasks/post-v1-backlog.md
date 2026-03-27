# Post v1.0 — Backlog

> Items die nach dem v1.0 Release angegangen werden.

## Performance & Caching

- [ ] **Inkrementelle Analyse**
  - Per-File Result Cache in MetricsContainer
  - Nur geänderte Dateien neu analysieren (basierend auf filemtime)
  - Voraussetzung für --watch Mode

- [ ] **`--watch` Mode**
  - Dateisystem überwachen und bei Änderungen automatisch neu analysieren
  - Live-Update des Reports
  - Polling-basiert (filemtime) da PHP keine cross-platform FS-Events hat

## Erweiterbarkeit

- [ ] **Plugin-System**
  - Custom Visitors, Calculators, Predictions als externe Composer-Packages
  - Plugin-Discovery via Composer autoload
  - Plugin-Interface für Registrierung

- [ ] **Custom Rules**
  - Eigene Prediction-Rules definierbar in YAML oder PHP
  - Per-Directory Konfiguration (.editorconfig-ähnlich)
  - Rule-Sets (z.B. "strict", "legacy", "laravel")

## Report-Verbesserungen

- [ ] **Beispiel-Reports**
  - Öffentlich gehosteter Example-Report
  - Generiert aus einem bekannten Open-Source-Projekt
  - Zeigt alle Features des Tools

- [ ] **Report-Vergleich im HTML-Report**
  - Delta-Ansicht direkt im HTML-Report (nicht nur CLI)
  - Visuelle Diff-Ansicht für Metriken

## Weitere Ideen

- [ ] **Metrik-Konfiguration erweitern**
  - Metriken komplett an/ausschalten per Config
  - Custom Labels für Metriken
  - Eigene Metrik-Gruppen definieren

- [ ] **Composer Plugin**
  - `composer analyze` als Shortcut
  - Automatische Konfiguration basierend auf `composer.json` Autoload
