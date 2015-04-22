<?php

namespace Dice\Blanks;

class SharedInstanceTest1 {
    public $shared;
    
    public function __construct(Shared $shared) {
        $this->shared = $shared;		
    }
}
