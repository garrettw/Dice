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
    private $rules = [];
    private $cache = [];
    private $instances = [];

    public function __construct($defaultRule = [])
    {
        if (!empty($defaultRule)) {
            $this->rules['*'] = $defaultRule;
        }
    }

    public function addRule($match, array $rule)
    {
        $this->rules[self::normalizeName($match)] = \array_merge($this->getRule($match), $rule);
    }

    public function getRule($matching)
    {
        // first, check for exact match
        $matching = self::normalizeName($matching);

        if (isset($this->rules[$matching])) {
            return $this->rules[$matching];
        }

        // next, look for a rule where:
        foreach ($this->rules as $key => $rule) {
            if ($key !== '*'                        // its name isn't '*',
                && \is_subclass_of($matching, $key) // its name is a parent class,
                && empty($rule['instanceOf'])       // its instanceOf is not set,
                && (empty($rule['inherit']) || $rule['inherit'] === true) // and it allows inheritance
            ) {
                return $rule;
            }
        }

        // if we get here, return the default rule
        return (isset($this->rules['*'])) ? $this->rules['*'] : [];
    }

    public function create($classname, array $args = [], array $share = [])
    {
        // $classname = self::normalizeName($classname); // not sure if this is needed

        if (!empty($this->instances[$classname])) {
            // we've already created one so just return that same one
            return $this->instances[$classname];
        }

        // so now, either we need a new instance or just don't have one stored
        if (!empty($this->cache[$classname])) {
            // but we do have the function stored that creates it, so call that
            return $this->cache[$classname]($args, $share);
        }

        $rule = $this->getRule($classname);
        // get an object to inspect target class
        $class = new \ReflectionClass(isset($rule['instanceOf']) ? $rule['instanceOf'] : $classname);
        $closure = $this->getClosure($classname, $rule, $class);

        if (isset($rule['call'])) {
            $closure = function (array $args, array $share) use ($closure, $class, $rule) {
                $object = $closure($args, $share);
                foreach ($rule['call'] as $call) {
                    call_user_func_array(
                        [$object, $call[0]],
                        $this->getParams($class->getMethod($call[0]), $rule)->__invoke($this->expand($call[1]))
                    );
                }

                return $object;
            };
        }

        $this->cache[$classname] = $closure;

        return $this->cache[$classname]($args, $share);
    }

    private function getClosure($name, array $rule, \ReflectionClass $class)
    {
        $constructor = $class->getConstructor();
        $params = ($constructor) ? $this->getParams($constructor, $rule) : null;

        if (isset($rule['shared']) && $rule['shared'] === true) {
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

                $this->instances[self::normalizeNamespace($name)] = $this->instances[$name];

                return $this->instances[$name];
            };
        }

        if ($params) {
            return function (array $args, array $share) use ($class, $params) {
                return $class->newInstanceArgs($params($args, $share));
            };
        }

        return function () use ($class) {
            return new $class->name();
        };
    }

    private function getParams(\ReflectionMethod $method, array $rule)
    {
        $paramInfo = [];
        foreach ($method->getParameters() as $param) {
            // get the class hint of each param, if there is one
            $class = ($class = $param->getClass()) ? $class->name : null;
            $defaultValue = ($param->isDefaultValueAvailable()) ? $param->getDefaultValue() : null;
            // determine if the param can be null, if we need to substitute a
            // different class, or if we need to force a new instance for it
            $paramInfo[] = [
                $class,
                $param,
                isset($rule['substitutions']) && \array_key_exists($class, $rule['substitutions']),
            ];
        }

        return function (array $args, array $share = []) use ($paramInfo, $rule) {
            if (isset($rule['shareInstances'])) {
                $share = \array_merge(
                    $share,
                    \array_map([$this, 'create'], $rule['shareInstances'])
                );
            }

            if (!empty($share) || isset($rule['constructParams'])) {
                $args = \array_merge(
                    $args,
                    (isset($rule['constructParams'])) ? $this->expand($rule['constructParams']) : [],
                    $share
                );
            }

            $parameters = [];

            foreach ($paramInfo as $pi) {
                list($class, $param, $sub) = $pi;

                if (!empty($args)) {
                    foreach ($args as $i => $arg) {
                        if ($class !== null
                            && ($arg instanceof $class || ($arg === null && $param->allowsNull()))
                        ) {
                            $parameters[] = \array_splice($args, $i, 1)[0];
                            continue 2;
                        }
                    }
                }

                if ($class !== null) {
                    $parameters[] = ($sub)
                        ? $this->expand($rule['substitutions'][$class], $share)
                        : $this->create($class, [], $share);
                    continue;
                }

                if ($args) {
                    $parameters[] = $this->expand(\array_shift($args));
                    continue;
                }

                $parameters[] = ($param->isDefaultValueAvailable()) ? $param->getDefaultValue() : null;
            }

            return $parameters;
        };
    }

    private function expand($param, array $share = [])
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
            if (isset($param['params'])) {
                return \call_user_func_array($param['instance'], $this->expand($param['params']));
            }

            return \call_user_func($param['instance']);
        }

        // it's a lazy instance's class name string
        return $this->create($param['instance'], [], $share);
    }

    private static function normalizeName($name)
    {
        return \strtolower(self::normalizeNamespace($name));
    }

    private static function normalizeNamespace($name)
    {
        return \ltrim($name, '\\');
    }
}
