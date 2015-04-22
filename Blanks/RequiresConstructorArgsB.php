<?php

namespace Dice\Blanks;

class RequiresConstructorArgsB {
    public $a;
    public $foo;
    public $bar;
    public function __construct(A $a, $foo, $bar) {
        $this->a = $a;
        $this->foo = $foo;
        $this->bar = $bar;
    }
}
