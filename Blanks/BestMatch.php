<?php

namespace Dice\Blanks;

class BestMatch {
    public $a;
    public $string;
    public $b;

    public function __construct($string, A $a, B $b) {
        $this->a = $a;
        $this->string = $string;
        $this->b = $b;
    }
}
