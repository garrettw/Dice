<?php

namespace Dice\Blanks;

class MethodWithDefaultValue {
    public $a;
    public $foo;

    public function __construct(A $a, $foo = 'bar') {
        $this->a = $a;
        $this->foo = $foo;
    }
}
