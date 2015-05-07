<?php

namespace spec\Dice;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class DiceSpec extends ObjectBehavior
{
    public function it_is_initializable()
    {
        $this->shouldBeAnInstanceOf('Dice\Dice');
    }

    public function it_creates_a_basic_object()
    {
        $a = $this->create('spec\Dice\NoConstructor');

        $a->shouldBeAnInstanceOf('spec\Dice\NoConstructor');
    }

    public function it_instantiates_internal_class()
    {
        $rule = ['constructParams' => ['.']];
        $this->addRule('DirectoryIterator', $rule);

        $dir = $this->create('DirectoryIterator');

        $dir->shouldBeAnInstanceOf('DirectoryIterator');
    }

    public function it_instantiates_extended_internal_class()
    {
        $rule = ['constructParams' => ['.']];
        $this->addRule('spec\Dice\MyDirectoryIterator', $rule);

        $dir = $this->create('spec\Dice\MyDirectoryIterator');

        $dir->shouldBeAnInstanceOf('spec\Dice\MyDirectoryIterator');
    }

    public function it_instantiates_extended_internal_class_with_constructor()
    {
        $rule = ['constructParams' => ['.']];
        $this->addRule('spec\Dice\MyDirectoryIterator2', $rule);

        $dir = $this->create('spec\Dice\MyDirectoryIterator2');

        $dir->shouldBeAnInstanceOf('spec\Dice\MyDirectoryIterator2');
    }

    public function it_no_more_assign()
    {
        $rule = ['substitutions' => ['spec\Dice\Bar77' => ['instance' => function() {
            return \spec\Dice\Baz77::create();
        }]]];
        $this->addRule('spec\Dice\Foo77', $rule);

        $foo = $this->create('spec\Dice\Foo77');

        $foo->bar->shouldBeAnInstanceOf('spec\Dice\Bar77');
        $foo->bar->a->shouldEqual('Z');
    }

    public function it_consumes_args()
    {
        $rule = ['constructParams' => ['A']];
        $this->addRule('spec\Dice\ConsumeArgsSub', $rule);

        $foo = $this->create('spec\Dice\ConsumeArgsTop', ['B']);

        $foo->a->s->shouldEqual('A');
    }

    public function it_assigns_shared_named()
    {
        $rule = ['shared' => true, 'instanceOf' => function() {
            return \spec\Dice\Baz77::create();
        }];
        $this->addRule('$SharedBaz', $rule);

        //$rule2
    }

    public function it_does_pdo()
    {
        $pdo = $this->create('mysqli');
    }

    public function it_sets_default_rule()
    {
        $defaultBehaviour = ['shared' => true, 'newInstances' => ['Foo', 'Bar']];

        $this->addRule('*', $defaultBehaviour);

        // $this->getWrappedObject()->getRule('*') should == $defaultBehaviour
    }

    public function it_default_rule_works()
    {
        $defaultBehaviour = ['shared' => true];
        $this->addRule('*', $defaultBehaviour);

        $a1 = $this->create('\spec\Dice\A');
        $a2 = $this->create('\spec\Dice\A');

        $this->getRule('\spec\Dice\A')['shared']->shouldBe(true);
        $a1->shouldBeLike($a2);
    }

    public function it_creates()
    {
        $myobj = $this->create('stdClass');

        $myobj->shouldBeAnInstanceOf('stdClass');
    }

    public function it_cant_create_invalid()
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
        $a = $this->create('spec\Dice\A');

