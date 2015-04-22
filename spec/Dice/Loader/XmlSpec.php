<?php

namespace spec\Dice\Loader;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class XmlSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType('Dice\Loader\Xml');
    }
}
