# Phase 2 — Architektur-Refactoring

> **Ziel:** Saubere, erweiterbare Codebasis, die moderne PHP-8.5-Features nutzt und leicht testbar ist.

> **Hinweis:** Vor jeder Umsetzung zuerst in den Plan-Mode wechseln! Betroffenen Code lesen, Abhängigkeiten verstehen, Plan mit dem User abstimmen — erst dann implementieren.

## Prio 1: God Classes aufbrechen

- [x] **MetricsController aufteilen** (434 → ~220 Zeilen)
  - `MetricTypeRegistry` extrahiert — Registrierung und Lookup von MetricTypes
  - `MetricCollectionFactory` extrahiert — Erstellung von Metric-Collections
  - MetricsController bleibt als schlanke Fassade für CRUD + Collection-Zugriffe

- [x] **IdentifyVisitor aufteilen** (809 → 454 Zeilen)
  - `ParameterAnalyzer` extrahiert — Parameter/Return-Type-Analyse (128 Zeilen)
  - `ClassMemberAnalyzer` extrahiert — Methoden/Properties/Constants (249 Zeilen)
  - Tote Arrays entfernt ($interfaces, $traits, $enums, $methods)

- [x] **HtmlReport** → Verschoben auf Phase 3 (Report-Redesign)
  - In Phase 1 bereits von 11 auf 8 Methoden konsolidiert

## Prio 2: Moderne PHP-Patterns

- [x] **Enums statt Klassen-Konstanten**
  - `MetricValueType` Enum (Int, Float, String, Array, Percentage, Count, Bool, Storage)
  - `MetricVisibility` Enum (ShowEverywhere, ShowDetails, ShowList, ShowNowhere, ShowCoupling)
  - `BetterDirection` Enum (Irrelevant, High, Low)
  - Alle Referenzen umgestellt (MetricType, MetricValue, MetricsController, Application, DataProviders, metric-types)

- [x] **Readonly Properties** — `ProjectIdentifier` als `readonly class`

- [x] **Named Arguments** — MetricType::fromArray() Constructor-Aufruf

- [x] **Constructor Promotion** — bereits einheitlich umgesetzt (MetricsSplitter, Analyzer etc.)

## Prio 3: Metric-Types modernisieren

- [x] **`data/metric-types.php` (1759 Zeilen) aufgebrochen**
  - 6 Kategorie-Dateien: project.php (94), file.php (45), class.php (21), method.php (5), function.php (0), package.php (8)
  - metric-types.php ist jetzt ein schlanker Loader mit `array_merge(require ...)`

- [x] **Inkonsistentes Naming gefixt**
  - `overAll` → `overall` für 4 Metric-Keys (MethodsCount, Public/Private/Static)
  - Alle Referenzen in Code und Tests aktualisiert

- [ ] **Validierung der Metric-Definitionen** (nice-to-have)
  - Schema-Validation beim Laden: Pflichtfelder prüfen
  - Doppelte Keys erkennen und warnen

## Prio 4: Variable-Variable-Pattern entfernen

- [x] **`CouplingCalculator.php`** — `$this->$collectionKey` durch `$this->collectionMap[...]` ersetzt

## Prio 5: Test-Infrastruktur ausbauen

- [x] **Unit-Tests für MetricsController** — CRUD-Operationen, Collection-Zugriff (6 Tests)
- [x] **Unit-Tests für MetricTypeRegistry** — Registrierung, Lookup, Visibility-Filter (5 Tests)
- [x] **Unit-Tests für MetricCollectionFactory** — Erstellung aller Collection-Typen (7 Tests)
- [x] **Unit-Tests für MaintainabilityIndexCalculator** — MI-Formel, Defaults, Skip-Logik (3 Tests)
- [ ] **Unit-Tests für Report-DataProviders** (verschoben auf Phase 3)
- [ ] **Integration-Tests: Full Pipeline** (verschoben)
- [ ] **Performance-Benchmarks** (verschoben)
