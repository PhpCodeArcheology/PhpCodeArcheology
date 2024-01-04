<?php

declare(strict_types=1);

class AnonymousClassWithMethods
{
    public function testMethod1(): object
    {
        return new class {
          public function testMethod2()
          {
              $x = 1;
          }

          private function testMethod3()
          {
              // Test
              $y = 2;

              return 'foo';
          }
        };
    }
}
