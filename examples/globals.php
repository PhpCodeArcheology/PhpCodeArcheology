<?php

define('TEST', 1);
const TEST2 = 2;

if (isset($_GET['test'])) {
    echo $_GET['test'];
}

foreach ($_SESSION as $key => $value) {
    echo "$key: $value";
}

function testFunctionWithGlobals() {
    $_SESSION['test'] = 'xx';
}

function testFunctionWithGlobalsAsParam($test) {
    $_SESSION['test'] = $test;
}

testFunctionWithGlobalsAsParam($_POST['test']);

class TestClass
{
    private array $post;

    public function __constructor()
    {
        $this->post = $_POST;
    }
}
