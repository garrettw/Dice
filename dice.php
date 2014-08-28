<?php

/**
 * @description	Dice - A minimal Dependency Injection Container for PHP  
 * @author		Tom Butler tom@r.je
 * @copyright	2012-2014 Tom Butler <tom@r.je> | http://r.je/dice.html
 * @license		http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version		1.3.1
 */

namespace Dice;

class Dice
{
    private $rules = [];
    private $cache = [];
    private $instances = [];
    
    public function addRule($name, Rule $rule)
    {
        $this->rules[ltrim($name, '\\')] = $rule;
    }

    public function getRule($name)
    {
        // first, check for exact match
        if (isset($this->rules[ltrim($name, '\\')])):
            return $this->rules[ltrim($name, '\\')];
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
    }
        
    public function create($component, array $args = [], $forceNewInstance = false)
    {
        if (!$forceNewInstance && isset($this->instances[$component])):
            return $this->instances[$component];
        endif;
            
        if (empty($this->cache[$component])):
            $rule = $this->getRule($component);
            $class = new \ReflectionClass($rule->instanceOf ?: $component);
            $constructor = $class->getConstructor();			
            $params = $constructor ? $this->getParams($constructor, $rule) : null;
            
            $this->cache[$component] = 
                function($args, $forceNewInstance)
                use ($component, $rule, $class, $constructor, $params)
                {
                    if ($rule->shared):
                        if ($constructor):
                            try {
                                $object = $class->newInstanceWithoutConstructor();
                                $constructor->invokeArgs($object, $params($args));
                            } catch (\ReflectionException $r) {
                                $object = $class->newInstanceArgs($params($args));
                            }
                        else:
                            $object = $class->newInstanceWithoutConstructor();
                        endif;
                        
                        $this->instances[$component] = $object;
                    else:
                        $object = $params ?
                            $class->newInstanceArgs($params($args))
                            : new $class->name
                        ;
                    endif;
                    
                    if (!empty($rule->call)):
                        foreach ($rule->call as $call):
                            $class->getMethod($call[0])
                                ->invokeArgs($object,
                                    call_user_func(
                                        $this->getParams(
                                            $class->getMethod(
                                                $this->expand($call[0])
                                            ),
                                            new Rule
                                        ),
                                        $call[1]
                                    )
                                )
                            ;
                        endforeach;
                    endif;
                    return $object;
                }
            ;
        endif;
        return $this->cache[$component]($args, $forceNewInstance);
    }
    
    private function expand($param, array $share = [])
    {
        if (is_array($param)):
            return array_map(
                function($p) use($share) { return $this->expand($p, $share); },
                $param
            );
        endif;
        
        if ($param instanceof Instance):
            return $this->create($param->name, $share);
        endif;
        
        if (is_callable($param)):
            return $param($this);
        endif;
        
        return $param;
    }
        
    private function getParams(\ReflectionMethod $method, Rule $rule)
    {
        $subs = $rule->substitutions ?: null;
        $paramClasses = [];
        
        foreach ($method->getParameters() as $param):
            $paramClasses[] = $param->getClass() ? $param->getClass()->name : null;
        endforeach;
        
        return function($args) use ($paramClasses, $rule, $subs) {
            $share = empty($rule->shareInstances) ?
                []
                : array_map([$this, 'create'], $rule->shareInstances)
            ;
            if (!empty($share) || !empty($rule->constructParams)):
                $args = array_merge(
                    $args, 
                    $this->expand($rule->constructParams, $share),
                    $share
                );
            endif;
            
            $parameters = [];
            
            foreach ($paramClasses as $class):
                if (!empty($args)):
                    for ($i = 0; $i < count($args); ++$i):
                        if ($class && $args[$i] instanceof $class):
                            $parameters[] = array_splice($args, $i, 1)[0];
                            continue 2;
                        endif;
                    endfor;
                endif;
                
                if ($subs && isset($subs[$class])):
                    $parameters[] = is_string($subs[$class]) ?
                        $this->create($subs[$class])
                        : $this->expand($subs[$class])
                    ;
                elseif ($class):
                    $parameters[] = $this->create(
                        $class,
                        $share,
                        (!empty($rule->newInstances) 
                            && in_array($class, $rule->newInstances)
                        )
                    );
                elseif (!empty($args)):
                    $parameters[] = array_shift($args);
                endif;
            endforeach;
            
            return $parameters;
        };	
    }
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
}

class Instance
{
    public $name;
    
    public function __construct($instance)
    {
        $this->name = $instance;
    }
}
