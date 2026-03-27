# Phase 5 — Git-Integration

> **Ziel:** Temporale Metriken aus der Git-History, die zeigen wie der Code sich entwickelt und wo Risiken liegen.

> **Hinweis:** Vor jeder Umsetzung zuerst in den Plan-Mode wechseln! Betroffenen Code lesen, Abhängigkeiten verstehen, Plan mit dem User abstimmen — erst dann implementieren.

## Prio 1: Grundlegende Git-Analyse

- [ ] **Git-History-Reader implementieren**
  - `GitAnalyzer`-Klasse die `git log` auswertet
  - Konfigurierbar: Zeitraum, Branch, Autoren
  - Caching der Git-Daten für schnelle Re-Runs
  - Fallback: Graceful degrade wenn kein Git-Repo

- [ ] **Change Frequency (Churn Rate)**
  - Wie oft wurde eine Datei/Klasse in den letzten N Monaten geändert
  - Hotspot-Erkennung: Häufig geändert + hohe Complexity = Risiko
  - Referenz: Adam Tornhill "Your Code as a Crime Scene"
  - Report: Churn-vs-Complexity Bubble Chart

- [ ] **Code Age**
  - Durchschnittsalter des Codes pro Datei (letzter Commit pro Zeile)
  - Zeigt: Welcher Code wurde lange nicht angefasst?
  - Alter Code + hohe Complexity = "schlafende Gefahr"

- [ ] **Author Diversity / Knowledge Distribution**
  - Wie viele Autoren haben an einer Datei gearbeitet?
  - "Bus Factor": Dateien die nur ein Autor kennt
  - Knowledge-Map: Wer kennt welchen Teil des Codes?

## Prio 2: Trend-Analyse

- [ ] **Metrik-Trends über Git-Tags/Releases**
  - Analyse zu verschiedenen Zeitpunkten (Tags) ausführen
  - Trend: Wird der Code besser oder schlechter?
  - Automatische Warnung: "Complexity steigt seit 3 Releases"

- [ ] **Commit-Impact-Analyse**
  - Welche Commits haben die Metriken am meisten verschlechtert?
  - "Dieser Commit hat die durchschnittliche CC um 15% erhöht"
  - Nützlich für Code-Review-Prozesse

- [ ] **Growth Rate**
  - LOC-Wachstum über Zeit
  - Verhältnis: Neuer Code vs. geänderter Code vs. gelöschter Code
  - Trend: Wächst das Projekt kontrolliert oder explodiert es?

## Prio 2b: Von anderen Phasen verschoben

- [ ] **OCP (Open/Closed Principle) Indicator**
  - Klassen die häufig geändert werden → OCP-Verstoß
  - Kombination aus Git-Churn + Metrik-Daten
  - Aus Phase 4 SOLID Indicators hierher verschoben

- [ ] **Inline-Sparklines in Tabellen**
  - Mini-Trend-Charts pro Entity (Klasse/Datei) in den Listen-Tabellen
  - Benötigt History-Daten pro Entity (nicht nur Projekt-Level)
  - Aus Phase 3 Report-Redesign hierher verschoben

## Prio 3: Erweiterte Git-Metriken

- [ ] **Temporal Coupling**
  - Dateien die immer zusammen geändert werden (aber nicht voneinander abhängen)
  - Hinweis auf versteckte Kopplung oder fehlendes Refactoring
  - Darstellung als Coupling-Graph

- [ ] **Refactoring-History**
  - Erkennung von Refactoring-Commits (Renames, Moves, Extracts)
  - Zeigt: Wird aktiv am Code gearbeitet oder nur Features draufgepackt?

- [ ] **Code Review Insights**
  - Wenn PR-Daten verfügbar (GitHub/GitLab API):
  - Review-Coverage: Welcher Code wurde reviewed?
  - Review-Turnaround: Wie lange dauern Reviews?
  - Merge-Frequency pro Package

## Prio 4: Report-Integration

- [ ] **Git-Dashboard-Seite im Report**
  - Timeline-Chart: Commits über Zeit
  - Hotspot-Map: Churn × Complexity
  - Author-Distribution-Chart
  - Code-Age-Heatmap

- [ ] **Git-Metriken in bestehende Seiten integrieren**
  - Klassen-Detail: Letzte Änderung, Änderungshäufigkeit, Autoren
  - Datei-Detail: Git-Blame-Zusammenfassung, Code-Age per Abschnitt
  - Problem-Seite: "Häufig geändert UND problematisch" als eigene Kategorie
