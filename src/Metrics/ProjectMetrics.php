<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Metrics;

class ProjectMetrics implements MetricsInterface
{
    use MetricsTrait;

    private IdentifierInterface $identifier;

    private array $overallMetrics = [];

    public function __construct(private string $path)
    {
        $this->identifier = ProjectIdentifier::ofPath($this->path);

        $this->overallMetrics = [
            'overallFiles' => 0,
            'overallFileErrors' => 0,
            'overallFunctions' => 0,
            'overallClasses' => 0,
            'overallAbstractClasses' => 0,
            'overallInterfaces' => 0,
            'overallMethods' => 0,
            'overallPrivateMethods' => 0,
            'overallPublicMethods' => 0,
            'overallStaticMethods' => 0,
            'overallLoc' => 0,
            'overallCloc' => 0,
            'overallLloc' => 0,
            'overallMostComplexFile' => '-',
            'overallMostComplexClass' => '-',
            'overallMostComplexMethod' => '-',
            'overallMostComplexFunction' => '-',
        ];

        foreach ($this->overallMetrics as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function getIdentifier(): IdentifierInterface
    {
        return $this->identifier;
    }

    public function getName(): string
    {
        return 'Project';
    }

    public function getOverallMetrics(): array
    {
        return $this->overallMetrics;
    }
}