        $a->b->shouldBeAnInstanceOf('spec\Dice\B');
        $a->b->c->shouldBeAnInstanceOf('spec\Dice\C');
        $a->b->c->d->shouldBeAnInstanceOf('spec\Dice\D');
        $a->b->c->e->shouldBeAnInstanceOf('spec\Dice\E');
        $a->b->c->e->d->shouldBeAnInstanceOf('spec\Dice\D');
    }

    public function it_creates_new_instances()
    {
        $rule = ['shared' => true];
        $this->addRule('spec\Dice\B', $rule);

        $rule = ['newInstances' => ['spec\Dice\B']];
        $this->addRule('spec\Dice\A', $rule);

        $a1 = $this->create('spec\Dice\A');
        $a2 = $this->create('spec\Dice\A');

        $a1->b->shouldNotBe($a2->b);
    }

    public function it_assigns_default_value()
    {
        $obj = $this->create('spec\Dice\MethodWithDefaultValue');

        $obj->foo->shouldEqual('bar');
    }

    public function it_assigns_default_null()
    {
        $rule = ['constructParams' => [['instance' => 'spec\Dice\A'], null]];
        $this->addRule('spec\Dice\MethodWithDefaultNull', $rule);

        $obj = $this->create('spec\Dice\MethodWithDefaultNull');

        $obj->b->shouldEqual(null);
    }

    public function it_substitutes_null()
    {
        $rule = ['substitutions' => ['spec\Dice\B' => null]];
        $this->addRule('spec\Dice\MethodWithDefaultNull', $rule);

        $obj = $this->create('spec\Dice\MethodWithDefaultNull');

        $obj->b->shouldEqual(null);
    }

    public function it_shared_named()
    {
        $rule = ['shared' => true, 'instanceOf' => 'spec\Dice\A'];

        $this->addRule('[A]', $rule);

        $a1 = $this->create('[A]');
        $a2 = $this->create('[A]');

        $a1->shouldEqual($a2);
    }

    public function it_can_force_new_instance()
    {
        $rule = ['shared' => true];
        $this->addRule('spec\Dice\A', $rule);

        $a1 = $this->create('spec\Dice\A');
        $a2 = $this->create('spec\Dice\A');
        $a3 = $this->create('spec\Dice\A', array(), true);

        $a1->shouldEqual($a2);
        $a1->shouldNotEqual($a3);
        $a2->shouldNotEqual($a3);
    }

    public function it_shares()
    {
        $shared = ['shared' => true];
        $this->addRule('spec\Dice\MyObj', $shared);

        $obj = $this->create('spec\Dice\MyObj');
        $obj2 = $this->create('spec\Dice\MyObj');

        $obj->shouldBeAnInstanceOf('spec\Dice\MyObj');
        $obj2->shouldBeAnInstanceOf('spec\Dice\MyObj');

        $obj->shouldEqual($obj2);

        //This check isn't strictly needed but it's nice to have that safety measure!
        $obj->setFoo('bar');
        $obj->getFoo()->shouldEqual($obj2->getFoo());
        $obj->getFoo()->shouldEqual('bar');
        $obj2->getFoo()->shouldEqual('bar');
    }

    public function it_substitutes_text()
    {
        $rule = ['substitutions' => ['spec\Dice\B' => ['instance' => 'spec\Dice\ExtendedB']]];
        $this->addRule('spec\Dice\A', $rule);

        $a = $this->create('spec\Dice\A');

        $a->b->shouldBeAnInstanceOf('spec\Dice\ExtendedB');
    }

    public function it_substitutes_mixed_case_text()
    {
        $rule = ['substitutions' => ['spec\Dice\B' => ['instance' => 'spec\Dice\exTenDedb']]];
        $this->addRule('spec\Dice\A', $rule);

        $a = $this->create('spec\Dice\A');

        $a->b->shouldBeAnInstanceOf('spec\Dice\ExtendedB');
    }

    public function it_substitutes_callback()
    {
        $injection = $this->getWrappedObject();
        $rule = ['substitutions' => ['spec\Dice\B' => ['instance' =>
            function() use ($injection) {
                return $injection->create('spec\Dice\ExtendedB');
            }
        ]]];
        $this->addRule('spec\Dice\A', $rule);

        $a = $this->create('spec\Dice\A');

        $a->b->shouldBeAnInstanceOf('spec\Dice\ExtendedB');
    }

    public function it_substitutes_object()
    {
        $rule = ['substitutions' => ['spec\Dice\B' =>
            $this->getWrappedObject()->create('spec\Dice\ExtendedB')
        ]];
        $this->addRule('spec\Dice\A', $rule);

        $a = $this->create('spec\Dice\A');

        $a->b->shouldBeAnInstanceOf('spec\Dice\ExtendedB');
    }

    public function it_substitutes_string()
    {
        $rule = ['substitutions' => ['spec\Dice\B' =>
            ['instance' => 'spec\Dice\ExtendedB']
        ]];
        $this->addRule('spec\Dice\A', $rule);

        $a = $this->create('spec\Dice\A');

        $a->b->shouldBeAnInstanceOf('spec\Dice\ExtendedB');
    }

    public function it_constructs_with_params()
    {
        $rule = ['constructParams' => ['foo', 'bar']];
        $this->addRule('spec\Dice\RequiresConstructorArgsA', $rule);

        $obj = $this->create('spec\Dice\RequiresConstructorArgsA');

        $obj->foo->shouldEqual('foo');
        $obj->bar->shouldEqual('bar');
    }

    public function it_constructs_with_nested_params()
    {
        $rule = ['constructParams' => ['foo', 'bar']];
        $this->addRule('spec\Dice\RequiresConstructorArgsA', $rule);
        $rule = ['shareInstances' => ['spec\Dice\D']];
        $this->addRule('spec\Dice\ParamRequiresArgs', $rule);

        $obj = $this->create('spec\Dice\ParamRequiresArgs');

        $obj->a->foo->shouldEqual('foo');
        $obj->a->bar->shouldEqual('bar');
    }

    public function it_constructs_with_mixed_params()
    {
        $rule = ['constructParams' => ['foo', 'bar']];
        $this->addRule('spec\Dice\RequiresConstructorArgsB', $rule);

        $obj = $this->create('spec\Dice\RequiresConstructorArgsB');

        $obj->foo->shouldEqual('foo');
        $obj->bar->shouldEqual('bar');
        $obj->a->shouldBeAnInstanceOf('spec\Dice\A');
    }

    public function it_constructs_with_args()
    {
        $obj = $this->create('spec\Dice\RequiresConstructorArgsA', ['foo', 'bar']);

        $obj->foo->shouldEqual('foo');
        $obj->bar->shouldEqual('bar');
    }

    public function it_constructs_with_mixed_args()
    {
        $obj = $this->create('spec\Dice\RequiresConstructorArgsB', ['foo', 'bar']);

        $obj->foo->shouldEqual('foo');
        $obj->bar->shouldEqual('bar');
        $obj->a->shouldBeAnInstanceOf('spec\Dice\A');
    }

    public function it_creates_with_1_arg()
    {
        $a = $this->create('spec\Dice\A', [$this->create('spec\Dice\ExtendedB')]);

        $a->b->shouldBeAnInstanceOf('spec\Dice\ExtendedB');
    }

    public function it_creates_with_2_args()
    {
        $a2 = $this->create('spec\Dice\A2', [$this->create('spec\Dice\ExtendedB'), 'Foo']);

        $a2->b->shouldBeAnInstanceOf('spec\Dice\B');
        $a2->c->shouldBeAnInstanceOf('spec\Dice\C');
        $a2->foo->shouldEqual('Foo');
    }

    public function it_creates_with_2_reversed_args()
    {
        //reverse order args. It should be smart enough to handle this.
        $a2 = $this->create('spec\Dice\A2', ['Foo', $this->create('spec\Dice\ExtendedB')]);

        $a2->b->shouldBeAnInstanceOf('spec\Dice\B');
        $a2->c->shouldBeAnInstanceOf('spec\Dice\C');
        $a2->foo->shouldEqual('Foo');
    }

    public function it_creates_with_2_other_args()
    {
        $a2 = $this->create('spec\Dice\A3', ['Foo', $this->create('spec\Dice\ExtendedB')]);

        $a2->b->shouldBeAnInstanceOf('spec\Dice\B');
        $a2->c->shouldBeAnInstanceOf('spec\Dice\C');
        $a2->foo->shouldEqual('Foo');
    }

    public function it_shares_multiple_instances_by_name_mixed()
    {
        $rule = ['shared' => true, 'constructParams' => ['FirstY']];
        $this->addRule('spec\Dice\Y', $rule);

        $rule = ['shared' => true, 'constructParams' => ['SecondY'],
            'instanceOf' => 'spec\Dice\Y'
        ];
        $this->addRule('[Y2]', $rule);

        $rule = ['constructParams' =>
            [['instance' => 'spec\Dice\Y'], ['instance' => '[Y2]']]
        ];
        $this->addRule('spec\Dice\HasTwoSameDependencies', $rule);

        $z = $this->create('spec\Dice\HasTwoSameDependencies');

        $z->ya->name->shouldEqual('FirstY');
        $z->yb->name->shouldEqual('SecondY');
    }

    public function it_non_shared_component_by_name()
    {
        $rule = ['instanceOf' => 'spec\Dice\Y3', 'constructParams' => ['test']];
        $this->addRule('$Y2', $rule);

        $y2 = $this->create('$Y2');
        //echo $y2->name;
        $y2->shouldBeAnInstanceOf('spec\Dice\Y3');

        $rule = ['constructParams' => [['instance' => '$Y2']]];
        $this->addRule('spec\Dice\Y1', $rule);

        $y1 = $this->create('spec\Dice\Y1');
        $y1->y->shouldBeAnInstanceOf('spec\Dice\Y3');
    }

    public function it_non_shared_component_by_name_a()
    {
        $rule = ['instanceOf' => 'spec\Dice\ExtendedB'];
        $this->addRule('$B', $rule);

        $rule = ['constructParams' => [['instance' => '$B']]];
        $this->addRule('spec\Dice\A', $rule);

        $a = $this->create('spec\Dice\A');

        $a->b->shouldBeAnInstanceOf('spec\Dice\ExtendedB');
    }

    public function it_substitutes_by_name()
    {
        $rule = ['instanceOf' => 'spec\Dice\ExtendedB'];
        $this->addRule('$B', $rule);

        $rule = ['substitutions' => ['spec\Dice\B' => ['instance' => '$B']]];
        $this->addRule('spec\Dice\A', $rule);

        $a = $this->create('spec\Dice\A');

        $a->b->shouldBeAnInstanceOf('spec\Dice\ExtendedB');
    }

    public function it_does_multiple_substitutions()
    {
        $rule = ['instanceOf' => 'spec\Dice\Y', 'constructParams' => ['first']];
        $this->addRule('$YA', $rule);

        $rule = ['instanceOf' => 'spec\Dice\Y', 'constructParams' => ['second']];
        $this->addRule('$YB', $rule);

        $rule = ['constructParams' => [['instance' => '$YA'], ['instance' => '$YB']]];
        $this->addRule('spec\Dice\HasTwoSameDependencies', $rule);

        $twodep = $this->create('spec\Dice\HasTwoSameDependencies');

        $twodep->ya->name->shouldEqual('first');
        $twodep->yb->name->shouldEqual('second');
    }

    public function it_calls()
    {
        $rule = ['call' => [['callMe', []]]];
        $this->addRule('spec\Dice\TestCall', $rule);

        $object = $this->create('spec\Dice\TestCall');

        $object->isCalled->shouldBe(true);
    }

    public function it_calls_with_parameters()
    {
        $rule = ['call' => [['callMe', ['one', 'two']]]];
        $this->addRule('spec\Dice\TestCall2', $rule);

        $object = $this->create('spec\Dice\TestCall2');

        $object->foo->shouldEqual('one');
        $object->bar->shouldEqual('two');
    }

    public function it_calls_with_instance()
    {
        $rule = ['call' => [['callMe', [['instance' => 'spec\Dice\A']]]]];
        $this->addRule('spec\Dice\TestCall3', $rule);

        $object = $this->create('spec\Dice\TestCall3');

        $object->a->shouldBeAnInstanceOf('spec\Dice\a');
    }

    public function it_calls_with_raw_instance()
    {
        $rule = ['call' => [['callMe',
            [$this->getWrappedObject()->create('spec\Dice\A')]
        ]]];
        $this->addRule('spec\Dice\TestCall3', $rule);

        $object = $this->create('spec\Dice\TestCall3');

        $object->a->shouldBeAnInstanceOf('spec\Dice\A');
    }

    public function it_calls_with_raw_instance_and_matches_on_inheritance()
    {
        $rule = ['call' => [['callMe',
            [$this->getWrappedObject()->create('spec\Dice\A')]
        ]]];
        $this->addRule('spec\Dice\TestCall3', $rule);

        $object = $this->create('spec\Dice\TestCall3');

        $object->a->shouldBeAnInstanceOf('spec\Dice\A');
    }

    public function it_can_use_interface_rules()
    {
        $rule = ['shared' => true];
        $this->addRule('spec\Dice\TestInterface', $rule);

        $one = $this->create('spec\Dice\InterfaceTestClass');
        $two = $this->create('spec\Dice\InterfaceTestClass');

        $one->shouldImplement('spec\Dice\TestInterface');
        $one->shouldEqual($two);
    }

    public function it_applies_rules_to_child_classes()
    {
        $rule = ['call' => [['stringset', ['test']]]];
        $this->addRule('spec\Dice\B', $rule);

        $xb = $this->create('spec\Dice\ExtendedB');

        $xb->s->shouldEqual('test');
    }

    public function it_matches_best()
    {
        $bestMatch = $this->create('spec\Dice\BestMatch', ['foo', $this->create('spec\Dice\A')]);

        $bestMatch->string->shouldEqual('foo');
        $bestMatch->a->shouldBeAnInstanceOf('spec\Dice\A');
    }

    public function it_shares_instances()
    {
        $rule = ['shareInstances' => ['spec\Dice\Shared']];
        $this->addRule('spec\Dice\TestSharedInstancesTop', $rule);

        $shareTest = $this->create('spec\Dice\TestSharedInstancesTop');

        $shareTest->shouldBeAnInstanceOf('spec\Dice\TestSharedInstancesTop');
        $shareTest->share1->shouldBeAnInstanceOf('spec\Dice\SharedInstanceTest1');
        $shareTest->share2->shouldBeAnInstanceOf('spec\Dice\SharedInstanceTest2');
        $shareTest->share1->shared->shouldEqual($shareTest->share2->shared);
        $shareTest->share1->shared->uniq->shouldEqual($shareTest->share2->shared->uniq);
    }

    public function it_shares_named_instances()
    {
        $rule = ['instanceOf' => 'spec\Dice\Shared'];
        $this->addRule('$Shared', $rule);
        $rule = ['shareInstances' => ['$Shared']];
        $this->addRule('spec\Dice\TestSharedInstancesTop', $rule);

        $shareTest = $this->create('spec\Dice\TestSharedInstancesTop');
        $shareTest2 = $this->create('spec\Dice\TestSharedInstancesTop');

        $shareTest->shouldBeAnInstanceOf('spec\Dice\TestSharedInstancesTop');
        $shareTest->share1->shouldBeAnInstanceOf('spec\Dice\SharedInstanceTest1');
        $shareTest->share2->shouldBeAnInstanceOf('spec\Dice\SharedInstanceTest2');
        $shareTest->share1->shared->shouldEqual($shareTest->share2->shared);
        $shareTest->share1->shared->uniq->shouldEqual($shareTest->share2->shared->uniq);
        $shareTest2->share1->shared->shouldNotEqual($shareTest->share2->shared);
    }

    public function it_shares_nested_instances()
    {
        $rule = ['shareInstances' => ['spec\Dice\D']];
        $this->addRule('spec\Dice\A4',$rule);

        $a = $this->create('spec\Dice\A4');

        $a->e->d->shouldEqual($a->m2->e->d);
    }

    public function it_shares_multiple_instances()
    {
        $rule = ['shareInstances' => ['spec\Dice\Shared']];
        $this->addRule('spec\Dice\TestSharedInstancesTop', $rule);

        $shareTest = $this->create('spec\Dice\TestSharedInstancesTop');
        $shareTest2 = $this->create('spec\Dice\TestSharedInstancesTop');

        $shareTest->shouldBeAnInstanceOf('spec\Dice\TestSharedInstancesTop');
        $shareTest->share1->shouldBeAnInstanceOf('spec\Dice\SharedInstanceTest1');
        $shareTest->share2->shouldBeAnInstanceOf('spec\Dice\SharedInstanceTest2');
        $shareTest->share1->shared->shouldEqual($shareTest->share2->shared);
        $shareTest->share1->shared->uniq->shouldEqual($shareTest->share2->shared->uniq);
        $shareTest2->share1->shared->shouldEqual($shareTest2->share2->shared);
        $shareTest2->share1->shared->uniq->shouldEqual($shareTest2->share2->shared->uniq);
        $shareTest->share1->shared->shouldNotEqual($shareTest2->share2->shared);
        $shareTest->share1->shared->uniq->shouldNotEqual($shareTest2->share2->shared->uniq);
    }

    public function it_namespaces_with_slash()
    {
        $a = $this->create('\spec\Dice\NoConstructor');

        $a->shouldBeAnInstanceOf('\spec\Dice\NoConstructor');
    }

    public function it_applies_rules_to_namespaces_with_slash()
    {
        $rule = ['substitutions' => ['spec\Dice\B' => ['instance' => 'spec\Dice\ExtendedB']]];
        $this->addRule('\spec\Dice\A', $rule);

        $a = $this->create('\spec\Dice\A');
        $a->b->shouldBeAnInstanceOf('spec\Dice\ExtendedB');
    }

    // public function testNamespaceTypeHint

    public function it_injects_namespaces()
    {
        $a = $this->create('spec\Dice\A');

        $a->shouldBeAnInstanceOf('spec\Dice\A');
        $a->b->shouldBeAnInstanceOf('spec\Dice\B');
    }

    public function it_namespaces_rules()
    {
        $rule = [];
        $this->addRule('spec\Dice\B', $rule);

        $this->getRule('spec\Dice\B')->shouldEqual($this->getRule('*'));
    }

    /* public function it_handles_cyclic_references()
    {
        $rule = new \Dice\Rule;
        $rule->shared = true;
        $this->addRule('spec\Dice\CyclicB', $rule);

        $a = $this->create('spec\Dice\CyclicA');

        $a->b->shouldBeAnInstanceOf('spec\Dice\CyclicB');
        $a->b->a->shouldBeAnInstanceOf('spec\Dice\CyclicA');

        $a->b->shouldEqual($a->b->a->b);
    } */

    public function it_shared_class_with_trait_extends_internal_class()
    {
        $rule = ['shared' => true, 'constructParams' => ['.']];
        $this->addRule('spec\Dice\MyDirectoryIteratorWithTrait', $rule);

        $dir = $this->create('spec\Dice\MyDirectoryIteratorWithTrait');

        $dir->shouldBeAnInstanceOf('spec\Dice\MyDirectoryIteratorWithTrait');
    }

    public function it_handles_precedence_of_construct_params()
    {
        $rule = ['constructParams' => ['A', 'B']];
        $this->addRule('spec\Dice\RequiresConstructorArgsA', $rule);

        $a1 = $this->create('spec\Dice\RequiresConstructorArgsA');
        $a2 = $this->create('spec\Dice\RequiresConstructorArgsA', ['C', 'D']);

        $a1->foo->shouldEqual('A');
        $a1->bar->shouldEqual('B');
        $a2->foo->shouldEqual('C');
        $a2->bar->shouldEqual('D');
    }

    public function it_handles_null_scalar()
    {
        $rule = ['constructParams' => [null]];
        $this->addRule('spec\Dice\NullScalar', $rule);

        $obj = $this->create('spec\Dice\NullScalar');

        $obj->string->shouldEqual(null);
    }

    public function it_handles_nested_null_scalars()
    {
        $rule = ['constructParams' => [null]];
        $this->addRule('spec\Dice\NullScalar', $rule);

        $obj = $this->create('spec\Dice\NullScalarNested');

        $obj->nullScalar->string->shouldEqual(null);
    }
}

