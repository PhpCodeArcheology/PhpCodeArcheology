<?php

function getClass(): object
{
    return new class {
        private array $arr = [];

        public function getTest(): string
        {
            return 'Test';
        }
    };
}

$testClass = getClass();
$value = $testClass->getTest();
