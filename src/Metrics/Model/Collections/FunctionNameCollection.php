<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Model\Collections;

class FunctionNameCollection implements CollectionInterface, \IteratorAggregate, \Countable
{
    use CollectionTrait;
}