class A {
    public $b;
    public function __construct(B $b) {
        $this->b = $b;
    }
}

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

class A3 {
    public $b;
    public $c;
    public $foo;
    public function __construct(C $c, $foo, B $b) {
        $this->b = $b;
        $this->foo = $foo;
        $this->c = $c;
    }
}

class A4 {
    public $e;
    public $m2;
    public function __construct(E $e, M2 $m2) {
        $this->e = $e;
        $this->m2 = $m2;
    }
}

class B {
    public $c;
    public $s = '';
    public function __construct(C $c) {
        $this->c = $c;
    }

    public function stringset($str) {
        $this->s = $str;
    }
}

class Bar77 {
    public $a;
    public function __construct($a) {
        $this->a = $a;
    }
}

class Baz77 {
    public static function create() {
        return new Bar77('Z');
    }
}

class BestMatch {
    public $a;
    public $string;
    public $b;

    public function __construct($string, A $a, B $b) {
        $this->a = $a;
        $this->string = $string;
        $this->b = $b;
    }
}

class C {
    public $d;
    public $e;
    public function __construct(D $d, E $e) {
        $this->d = $d;
        $this->e = $e;
    }
}

class ConsumeArgsSub {
    public $s;
    public function __construct($s) {
        $this->s = $s;
    }
}

