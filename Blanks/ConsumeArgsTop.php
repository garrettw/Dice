<?php

namespace Dice\Blanks;

class ConsumeArgsTop {
    public $s;
    public $a;
    public function __construct(ConsumeArgsSub $a, $s) {
        $this->a = $a;
        $this->s = $s;
    }
}
