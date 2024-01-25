<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Model\Collections;

class ConstantCollection implements CollectionInterface, \IteratorAggregate, \Countable
{
    use CollectionTrait;
}
