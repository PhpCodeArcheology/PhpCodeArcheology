# Phase 8 — Knowledge Graph, MCP Server & Report-Isolation

> **Ziel:** PhpCodeArcheology wird zum AI-native Analyse-Tool. Codebase-Wissen wird als
> Knowledge Graph exportiert und per MCP Server live abfragbar. Zusätzlich wird das
> Report-Overwrite-Problem gelöst.

> **Hinweis:** Vor jeder Umsetzung zuerst in den Plan-Mode wechseln! Betroffenen Code lesen, Abhängigkeiten verstehen, Plan mit dem User abstimmen — erst dann implementieren.

---

## Prio 1: Report-Verzeichnis-Isolation (Bugfix)

**Problem:** HTML- und Markdown-Reports rufen `clearReportDir()` auf und löschen dabei alle
vorherigen Reports im selben Verzeichnis. JSON/SARIF/AI-Summary schreiben einfach ins gleiche
Verzeichnis und vermischen sich mit HTML-Assets.

**Lösung:** Report-Typ-spezifische Unterverzeichnisse.

- [ ] **Report-Typ-Unterverzeichnisse einführen**
  - `reportDir/html/` für HTML-Reports
  - `reportDir/markdown/` für Markdown-Reports
  - `reportDir/json/` für `report.json`
  - `reportDir/sarif/` für `report.sarif.json`
  - `reportDir/ai-summary/` für `ai-summary.md`
  - `reportDir/graph/` für den neuen Knowledge Graph (Prio 2)
  - `clearReportDir()` löscht nur das jeweilige Unterverzeichnis
  - `history.jsonl` bleibt im Root von `reportDir/` (shared über alle Report-Typen)

- [ ] **Mehrere Report-Typen gleichzeitig generieren**
  - `--report-type=html,json` erzeugt beide Reports in einem Run
  - Komma-separierte Liste oder mehrfaches `--report-type` Flag
  - Spart doppelte Analyse-Zeit

- [ ] **Abwärtskompatibilität**
  - Wenn `reportDir/index.html` existiert (altes Format): Migration-Hinweis ausgeben
  - Bestehende `history.jsonl` im Root bleibt unberührt

**Betroffene Dateien:**
- `src/Report/ReportTrait.php` — `clearReportDir()` anpassen
- `src/Report/HtmlReport.php` — Unterverzeichnis nutzen
- `src/Report/MarkdownReport.php` — Unterverzeichnis nutzen
- `src/Report/JsonReport.php` — Unterverzeichnis nutzen
- `src/Report/SarifReport.php` — Unterverzeichnis nutzen
- `src/Report/AiSummaryReport.php` — Unterverzeichnis nutzen
- `src/Report/ReportFactory.php` — Mehrfach-Report-Logik
- `src/Application/Application.php` — Mehrfach-Report orchestrieren
- `src/Application/ArgumentParser.php` — Komma-separierte Report-Types parsen

---

## Prio 2: Knowledge Graph Export

**Ziel:** Neuer Report-Typ `--report-type=graph` der die Codebase-Struktur als Graph
exportiert (Nodes + Edges). Maschinenlesbar für AI-Tools und als interaktive
D3-Visualisierung im HTML-Report.

### 2a: Graph-Datenmodell & JSON-Export

- [ ] **Graph-Datenmodell definieren**
  - **Nodes** mit Typen:
    - `file` — PHP-Dateien (Metriken: loc, lloc, cc, mi)
    - `class` — Klassen, Interfaces, Traits, Enums (Metriken: cc, lcom, mi, instability)
    - `function` — Funktionen und Methoden (Metriken: cc, cogC, params)
    - `package` — Namespaces/Packages (Metriken: cohesion, abstractness, instability)
    - `author` — Git-Autoren (Metriken: commitCount, filesChanged)
  - **Edges** mit Typen:
    - `contains` — File → Class, Class → Method, Package → Class
    - `extends` — Class → Parent Class
    - `implements` — Class → Interface
    - `uses_trait` — Class → Trait
    - `depends_on` — Class → Class (Dependency)
    - `belongs_to` — Class/File → Package
    - `calls` — Function → Function (wenn erkennbar)
    - `authored_by` — File → Author (Git)
    - `cycle_member` — Class ↔ Class (Dependency Cycle, bidirektional)
  - **Node-Attribute:** id, type, name, path, metrics (Key-Value), problems (Array)
  - **Edge-Attribute:** source, target, type, weight (optional, z.B. Coupling-Stärke)

