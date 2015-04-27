<?php

namespace spec\Dice;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class DiceSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldBeAnInstanceOf('Dice\Dice');
    }

    function it_creates_a_basic_object()
    {
        $a = $this->create('Dice\Blanks\NoConstructor');

        $a->shouldBeAnInstanceOf('Dice\Blanks\NoConstructor');
    }

    function it_instantiates_internal_class()
    {
        $rule = new \Dice\Rule;
        $rule->constructParams[] = '.';
        $this->addRule('DirectoryIterator', $rule);

        $dir = $this->create('DirectoryIterator');

        $dir->shouldBeAnInstanceOf('DirectoryIterator');
    }

    function it_instantiates_extended_internal_class()
    {
        $rule = new \Dice\Rule;
        $rule->constructParams[] = '.';
        $this->addRule('Dice\Blanks\MyDirectoryIterator', $rule);

        $dir = $this->create('Dice\Blanks\MyDirectoryIterator');

        $dir->shouldBeAnInstanceOf('Dice\Blanks\MyDirectoryIterator');
    }

    function it_instantiates_extended_internal_class_with_constructor()
    {
        $rule = new \Dice\Rule;
        $rule->constructParams[] = '.';
        $this->addRule('Dice\Blanks\MyDirectoryIterator2', $rule);

        $dir = $this->create('Dice\Blanks\MyDirectoryIterator2');

        $dir->shouldBeAnInstanceOf('Dice\Blanks\MyDirectoryIterator2');
    }

    function it_no_more_assign()
    {
        $rule = new \Dice\Rule;
        $rule->substitutions['Dice\Blanks\Bar77'] = new \Dice\Instance(function() {
            return \Dice\Blanks\Baz77::create();
        });
        $this->addRule('Dice\Blanks\Foo77', $rule);

        $foo = $this->create('Dice\Blanks\Foo77');

        $foo->bar->shouldBeAnInstanceOf('Dice\Blanks\Bar77');
        $foo->bar->a->shouldEqual('Z');
    }

    function it_consumes_args()
    {
        $rule = new \Dice\Rule;
        $rule->constructParams = ['A'];
        $this->addRule('Dice\Blanks\ConsumeArgsSub', $rule);

        $foo = $this->create('Dice\Blanks\ConsumeArgsTop', ['B']);

        $foo->a->s->shouldEqual('A');
    }

    function it_assigns_shared_named()
    {
        $rule = new \Dice\Rule;
        $rule->shared = true;
        $rule->instanceOf = function() {
            return \Dice\Blanks\Baz77::create();
        };
        $this->addRule('$SharedBaz', $rule);

        //$rule2
    }

    function it_does_pdo()
    {
        $pdo = $this->create('mysqli');
    }

    function it_sets_default_rule()
    {
        $defaultBehaviour = new \Dice\Rule();
        $defaultBehaviour->shared = true;
        $defaultBehaviour->newInstances = array('Foo', 'Bar');

        $this->addRule('*', $defaultBehaviour);

        $this->getRule('*')->shouldBeLike($defaultBehaviour);
    }

    function it_default_rule_works()
    {
        $defaultBehaviour = new \Dice\Rule();
        $defaultBehaviour->shared = true;
        $this->addRule('*', $defaultBehaviour);

        $a1 = $this->create('\Dice\Blanks\A');
        $a2 = $this->create('\Dice\Blanks\A');

        $this->getRule('\Dice\Blanks\A')->shared->shouldBe(true);
        $a1->shouldBeLike($a2);
    }

    function it_creates()
    {
        $myobj = $this->create('stdClass');

        $myobj->shouldBeAnInstanceOf('stdClass');
    }

    function it_cant_create_invalid()
    {
        //"can't expect default exception". Not sure why.
        $this->shouldThrow('\Exception')->duringCreate('SomeClassThatDoesNotExist');
    }

    /*
     * Object graph creation cannot be tested with mocks because the constructor needs to be tested.
     * You can't set 'expects' on the objects which are created making them redundant for that as well
     * Need real classes to test with unfortunately.
     */
    public function it_creates_object_graph()
    {
        $a = $this->create('Dice\Blanks\A');

        $a->b->shouldBeAnInstanceOf('Dice\Blanks\B');
        $a->b->c->shouldBeAnInstanceOf('Dice\Blanks\C');
        $a->b->c->d->shouldBeAnInstanceOf('Dice\Blanks\D');
        $a->b->c->e->shouldBeAnInstanceOf('Dice\Blanks\E');
        $a->b->c->e->f->shouldBeAnInstanceOf('Dice\Blanks\F');
    }

    public function it_creates_new_instances()
    {
        $rule = new \Dice\Rule;
        $rule->shared = true;
        $this->addRule('Dice\Blanks\B', $rule);

        $rule = new \Dice\Rule;
        $rule->newInstances[] = 'Dice\Blanks\B';
        $this->addRule('Dice\Blanks\A', $rule);

        $a1 = $this->create('Dice\Blanks\A');
        $a2 = $this->create('Dice\Blanks\A');

        $a1->b->shouldNotBe($a2->b);
    }

    public function it_assigns_default_value()
    {
        $obj = $this->create('Dice\Blanks\MethodWithDefaultValue');

        $obj->foo->shouldEqual('bar');
    }

    public function it_assigns_default_null()
    {
        $rule = new \Dice\Rule;
        $rule->constructParams = [new \Dice\Instance('Dice\Blanks\A'), null];
        $this->addRule('Dice\Blanks\MethodWithDefaultNull', $rule);

        $obj = $this->create('Dice\Blanks\MethodWithDefaultNull');

        $obj->b->shouldEqual(null);
    }

    public function it_substitutes_null()
    {
        $rule = new \Dice\Rule;
        $rule->substitutions['Dice\Blanks\B'] = null;
        $this->addRule('Dice\Blanks\MethodWithDefaultNull', $rule);

        $obj = $this->create('Dice\Blanks\MethodWithDefaultNull');

        $obj->b->shouldEqual(null);
    }

    public function it_shared_named()
    {
        $rule = new \Dice\Rule;
        $rule->shared = true;
        $rule->instanceOf = 'Dice\Blanks\A';

        $this->addRule('[A]', $rule);

        $a1 = $this->create('[A]');
        $a2 = $this->create('[A]');

        $a1->shouldEqual($a2);
    }

    public function it_can_force_new_instance()
    {
        $rule = new \Dice\Rule;
        $rule->shared = true;
        $this->addRule('Dice\Blanks\A', $rule);

        $a1 = $this->create('Dice\Blanks\A');
        $a2 = $this->create('Dice\Blanks\A');
        $a3 = $this->create('Dice\Blanks\A', array(), true);

        $a1->shouldEqual($a2);
        $a1->shouldNotEqual($a3);
        $a2->shouldNotEqual($a3);
    }

    public function it_shares()
    {
        $shared = new \Dice\Rule;
        $shared->shared = true;
        $this->addRule('Dice\Blanks\MyObj', $shared);

        $obj = $this->create('Dice\Blanks\MyObj');
        $obj2 = $this->create('Dice\Blanks\MyObj');

        $obj->shouldBeAnInstanceOf('Dice\Blanks\MyObj');
        $obj2->shouldBeAnInstanceOf('Dice\Blanks\MyObj');

        $obj->shouldEqual($obj2);

        //This check isn't strictly needed but it's nice to have that safety measure!
        $obj->setFoo('bar');
        $obj->getFoo()->shouldEqual($obj2->getFoo());
        $obj->getFoo()->shouldEqual('bar');
        $obj2->getFoo()->shouldEqual('bar');
    }

    public function it_substitutes_text()
    {
        $rule = new \Dice\Rule;
        $rule->substitutions['Dice\Blanks\B'] = new \Dice\Instance('Dice\Blanks\ExtendedB');
        $this->addRule('Dice\Blanks\A', $rule);

        $a = $this->create('Dice\Blanks\A');

        $a->b->shouldBeAnInstanceOf('Dice\Blanks\ExtendedB');
    }

    public function it_substitutes_mixed_case_text()
    {
        $rule = new \Dice\Rule;
        $rule->substitutions['Dice\Blanks\B'] = new \Dice\Instance('Dice\Blanks\exTenDedb');
        $this->addRule('Dice\Blanks\A', $rule);

        $a = $this->create('Dice\Blanks\A');

        $a->b->shouldBeAnInstanceOf('Dice\Blanks\ExtendedB');
    }

    public function it_substitutes_callback()
    {
        $rule = new \Dice\Rule;
        $injection = $this->getWrappedObject();
        $rule->substitutions['Dice\Blanks\B'] = new \Dice\Instance(function() use ($injection) {
            return $injection->create('Dice\Blanks\ExtendedB');
        });
        $this->addRule('Dice\Blanks\A', $rule);

        $a = $this->create('Dice\Blanks\A');

        $a->b->shouldBeAnInstanceOf('Dice\Blanks\ExtendedB');
    }

    public function it_substitutes_object()
    {
        $rule = new \Dice\Rule;
        $rule->substitutions['Dice\Blanks\B'] = $this->getWrappedObject()->create('Dice\Blanks\ExtendedB');
        $this->addRule('Dice\Blanks\A', $rule);

        $a = $this->create('Dice\Blanks\A');

        $a->b->shouldBeAnInstanceOf('Dice\Blanks\ExtendedB');
    }

    public function it_substitutes_string()
    {
        $rule = new \Dice\Rule;
        $rule->substitutions['Dice\Blanks\B'] = new \Dice\Instance('Dice\Blanks\ExtendedB');
        $this->addRule('Dice\Blanks\A', $rule);

        $a = $this->create('Dice\Blanks\A');

        $a->b->shouldBeAnInstanceOf('Dice\Blanks\ExtendedB');
    }

    public function it_constructs_with_params()
    {
        $rule = new \Dice\Rule;
        $rule->constructParams = array('foo', 'bar');
        $this->addRule('Dice\Blanks\RequiresConstructorArgsA', $rule);

        $obj = $this->create('Dice\Blanks\RequiresConstructorArgsA');

        $obj->foo->shouldEqual('foo');
        $obj->bar->shouldEqual('bar');
    }

    public function it_constructs_with_nested_params()
    {
        $rule = new \Dice\Rule;
        $rule->constructParams = array('foo', 'bar');
        $this->addRule('Dice\Blanks\RequiresConstructorArgsA', $rule);
        $rule = new \Dice\Rule;
        $rule->shareInstances = array('Dice\Blanks\D');
        $this->addRule('Dice\Blanks\ParamRequiresArgs', $rule);

        $obj = $this->create('Dice\Blanks\ParamRequiresArgs');

        $obj->a->foo->shouldEqual('foo');
        $obj->a->bar->shouldEqual('bar');
    }

    public function it_constructs_with_mixed_params()
    {
        $rule = new \Dice\Rule;
        $rule->constructParams = array('foo', 'bar');
        $this->addRule('Dice\Blanks\RequiresConstructorArgsB', $rule);

        $obj = $this->create('Dice\Blanks\RequiresConstructorArgsB');

        $obj->foo->shouldEqual('foo');
        $obj->bar->shouldEqual('bar');
        $obj->a->shouldBeAnInstanceOf('Dice\Blanks\A');
    }

    public function it_constructs_with_args()
    {
        $obj = $this->create('Dice\Blanks\RequiresConstructorArgsA', array('foo', 'bar'));

        $obj->foo->shouldEqual('foo');
        $obj->bar->shouldEqual('bar');
    }

    public function it_constructs_with_mixed_args()
    {
        $obj = $this->create('Dice\Blanks\RequiresConstructorArgsB', array('foo', 'bar'));

        $obj->foo->shouldEqual('foo');
        $obj->bar->shouldEqual('bar');
        $obj->a->shouldBeAnInstanceOf('Dice\Blanks\A');
    }

    public function it_creates_with_1_arg()
    {
        $a = $this->create('Dice\Blanks\A', array($this->create('Dice\Blanks\ExtendedB')));

        $a->b->shouldBeAnInstanceOf('Dice\Blanks\ExtendedB');
    }

    public function it_creates_with_2_args()
    {
        $a2 = $this->create('Dice\Blanks\A2', array($this->create('Dice\Blanks\ExtendedB'), 'Foo'));

        $a2->b->shouldBeAnInstanceOf('Dice\Blanks\B');
        $a2->c->shouldBeAnInstanceOf('Dice\Blanks\C');
        $a2->foo->shouldEqual('Foo');
    }

    public function it_creates_with_2_reversed_args()
    {
        //reverse order args. It should be smart enough to handle this.
        $a2 = $this->create('Dice\Blanks\A2', array('Foo', $this->create('Dice\Blanks\ExtendedB')));

        $a2->b->shouldBeAnInstanceOf('Dice\Blanks\B');
        $a2->c->shouldBeAnInstanceOf('Dice\Blanks\C');
        $a2->foo->shouldEqual('Foo');
    }

    public function it_creates_with_2_other_args()
    {
        $a2 = $this->create('Dice\Blanks\A3', array('Foo', $this->create('Dice\Blanks\ExtendedB')));

        $a2->b->shouldBeAnInstanceOf('Dice\Blanks\B');
        $a2->c->shouldBeAnInstanceOf('Dice\Blanks\C');
        $a2->foo->shouldEqual('Foo');
    }

    public function it_shares_multiple_instances_by_name_mixed()
    {
        $rule = new \Dice\Rule;
        $rule->shared = true;
        $rule->constructParams[] = 'FirstY';
        $this->addRule('Dice\Blanks\Y', $rule);

        $rule = new \Dice\Rule;
        $rule->instanceOf = 'Dice\Blanks\Y';
        $rule->shared = true;
        $rule->constructParams[] = 'SecondY';
        $this->addRule('[Y2]', $rule);

        $rule = new \Dice\Rule;
        $rule->constructParams = array(new \Dice\Instance('Dice\Blanks\Y'), new \Dice\Instance('[Y2]'));
        $this->addRule('Dice\Blanks\Z', $rule);

        $z = $this->create('Dice\Blanks\Z');

        $z->y1->name->shouldEqual('FirstY');
        $z->y2->name->shouldEqual('SecondY');
    }

    public function it_non_shared_component_by_name()
    {
        $rule = new \Dice\Rule;
        $rule->instanceOf = 'Dice\Blanks\Y3';
        $rule->constructParams[] = 'test';
        $this->addRule('$Y2', $rule);

        $y2 = $this->create('$Y2');
        //echo $y2->name;
        $y2->shouldBeAnInstanceOf('Dice\Blanks\Y3');

        $rule = new \Dice\Rule;
        $rule->constructParams[] = new \Dice\Instance('$Y2');
        $this->addRule('Dice\Blanks\Y1', $rule);

        $y1 = $this->create('Dice\Blanks\Y1');
        $y1->y2->shouldBeAnInstanceOf('Dice\Blanks\Y3');
    }

    public function it_non_shared_component_by_name_a()
    {
        $rule = new \Dice\Rule;
        $rule->instanceOf = 'Dice\Blanks\ExtendedB';
        $this->addRule('$B', $rule);

        $rule = new \Dice\Rule;
        $rule->constructParams[] = new \Dice\Instance('$B');
        $this->addRule('Dice\Blanks\A', $rule);

        $a = $this->create('Dice\Blanks\A');

        $a->b->shouldBeAnInstanceOf('Dice\Blanks\ExtendedB');
    }

    public function it_substitutes_by_name()
    {
        $rule = new \Dice\Rule;
        $rule->instanceOf = 'Dice\Blanks\ExtendedB';
        $this->addRule('$B', $rule);

        $rule = new \Dice\Rule;
        $rule->substitutions['Dice\Blanks\B'] = new \Dice\Instance('$B');
        $this->addRule('Dice\Blanks\A', $rule);

        $a = $this->create('Dice\Blanks\A');

        $a->b->shouldBeAnInstanceOf('Dice\Blanks\ExtendedB');
    }

    public function it_does_multiple_substitutions()
    {
        $rule = new \Dice\Rule;
        $rule->instanceOf = 'Dice\Blanks\Y2';
        $rule->constructParams[] = 'first';
        $this->addRule('$Y2A', $rule);

        $rule = new \Dice\Rule;
        $rule->instanceOf = 'Dice\Blanks\Y2';
        $rule->constructParams[] = 'second';
        $this->addRule('$Y2B', $rule);

        $rule = new \Dice\Rule;
        $rule->constructParams = array(new \Dice\Instance('$Y2A'), new \Dice\Instance('$Y2B'));
        $this->addRule('Dice\Blanks\HasTwoSameDependencies', $rule);

        $twodep = $this->create('Dice\Blanks\HasTwoSameDependencies');

        $twodep->y2a->name->shouldEqual('first');
        $twodep->y2b->name->shouldEqual('second');
    }

    public function it_calls()
    {
        $rule = new \Dice\Rule;
        $rule->call[] = array('callMe', array());
        $this->addRule('Dice\Blanks\TestCall', $rule);

        $object = $this->create('Dice\Blanks\TestCall');

        $object->isCalled->shouldBe(true);
    }

    public function it_calls_with_parameters()
    {
        $rule = new \Dice\Rule;
        $rule->call[] = array('callMe', array('one', 'two'));
        $this->addRule('Dice\Blanks\TestCall2', $rule);

        $object = $this->create('Dice\Blanks\TestCall2');

        $object->foo->shouldEqual('one');
        $object->bar->shouldEqual('two');
    }

    public function it_calls_with_instance()
    {
        $rule = new \Dice\Rule;
        $rule->call[] = array('callMe', array(new \Dice\Instance('Dice\Blanks\A')));
        $this->addRule('Dice\Blanks\TestCall3', $rule);

        $object = $this->create('Dice\Blanks\TestCall3');

        $object->a->shouldBeAnInstanceOf('Dice\Blanks\a');
    }

    public function it_calls_with_raw_instance()
    {
        $rule = new \Dice\Rule;
        $rule->call[] = array('callMe', array($this->getWrappedObject()->create('Dice\Blanks\A')));
        $this->addRule('Dice\Blanks\TestCall3', $rule);

        $object = $this->create('Dice\Blanks\TestCall3');

        $object->a->shouldBeAnInstanceOf('Dice\Blanks\A');
    }

    public function it_calls_with_raw_instance_and_matches_on_inheritance()
    {
        $rule = new \Dice\Rule;
        $rule->call[] = array('callMe', array($this->getWrappedObject()->create('Dice\Blanks\A')));
        $this->addRule('Dice\Blanks\TestCall3', $rule);

        $object = $this->create('Dice\Blanks\TestCall3');

        $object->a->shouldBeAnInstanceOf('Dice\Blanks\A');
    }

    public function it_can_use_interface_rules()
    {
        $rule = new \Dice\Rule;
        $rule->shared = true;
        $this->addRule('Dice\Blanks\TestInterface', $rule);

        $one = $this->create('Dice\Blanks\InterfaceTestClass');
        $two = $this->create('Dice\Blanks\InterfaceTestClass');

        $one->shouldImplement('Dice\Blanks\TestInterface');
        $one->shouldEqual($two);
    }

    public function it_applies_rules_to_child_classes()
    {
        $rule = new \Dice\Rule;
        $rule->call[] = array('stringset', array('test'));
        $this->addRule('Dice\Blanks\B', $rule);

        $xb = $this->create('Dice\Blanks\ExtendedB');

        $xb->s->shouldEqual('test');
    }

    public function it_matches_best()
    {
        $bestMatch = $this->create('Dice\Blanks\BestMatch', array('foo', $this->create('Dice\Blanks\A')));

        $bestMatch->string->shouldEqual('foo');
        $bestMatch->a->shouldBeAnInstanceOf('Dice\Blanks\A');
    }

    public function it_shares_instances()
    {
        $rule = new \Dice\Rule();
        $rule->shareInstances = ['Dice\Blanks\Shared'];
        $this->addRule('Dice\Blanks\TestSharedInstancesTop', $rule);

        $shareTest = $this->create('Dice\Blanks\TestSharedInstancesTop');

        $shareTest->shouldBeAnInstanceOf('Dice\Blanks\TestSharedInstancesTop');
        $shareTest->share1->shouldBeAnInstanceOf('Dice\Blanks\SharedInstanceTest1');
        $shareTest->share2->shouldBeAnInstanceOf('Dice\Blanks\SharedInstanceTest2');
        $shareTest->share1->shared->shouldEqual($shareTest->share2->shared);
        $shareTest->share1->shared->uniq->shouldEqual($shareTest->share2->shared->uniq);
    }

    public function it_shares_named_instances()
    {
        $rule = new \Dice\Rule();
        $rule->instanceOf = 'Dice\Blanks\Shared';
        $this->addRule('$Shared', $rule);
        $rule = new \Dice\Rule();
        $rule->shareInstances = ['$Shared'];
        $this->addRule('Dice\Blanks\TestSharedInstancesTop', $rule);

        $shareTest = $this->create('Dice\Blanks\TestSharedInstancesTop');
        $shareTest2 = $this->create('Dice\Blanks\TestSharedInstancesTop');

        $shareTest->shouldBeAnInstanceOf('Dice\Blanks\TestSharedInstancesTop');
        $shareTest->share1->shouldBeAnInstanceOf('Dice\Blanks\SharedInstanceTest1');
        $shareTest->share2->shouldBeAnInstanceOf('Dice\Blanks\SharedInstanceTest2');
        $shareTest->share1->shared->shouldEqual($shareTest->share2->shared);
        $shareTest->share1->shared->uniq->shouldEqual($shareTest->share2->shared->uniq);
        $shareTest2->share1->shared->shouldNotEqual($shareTest->share2->shared);
    }

    public function it_shares_nested_instances()
    {
        $rule = new \Dice\Rule();
        $rule->shareInstances = ['Dice\Blanks\F'];
        $this->addRule('Dice\Blanks\A4',$rule);

        $a = $this->create('Dice\Blanks\A4');

        $a->m1->f->shouldEqual($a->m2->e->f);
    }

    public function it_shares_multiple_instances()
    {
        $rule = new \Dice\Rule();
        $rule->shareInstances = ['Dice\Blanks\Shared'];
        $this->addRule('Dice\Blanks\TestSharedInstancesTop', $rule);

        $shareTest = $this->create('Dice\Blanks\TestSharedInstancesTop');
        $shareTest2 = $this->create('Dice\Blanks\TestSharedInstancesTop');

        $shareTest->shouldBeAnInstanceOf('Dice\Blanks\TestSharedInstancesTop');
        $shareTest->share1->shouldBeAnInstanceOf('Dice\Blanks\SharedInstanceTest1');
        $shareTest->share2->shouldBeAnInstanceOf('Dice\Blanks\SharedInstanceTest2');
        $shareTest->share1->shared->shouldEqual($shareTest->share2->shared);
        $shareTest->share1->shared->uniq->shouldEqual($shareTest->share2->shared->uniq);
        $shareTest2->share1->shared->shouldEqual($shareTest2->share2->shared);
        $shareTest2->share1->shared->uniq->shouldEqual($shareTest2->share2->shared->uniq);
        $shareTest->share1->shared->shouldNotEqual($shareTest2->share2->shared);
        $shareTest->share1->shared->uniq->shouldNotEqual($shareTest2->share2->shared->uniq);
    }

    public function it_namespaces_with_slash()
    {
        $a = $this->create('\Dice\Blanks\NoConstructor');

        $a->shouldBeAnInstanceOf('\Dice\Blanks\NoConstructor');
    }

    public function it_applies_rules_to_namespaces_with_slash()
    {
        $rule = new \Dice\Rule;
        $rule->substitutions['Dice\Blanks\B'] = new \Dice\Instance('Dice\Blanks\ExtendedB');
        $this->addRule('\Dice\Blanks\A', $rule);

        $a = $this->create('\Dice\Blanks\A');
        $a->b->shouldBeAnInstanceOf('Dice\Blanks\ExtendedB');
    }

    // public function testNamespaceTypeHint

    public function it_injects_namespaces()
    {
        $a = $this->create('Dice\Blanks\A');

        $a->shouldBeAnInstanceOf('Dice\Blanks\A');
        $a->b->shouldBeAnInstanceOf('Dice\Blanks\B');
    }

    public function it_namespaces_rules()
    {
        $rule = new \Dice\Rule;
        $this->addRule('Dice\Blanks\B', $rule);

        $this->getRule('Dice\Blanks\B')->shouldEqual($rule);
    }

    /* public function it_handles_cyclic_references()
    {
        $rule = new \Dice\Rule;
        $rule->shared = true;
        $this->addRule('Dice\Blanks\CyclicB', $rule);

        $a = $this->create('Dice\Blanks\CyclicA');

        $a->b->shouldBeAnInstanceOf('Dice\Blanks\CyclicB');
        $a->b->a->shouldBeAnInstanceOf('Dice\Blanks\CyclicA');

        $a->b->shouldEqual($a->b->a->b);
    } */

    public function it_shared_class_with_trait_extends_internal_class()
    {
        $rule = new \Dice\Rule;
        $rule->shared = true;
        $rule->constructParams = ['.'];
        $this->addRule('Dice\Blanks\MyDirectoryIteratorWithTrait', $rule);

        $dir = $this->create('Dice\Blanks\MyDirectoryIteratorWithTrait');

        $dir->shouldBeAnInstanceOf('Dice\Blanks\MyDirectoryIteratorWithTrait');
    }

    public function it_handles_precedence_of_construct_params()
    {
        $rule = new \Dice\Rule;
        $rule->constructParams = ['A', 'B'];
        $this->addRule('Dice\Blanks\RequiresConstructorArgsA', $rule);

        $a1 = $this->create('Dice\Blanks\RequiresConstructorArgsA');
        $a2 = $this->create('Dice\Blanks\RequiresConstructorArgsA', ['C', 'D']);

        $a1->foo->shouldEqual('A');
        $a1->bar->shouldEqual('B');
        $a2->foo->shouldEqual('C');
        $a2->bar->shouldEqual('D');
    }

    public function it_handles_null_scalar()
    {
        $rule = new \Dice\Rule;
        $rule->constructParams = [null];
        $this->addRule('Dice\Blanks\NullScalar', $rule);

        $obj = $this->create('Dice\Blanks\NullScalar');

        $obj->string->shouldEqual(null);
    }

    public function it_handles_nested_null_scalars()
    {
        $rule = new \Dice\Rule;
        $rule->constructParams = [null];
        $this->addRule('Dice\Blanks\NullScalar', $rule);

        $obj = $this->create('Dice\Blanks\NullScalarNested');

        $obj->nullScalar->string->shouldEqual(null);
    }
}
