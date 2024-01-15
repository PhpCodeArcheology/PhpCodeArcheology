<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;

class VariablesCalculator implements CalculatorInterface
{
    use CalculatorTrait;

    public function calculate(MetricsCollectionInterface $metrics): void
    {
    }

    public function afterTraverse(): void
    {
        foreach ($this->metrics->get('classes') as $classId => $className) {
            $classMetrics = $this->metrics->get($classId);

            $superglobals = $classMetrics->get('superglobals')?->getValue() ?? [];
            $variables = $classMetrics->get('variables')?->getValue() ?? [];
            $constants = $classMetrics->get('constants')?->getValue() ?? [];

            $superglobalsUsed = array_sum($superglobals);
            $distinctSuperglobalsUsed = count(array_filter($superglobals, fn($variableCount) => $variableCount > 0));
            $variablesUsed = array_sum($variables);
            $distinctVariablesUsed = count($variables);
            $constantsUsed = array_sum($constants);
            $distinctConstantsUsed = count($constants);
            $superglobalMetric = $variablesUsed > 0 ?
                round((($superglobalsUsed + $constantsUsed) / ($superglobalsUsed + $variablesUsed + $constantsUsed)) * 100, 2)
                : 0;

            $this->setMetricValues($classMetrics, [
                'superglobalsUsed' => $superglobalsUsed,
                'distinctSuperglobalsUsed' => $distinctSuperglobalsUsed,
                'variablesUsed' => $variablesUsed,
                'distinctVariablesUsed' => $distinctVariablesUsed,
                'constantsUsed' => $constantsUsed,
                'distinctConstantsUsed' => $distinctConstantsUsed,
                'superglobalMetric' => $superglobalMetric,
            ]);

            $this->metrics->set($classId, $classMetrics);
        }
    }
}
