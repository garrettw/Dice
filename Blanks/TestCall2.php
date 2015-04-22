<?php

namespace Dice\Blanks;

class TestCall2 {
    public $foo;
    public $bar;
    public function callMe($foo, $bar) {
        $this->foo = $foo;
        $this->bar = $bar;
    }
}
