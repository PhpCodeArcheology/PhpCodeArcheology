<?php

class Creator
{
    public function testMethod1($arr)
    {
        foreach ($arr as $a) {
            if ($a >= 4) {
                continue;
            }
        }
    }

    public function testMethod2($c) {
        if ($c === 1) {
            $c = 4;
        }

        return new class {
          public function testMethod3($c)
          {
              switch ($c) {
                  case 'a':
                      return 'z';

                  case 'b':
                      return 'y';
              }

              return null;
          }
        };
    }
}
