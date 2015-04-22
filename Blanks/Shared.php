<?php

namespace Dice\Blanks;

class Shared {
    public $uniq;
    
    public function __construct() {
        $this->uniq = uniqid();
    }
}
