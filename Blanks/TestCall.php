<?php

namespace Dice\Blanks;

class TestCall {
    public $isCalled = false;

    public function callMe() {
        $this->isCalled = true;
    }
}
