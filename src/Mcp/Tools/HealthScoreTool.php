<?php

declare(strict_types=1);

namespace PhpCodeArch\Mcp\Tools;

use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\MetricValue;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;

class HealthScoreTool
{
    public function __construct(
        private readonly DataProviderFactory $factory,
    ) {
    }

    public function getHealthScore(): string
    {
        try {
            $data = $this->factory->getProjectDataProvider()->getTemplateData();
            $rawElements = $data['elements'] ?? null;
            $elements = is_array($rawElements) ? $rawElements : [];

            $getString = function (string $key) use ($elements): string {
                $val = $elements[$key] ?? null;

                return $val instanceof MetricValue ? $val->asString() : '';
            };
            $getInt = function (string $key) use ($elements): int {
                $val = $elements[$key] ?? null;

                return $val instanceof MetricValue ? $val->asInt() : 0;
            };
            $getFloat = function (string $key) use ($elements): float {
                $val = $elements[$key] ?? null;

                return $val instanceof MetricValue ? $val->asFloat() : 0.0;
            };

            $score = $getString(MetricKey::HEALTH_SCORE);
            $grade = $getString(MetricKey::HEALTH_SCORE_GRADE);
            $debt = $getString(MetricKey::OVERALL_TECHNICAL_DEBT_SCORE);
            $errors = $getInt(MetricKey::OVERALL_ERROR_COUNT);
            $warnings = $getInt(MetricKey::OVERALL_WARNING_COUNT);
            $info = $getInt(MetricKey::OVERALL_INFORMATION_COUNT);
            $files = $getInt(MetricKey::OVERALL_FILES);
            $classes = $getInt(MetricKey::OVERALL_CLASSES);
            $functions = $getInt(MetricKey::OVERALL_FUNCTION_COUNT);
            $methods = $getInt(MetricKey::OVERALL_METHODS_COUNT);
            $lloc = $getInt(MetricKey::OVERALL_LLOC);
            $avgCC = $getFloat(MetricKey::OVERALL_AVG_CC);
            $avgMI = $getFloat(MetricKey::OVERALL_AVG_MI);

            $lines = [
                '# Code Health Report',
                '',
                '## Overall Health Score',
                "Score: {$score}/100 (Grade: {$grade})",
                "Technical Debt: {$debt} debt-points per 100 lines",
                '',
                '## Problem Summary',
                "Errors:   {$errors}",
                "Warnings: {$warnings}",
                "Info:     {$info}",
                '',
                '## Project Statistics',
                "Files:     {$files}",
                "Classes:   {$classes}",
                "Functions: {$functions}",
                "Methods:   {$methods}",
                "LLOC:      {$lloc}",
                '',
                '## Code Quality Metrics',
                'Avg Cyclomatic Complexity: '.round($avgCC, 2),
                'Avg Maintainability Index: '.round($avgMI, 2),
            ];

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return 'An error occurred while retrieving the health score.';
        }
    }
}
