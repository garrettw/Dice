<?php

namespace Dice\Blanks;

class A2 {
	public $b;
	public $c;
	public $foo;
	public function __construct(B $b, C $c, $foo) {
		$this->b = $b;
		$this->foo = $foo;
		$this->c = $c;
	}
}
