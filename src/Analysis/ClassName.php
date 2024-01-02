<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Analysis;

use PhpParser\Node;

/**
 * ClassName ValueObject
 *
 * Creates a ClassName out of a PHPParser Node while detecting anonymous classes.
 */
class ClassName
{
    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->name;
    }

    /**
     * @param Node $node
     * @return ClassName
     */
    public static function ofNode(Node $node): ClassName
    {
        $name = $node->namespacedName ?? 'anonymous@' . spl_object_hash($node);

        return new self((string) $name);
    }

    /**
     * Private constructor
     *
     * @param string $name
     */
    private function __construct(private string $name)
    {

    }
}
