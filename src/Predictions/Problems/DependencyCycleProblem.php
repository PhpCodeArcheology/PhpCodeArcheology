<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions\Problems;

readonly class DependencyCycleProblem extends AbstractProblem
{
    static function ofProblemLevelAndMessage(int $problemLevel, string $message): DependencyCycleProblem
    {
        return new DependencyCycleProblem($problemLevel, $message);
    }

    public function getName(): string
    {
        return 'Circular dependency';
    }
}
