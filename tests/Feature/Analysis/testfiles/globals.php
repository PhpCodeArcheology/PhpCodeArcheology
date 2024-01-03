<?php

$test = $_POST['test'];

function testRequest($_REQUEST) {
    if ($_REQUEST === $_GET) {
        return;
    }

    $GLOBALS['cartoon'] = 'Donald Duck';
}

class ServerStats
{
    public function parseData()
    {
        $server = $_SERVER;
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