class ConsumeArgsTop {
    public $s;
    public $a;
    public function __construct(ConsumeArgsSub $a, $s) {
        $this->a = $a;
        $this->s = $s;
    }
}

class CyclicA {
    public $b;

    public function __construct(CyclicB $b) {
        $this->b = $b;
    }
}

class CyclicB {
    public $a;

    public function __construct(CyclicA $a) {
        $this->a = $a;
    }
}

class D {}

class E {
    public $d;
    public function __construct(D $d) {
        $this->d = $d;
    }
}

class ExtendedB extends B {}

class Foo77 {
    public $bar;
    public function __construct(Bar77 $bar) {
        $this->bar = $bar;
    }
}

class HasTwoSameDependencies {
    public $ya;
    public $yb;

    public function __construct(Y $ya, Y $yb) {
        $this->ya = $ya;
        $this->yb = $yb;
    }
}

class InterfaceTestClass implements TestInterface {}

class M2 {
	public $e;
	public function __construct(E $e) {
		$this->e = $e;
	}
}

class MethodWithDefaultNull {
    public $a;
    public $b;
    public function __construct(A $a, B $b = null) {
        $this->a = $a;
        $this->b = $b;
    }
}

class MethodWithDefaultValue {
    public $a;
    public $foo;

    public function __construct(A $a, $foo = 'bar') {
        $this->a = $a;
        $this->foo = $foo;
    }
}