- [ ] **GraphDataProvider implementieren**
  - Neuer DataProvider in `src/Report/DataProvider/GraphDataProvider.php`
  - Liest aus `MetricsController`: alle Collections, Dependencies, Git-Daten
  - Erzeugt `nodes[]` + `edges[]` Arrays
  - Dependency-Daten kommen aus `DependencyVisitor` (uses/usedBy)
  - Cycle-Daten kommen aus `DependencyCycleCalculator`
  - Vererbungsdaten kommen aus `IdentifyVisitor` (extends, implements, traits)
  - Git-Daten kommen aus `GitAnalyzer`

- [ ] **GraphReport implementieren**
  - Neuer Report-Typ in `src/Report/GraphReport.php`
  - Schreibt `graph.json` ins Verzeichnis `reportDir/graph/`
  - JSON-Struktur:
    ```json
    {
      "version": "1.0",
      "generatedAt": "...",
      "nodes": [
        { "id": "class:App\\UserService", "type": "class", "name": "UserService",
          "path": "src/UserService.php", "metrics": { "cc": 12, "lcom": 3 },
          "problems": [{ "level": "warning", "message": "..." }] }
      ],
      "edges": [
        { "source": "class:App\\UserService", "target": "class:App\\UserRepository",
          "type": "depends_on", "weight": 1 }
      ],
      "clusters": [
        { "id": "package:App\\Services", "name": "App\\Services", "nodeIds": ["..."] }
      ],
      "cycles": [
        { "nodes": ["class:A", "class:B", "class:C"], "length": 3 }
      ]
    }
    ```
  - In `ReportFactory` registrieren

- [ ] **DependencyVisitor erweitern: Vererbungs-Edges**
  - Aktuell speichert der Visitor `usesCount`/`usedFromOutsideCount` als Zahlen
  - Für den Graph brauchen wir die konkreten Ziel-Klassen als Liste
  - Prüfen was `DependencyVisitor` bereits speichert vs. was fehlt
  - `extends`, `implements`, `uses` (Traits) als separate Edge-Listen

### 2b: D3-Visualisierung im HTML-Report

- [ ] **Neue HTML-Seite: `knowledge-graph.html`**
  - Neue Twig-Template-Datei `templates/html/knowledge-graph.html.twig`
  - D3.js Force-Directed Graph als Hauptvisualisierung
  - D3.js als minifizierte Datei in `templates/html/assets/js/d3.min.js`
  - Graph-Daten inline als `<script>const graphData = {{ graphJson|raw }};</script>`

- [ ] **D3 Force-Directed Graph Features**
  - **Node-Styling:** Farbe nach Typ (class=cyan, interface=purple, trait=orange, file=gray, package=green)
  - **Node-Größe:** Skaliert nach Metrik (z.B. LOC oder CC)
  - **Edge-Styling:** Farbe nach Typ (depends_on=weiß, extends=blau, implements=lila, cycle=rot)
  - **Hover-Tooltips:** Node-Name, Typ, Key-Metriken, Problem-Count
  - **Click:** Öffnet die Detail-Seite der Klasse/Datei
  - **Zoom & Pan:** D3-Zoom-Behavior (Mausrad + Drag)
  - **Filter-Controls:**
    - Checkbox: Node-Typen ein/ausblenden (Classes, Interfaces, Files, Packages)
    - Checkbox: Edge-Typen ein/ausblenden (Dependencies, Inheritance, Cycles)
    - Slider: Minimum-CC-Filter (nur Nodes mit CC >= X anzeigen)
    - Toggle: "Nur Probleme" — Nodes ohne Probleme ausblenden
  - **Cycle-Highlighting:** Dependency-Cycles als rot hervorgehobene Subgraphs
  - **Package-Clustering:** Optionaler Modus der Nodes nach Package gruppiert (D3 Convex Hull)
  - **Light/Dark Theme Support:** Wie bei allen anderen Seiten

- [ ] **Dashboard-Widget: Mini-Graph**
  - Kleines Dependency-Graph-Widget auf dem Dashboard (`index.html.twig`)
  - Zeigt nur die Top-10 most-connected Klassen
  - Link zur vollständigen Graph-Seite

- [ ] **Navigation erweitern**
  - Link "Knowledge Graph" in die Hauptnavigation aufnehmen
  - Zwischen "Git" und "Glossary" einordnen

