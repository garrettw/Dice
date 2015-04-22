<?php

namespace spec\Dice;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class InstanceSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->beConstructedWith('');
        $this->shouldHaveType('Dice\Instance');
    }
}
