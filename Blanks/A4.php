<?php

namespace Dice\Blanks;

class A4 {
    public $m1;
    public $m2;
    public function __construct(M1 $m1, M2 $m2) {
        $this->m1 = $m1;
        $this->m2 = $m2;
    }
}