class MyDirectoryIterator extends \DirectoryIterator {}

class MyDirectoryIterator2 extends \DirectoryIterator {
    public function __construct($f) {
        parent::__construct($f);
    }
}

trait MyTrait {
    public function foo() {}
}

class MyDirectoryIteratorWithTrait extends \DirectoryIterator {
    use MyTrait;
}

class MyObj {
    private $foo;
    public function setFoo($foo) {
        $this->foo = $foo;
    }
    public function getFoo() {
        return $this->foo;
    }
}

class NoConstructor {
    public $a = 'b';
}

class NullScalar {
    public $string;
    public function __construct($string = null) {
        $this->string = $string;
    }
}

class NullScalarNested {
    public $nullScalar;
    public function __construct(NullScalar $nullScalar) {
        $this->nullScalar = $nullScalar;
    }
}

class ParamRequiresArgs {
    public $a;

    public function __construct(D $d, RequiresConstructorArgsA $a) {
        $this->a = $a;
    }
}

class RequiresConstructorArgsA {
    public $foo;
    public $bar;
    public function __construct($foo, $bar) {
        $this->foo = $foo;
        $this->bar = $bar;
    }
}

class RequiresConstructorArgsB {
    public $a;
    public $foo;
    public $bar;
    public function __construct(A $a, $foo, $bar) {
        $this->a = $a;
        $this->foo = $foo;
        $this->bar = $bar;
    }
}

class Shared {
    public $uniq;

    public function __construct() {
        $this->uniq = uniqid();
    }
}

class SharedInstanceTest1 {
    public $shared;

    public function __construct(Shared $shared) {
        $this->shared = $shared;
    }
}

class SharedInstanceTest2 {
    public $shared;
    public function __construct(Shared $shared) {
        $this->shared = $shared;
    }
}

class TestCall {
    public $isCalled = false;

    public function callMe() {
        $this->isCalled = true;
    }
}

class TestCall2 {
    public $foo;
    public $bar;
    public function callMe($foo, $bar) {
        $this->foo = $foo;
        $this->bar = $bar;
    }
}

class TestCall3 {
    public $a;
    public function callMe(A $a) {
        $this->a = $a;
    }
}

interface TestInterface {}

class TestSharedInstancesTop {
    public $share1;
    public $share2;

    public function __construct(SharedInstanceTest1 $share1, SharedInstanceTest2 $share2) {
        $this->share1 = $share1;
        $this->share2 = $share2;
    }
}

class Y {
    public $name;
    public function __construct($name) {
        $this->name = $name;
    }
}

class Y1 {
    public $y;

    public function __construct(Y $y) {
        $this->y = $y;
    }
}

class Y3 extends Y {}