**Betroffene Dateien:**
- `src/Report/DataProvider/GraphDataProvider.php` — NEU
- `src/Report/GraphReport.php` — NEU
- `src/Report/ReportFactory.php` — Graph-Typ registrieren
- `src/Report/HtmlReport.php` — Graph-Seite generieren, Dashboard-Widget
- `templates/html/knowledge-graph.html.twig` — NEU
- `templates/html/index.html.twig` — Dashboard-Widget
- `templates/html/parts/navi.html.twig` — Navigation
- `templates/html/assets/js/d3.min.js` — NEU (D3.js Library)
- `data/metrics/class.php` — ggf. neue Metriken für Edge-Daten

---

## Prio 3: MCP Server

**Ziel:** PhpCodeArcheology als MCP Server, der nach einer Analyse die Metriken live
an AI-Tools (Claude Code, Cursor, Windsurf etc.) ausliefert. Kein anderes PHP-Analyse-Tool
bietet das — maximaler Differenziator.

### 3a: MCP Server Grundgerüst

- [ ] **MCP-Protokoll-Implementierung in PHP**
  - MCP nutzt JSON-RPC über stdio (stdin/stdout)
  - Eigene leichtgewichtige Implementierung in `src/Mcp/` — kein Framework nötig
  - Klassen: `McpServer`, `McpRouter`, `McpToolDefinition`
  - Liest JSON-RPC Requests von stdin, schreibt Responses auf stdout
  - Unterstützt: `initialize`, `tools/list`, `tools/call`
  - Error-Handling nach MCP-Spezifikation

- [ ] **Neuer Subcommand: `phpcodearcheology mcp`**
  - Startet den MCP Server als stdio-Prozess
  - Führt beim Start automatisch eine Analyse durch
  - Hält `MetricsController` im Speicher
  - Optional: `--config=path` für Konfiguration
  - Optional: `--cache` für dateibasiertes Caching zwischen Restarts

- [ ] **MCP Server in Application.php registrieren**
  - Neuer Command in `src/Application/Command/McpCommand.php`
  - Ruft `runAnalysis()` auf, übergibt `MetricsController` an `McpServer`
  - stdio-Loop: Request lesen → Tool dispatchen → Response schreiben

### 3b: MCP Tools (Abfrage-Endpunkte)

- [ ] **Tool: `get_health_score`**
  - Keine Parameter
  - Gibt zurück: Health Score, Grade, Subscores, Delta zum letzten Run
  - Nutzt: `ProjectDataProvider`

- [ ] **Tool: `get_problems`**
  - Parameter: `file?`, `class?`, `level?` (error/warning/info), `limit?`
  - Gibt zurück: Gefilterte Problem-Liste mit Entity, Level, Message, Recommendation
  - Nutzt: `ProblemDataProvider`

- [ ] **Tool: `get_metrics`**
  - Parameter: `entity` (file path, class FQN, oder function name), `metrics?` (Liste)
  - Gibt zurück: Alle oder gefilterte Metriken der Entity
  - Nutzt: `MetricsController::getMetricCollectionByIdentifierString()`

- [ ] **Tool: `get_hotspots`**
  - Parameter: `limit?` (default: 10)
  - Gibt zurück: Top-N Hotspots (Churn × Complexity) mit Git-Daten
  - Nutzt: `GitDataProvider`

- [ ] **Tool: `get_refactoring_priorities`**
  - Parameter: `limit?` (default: 10)
  - Gibt zurück: Priorisierte Klassen-Liste mit Score, Drivers, Recommendation
  - Nutzt: `RefactoringPriorityDataProvider`

- [ ] **Tool: `get_dependencies`**
  - Parameter: `class` (FQN)
  - Gibt zurück: Uses (ausgehend), UsedBy (eingehend), Cycles, Inheritance
  - Nutzt: `MetricsController` + Class-Metriken

- [ ] **Tool: `get_class_list`**
  - Parameter: `sort_by?` (cc, lcom, mi, refactoringPriority), `filter?` (min_cc, has_problems)
  - Gibt zurück: Sortierte/gefilterte Klassen-Liste mit Key-Metriken
  - Nutzt: `ClassDataProvider`

- [ ] **Tool: `get_graph`**
  - Parameter: `center?` (class FQN), `depth?` (default: 2), `types?` (node/edge types)
  - Gibt zurück: Subgraph um eine Klasse herum (Ego-Graph)
  - Nutzt: `GraphDataProvider`

