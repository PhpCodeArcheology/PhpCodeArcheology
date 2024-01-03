<?php

declare(strict_types=1);

class AnonymousClassWithMethods
{
    public function testMethod1(): object
    {
        return new class {
          public function testMethod2()
          {
          }

          private function testMethod3()
          {
          }
        };
    }
}
