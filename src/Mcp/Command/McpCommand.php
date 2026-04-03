<?php

declare(strict_types=1);

namespace PhpCodeArch\Mcp\Command;

use Mcp\Server\McpServer;
use PhpCodeArch\Application\Application;
use PhpCodeArch\Application\CliFormatter;
use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\Config;
use PhpCodeArch\Application\ConfigException;
use PhpCodeArch\Mcp\Output\StderrOutput;
use PhpCodeArch\Mcp\Tools\ClassListTool;
use PhpCodeArch\Mcp\Tools\DependenciesTool;
use PhpCodeArch\Mcp\Tools\GetTestCoverageTool;
use PhpCodeArch\Mcp\Tools\GraphTool;
use PhpCodeArch\Mcp\Tools\HealthScoreTool;
use PhpCodeArch\Mcp\Tools\HotspotsTool;
use PhpCodeArch\Mcp\Tools\ImpactAnalysisTool;
use PhpCodeArch\Mcp\Tools\MetricsTool;
use PhpCodeArch\Mcp\Tools\ProblemsTool;
use PhpCodeArch\Mcp\Tools\RefactoringTool;
use PhpCodeArch\Mcp\Tools\SearchCodeTool;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;

class McpCommand
{
    public function __construct(
        private readonly Application $application,
    ) {
    }

    public function execute(Config $config, CliOutput $output, CliFormatter $formatter): int
    {
        // Ensure files are configured (check before running analysis)
        if (!$config->has('files') || empty($config->get('files'))) {
            $config->set('files', [getcwd().'/src']);
        }

        try {
            $config->validate();
        } catch (ConfigException $e) {
            fwrite(STDERR, 'Error: '.$e->getMessage().PHP_EOL);

            return 1;
        }

        $config->applyMemoryLimit();

        // MCP uses STDOUT for JSON-RPC — all application output must go to STDERR
        $stderrOutput = new StderrOutput();

        [$metricsController, $problems] = $this->application->runAnalysis($config, $stderrOutput);

        $dataProviderFactory = new DataProviderFactory($metricsController);

        $healthTool = new HealthScoreTool($dataProviderFactory);
        $problemsTool = new ProblemsTool($dataProviderFactory);
        $hotspotsTool = new HotspotsTool($dataProviderFactory);
        $refactoringTool = new RefactoringTool($dataProviderFactory);
        $classListTool = new ClassListTool($dataProviderFactory);
        $metricsTool = new MetricsTool($metricsController);
        $dependenciesTool = new DependenciesTool($dataProviderFactory);
        $graphTool = new GraphTool($dataProviderFactory);
        $searchCodeTool = new SearchCodeTool($metricsController);
        $impactAnalysisTool = new ImpactAnalysisTool($dataProviderFactory);
        $testCoverageTool = new GetTestCoverageTool($dataProviderFactory);

        $server = new McpServer('PhpCodeArcheology');

        $server
            ->tool(
                'get_health_score',
                'Returns the overall code health score, grade, technical debt score, problem counts, and project statistics.',
                $healthTool->getHealthScore(...)
            )
            ->tool(
                'get_problems',
                'Returns a filtered list of code problems. Filter by severity (error/warning/info), type keyword, and limit.',
                $problemsTool->getProblems(...)
            )
            ->tool(
                'get_hotspots',
                'Returns the top N code hotspots ranked by churn × cyclomatic complexity. Files that change often and are complex.',
                $hotspotsTool->getHotspots(...)
            )
            ->tool(
                'get_refactoring_priorities',
                'Returns classes ranked by refactoring priority score. Includes recommendation and driving factors.',
                $refactoringTool->getRefactoringPriorities(...)
            )
            ->tool(
                'get_class_list',
                'Returns a sorted and filtered list of classes with key metrics (CC, LLOC, MI, refactoring priority, coupling).',
                $classListTool->getClassList(...)
            )
            ->tool(
                'get_metrics',
                'Returns all available metrics for a specific class, file, or function. Provide the entity name (e.g. \'UserService\').',
                $metricsTool->getMetrics(...)
            )
            ->tool(
                'get_dependencies',
                'Returns dependency information for a specific class — outgoing dependencies, incoming usage, and coupling metrics.',
                $dependenciesTool->getDependencies(...)
            )
            ->tool(
                'get_graph',
                'Returns the knowledge graph of the project as JSON with nodes, edges, clusters, and dependency cycles.',
                $graphTool->getGraph(...)
            )
            ->tool(
                'search_code',
                'Search for classes, files, or functions by name. Returns matching entities with key metrics.',
                $searchCodeTool->searchCode(...)
            )
            ->tool(
                'get_impact_analysis',
                'Analyzes the impact of changing a method. Shows direct and transitive callers across classes, affected class count, and call chains. Provide class_name (required) and optionally method_name and depth (default 2).',
                $impactAnalysisTool->getImpactAnalysis(...)
            )
            ->tool(
                'get_test_coverage',
                'Get test coverage analysis: test ratio, tested classes, and untested complex code gaps',
                $testCoverageTool->getTestCoverage(...)
            );

        $server->run();

        return 0;
    }
}
