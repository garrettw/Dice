<?php

/**
 * @description Dice - A minimal Dependency Injection Container for PHP
 * @author      Tom Butler tom@r.je
 * @author      Garrett Whitehorn http://garrettw.net/
 * @copyright   2012-2015 Tom Butler <tom@r.je> | http://r.je/dice.html
 * @license     http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version     2.0
 */

namespace Dice;

class Dice
{
    private $rules = ['*' => [
        'shared' => false, 'constructParams' => [], 'shareInstances' => [],
        'call' => [], 'inherit' => true, 'substitutions' => [],
        'instanceOf' => null, 'newInstances' => [],
    ]];
    private $cache = [];
    private $instances = [];

    public function __construct($defaultRule = [])
    {
        if ($defaultRule):
            $this->rules['*'] = $defaultRule;
        endif;
    }

    public function addRule($match, array $rule)
    {
        $match = ltrim(strtolower($match), '\\');
        $this->rules[$match] = array_merge($this->getRule($match), $rule);
    }

    public function getRule($matching)
    {
        // first, check for exact match
        $matching = ltrim(strtolower($matching), '\\');

        if (isset($this->rules[$matching])):
            return $this->rules[$matching];
        endif;

        // next, look for a rule where:
        foreach ($this->rules as $key => $rule):
            if ($key !== '*'                        // its name isn't '*',
                && is_subclass_of($matching, $key)  // its name is a parent class,
                && $rule['instanceOf'] === null     // its instanceOf is not set,
                && $rule['inherit'] === true        // and it allows inheritance
            ):
                return $rule;
            endif;
        endforeach;

        // if we get here, return the default rule
        return $this->rules['*'];
    } // public function getRule($name)

    public function create($name, array $args = [],
                           $forceNewInstance = false, array $share = [])
    {
        if (!$forceNewInstance && isset($this->instances[$name])):
            // we're not forcing a fresh instance at create-time,
            // and we've already created one so just return that same one
            return $this->instances[$name];
        endif;

        // so now, either we need a new instance or just don't have one stored
        if (!empty($this->cache[$name])):
            // but we do have the function stored that creates it, so call that
            return $this->cache[$name]($args, $share);
        endif;

        $rule = $this->getRule($name);
        // get an object to inspect target class
        $class = new \ReflectionClass($rule['instanceOf'] ?: $name);
        $closure = $this->getClosure($name, $rule, $class);

        if ($rule['call']):
            $closure = function ($args, $share) use ($closure, $class, $rule)
                {
                    $object = $closure($args, $share);
                    foreach ($rule['call'] as $call):
                        $class->getMethod($call[0])->invokeArgs(
                            $object,
                            call_user_func(
                                $this->getParams($class->getMethod($call[0]), $rule),
                                $this->expand($call[1])
                            )
                        );
                    endforeach;
                    return $object;
                }
            ;
        endif;

        $this->cache[$name] = $closure;
        return $this->cache[$name]($args, $share);
    } // public function create($name, array $args = [], $forceNewInstance = false, array $share = [])

    private function getClosure($name, array $rule, \ReflectionClass $class)
    {
        $constructor = $class->getConstructor();
        $params = $constructor ? $this->getParams($constructor, $rule) : null;

        if ($rule['shared']):
            return function($args, $share)
                use ($name, $class, $constructor, $params)
                {
                    if ($constructor):
                        try {
                            $this->instances[$name] = $class->newInstanceWithoutConstructor();
                            $constructor->invokeArgs($this->instances[$name], $params($args, $share));
                        } catch (\ReflectionException $r) {
                            $this->instances[$name] = $class->newInstanceArgs($params($args, $share));
                        }
                    else:
                        $this->instances[$name] = $class->newInstanceWithoutConstructor();
                    endif;

                    return $this->instances[$name];
                }
            ;
        endif;

        if ($params):
            return function($args, $share) use ($class, $params)
                {
                    return $class->newInstanceArgs($params($args, $share));
                }
            ;
        endif;

        $classname = $class->name;

        return function($args, $share) use ($classname) { return new $classname; };
    } // private function getClosure($name, array $rule, \ReflectionClass $class)

    private function getParams(\ReflectionMethod $method, array $rule)
    {
        $paramInfo = [];
        foreach ($method->getParameters() as $param):
            // get the class hint of each param, if there is one
            $class = ($class = $param->getClass()) ? $class->name : null;
            // determine if the param can be null, if we need to substitute a
            // different class, or if we need to force a new instance for it
            $paramInfo[] = [
                $class,
                $param->allowsNull(),
                array_key_exists($class, $rule['substitutions']),
                in_array($class, $rule['newInstances']),
            ];
        endforeach;

        return function($args, $share = []) use ($paramInfo, $rule) {
            if ($rule['shareInstances']):
                $share = array_merge(
                    $share,
                    array_map([$this, 'create'], $rule['shareInstances'])
                );
            endif;

            if ($share || $rule['constructParams']):
                $args = array_merge($args,
                    $this->expand($rule['constructParams'], $share), $share
                );
            endif;

            $parameters = [];

            foreach ($paramInfo as list($class, $allowsNull, $sub, $new)):

                foreach ($args as $i => $val):
                    if ($class && $val instanceof $class
                        || ($val === null && $allowsNull)
                    ):
                        $parameters[] = array_splice($args, $i, 1)[0];
                        continue 2;
                    endif;
                endforeach;

                if ($class):
                    $parameters[] = $sub
                        ? $this->expand($rule['substitutions'][$class], $share)
                        : $this->create($class, [], $new, $share);
                    continue;
                endif;

                if ($args):
                    $parameters[] = $this->expand(array_shift($args));
                endif;
            endforeach;

            return $parameters;
        };
    } // private function getParams(\ReflectionMethod $method, Rule $rule)

    private function expand($param, array $share = [])
    {
        if (!is_array($param)):
            // doesn't need any processing
            return $param;
        endif;

        if (!isset($param['instance'])):
            // not a lazy instance, so recurse to catch any on deeper levels
            foreach ($param as &$value):
                $value = $this->expand($value, $share);
            endforeach;
            return $param;
        endif;

        if (is_callable($param['instance'])):
            // it's a lazy instance formed by a function
            return call_user_func($param['instance'], $this, $share);
        endif;

        // it's a lazy instance's class name string
        return $this->create($param['instance'], [], false, $share);
    } // private function expand($param, array $share = [])
}
