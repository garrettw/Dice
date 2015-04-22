<?php

namespace spec\Dice;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class RuleSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType('Dice\Rule');
    }
}
