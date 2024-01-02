<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Analysis;

use PhpParser\Node;

class ClassName
{
    public function __toString(): string
    {
        return $this->name;
    }

    public static function ofNode(Node $node): ClassName
    {
        $name = $node->namespacedName ?? 'anonymous@' . spl_object_hash($node);

        return new self((string) $name);
    }

    private function __construct(private string $name)
    {

    }
}
