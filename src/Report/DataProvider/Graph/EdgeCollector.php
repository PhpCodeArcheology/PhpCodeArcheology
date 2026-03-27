<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider\Graph;

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Identity\FunctionAndClassIdentifier;
use PhpCodeArch\Metrics\Model\Collections\CollectionInterface;

class EdgeCollector
{
    public function __construct(
        private readonly MetricsController $metricsController,
    ) {
    }

    /**
     * @param array<string, true>   $knownMethodIds
     * @param array<string, string> $nameToId
     *
     * @return list<array<string, mixed>>
     */
    public function buildMethodCallEdges(array $knownMethodIds, array $nameToId): array
    {
        $edges = [];

        foreach (array_keys($knownMethodIds) as $methodId) {
            $methodCallsCollection = $this->metricsController->getCollectionByIdentifierString($methodId, 'methodCalls');
            if (!$methodCallsCollection instanceof CollectionInterface) {
                continue;
            }

            /** @var array<string, int> $callCounts */
            $callCounts = [];
            foreach ($methodCallsCollection->getAsArray() as $call) {
                if (!is_array($call)) {
                    continue;
                }
                $targetClass = is_string($call['targetClass'] ?? null) ? $call['targetClass'] : '';
                $targetMethod = is_string($call['targetMethod'] ?? null) ? $call['targetMethod'] : '';
                if ('' === $targetClass || '' === $targetMethod) {
                    continue;
                }
                $callKey = $targetClass.'::'.$targetMethod;
                $callCounts[$callKey] = ($callCounts[$callKey] ?? 0) + 1;
            }

            foreach ($callCounts as $callKey => $count) {
                [$targetClassName, $targetMethodName] = explode('::', $callKey, 2);

                if (!isset($nameToId[$targetClassName])) {
                    continue;
                }

                $targetMethodId = (string) FunctionAndClassIdentifier::ofNameAndPath(
                    $targetMethodName,
                    $targetClassName
                );

                if (!isset($knownMethodIds[$targetMethodId])) {
                    continue;
                }

                $edges[] = [
                    'source' => 'method:'.$methodId,
                    'target' => 'method:'.$targetMethodId,
                    'type' => 'calls',
                    'weight' => $count,
                ];
            }
        }

        return $edges;
    }
}
