<?php

namespace Dice\Blanks;

class SharedInstanceTest2 {
    public $shared;
    public function __construct(Shared $shared) {
        $this->shared = $shared;
    }
}