- [ ] **Tool: `search_code`**
  - Parameter: `query` (Klassenname, Metrik-Filter, Problem-Typ)
  - Gibt zurück: Matching Entities mit Key-Metriken
  - Beispiel: `{"query": "cc > 20"}` → alle Klassen mit CC > 20

### 3c: MCP-Konfiguration & Integration

- [ ] **MCP Server Config in php-codearch-config.yaml**
  ```yaml
  mcp:
    enable: true
    autoRefresh: true   # Bei Dateiänderungen automatisch neu analysieren
    cacheTtl: 300       # Cache-TTL in Sekunden
  ```

- [ ] **Claude Code Integration dokumentieren**
  - Anleitung für `.claude/settings.json`:
    ```json
    {
      "mcpServers": {
        "phpcodearcheology": {
          "command": "vendor/bin/phpcodearcheology",
          "args": ["mcp"]
        }
      }
    }
    ```
  - Anleitung für globale Installation
  - README-Sektion "AI Integration"

- [ ] **Auto-Refresh bei Dateiänderungen**
  - Filemtime-basierter Check bei jedem Tool-Call
  - Wenn sich Dateien geändert haben: inkrementelle Re-Analyse
  - Voraussetzung: Inkrementelle Analyse (kann initial auch Full-Refresh sein)

**Betroffene Dateien:**
- `src/Mcp/McpServer.php` — NEU: JSON-RPC stdio Server
- `src/Mcp/McpRouter.php` — NEU: Tool-Dispatch
- `src/Mcp/McpToolDefinition.php` — NEU: Tool-Schema-Definition
- `src/Mcp/Tools/*.php` — NEU: Je ein Tool-Handler pro Endpunkt
- `src/Application/Command/McpCommand.php` — NEU: Subcommand
- `src/Application/Application.php` — MCP-Command registrieren

---

## Prio 4: Inkrementelle Analyse

**Ziel:** Nur geänderte Dateien neu analysieren. Voraussetzung für schnellen MCP-Refresh
und späteren `--watch` Mode.

- [ ] **File-Change-Detection**
  - `filemtime()` pro Datei speichern nach Analyse
  - Bei Re-Analyse: Nur Dateien mit neuerer mtime parsen
  - Cache-Datei: `.phpcodearch-cache.json` im Projekt-Root
  - Cache-Struktur: `{ "files": { "path": { "mtime": 123, "hash": "abc" } } }`

- [ ] **Inkrementeller MetricsController**
  - Bestehende Metriken aus Cache laden
  - Nur geänderte Dateien neu analysieren und in den Controller mergen
  - Calculators die auf Projekt-Ebene aggregieren müssen komplett neu laufen
  - Predictions müssen für geänderte + abhängige Dateien neu laufen

- [ ] **Cache-Invalidierung bei Dependency-Änderungen**
  - Wenn Klasse A sich ändert und Klasse B von A abhängt: auch B neu analysieren
  - Dependency-Graph aus dem Cache nutzen um Kaskaden zu erkennen
  - Konservativ: Im Zweifel mehr neu analysieren als zu wenig

- [ ] **CLI-Flag: `--no-cache` / `--fresh`**
  - Erzwingt Full-Analyse ohne Cache
  - Nützlich bei Problemen oder nach Branch-Wechsel

**Betroffene Dateien:**
- `src/Application/CacheManager.php` — NEU
- `src/Application/Application.php` — Cache-Integration
- `src/Application/Analyzer.php` — Selektive Datei-Analyse

---

## Zusammenfassung & Release-Planung

| Prio | Feature | Version | Abhängigkeiten |
|------|---------|---------|----------------|
| 1 | Report-Verzeichnis-Isolation | v1.6.0 | Keine |
| 2a | Knowledge Graph JSON-Export | v1.7.0 | Prio 1 (reportDir) |
| 2b | D3-Visualisierung im HTML-Report | v1.8.0 | Prio 2a (Graph-Daten) |
| 3a | MCP Server Grundgerüst | v2.0.0 | Prio 2a (Graph für get_graph Tool) |
| 3b | MCP Tools | v2.0.0 | Prio 3a |
| 3c | MCP Config & Doku | v2.0.0 | Prio 3b |
| 4 | Inkrementelle Analyse | v2.1.0 | Prio 3 (MCP braucht schnellen Refresh) |

**v2.0.0** wäre ein Major Release wegen dem neuen MCP-Paradigma — PhpCodeArcheology wird
vom reinen Report-Generator zum **AI-native Code Intelligence Server**.
