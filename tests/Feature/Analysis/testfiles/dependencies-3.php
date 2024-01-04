<?php

declare(strict_types=1);

namespace Testfile;

use PhpParser\NodeTraverser;

class TheGreatExtender
{}

function create(): object {
    return new class extends TheGreatExtender
    {
        public function testMethod1()
        {
            $trav = new NodeTraverser();
        }
    };
}
