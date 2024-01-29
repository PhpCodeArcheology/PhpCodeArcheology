<?php

declare(strict_types=1);

namespace Testfile;

use PhpParser\NodeTraverser;

trait TestTrait
{}

interface FooInterface
{}

abstract class AbstractClass
{

}

class X extends AbstractClass
{}

class FooClass implements FooInterface
{
    const TEST = 1;
    public function __construct(
        private NodeTraverser $traverser
    )
    {

    }

    public function testMethod1()
    {

    }
}

class BarClass extends FooClass
{
    use TestTrait;

    public function testMethod3()
    {
        ClassWithStaticMethod::testMethod2();
    }
}

class ClassWithStaticMethod
{
    public static function testMethod2()
    {
        $x = 2;

        FooClass::TEST;
    }
}

function testFunction1(BarClass $barClass) {
    $barClass->testMethod1();
}

ClassWithStaticMethod::testMethod2();

function testFunction2() {
    ClassWithStaticMethod::testMethod2();
}

