<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Model\Collections;

class ParameterCollection implements CollectionInterface, \IteratorAggregate, \Countable
{
    use CollectionTrait;
}
