# Verbesserungsplan — Code-Review März 2026

> Umfassende Analyse durch 5 spezialisierte Review-Agenten.
> Ziel: Health Score von **77.9 (C)** auf **85+ (B)** steigern und Code-Qualität systematisch verbessern.

---

## Überblick: Aktuelle Lage

| Metrik | Wert |
|--------|------|
| Health Score | 77.9 / 100 (Grade C) |
| Technical Debt | 9.38 debt-points / 100 LOC |
| Errors / Warnings / Info | 1.471 / 119 / 6 |
| Dateien / Klassen / LLOC | 195 / 173 / 11.810 |
| Avg. Cyclomatic Complexity | 6.53 |
| Avg. Maintainability Index | 93.56 |
| PHPStan Level max Fehler | 1.000+ |
| PHP CS Fixer Änderungen | 157 / 195 Dateien (80,5%) |
| Rector Änderungen | 99 / 195 Dateien (50,8%) |

---

## Phase 1: Kritische Bugs fixen (Prio: SOFORT)

Diese Bugs verfälschen aktiv die Analyse-Ergebnisse und müssen vor allem anderen behoben werden.

### 1.1 Halstead-Operand-Bug auf Methoden-Level
- **Datei:** `src/Analysis/HalsteadMetricsVisitor.php:319`
- **Problem:** `get_class($node)` statt `$name` für Methoden-Level Operanden → alle Methoden-Halstead-Metriken (Volume, Difficulty, Effort) sind falsch berechnet
- **Impact:** Verfälscht Difficulty-Werte aller Methoden → 1.471 Errors basieren teils auf falschen Grunddaten
- **Fix:** `$this->functionOperands[$key][] = $name;` (analog zu Zeilen 307 und 313)

### 1.2 CouplingCalculator::handleMetric() — fehlender getValue()
- **Datei:** `src/Calculators/CouplingCalculator.php:256–286`
- **Problem:** `getMetricValueByIdentifierString()` gibt MetricValue-Objekt zurück, nicht den Wert. Array-Append und Inkrement auf MetricValue-Objekt statt auf dem eigentlichen Wert
- **Impact:** File/Function-Kopplungsberechnung ist komplett falsch oder wirft Runtime-Fehler
- **Fix:** `->getValue() ?? []` analog zu `handleClass()` ergänzen

### 1.3 LayerViolationCalculator — Layer 0 wird übersprungen
- **Datei:** `src/Calculators/LayerViolationCalculator.php:82`
- **Problem:** `$layerIndex <= 0` statt `$layerIndex < 0` → Entity/Model/Domain-Klassen (Layer 0), die Services importieren, erzeugen keine Violation
- **Fix:** `if ($layerIndex < 0) { continue; }`

### 1.4 MaintainabilityIndexCalculator — inkonsistenter Default
- **Datei:** `src/Calculators/MaintainabilityIndexCalculator.php:43–49`
- **Problem:** Default `maintainabilityIndex = 171` aber `maintainabilityIndexWithoutComments = 50` bei `commentWeight = 0` → logischer Widerspruch
- **Fix:** Beide auf denselben Wert setzen (171 für "perfekt" oder konsistente Formel anwenden)

### 1.5 DistanceFromMainline — fehlendes abs()
- **Datei:** `src/Calculators/CouplingCalculator.php:135`
- **Problem:** Robert Martin Formel D = |A + I - 1|, Absolutwert fehlt bei der Speicherung → negative Werte möglich
- **Fix:** `abs($overallAbstractness + $avgInstability - 1)` direkt bei Berechnung

---

## Phase 2: Sicherheitslücken schließen (Prio: HOCH)

### 2.1 XXE-Schutz bei XML-Parsing (MEDIUM)
- **Datei:** `src/Application/Service/CloverXmlParser.php:17`
- **Problem:** `simplexml_load_file()` ohne explizite Entity-Deaktivierung; in PHP 8.0+ standardmäßig sicher, aber kein expliziter Schutz im Code
- **Fix:** `LIBXML_NONET`-Flag verwenden oder via `DOMDocument` mit deaktivierten Entities laden

### 2.2 Script-Injection in HTML-Reports (MEDIUM)
- **Dateien:** `templates/html/knowledge-graph.html.twig:317`, `templates/html/index.html.twig:486`
- **Problem:** `{{ graphJson|raw }}` in `<script>`-Tags ohne `JSON_HEX_TAG` → `</script>` in JSON könnte Script-Tag schließen
- **Fix:** `JSON_HEX_TAG | JSON_HEX_AMP` zu allen `json_encode()`-Aufrufen in `HtmlReport.php` hinzufügen

