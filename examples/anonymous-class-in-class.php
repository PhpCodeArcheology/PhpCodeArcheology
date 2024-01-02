<?php

declare(strict_types=1);

class CreateAClass
{
    public function wrap()
    {
        return new class {
            public function createAll()
            {}
        };
    }
}
