<?php

namespace Dice\Blanks;

class CyclicA {
    public $b;

    public function __construct(CyclicB $b) {
        $this->b = $b;
    }
}
