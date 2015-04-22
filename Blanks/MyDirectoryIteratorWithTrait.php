<?php

namespace Dice\Blanks;

trait MyTrait {
    public function foo() {}
}

class MyDirectoryIteratorWithTrait extends \DirectoryIterator {
    use MyTrait;
}
