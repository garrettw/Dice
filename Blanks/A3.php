<?php

namespace Dice\Blanks;

class A3 {
    public $b;
    public $c;
    public $foo;
    public function __construct(C $c, $foo, B $b) {
        $this->b = $b;
        $this->foo = $foo;
        $this->c = $c;
    }
}
