# Phase 3 — Report-Redesign

> **Ziel:** Visuell ansprechende, interaktive Reports, die auf einen Blick den Zustand des Projekts zeigen.

> **Hinweis:** Vor jeder Umsetzung zuerst in den Plan-Mode wechseln! Betroffenen Code lesen, Abhängigkeiten verstehen, Plan mit dem User abstimmen — erst dann implementieren.

## Prio 1: Dashboard komplett neu gestaltet

- [x] **Health Score mit Gauge-Chart**
  - HealthScoreCalculator berechnet Score 0-100 (gewichtet: MI 30%, Problems 25%, CC 20%, Coupling 15%, Structure 10%)
  - Doughnut-Chart als visueller Gauge, farbcodiert (Grün/Gelb/Rot)
  - Grade A-F als Textanzeige

- [x] **Übersichts-Karten mit Key Metrics**
  - Codebase: LOC, Files, Classes, Methods
  - Complexity: Avg CC, Avg MI, Functions
  - Farbcodierung nach Schweregrad

- [x] **Problem-Summary als Bar-Chart**
  - Horizontaler Balken: Errors/Warnings/Info mit Chart.js
  - Direkte Zahlen + Farbcodes

- [x] **Top Problems Liste**
  - Die schlimmsten Klassen/Dateien direkt auf dem Dashboard
  - Klickbar → führt zur Detail-Seite
  - "View all problems" Link

- [x] **Trend-Chart über alle Runs**
  - JSONL-History (append-only, eine Zeile pro Run)
  - Linienchart mit Avg CC und Avg MI über Zeit
  - Dual-Y-Achsen für verschiedene Skalen
  - Migration von alter history.json automatisch

- [x] **Alle Metriken als Grid**
  - Bestehende metric-tiles am Ende des Dashboards

## Prio 2: Visualisierungen

- [x] **Instability-Abstractness-Chart: Plotly → Chart.js**
  - Scatter-Chart mit Chart.js Custom-Plugin für farbige Zonen
  - Zone Labels: "Zone of Pain", "Zone of Uselessness", "Main Sequence"
  - Baseline-Diagonale, interaktive Tooltips

- [x] **Dependency-Graph interaktiv machen**
  - Mermaid ersetzt durch Bubble Chart (CC vs Coupling) + Dependency Matrix
  - Klassen-Detail: CSS-basiertes Dependency-Flow-Diagramm

- [x] **Complexity-Heatmap** (Methoden-CC Bar-Chart auf Klassen-Detail)
- [x] **Code-Smell-Radar/Spider-Chart pro Klasse** (Radar: CC/LCOM/LOC/Uses/UsedBy)

## Prio 3: Tabellen & Listen modernisiert

- [x] **Client-seitige Pagination** (50 pro Seite)
  - Automatisch bei > 50 Zeilen
  - Seitennavigation mit Seitenzahlen
  - Reagiert auf Filter und Sort

- [x] **Filter verbessert**
  - Robusteres Matching (data-filter Attribut oder textContent Fallback)
  - Filter + Pagination Integration

- [x] **Sortierung verbessert**
  - Sort-Icons mit Rotation-Feedback
  - Re-Pagination nach Sort

- [x] **Erweiterte Filter** (Problem-Level Dropdown: All/Errors/Warnings/With issues/No issues)
- [ ] **Inline-Sparklines in Tabellen** (benötigt History-Daten pro Entity → Phase 5)

## Prio 4: Detail-Seiten

- [x] **Klassen-Detail: Methoden-Heatmap** (CC Bar-Chart, farbcodiert)
- [x] **Klassen-Detail: Class Profile Radar** (CC/LCOM/LOC/Uses/UsedBy)
- [x] **Breadcrumb-Navigation: Package → File → Class → Method**
  - Namespace im Class-Breadcrumb, Classes-Link in Chart/Coupling-Breadcrumbs
  - Method-Breadcrumb: Home → Classes → Namespace → ClassName → Method

## Prio 5: Technische Verbesserungen

- [x] **Offline-fähig — Alle Assets lokal gebündelt**
  - Chart.js v4 (206KB) → ersetzt Plotly (3.5MB)
  - Mermaid v10 (3.3MB) → lokal
  - svg-pan-zoom (30KB) → lokal
  - Sortable (1KB) → lokal
  - Keine CDN-Referenzen mehr

- [x] **Tailwind v3.4.17 kompiliert**
  - Standalone CLI (kein Node.js nötig)
  - Nur genutzte Klassen → 17KB statt 33KB
  - Minifiziert

- [x] **Dark/Light Theme Toggle** (CSS Overrides + localStorage)
- [x] **Responsive Navigation** (Hamburger-Menu auf Mobile)
- [x] **Export-Funktionen** (CSV-Export aus gefilterten Tabellen)

## Backend-Änderungen

- [x] **History: JSON → JSONL** (append-only, Multi-Run-Tracking)
- [x] **HealthScoreCalculator** (neuer Calculator in der Pipeline)
- [x] **HistoryDataProvider** (liest alle JSONL-Zeilen für Trend-Charts)
- [x] **Plotly-Referenzen komplett entfernt** (`usesCharts` etc.)
