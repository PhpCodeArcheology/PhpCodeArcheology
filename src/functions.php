<?php

declare(strict_types=1);

namespace PhpCodeArch;

use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\NullableType;
use PhpParser\PrettyPrinter\Standard;

/**
 * @param mixed $node
 * @return string|null
 */
function getNodeName(mixed $node): ?string
{
    $prettyPrinter = new Standard();

    if ($node instanceof NullableType) {
        $returnType = 'null|';
        return $returnType.getNodeName($node->type);
    }

    if (is_string($node)) {
        return $node;
    }

    if ($node instanceof FullyQualified) {
        return (string) $node;
    }

    if ($node instanceof New_ || $node instanceof StaticCall) {
        return getNodeName($node->class);
    }

    if ($node instanceof Concat) {
        return '{' . $prettyPrinter->prettyPrint([$node]) . '}';
    }

    if (isset($node->class)) {
        return getNodeName($node->class);
    }

    if ($node instanceof Name) {
        return implode($node->getParts());
    }

    if (isset($node->name) && $node->name instanceof Variable) {
        return getNodeName($node->name);
    }

    if (isset($node->name) && ! is_string($node->name)) {
        return getNodeName($node->name);
    }

    if (isset($node->name) && null === $node->name) {
        return 'anonymous@' . spl_object_hash($node);
    }

    if (isset($node->name)) {
        return (string) $node->name;
    }

    return null;
}

function incrementOr1IfNull(mixed $value): mixed
{
    if (! $value) {
        return 1;
    }

    return $value + 1;
}
