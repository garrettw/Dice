<?php

namespace Dice\Blanks;

class NullScalar {
    public $string;
    public function __construct($string = null) {
        $this->string = $string;
    }
}
