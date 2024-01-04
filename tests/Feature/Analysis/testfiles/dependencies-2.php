<?php

declare(strict_types=1);

namespace Testfile;

use PhpParser\NodeTraverser;

interface AnonClassInterface
{}

class Creator
{
    public function create(): object
    {
        return new class implements AnonClassInterface
        {
            public function testMethod1()
            {
                $trav = new NodeTraverser();
            }
        };
    }
}
