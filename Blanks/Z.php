<?php

namespace Dice\Blanks;

class Z {
    public $y1;
    public $y2;
    public function __construct(Y $y1, Y $y2) {
        $this->y1 = $y1;
        $this->y2 = $y2;
    }
}
