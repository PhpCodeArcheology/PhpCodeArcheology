# PhpCodeArcheology — Roadmap & Tasks

Dieses Verzeichnis enthält die strukturierte Aufgabenliste für die Weiterentwicklung von PhpCodeArcheology.
Die Tasks sind in Phasen und Prioritäten organisiert.

## Phasen-Übersicht

| Phase | Fokus | Status |
|-------|-------|--------|
| [Phase 1](phase-1-stability.md) | Stabilität & Memory | Offen |
| [Phase 2](phase-2-refactoring.md) | Architektur-Refactoring | Offen |
| [Phase 3](phase-3-reports.md) | Report-Redesign | Offen |
| [Phase 4](phase-4-metrics.md) | Neue Metriken | Offen |
| [Phase 5](phase-5-git.md) | Git-Integration | Offen |
| [Phase 6](phase-6-ai.md) | AI-Integration & Output | Offen |
| [Phase 7](phase-7-dx.md) | Developer Experience | Offen |
| [Phase 8](phase-8-knowledge-graph-mcp.md) | Knowledge Graph, MCP Server & Report-Isolation | Offen |

## Agent-Lead Prompts (Phase 8)

Die Phase-8-Features sind als Agent-Lead-Prompts aufbereitet — jede Datei ist ein eigenständiger Prompt für Claude Code, der ein Agent-Team koordiniert:

| Version | Feature | Prompt | Abhängigkeit |
|---------|---------|--------|--------------|
| v1.6.0 | Report-Verzeichnis-Isolation | [v1.6.0-report-isolation.md](v1.6.0-report-isolation.md) | Keine |
| v1.7.0 | Knowledge Graph JSON-Export | [v1.7.0-knowledge-graph-export.md](v1.7.0-knowledge-graph-export.md) | v1.6.0 |
| v1.8.0 | D3 Knowledge Graph Visualisierung | [v1.8.0-d3-visualization.md](v1.8.0-d3-visualization.md) | v1.7.0 |
| v2.0.0 | MCP Server | [v2.0.0-mcp-server.md](v2.0.0-mcp-server.md) | v1.7.0 |
| v2.1.0 | Inkrementelle Analyse | [v2.1.0-incremental-analysis.md](v2.1.0-incremental-analysis.md) | v2.0.0 |

## Wie wir arbeiten

- Tasks werden Phase für Phase abgearbeitet
- Innerhalb einer Phase: Priorität 1 zuerst
- Jeder Task hat eine Checkbox — abhaken wenn erledigt
- Bei Fragen oder Änderungen: direkt im jeweiligen Phase-Dokument anpassen

## Wichtig: Immer zuerst planen!

**Vor jeder Umsetzung muss geplant werden.** Bevor Code geschrieben oder geändert wird:

1. **In den Plan-Mode wechseln** — Den betroffenen Code lesen, Abhängigkeiten verstehen, Risiken identifizieren
2. **Plan mit dem User abstimmen** — Erst wenn der Ansatz bestätigt ist, mit der Umsetzung beginnen
3. **Keine voreiligen Änderungen** — Lieber einmal zu viel planen als blind refactoren

Das gilt besonders für Refactoring-Tasks, wo unüberlegte Änderungen schnell Regressionen verursachen.
