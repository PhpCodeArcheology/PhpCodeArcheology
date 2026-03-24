<?php

declare(strict_types=1);

namespace PhpCodeArch\Mcp\Tools;

use PhpCodeArch\Report\DataProvider\DataProviderFactory;

class HealthScoreTool
{
    public function __construct(
        private readonly DataProviderFactory $factory
    ) {
    }

    public function getHealthScore(): string
    {
        try {
            $data = $this->factory->getProjectDataProvider()->getTemplateData();
            $elements = $data['elements'] ?? [];

            $get = fn(string $key) => isset($elements[$key]) ? $elements[$key]->getValue() : null;

            $score = $get('healthScore');
            $grade = $get('healthScoreGrade');
            $debt = $get('overallTechnicalDebtScore');
            $errors = $get('overallErrorCount') ?? 0;
            $warnings = $get('overallWarningCount') ?? 0;
            $info = $get('overallInformationCount') ?? 0;
            $files = $get('overallFiles') ?? 0;
            $classes = $get('overallClasses') ?? 0;
            $functions = $get('overallFunctions') ?? 0;
            $methods = $get('overallMethods') ?? 0;
            $lloc = $get('overallLloc') ?? 0;
            $avgCC = $get('overallAvgCC') ?? 0;
            $avgMI = $get('overallAvgMI') ?? 0;

            $lines = [
                "# Code Health Report",
                "",
                "## Overall Health Score",
                "Score: {$score}/100 (Grade: {$grade})",
                "Technical Debt: {$debt} debt-points per 100 lines",
                "",
                "## Problem Summary",
                "Errors:   {$errors}",
                "Warnings: {$warnings}",
                "Info:     {$info}",
                "",
                "## Project Statistics",
                "Files:     {$files}",
                "Classes:   {$classes}",
                "Functions: {$functions}",
                "Methods:   {$methods}",
                "LLOC:      {$lloc}",
                "",
                "## Code Quality Metrics",
                "Avg Cyclomatic Complexity: " . round((float) $avgCC, 2),
                "Avg Maintainability Index: " . round((float) $avgMI, 2),
            ];

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return "Error retrieving health score: " . $e->getMessage();
        }
    }
}
