<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer;

use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;

function getNodeName(mixed $node):mixed {
    if (is_string($node)) {
        return $node;
    }

    if ($node instanceof FullyQualified) {
        return (string) $node;
    }

    if ($node instanceof New_ || $node instanceof StaticCall) {
        return getNodeName($node->class);
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

    if (isset($node->name)) {
        return (string) $node->name;
    }

    return $node;
}