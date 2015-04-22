<?php

namespace Dice\Blanks;

class NullScalarNested {
    public $nullScalar;
    public function __construct(NullScalar $nullScalar) {
        $this->nullScalar = $nullScalar;
    }
}
