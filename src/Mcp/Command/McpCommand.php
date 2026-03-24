<?php

declare(strict_types=1);

namespace PhpCodeArch\Mcp\Command;

use PhpCodeArch\Application\Application;
use PhpCodeArch\Application\CliFormatter;
use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\Config;
use PhpCodeArch\Application\ConfigException;
use PhpCodeArch\Mcp\Output\StderrOutput;
use PhpCodeArch\Mcp\Tools\ClassListTool;
use PhpCodeArch\Mcp\Tools\DependenciesTool;
use PhpCodeArch\Mcp\Tools\GraphTool;
use PhpCodeArch\Mcp\Tools\HealthScoreTool;
use PhpCodeArch\Mcp\Tools\HotspotsTool;
use PhpCodeArch\Mcp\Tools\MetricsTool;
use PhpCodeArch\Mcp\Tools\ProblemsTool;
use PhpCodeArch\Mcp\Tools\RefactoringTool;
use PhpCodeArch\Mcp\Tools\SearchCodeTool;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;
use PhpMcp\Server\Server;
use PhpMcp\Server\Transports\StdioServerTransport;

class McpCommand
{
    public function __construct(
        private readonly Application $application
    ) {
    }

    public function execute(Config $config, CliOutput $output, CliFormatter $formatter): int
    {
        // Ensure files are configured (check before running analysis)
        if (!$config->has('files') || empty($config->get('files'))) {
            $config->set('files', [getcwd() . '/src']);
        }

        try {
            $config->validate();
        } catch (ConfigException $e) {
            fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
            return 1;
        }

        $memoryLimit = $config->get('memoryLimit') ?? '1G';
        ini_set('memory_limit', $memoryLimit);

        // MCP uses STDOUT for JSON-RPC — all application output must go to STDERR
        $stderrOutput = new StderrOutput();

        [$metricsController, $problems] = $this->application->runAnalysis($config, $stderrOutput);

        $dataProviderFactory = new DataProviderFactory($metricsController);

        $healthTool = new HealthScoreTool($dataProviderFactory);
        $problemsTool = new ProblemsTool($dataProviderFactory);
        $hotspotsTool = new HotspotsTool($dataProviderFactory);
        $refactoringTool = new RefactoringTool($dataProviderFactory);
        $classListTool = new ClassListTool($dataProviderFactory);
        $metricsTool = new MetricsTool($dataProviderFactory, $metricsController);
        $dependenciesTool = new DependenciesTool($dataProviderFactory);
        $graphTool = new GraphTool($dataProviderFactory);
        $searchCodeTool = new SearchCodeTool($dataProviderFactory, $metricsController);

        $server = Server::make()
            ->withServerInfo('PhpCodeArcheology', Application::VERSION)
            ->withTool(
                handler: $healthTool->getHealthScore(...),
                name: 'get_health_score',
                description: 'Returns the overall code health score, grade, technical debt score, problem counts, and project statistics.'
            )
            ->withTool(
                handler: $problemsTool->getProblems(...),
                name: 'get_problems',
                description: 'Returns a filtered list of code problems. Filter by severity (error/warning/info), type keyword, and limit.'
            )
            ->withTool(
                handler: $hotspotsTool->getHotspots(...),
                name: 'get_hotspots',
                description: 'Returns the top N code hotspots ranked by churn × cyclomatic complexity. Files that change often and are complex.'
            )
            ->withTool(
                handler: $refactoringTool->getRefactoringPriorities(...),
                name: 'get_refactoring_priorities',
                description: 'Returns classes ranked by refactoring priority score. Includes recommendation and driving factors.'
            )
            ->withTool(
                handler: $classListTool->getClassList(...),
                name: 'get_class_list',
                description: 'Returns a sorted and filtered list of classes with key metrics (CC, LLOC, MI, refactoring priority, coupling).'
            )
            ->withTool(
                handler: $metricsTool->getMetrics(...),
                name: 'get_metrics',
                description: 'Returns all available metrics for a specific class, file, or function. Provide the entity name (e.g. \'UserService\').'
            )
            ->withTool(
                handler: $dependenciesTool->getDependencies(...),
                name: 'get_dependencies',
                description: 'Returns dependency information for a specific class — outgoing dependencies, incoming usage, and coupling metrics.'
            )
            ->withTool(
                handler: $graphTool->getGraph(...),
                name: 'get_graph',
                description: 'Returns the knowledge graph of the project as JSON with nodes, edges, clusters, and dependency cycles.'
            )
            ->withTool(
                handler: $searchCodeTool->searchCode(...),
                name: 'search_code',
                description: 'Search for classes, files, or functions by name. Returns matching entities with key metrics.'
            )
            ->build();

        $server->listen(new StdioServerTransport());

        return 0;
    }
}
