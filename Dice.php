<?php

/**
 * @description Dice - A minimal Dependency Injection Container for PHP
 * @author      Tom Butler tom@r.je
 * @author      Garrett Whitehorn http://garrettw.net/
 * @copyright   2012-2014 Tom Butler <tom@r.je> | http://r.je/dice.html
 * @license     http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version     1.3.2
 */

namespace Dice;

class Dice
{
    private $rules = [];
    private $cache = [];
    private $instances = [];

    public function __construct($shareme = false)
    {
        if ($shareme):
            $this->instances['Dice\\Dice'] = $this;
        endif;
    }

    public function addRule($name, Rule $rule)
    {
        $this->rules[ltrim(strtolower($name), '\\')] = $rule;
    }

    public function getRule($name)
    {
        // first, check for exact match
        if (isset($this->rules[strtolower(ltrim($name, '\\'))])):
            return $this->rules[strtolower(ltrim($name, '\\'))];
        endif;

        // next, look for a rule where:
        foreach ($this->rules as $key => $rule):
            if ($rule->instanceOf === null          // its instanceOf is not set,
                && $key !== '*'                     // its name isn't '*',
                && is_subclass_of($name, $key)      // its name is a parent of arg,
                && $rule->inherit === true          // and it allows inheritance
            ):
                return $rule;
            endif;
        endforeach;

        // lastly, either return a default rule or a new empty rule
        return isset($this->rules['*']) ? $this->rules['*'] : new Rule;
    } // public function getRule($name)

    public function create($component, array $args = [],
                           $forceNewInstance = false, array $share = [])
    {
        if (!$forceNewInstance && isset($this->instances[$component])):
            return $this->instances[$component];
        endif;

        if (!empty($this->cache[$component])):
            return $this->cache[$component]($args, $share);
        endif;

        $rule = $this->getRule($component);
        $class = new \ReflectionClass($rule->instanceOf ?: $component);
        $constructor = $class->getConstructor();
        $params = $constructor ? $this->getParams($constructor, $rule) : null;

        $this->cache[$component] =
            function($args, $share)
            use ($component, $rule, $class, $constructor, $params)
            {
                if ($rule->shared):
                    if ($constructor):
                        try {
                            $object = $class->newInstanceWithoutConstructor();
                            $constructor->invokeArgs($object, $params($args, $share));
                        } catch (\ReflectionException $r) {
                            $object = $class->newInstanceArgs($params($args, $share));
                        }
                    else:
                        $object = $class->newInstanceWithoutConstructor();
                    endif;

                    $this->instances[$component] = $object;
                else:
                    $object = $params
                        ? $class->newInstanceArgs($params($args, $share))
                        : new $class->name;
                endif;

                if ($rule->call):
                    foreach ($rule->call as $call):
                        $class->getMethod($call[0])->invokeArgs(
                            $object,
                            call_user_func(
                                $this->getParams($class->getMethod($call[0]), $rule),
                                $this->expand($call[1])
                            )
                        );
                    endforeach;
                endif;
                return $object;
            }
        ;
        return $this->cache[$component]($args, $share);
    } // public function create($component, array $args = [], $forceNewInstance = false, array $share = [])

    private function expand($param, array $share = [])
    {
        if (is_array($param)):
            foreach ($param as &$key):
                $key = $this->expand($key, $share);
            endforeach;

        elseif ($param instanceof Instance):
            if (is_callable($param->name)):
                $param = call_user_func($param->name, $this, $share);
            else:
                $param = $this->create($param->name, [], false, $share);
            endif;
        endif;

        return $param;
    } // private function expand($param, array $share = [])

    private function getParams(\ReflectionMethod $method, Rule $rule)
    {
        $paramInfo = [];
        foreach ($method->getParameters() as $param):
            $class = ($class = $param->getClass()) ? $class->name : null;
            $paramInfo[] = [
                $class,
                $param->allowsNull(),
                array_key_exists($class, $rule->substitutions),
                in_array($class, $rule->newInstances),
            ];
        endforeach;

        return function($args, $share = []) use ($paramInfo, $rule) {
            if ($rule->shareInstances):
                $share = array_merge(
                    $share,
                    array_map([$this, 'create'], $rule->shareInstances)
                );
            endif;

            if ($share || $rule->constructParams):
                $args = array_merge($args,
                    $this->expand($rule->constructParams, $share), $share
                );
            endif;

            $parameters = [];

            foreach ($paramInfo as list($class, $allowsNull, $sub, $new)):
                if ($args && ($numargs = count($args))):
                    for ($i = 0; $i < $numargs; ++$i):
                        if ($class
                            && $args[$i] instanceof $class
                            || !$args[$i]
                            && $allowsNull
                        ):
                            $parameters[] = array_splice($args, $i, 1)[0];
                            continue 2;
                        endif;
                    endfor;
                endif;

                if ($class):
                    $parameters[] = $sub
                        ? $this->expand($rule->substitutions[$class], $share)
                        : $this->create($class, [], $new, $share);
                elseif ($args):
                    $parameters[] = $this->expand(array_shift($args));
                endif;
            endforeach;

            return $parameters;
        };
    } // private function getParams(\ReflectionMethod $method, Rule $rule)
}

class Rule
{
    public $instanceOf;
    public $shared          = false;
    public $inherit         = true;
    public $constructParams = [];
    public $substitutions   = [];
    public $newInstances    = [];
    public $call            = [];
    public $shareInstances  = [];

    public function __construct($params = [])
    {
        if (is_array($params) && count($params)):
            foreach ($params as $name => $val):
                $this->$name = $val;
            endforeach;
        endif;
    }
}

class Instance
{
    public $name;

    public function __construct($instance)
    {
        $this->name = $instance;
    }
}
