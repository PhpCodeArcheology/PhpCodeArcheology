<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Model\Collections;

class MethodCallCollection implements CollectionInterface, \IteratorAggregate, \Countable
{
    use CollectionTrait;
}
