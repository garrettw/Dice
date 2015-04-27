<?php

namespace Dice\Blanks;

class B {
    public $c;
    public $s = '';
    public function __construct(C $c) {
        $this->c = $c;
    }

    public function stringset($str) {
        $this->s = $str;
    }
}
