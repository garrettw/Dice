<?php

namespace Dice\Blanks;

class CyclicB {
    public $a;
    
    public function __construct(CyclicA $a) {
        $this->a = $a;
    }
}