### 2.3 Twig Debug immer aktiv (LOW)
- **Datei:** `src/Application/Application.php:163–166`
- **Fix:** Debug-Extension nur bei explizitem Flag/Env-Variable aktivieren

### 2.4 Exception-Leakage in MCP-Tools (LOW)
- **Datei:** `src/Mcp/Tools/SearchCodeTool.php:96` (und andere MCP-Tools)
- **Fix:** Generische Fehlermeldung nach außen, Details intern loggen

### 2.5 memory_limit ohne Validierung (LOW)
- **Dateien:** `src/Application/Application.php:91`, `src/Application/Command/BaselineCommand.php:54`
- **Fix:** Whitelist-Validierung mit Regex `^[0-9]+[KMG]?$`

### 2.6 `|raw`-Filter für Dependencies in Templates (LOW)
- **Dateien:** `templates/html/single-class.html.twig:99–123`
- **Fix:** CSS `white-space: pre-line` statt `join('<br>')|raw`

---

## Phase 3: Automatische Code-Fixes (Prio: HOCH, schneller Gewinn)

### 3.1 Rector ausführen
- 99 Dateien mit automatischen Typ-Korrekturen
- Rein mechanische Änderungen, kein Risiko
- **Aktion:** `vendor/bin/rector process src/`

### 3.2 PHP CS Fixer ausführen
- 157 Dateien mit Style-Korrekturen
- `declare_strict_types` in alle Dateien
- Import-Reihenfolge normalisieren
- **Aktion:** `vendor/bin/php-cs-fixer fix src/`

### 3.3 .gitignore ergänzen
Fehlende Einträge:
```
.php-cs-fixer.cache
.phpunit.result.cache
```

---

## Phase 4: Design-Refactoring — God Classes aufbrechen (Prio: HOCH)

### 4.1 Application.php aufteilen (Hotspot-Score: 1.664)
- **Aktuell:** 492 LOC, CC=52, 66 Abhängigkeiten, Dependency-Cycle, ~10 Verantwortlichkeiten
- **Ziel-Aufteilung:**
  - `AnalysisPipeline` — Orchestrierung von Visitors, Calculators, Predictors
  - `ConfigBuilder` — Config-Parsing, Framework-Erkennung, Defaults
  - `ReportOrchestrator` — Report-Erstellung und -Dispatch
  - `ServiceContainer` / `ServiceFactory` — DI für Calculators, Predictors, Visitors
- **Impact:** Löst auch Dependency-Cycle, senkt Hotspot-Score dramatisch

### 4.2 GraphDataProvider.php aufteilen (CC=78, MI=32)
- **Aktuell:** 498 LOC, schlechtester MI im gesamten Projekt
- **Ziel-Aufteilung:**
  - `GraphNodeBuilder` — Knoten-Erstellung
  - `GraphEdgeBuilder` — Kanten-Erstellung
  - `GraphGitAggregator` — Git-Daten-Aggregation

### 4.3 TestMappingCalculator aufteilen (CC=69, LCOM=3)
- **Aktuell:** God Object, 3+ Verantwortlichkeiten laut LCOM
- **Ziel:** Namespace-Matcher, Convention-Matcher, Coverage-Mapper als separate Klassen

### 4.4 ImpactAnalysisTool umstrukturieren (Tech Debt: 10.39)
- **Aktuell:** Nur 2 Methoden mit avgCC=26 — beide sind Monster-Methoden
- **Fix:** In kleine, testbare Methoden aufteilen; Graph-Traversal in `GraphService` auslagern

---

## Phase 5: Architektur & Patterns verbessern (Prio: MITTEL)

### 5.1 Dependency Injection einführen
- **Problem:** 12 Calculators + 14 Predictors + Visitors direkt mit `new` instanziiert
- **Lösung:** Service-Factory oder leichtgewichtigen Container einführen → Testbarkeit steigt

### 5.2 Visitor-Interface vervollständigen
- **Problem:** `init()`, `injectConfig()`, `afterSetPath()` werden per `method_exists()` geprüft
- **Lösung:** `InitializableVisitorInterface`, `ConfigAwareVisitorInterface` definieren

### 5.3 Layer-Verletzung Metrics↔Predictions auflösen
- **Problem:** `MetricsController` importiert `PredictionInterface` → zirkuläre Layer-Abhängigkeit
- **Lösung:** `ProblemInterface` in Metrics-Namespace verschieben oder generisches Interface

### 5.4 Calculator/Prediction-Registry statt Hardcoded-Listen
- **Problem:** Alle Calculators/Predictors in Application.php hardcoded (OCP-Verletzung)
- **Lösung:** Auto-Discovery oder Registry-Pattern → neue Calculators/Predictors ohne Application.php-Änderung

