<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Model\Collections;

/** @implements \IteratorAggregate<array-key, mixed> */
class MethodCallCollection implements CollectionInterface, \IteratorAggregate, \Countable
{
    use CollectionTrait;
}
