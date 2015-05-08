<?php

namespace spec\Dice\Loader;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class JsonSpec extends ObjectBehavior
{
    private $dice;

    public function let()
    {
        $this->dice = new \Dice\Dice();
    }

    public function it_is_initializable()
    {
        $this->shouldHaveType('Dice\Loader\Json');
    }

    public function it_sets_default_rule()
    {
        $json = '{
"rules": [
		{
			"name": "*",
			"shared": true
		}
	]
}';

		$equivalentRule = ['shared' => true];

		//$this->dice->expects($this->once())->method('addRule')->with($this->equalTo('*'), $this->equalTo($equivalentRule));
		//$this->dice->addRule('*', $equivalentRule);
		$this->load($json, $this->dice);
    }

    public function it_shares()
    {
        $json = '{
"rules": [
		{
			"name": "A",
			"shared": true
		}
	]
}';

        $equivalentRule = ['shared' => true];

		//$this->dice->addRule('A', $equivalentRule);
		$this->load($json, $this->dice);
    }

    public function it_uses_construct_params()
    {
        $json = '{
"rules": [
		{
			"name": "A",
			"constructParams": ["A", "B"]
		}
	]
}';

		$equivalentRule = ['constructParams' => ['A', 'B']];

		//$this->dice->addRule('A', $equivalentRule);
		$this->load($json, $this->dice);
    }

    public function it_substitutes()
    {
        $json = '{
"rules": [
		{
			"name": "A",
			"substitutions": {"spec\\\\Dice\\\\B": {"instance": "spec\\\\Dice\\\\C"}}
		}
	]
}';

		$equivalentRule = ['substitutions' => ['spec\Dice\B' => ['instance' => 'spec\Dice\C']]];

		//$this->dice->addRule('A', $equivalentRule);
		$this->load($json, $this->dice);
    }
}