### 5.5 Config-Klasse typsicher machen
- **Problem:** String-Literal-Keys ohne IDE-Support oder Typ-Checks
- **Lösung:** Konstanten für Config-Keys oder typsicheres DTO

### 5.6 DataProvider-Caching
- **Problem:** DataProviderFactory gibt bei jedem Aufruf neue Instanzen zurück → mehrfache Collection-Iteration
- **Lösung:** Lazy-Singleton-Caching in der Factory

---

## Phase 6: Berechnungs-Korrekturen (Prio: MITTEL)

### 6.1 TooComplexPrediction — Double-Counting beheben
- **Datei:** `src/Predictions/TooComplexPrediction.php:88–125`
- **Problem:** `avgMethodCc > threshold` zählt zweimal als Problem

### 6.2 VariablesCalculator — Division-by-Zero-Guard korrigieren
- **Datei:** `src/Calculators/VariablesCalculator.php:67`
- **Problem:** Guard prüft nur `variablesUsed > 0`, Nenner enthält aber 3 Summanden

### 6.3 GodClassPrediction — suspectIndex-Kalibrierung
- **Datei:** `src/Predictions/GodClassPrediction.php:70–78`
- **Problem:** `suspectIndex` wird pro langer Methode inkrementiert → False Positives

### 6.4 TooComplexPrediction LCOM — Null-Durchschnitt abfangen
- **Datei:** `src/Predictions/TooComplexPrediction.php:183–185`
- **Problem:** Bei avgLcom=0 wird jede Klasse mit lcom>0 gewarnt

### 6.5 Spaceship-Operator CC=2 überprüfen
- **Datei:** `src/Analysis/CyclomaticComplexityVisitor.php:232–234`
- **Problem:** `<=>` als CC+2 ist in der Literatur nicht belegt — prüfen ob +1 korrekt wäre

### 6.6 PackageCohesionCalculator — Normalisierung
- **Datei:** `src/Calculators/PackageCohesionCalculator.php:100`
- **Problem:** Kohäsionswert kann >1 sein → keine Standardformel

### 6.7 ProjectCalculator — Null-Check ergänzen
- **Datei:** `src/Calculators/ProjectCalculator.php:80`
- **Fix:** `?->getValue() ?? 0` für `commentWeight`

### 6.8 HealthScoreCalculator — Kommentare aktualisieren
- **Problem:** Prozent-Angaben in Kommentaren weichen von tatsächlichen Gewichten ab

### 6.9 TestMappingCalculator — ShortName-Kollision
- **Datei:** `src/Calculators/TestMappingCalculator.php:165`
- **Problem:** Bei doppelten ShortNames wird nur die letzte Klasse gespeichert

---

## Phase 7a: Metrics-System typsicher machen (VOR PHPStan)

### Kernproblem
Das gesamte Metrics-System arbeitet mit `mixed`-Arrays und `MetricValue`-Wrappern die alles als `mixed` rein- und rausreichen. Das ist die Hauptursache für 500+ PHPStan-Fehler (argument.type, missingType.iterableValue, offsetAccess.nonOffsetAccessible).

### Maßnahmen
1. **DTOs pro Metrik-Gruppe** — `HalsteadMetrics`, `CouplingMetrics`, `ComplexityMetrics`, `CohesionMetrics`, etc. mit typisierten Properties
2. **Typisierte Collections** — statt `array<string, mixed>` dedizierte Collection-Klassen
3. **Enum-basierte Keys** — statt Magic Strings (`'cc'`, `'lloc'`, `'volume'`) Enums für Metrik-Zugriffe
4. **Calculator-Rückgaben** — typsichere DTOs statt assoziative Arrays
5. **MetricValue** — generisch machen (`MetricValue<int>`, `MetricValue<float>`) oder durch spezifische Typen ersetzen
6. **Config-Keys** — von String-Literals auf Enums/Konstanten umstellen

### Impact
- Löst den Großteil der PHPStan `mixed`-Fehler automatisch
- Verbessert IDE-Support (Autocomplete, Refactoring)
- Macht das System testbarer (typisierte Assertions statt `mixed`-Vergleiche)
- Architektur-Konsistenz: einheitliches Muster für alle Metrik-Gruppen

---

## Phase 7b: PHPStan-Level systematisch erhöhen (Prio: MITTEL)

### 7.1 Ziel: PHPStan Level 5 als Baseline
- Aktuell: 263 Fehler auf Level 5
- Hauptprobleme: fehlende `array<K,V>`-Typen, `mixed`-Probleme im Metrics-System
- **Aktion:** PHPDoc-Annotations für Array-Typen systematisch ergänzen

