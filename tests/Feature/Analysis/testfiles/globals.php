<?php

const TEST_CONSTANT_1 = 1;

$test = $_POST['test'];

function testRequest($_REQUEST) {
    if ($_REQUEST === $_GET) {
        return;
    }

    $GLOBALS['cartoon'] = 'Donald Duck';
    $v = TEST_CONSTANT_1;
}

class ServerStats
{
    public function parseData()
    {
        $server = $_SERVER;
        $v = TEST_CONSTANT_1;
    }

    public function createTesterClass()
    {
        return new class ($_FILES, $_COOKIE, $_SESSION) {
          public function test()
          {
              if ($_FILES['bar'] === $_SESSION['foo']) {
                  return true;
              }

              return false;
          }
        };
    }

    private function reactToEnv()
    {
        return $_ENV['dev'];
    }
}
