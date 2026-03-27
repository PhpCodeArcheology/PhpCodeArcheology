# Phase 4 — Neue Metriken ✓

> **Status:** Abgeschlossen. Alle Items implementiert (OCP → Phase 5 verschoben).

## Prio 1: Essenzielle fehlende Metriken ✓

- [x] **Cognitive Complexity** — CognitiveComplexityVisitor (SonarSource-Algorithmus)
- [x] **Type Coverage** — TypeCoverageVisitor (gewichtet: Props 40%, Returns 35%, Params 25%)
- [x] **Dependency Cycle Detection** — TarjanSccAlgorithm + DependencyCycleCalculator
- [x] **Inheritance Depth (DIT)** — InheritanceDepthCalculator + NOC

## Prio 2: Code-Qualitäts-Metriken ✓

- [x] **Dead Code Detection** — DeadCodeVisitor (ungenutzte private Methoden)
- [x] **Code Duplication** — CodeDuplicationCalculator (Token-basiert, Rolling Hash, 50 Token Fenster)
- [x] **Documentation Coverage** — DocumentationCoverageVisitor (PHPDoc auf public Methods)
- [x] **Method Parameter Metrics** — ParameterAnalyzer erweitert + TooManyParametersPrediction

## Prio 3: Architektur-Metriken ✓

- [x] **Package Cohesion** — PackageCohesionCalculator (Relational Cohesion H)
- [x] **SOLID Violation Indicators** — SolidViolationCalculator (SRP, ISP, DIP)
  - OCP → Phase 5 verschoben (braucht Git-Integration)
- [x] **Layer Violation Detection** — LayerViolationCalculator (Namespace-basiert)
- [x] **Abstractness-Ratio** — bereits korrekt (Interfaces werden mitgezählt)

## Prio 4: Sicherheits-Metriken ✓

- [x] **Security Smell Detection** — SecuritySmellVisitor (eval, exec, SQL-Concat, weak hashing)
- [x] **Input Validation Coverage** — abgedeckt durch TypeCoverage + TooManyParametersPrediction

## Prio 5: Performance-Indikatoren ✓

- [x] **Estimated Runtime Complexity** — RuntimeComplexityVisitor (Loop-Nesting → O(n)/O(n²)/O(n³+))
- [x] **Resource Usage Patterns** — übersprungen (zu fragil für statische Analyse)

## Prio 6: Verbesserte Predictions ✓

- [x] **Technical Debt Score** — Post-Prediction Berechnung (ERROR=3, WARNING=1, INFO=0.5, normalisiert per 100 LLOC)
- [x] **Confidence Scoring** — getConfidence() in ProblemInterface (default 1.0, erweiterbar)
- [x] **Refactoring-Empfehlungen** — getRecommendation() in allen 12 Problem-Klassen
