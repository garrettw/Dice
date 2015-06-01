<?php

/**
 * @description Dice - A minimal Dependency Injection Container for PHP
 *
 * @author      Tom Butler tom@r.je
 * @author      Garrett Whitehorn http://garrettw.net/
 * @copyright   2012-2015 Tom Butler <tom@r.je> | http://r.je/dice.html
 * @license     http://www.opensource.org/licenses/bsd-license.php  BSD License
 *
 * @version     2.0
 */

namespace Dice;

class Dice
{
    protected $rules = ['*' => [
        'shared' => false, 'constructParams' => [], 'shareInstances' => [],
        'call' => [], 'inherit' => true, 'substitutions' => [],
        'instanceOf' => null, 'newInstances' => [],
    ]];
    protected $cache = [];
    protected $instances = [];

    public function __construct($defaultRule = [])
    {
        if (!empty($defaultRule)) {
            $this->rules['*'] = $defaultRule;
        }
    }

    public function addRule($match, array $rule)
    {
        $this->rules[$this->normalizeName($match)] = \array_merge($this->getRule($match), $rule);
    }

    public function getRule($matching)
    {
        // first, check for exact match
        $matching = $this->normalizeName($matching);

        if (isset($this->rules[$matching])) {
            return $this->rules[$matching];
        }

        // next, look for a rule where:
        foreach ($this->rules as $key => $rule) {
            if ($key !== '*'                        // its name isn't '*',
                && \is_subclass_of($matching, $key)  // its name is a parent class,
                && $rule['instanceOf'] === null     // its instanceOf is not set,
                && $rule['inherit'] === true        // and it allows inheritance
            ) {
                return $rule;
            }
        }

        // if we get here, return the default rule
        return $this->rules['*'];
    }

    public function create($name, array $args = [],
                           $forceNewInstance = false, array $share = [])
    {
        if (!$forceNewInstance && isset($this->instances[$name])) {
            // we're not forcing a fresh instance at create-time,
            // and we've already created one so just return that same one
            return $this->instances[$name];
        }

        // so now, either we need a new instance or just don't have one stored
        if (!empty($this->cache[$name])) {
            // but we do have the function stored that creates it, so call that
            return $this->cache[$name]($args, $share);
        }

        $rule = $this->getRule($name);
        // get an object to inspect target class
        $class = new \ReflectionClass($rule['instanceOf'] ?: $name);
        $closure = $this->getClosure($name, $rule, $class);

        if ($rule['call']) {
            $closure = function (array $args, array $share) use ($closure, $class, $rule) {
                $object = $closure($args, $share);
                foreach ($rule['call'] as $call) {
                    $class->getMethod($call[0])->invokeArgs(
                        $object,
                        \call_user_func(
                            $this->getParams($class->getMethod($call[0]), $rule),
                            $this->expand($call[1])
                        )
                    );
                }

                return $object;
            };
        }

        $this->cache[$name] = $closure;

        return $this->cache[$name]($args, $share);
    }

    protected function getClosure($name, array $rule, \ReflectionClass $class)
    {
        $constructor = $class->getConstructor();
        $params = $constructor ? $this->getParams($constructor, $rule) : null;

        if ($rule['shared']):
            return function (array $args, array $share) use ($name, $class, $constructor, $params) {
                if ($constructor) {
                    try {
                        $this->instances[$name] = $class->newInstanceWithoutConstructor();
                        $constructor->invokeArgs($this->instances[$name], $params($args, $share));
                    } catch (\ReflectionException $r) {
                        $this->instances[$name] = $class->newInstanceArgs($params($args, $share));
                    }
                } else {
                    $this->instances[$name] = $class->newInstanceWithoutConstructor();
                }

                return $this->instances[$name];
            };
        endif;

        if ($params) {
            return function (array $args, array $share) use ($class, $params) {
                return $class->newInstanceArgs($params($args, $share));
            };
        }

        $classname = $class->name;

        return function () use ($classname) {
            return new $classname();
        };
    }

    protected function getParams(\ReflectionMethod $method, array $rule)
    {
        $paramInfo = [];
        foreach ($method->getParameters() as $param) {
            // get the class hint of each param, if there is one
            $class = ($class = $param->getClass()) ? $class->name : null;
            // determine if the param can be null, if we need to substitute a
            // different class, or if we need to force a new instance for it
            $paramInfo[] = [
                $class,
                $param->allowsNull(),
                \array_key_exists($class, $rule['substitutions']),
                \in_array($class, $rule['newInstances']),
            ];
        }

        return function (array $args, array $share = []) use ($paramInfo, $rule) {
            if ($rule['shareInstances']) {
                $share = \array_merge(
                    $share,
                    \array_map([$this, 'create'], $rule['shareInstances'])
                );
            }

            if (!empty($share) || $rule['constructParams']) {
                $args = \array_merge($args, $this->expand($rule['constructParams']), $share);
            }

            $parameters = [];

            foreach ($paramInfo as $param) {
                list($class, $allowsNull, $sub, $new) = $param;

                if (!empty($args)):
                    foreach ($args as $i => $arg) {
                        if ($class !== null && $arg instanceof $class
                            || ($arg === null && $allowsNull)
                        ) {
                            $parameters[] = \array_splice($args, $i, 1)[0];
                            continue 2;
                        }
                    }
                endif;

                if ($class !== null) {
                    $parameters[] = $sub
                        ? $this->expand($rule['substitutions'][$class], $share)
                        : $this->create($class, [], $new, $share);
                    continue;
                }

                if (!empty($args)) {
                    $parameters[] = $this->expand(\array_shift($args));
                }
            }

            return $parameters;
        };
    }

    protected function expand($param, array $share = [])
    {
        if (!\is_array($param)) {
            // doesn't need any processing
            return $param;
        }

        if (!isset($param['instance'])) {
            // not a lazy instance, so recurse to catch any on deeper levels
            foreach ($param as &$value) {
                $value = $this->expand($value, $share);
            }

            return $param;
        }

        if (\is_callable($param['instance'])) {
            // it's a lazy instance formed by a function
            return \call_user_func($param['instance'], $this);
        }

        // it's a lazy instance's class name string
        return $this->create($param['instance'], [], false, $share);
    }

    protected static function normalizeName($name)
    {
        return \ltrim(\strtolower($name), '\\');
    }
}
