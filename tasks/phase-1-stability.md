# Phase 1 — Stabilität & Memory-Management

> **Ziel:** Das Tool muss zuverlässig auf großen Projekten (10k+ Dateien) laufen, ohne abzustürzen.

> **Hinweis:** Vor jeder Umsetzung zuerst in den Plan-Mode wechseln! Betroffenen Code lesen, Abhängigkeiten verstehen, Plan mit dem User abstimmen — erst dann implementieren.

## Prio 1: Memory-Probleme beheben

- [x] **AST-Cleanup im Analyzer-Loop** (`Analyzer.php`)
  - `$ast` und `$phpCode` nach Traversal per `unset()` freigeben
  - `gc_collect_cycles()` alle 100 Dateien
  - `file_get_contents` Fehler abfangen

- [x] **Visitor-State zwischen Dateien zurücksetzen**
  - `IdentifyVisitor`: beforeTraverse() resetzt jetzt alle Arrays (interfaces, traits, enums, methods, outputCount)
  - `HalsteadMetricsVisitor`: beforeTraverse() implementiert (war TODO), resetzt alle Operator/Operand-Arrays
  - `LocVisitor`: beforeTraverse() resetzt functionNodes, HtmlLoc-Arrays, Stacks, Counters
  - `CyclomaticComplexityVisitor`: beforeTraverse() resetzt classCc, functionCc, Stacks
  - `DependencyVisitor`: beforeTraverse() implementiert, resetzt alle 10 State-Properties
  - `GlobalsVisitor`: beforeTraverse() resetzt alle 9 fehlenden Arrays/Stacks

- [x] **Report-Generierung: Daten nicht komplett im Speicher halten** (`HtmlReport.php`)
  - Files/Classes/Functions: Liste und Detail-Seiten in einer Methode zusammengefasst (kein doppelter DataProvider-Aufruf)
  - Entity-Liste aus `$data` extrahiert und Original freigegeben, statt pro Iteration zu kopieren
  - Explizites `unset($data, ...)` am Ende jeder Methode

- [x] **Konfigurierbares Memory-Limit** statt hardcoded 512M (`Application.php`)
  - Config-Option `memoryLimit`, Default `1G`
  - `ini_set` nach Config-Laden verschoben

## Prio 2: Fehlerbehandlung

- [x] **JSON-Operationen absichern** (`Application.php`)
  - `setHistoryDeltas()`: file_get_contents + json_decode Rückgabewert geprüft
  - `getHistoryDataFromFile()`: Null-Checks + fehlende Properties abgefangen
  - Korrupte `history.json` führt jetzt zu graceful Return statt Crash

- [x] **Encoding-Erkennung fixen** (`Analyzer.php`)
  - Typo `'UFT-8'` → `'UTF-8'` behoben
  - `mb_detect_encoding()` mit expliziter Encoding-Liste und strict-Mode
  - False-Return wird abgefangen

- [x] **Datei-Operationen absichern** (`FileList.php`)
  - `realpath()` false-Return wird geprüft, Datei übersprungen
  - `RecursiveDirectoryIterator` in try/catch gewrapped

- [x] **Parse-Fehler robuster behandeln**
  - Parse-Fehler werden in ErrorCollection erfasst und im Report pro Datei angezeigt (existierte)
  - File-Read-Fehler werden abgefangen (neu in dieser Session)
  - CLI-Zusammenfassung am Ende: "Analysed X of Y files successfully" + Warning bei Fehlern

## Prio 3: Grundlegende Korrektheit

- [x] **Typo fixen: `handeClass` → `handleClass`** (`CouplingCalculator.php`)
- [x] **Loose Comparison fixen: `!=` → `!==`** (`Node.php:46,49`)
- [x] **`HalsteadMetricsVisitor::beforeTraverse()` TODO aufgelöst** — vollständig implementiert
- [x] **Visibility fixen: `public` → `private`** (`CyclomaticComplexityVisitor.php:39,44`)
- [x] **Exit-Codes korrigiert: `exit;` → `exit(1);`** (`Application.php`)
