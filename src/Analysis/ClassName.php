<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;

/**
 * ClassName ValueObject.
 *
 * Creates a ClassName out of a PHPParser Node while detecting anonymous classes.
 */
class ClassName implements \Stringable
{
    public function __toString(): string
    {
        return $this->name;
    }

    public static function ofNode(ClassLike $node): ClassName
    {
        $anonymousIdentifier = sprintf('%d-%d', $node->getStartLine(), $node->getStartTokenPos());

        $name = $node->namespacedName instanceof Node\Name
            ? $node->namespacedName->toString()
            : 'anonymous@'.hash('crc32', $anonymousIdentifier);

        return new self($name);
    }

    /**
     * Private constructor.
     */
    private function __construct(private readonly string $name)
    {
    }
}
