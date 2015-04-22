<?php

namespace Dice\Blanks;

class HasTwoSameDependencies {
    public $y2a;
    public $y2b;

    public function __construct(Y2 $y2a, Y2 $y2b) {
        $this->y2a = $y2a;
        $this->y2b = $y2b;
    }
}
