<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Calculators;

use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;

class VariablesCalculator implements CalculatorInterface
{

    public function calculate(Metrics $metrics): void
    {
        foreach ($metrics->get('classes') as $classId => $className) {
            $classMetrics = $metrics->get($classId);

            $superglobals = $classMetrics->get('superglobals');
            $variables = $classMetrics->get('variables');
            $constants = $classMetrics->get('constants');

            $superglobalsUsed = array_sum($superglobals);
            $distinctSuperglobalsUsed = count(array_filter($superglobals, fn($variableCount) => $variableCount > 0));
            $variablesUsed = array_sum($variables);
            $distinctVariablesUsed = count($variables);
            $constantsUsed = array_sum($constants);
            $distinctConstantsUsed = count($constants);
            $superglobalMetric = $variablesUsed > 0 ?
                round((($superglobalsUsed + $constantsUsed) / ($superglobalsUsed + $variablesUsed + $constantsUsed)) * 100, 2)
                : 0;

            $classMetrics->set('superglobalsUsed', $superglobalsUsed);
            $classMetrics->set('distinctSuperglobalsUsed', $distinctSuperglobalsUsed);
            $classMetrics->set('variablesUsed', $variablesUsed);
            $classMetrics->set('distinctVariablesUsed', $distinctVariablesUsed);
            $classMetrics->set('constantsUsed', $constantsUsed);
            $classMetrics->set('distinctConstantsUsed', $distinctConstantsUsed);
            $classMetrics->set('superglobalMetric', $superglobalMetric);

            $metrics->set($classId, $classMetrics);
        }
    }
}
