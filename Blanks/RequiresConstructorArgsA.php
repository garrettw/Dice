<?php

namespace Dice\Blanks;

class RequiresConstructorArgsA {
    public $foo;
    public $bar;
    public function __construct($foo, $bar) {
        $this->foo = $foo;
        $this->bar = $bar;
    }
}
