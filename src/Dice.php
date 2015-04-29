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
        'inherit' => true, 'substitutions' => [], 'instanceOf' => null,
    ]];
    private $cache = [];
    private $instances = [];

    public function __construct($shareme = false)
    {
        if ($shareme):
            $this->instances['Dice\Dice'] = $this;
        endif;
    }

    public function addRule($match, array $rule, $mergewith = '*')
    {
        $this->rules[ltrim(strtolower($match), '\\')] =
            array_merge($this->rules[$mergewith], $rule);
    }

    public function getRule($matching)
    {
        // first, check for exact match
        if (isset($this->rules[strtolower(ltrim($matching, '\\'))])):
            return $this->rules[strtolower(ltrim($matching, '\\'))];
        endif;

        // next, look for a rule where:
        foreach ($this->rules as $key => $rule):
            if ($rule['instanceOf'] === null        // its instanceOf is not set,
                && $key !== '*'                     // its name isn't '*',
                && is_subclass_of($matching, $key)  // its name is a parent of arg,
                && $rule['inherit'] === true        // and it allows inheritance
            ):
                return $rule;
            endif;
        endforeach;

        // lastly, either return a default rule or a new empty rule
        return isset($this->rules['*']) ? $this->rules['*'] : [];
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
        $class = new \ReflectionClass(
            isset($rule['instanceOf']) ? $rule['instanceOf'] : $component
        );
        $constructor = $class->getConstructor();
        $params = $constructor ? $this->getParams($constructor, $rule) : null;

        if ($rule['shared']):
            $closure = function($args, $share)
                use ($component, $class, $constructor, $params)
                {
                    if ($constructor):
                        try {
                            $this->instances[$component] = $class->newInstanceWithoutConstructor();
                            $constructor->invokeArgs($this->instances[$component], $params($args, $share));
                        } catch (\ReflectionException $r) {
                            $this->instances[$component] = $class->newInstanceArgs($params($args, $share));
                        }
                    else:
                        $this->instances[$component] = $class->newInstanceWithoutConstructor();
                    endif;

                    return $this->instances[$component];
                }
            ;
        elseif ($params):
            $closure = function($args, $share) use ($class, $params)
                {
                    return $class->newInstanceArgs($params($args, $share));
                }
            ;
        else:
            $closure = function($args, $share) use ($class)
                {
                    return new $class->name;
                }
            ;
        endif;

        if (isset($rule['call'])):
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

        $this->cache[$component] = $closure;
        return $this->cache[$component]($args, $share);
    } // public function create($component, array $args = [], $forceNewInstance = false, array $share = [])

    private function expand($param, array $share = [])
    {
        if (is_array($param)):
            if (isset($param['instance'])):
                if (is_callable($param['instance'])):
                    $param = call_user_func($param['instance'], $this, $share);
                else:
                    $param = $this->create($param['instance'], [], false, $share);
                endif;
            endif;

            foreach ($param as &$key):
                $key = $this->expand($key, $share);
            endforeach;
        endif;

        return $param;
    } // private function expand($param, array $share = [])

    private function getParams(\ReflectionMethod $method, array $rule)
    {
        $paramInfo = [];
        foreach ($method->getParameters() as $param):
            $class = ($class = $param->getClass()) ? $class->name : null;
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
                if ($args):
                    for ($i = 0, $numargs = count($args); $i < $numargs; ++$i):
                        if ($class && $args[$i] instanceof $class
                            || ($args[$i] === null && $allowsNull)
                        ):
                            $parameters[] = array_splice($args, $i, 1)[0];
                            continue 2;
                        endif;
                    endfor;
                endif;

                if ($class):
                    $parameters[] = $sub
                        ? $this->expand($rule['substitutions'][$class], $share)
                        : $this->create($class, [], $new, $share);
                elseif ($args):
                    $parameters[] = $this->expand(array_shift($args));
                endif;
            endforeach;

            return $parameters;
        };
    } // private function getParams(\ReflectionMethod $method, Rule $rule)
}
