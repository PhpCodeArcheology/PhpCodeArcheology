<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Model\Collections;

class ErrorCollection implements CollectionInterface, \Countable, \IteratorAggregate
{
    use CollectionTrait;
}
