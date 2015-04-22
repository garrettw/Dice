<?php

namespace Dice\Blanks;

class ParamRequiresArgs {
    public $a;

    public function __construct(D $d, RequiresConstructorArgsA $a) {
        $this->a = $a;
    }
}
