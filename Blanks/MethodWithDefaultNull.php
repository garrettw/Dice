<?php

namespace Dice\Blanks;

class MethodWithDefaultNull {
    public $a;
    public $b;
    public function __construct(A $a, B $b = null) {
        $this->a = $a;
        $this->b = $b;
    }
}
