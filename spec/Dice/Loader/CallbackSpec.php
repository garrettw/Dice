<?php

namespace spec\Dice\Loader;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class CallbackSpec extends ObjectBehavior
{
    private $dice;

    public function let()
    {
        $this->dice = new \Dice\Dice();
    }

    public function it_is_initializable()
    {
        $this->beConstructedWith('');
        $this->shouldHaveType('Dice\Loader\Callback');
    }

    public function it_runs_from_property()
    {
        $this->beConstructedWith('\spec\Dice\Loader\TestConfig::dbServer');

		$this->run($this->dice)->shouldReturn('127.0.0.1');
    }

    public function it_runs_from_method()
    {
        $this->beConstructedWith('\spec\Dice\Loader\TestConfig::getFoo()');

		$this->run($this->dice)->shouldReturn('abc');
    }

    public function it_runs_from_method_with_arg()
    {
        $this->beConstructedWith('\spec\Dice\Loader\TestConfig::getBar(foobar)');

		$this->run($this->dice)->shouldReturn('foobar');
    }

    public function it_runs_from_method_with_args()
    {
        $this->beConstructedWith('\spec\Dice\Loader\TestConfig::getBaz(10,20,30)');

		$this->run($this->dice)->shouldReturn(60);
    }

    public function it_runs_from_deep_lookup()
    {
        $this->beConstructedWith('\spec\Dice\Loader\TestConfig::getObj()::foo');

		$this->run($this->dice)->shouldReturn('bar');
    }
}

class TestConfig {
    public $dbServer = '127.0.0.1';

    public function getFoo()
    {
        return 'abc';
    }

    public function getBar($bar)
    {
        return $bar;
    }

    public function getBaz($a, $b, $c)
    {
        return $a + $b + $c;
    }

    public function getObj()
    {
        $class = new \stdClass;
        $class->foo = 'bar';
        return $class;
    }
}