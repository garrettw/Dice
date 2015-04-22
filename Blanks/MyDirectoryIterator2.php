<?php

namespace Dice\Blanks;

class MyDirectoryIterator2 extends \DirectoryIterator {
    public function __construct($f) {
        parent::__construct($f);
    }
}