### 7.2 Schrittweise Erhöhung auf Level 8
| Level | Fehler | Hauptursache |
|-------|--------|-------------|
| 5 | 263 | `mixed`-Typen, fehlende Array-Generics |
| 6 | 628 | + `class.notFound` (Vendor-Stubs fehlen) |
| 7 | 675 | + fehlende Union-Types |
| 8 | 739 | + Property-Typen, Return-Typen |

### 7.3 phpstan-baseline.neon für bekannte Fehler
- Für den Übergang: Baseline generieren, neue Fehler sofort fixen
- `vendor/bin/phpstan analyse --generate-baseline`

### 7.4 PHPStan in CI einbinden
- GitHub Actions Workflow mit aktuellem Level als Gate

---

## Phase 8: Toter Code & Cleanups (Prio: NIEDRIG)

### 8.1 Tote Methode entfernen
- `DataProviderFactory::predictProgrammingParadigm()` — nie aufgerufen, nie fertiggestellt

### 8.2 Tote Properties entfernen
- `CalculatorTrait::$usedMetricTypes` — nie verwendet, Debug-Kommentar "Das ist ein Test"
- `DependencyVisitor::$currentClassMetrics` — wird nur zurückgesetzt, nie beschrieben/gelesen
- Ungenutzter `MetricsContainer`-Import in `CalculatorTrait`

### 8.3 Tippfehler korrigieren
- `DataProviderFactory::getPackagDataProvider()` → `getPackageDataProvider()` (fehlendes 'e')

### 8.4 PrettyPrinter cachen
- `LocVisitor.php:265,345,378` — `new PrettyPrinter\Standard()` bei jedem Aufruf → als Property cachen

### 8.5 ArgumentParser exit() entfernen
- `ArgumentParser.php:107` — `exit;` bei `--version` → Exception werfen für Testbarkeit

### 8.6 ob_start/echo Pattern ersetzen
- `ReportTrait::renderTemplate()` — `$twig->render()` direkt in `file_put_contents()` statt Output-Buffer

### 8.7 FQCN durch use-Statements ersetzen
- `Application.php:151,204,328,426` — `instanceof \PhpCodeArch\...` durch Imports ersetzen

---

## Phase 9: Test-Infrastruktur sichtbar machen (Prio: MITTEL)

### 9.1 Test-Coverage im Self-Scan aktivieren
- **Problem:** MCP Self-Analysis meldet "No test infrastructure detected" → 134/173 Klassen als "untested"
- **Ursache:** Tests liegen in `tests/`, aber das Tool analysiert nur `src/`
- **Fix:** `composer.json`-Parsing für Pest/PHPUnit sicherstellen, Test-Verzeichnisse korrekt erkennen
- **Impact:** Allein das könnte den Health Score um ~5–10 Punkte heben

### 9.2 Coverage-Datei generieren und einbinden
- `XDEBUG_MODE=coverage vendor/bin/pest --coverage-clover clover.xml`
- Dann: `php vendor/bin/phpcodearcheology --coverage-file clover.xml src/`

---

## .gitignore-Ergänzungen

```gitignore
# Statische Analyse & Code-Style
.php-cs-fixer.cache
.phpunit.result.cache
```

---

## Erwartete Impact-Prognose

| Maßnahme | Health Score Impact |
|----------|-------------------|
| Bug-Fixes Phase 1 (Halstead, Coupling) | +2–3 Punkte (weniger False Errors) |
| Tests sichtbar machen (Phase 9) | +5–10 Punkte (134 Klassen nicht mehr "untested") |
| Application.php aufteilen (Phase 4.1) | +2–3 Punkte (Hotspot eliminiert) |
| GraphDataProvider aufteilen (Phase 4.2) | +1–2 Punkte |
| Berechnungs-Korrekturen (Phase 6) | +1–2 Punkte (weniger False Positives) |
| **Gesamt geschätzt** | **77.9 → 88–95 (Grade B bis A)** |

---

## Zusammenfassung der Findings

| Quelle | Kritisch | Hoch/Schwer | Mittel | Niedrig/Info |
|--------|----------|-------------|--------|-------------|
| Security Review | 0 | 0 | 2 | 4 |
| Design Review | 4 | 7 | 10 | 7 |
| Calculation Review | 2 | 3 | 6 | 4 |
| Self-Analysis (MCP) | — | 10 Hotspots | 134 refactoring-nötig | — |
| Static Analysis (PHPStan) | — | — | 1.000+ Fehler (max) | — |
| Static Analysis (CS Fixer) | — | — | 157 Dateien | — |
| **Gesamt** | **6** | **10+** | **30+** | **15+** |
