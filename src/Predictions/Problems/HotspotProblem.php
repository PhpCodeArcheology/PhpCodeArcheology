<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions\Problems;

readonly class HotspotProblem extends AbstractProblem
{
    static function ofProblemLevelAndMessage(int $problemLevel, string $message): HotspotProblem
    {
        return new HotspotProblem($problemLevel, $message);
    }

    public function getName(): string
    {
        return 'Hotspot';
    }

    public function getRecommendation(): string
    {
        return 'This file is frequently changed and complex. Prioritize refactoring to reduce risk.';
    }
}
