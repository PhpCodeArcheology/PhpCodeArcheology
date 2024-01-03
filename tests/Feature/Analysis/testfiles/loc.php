<?php

declare(strict_types=1);

$x = 1;

/**
 * @return void
 */
function testFunction(): void {
    $y = 4;
}

class testClass
{
    public function testMethod1()
    {
        // Test
        $x = 3;
    }

    private function testMethod2(
        string $a,
        string $b
    )
    {
        $y = 4;
    }

    public function testMethod3()
    {
    }
}
