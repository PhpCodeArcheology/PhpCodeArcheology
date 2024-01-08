<?php

declare(strict_types=1);

class MethodTestClass
{}

class ClassWithMethods
{
    public function testMethod1()
    {
    }

    public function testMethod2($class = MethodTestClass::class, MethodTestClass $class2 = new MethodTestClass())
    {
    }

    private function testMethod3()
    {
    }

    public static function testMethod4()
    {
    }

    private static function testMethod5()
    {
    }

    protected function testMethod6()
    {
    }

    function testMethod7()
    {
    }
}
