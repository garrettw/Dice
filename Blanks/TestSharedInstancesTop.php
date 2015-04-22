<?php

namespace Dice\Blanks;

class TestSharedInstancesTop {
    public $share1;
    public $share2;

    public function __construct(SharedInstanceTest1 $share1, SharedInstanceTest2 $share2) {
        $this->share1 = $share1;
        $this->share2 = $share2;
    }
}
