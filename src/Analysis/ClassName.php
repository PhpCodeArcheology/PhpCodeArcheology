<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

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
        $attributes = $node->getAttributes();
        $anonymousIdentifier = sprintf('%s-%s', $attributes['startLine'], $attributes['startTokenPos']);

        $name = $node->namespacedName ?? 'anonymous@' . hash('crc32', $anonymousIdentifier);;

        return new self((string) $name);
    }

    /**
     * Private constructor
     *
     * @param string $name
     */
    private function __construct(private string $name)
    {}
}
