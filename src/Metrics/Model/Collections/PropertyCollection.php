<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Model\Collections;

class PropertyCollection implements CollectionInterface, \IteratorAggregate, \Countable
{
    use CollectionTrait;
}
