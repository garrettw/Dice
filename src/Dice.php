<?php

/**
 * @description Dice - A minimal Dependency Injection Container for PHP
 *
 * @author      Tom Butler tom@r.je
 * @author      Garrett Whitehorn http://garrettw.net/
 * @copyright   2012-2015 Tom Butler <tom@r.je> | https://r.je/dice.html
 * @license     http://www.opensource.org/licenses/bsd-license.php  BSD License
 *
 * @version     2.0
 */

namespace Dice;

class Dice
{
    private $rules = [];        // Rules which have been set using addRule()
    private $cache = [];        // A cache of closures based on class name so each class is only reflected once
    private $instances = [];    // Stores any instances marked as 'shared' so create() can return the same instance

    /**
     * Constructor which allows setting a default ruleset to apply to all objects.
     */
    public function __construct($defaultRule = [])
    {
        if (!empty($defaultRule)) {
            $this->rules['*'] = $defaultRule;
        }
    }

    /**
     * Adds a rule $rule to the class $classname.
     * See https://r.je/dice.html#example3 for $rule format.
     */
    public function addRule($classname, $rule, $swap = false)
    {
        if ($swap) {
            $temp = $rule;
            $rule = $classname;
            $classname = $temp;
        }
        $this->rules[self::normalizeName($classname)] = \array_merge($this->getRule($classname), $rule);
    }

    /**
     * Returns the rule that will be applied to the class $matching during create().
     *
     * @return array Ruleset that applies when instantiating the given name
     */
    public function getRule($name)
    {
        // first, check for exact match
        $normalname = self::normalizeName($name);

        if (isset($this->rules[$normalname])) {
            return $this->rules[$normalname];
        }

        // next, look for a rule where:
        foreach ($this->rules as $key => $rule) {
            if ($key !== '*'                    // it's not the default rule,
                && \is_subclass_of($name, $key) // its name is a parent class of what we're looking for,
                && empty($rule['instanceOf'])   // it's not a named instance,
                && (empty($rule['inherit']) || $rule['inherit'] === true) // and it applies to subclasses
            ) {
                return $rule;
            }
        }

        // if we get here, return the default rule if it's set
        return (isset($this->rules['*'])) ? $this->rules['*'] : [];
    }

    /**
     * Returns a fully constructed object based on $classname using $args and $share as constructor arguments
     *
     * @return object
     */
    public function create($classname, array $args = [], array $share = [])
    {
        if (!empty($this->instances[$classname])) {
            // we've already created a shared instance so return it to save the closure call.
            return $this->instances[$classname];
        }

        // so now, we either need a new instance or just don't have one stored
        // but if we have the closure stored that creates it, call that
        if (!empty($this->cache[$classname])) {
            return $this->cache[$classname]($args, $share);
        }

        $rule = $this->getRule($classname);
        // Reflect the class for inspection, this should only ever be done once per class and then be cached
        $class = new \ReflectionClass(isset($rule['instanceOf']) ? $rule['instanceOf'] : $classname);
        $closure = $this->getClosure($classname, $rule, $class);

        // When $rule['call'] is set, wrap the closure in another closure which calls the required methods after constructing the object.
        // By putting this in a closure, the loop is never executed unless call is actually set.
        if (isset($rule['call'])) {
            $closure = function (array $args, array $share) use ($closure, $class, $rule) {
                // Construct the object using the original closure
                $object = $closure($args, $share);

                foreach ($rule['call'] as $call) {
                    // Generate the method arguments using getParams() and call the returned closure
                    // (in php7 it will be ()() rather than __invoke)
                    $shareRule = ['shareInstances' => isset($rule['shareInstances']) ? $rule['shareInstances'] : []];
                    $callMeMaybe = isset($call[1]) ? $call[1] : [];
                    call_user_func_array(
                        [$object, $call[0]],
                        $this->getParams($class->getMethod($call[0]), $shareRule)
                            ->__invoke($this->expand($callMeMaybe))
                    );
                }

                return $object;
            };
        }

        $this->cache[$classname] = $closure;

        return $this->cache[$classname]($args, $share);
    }

    /**
     * Returns a closure for creating object $name based on $rule, caching the reflection object for later use.
     */
    private function getClosure($name, array $rule, \ReflectionClass $class)
    {
        $constructor = $class->getConstructor();
        // Create parameter-generating closure in order to cache reflection on the parameters.
        // This way $reflectmethod->getParameters() only ever gets called once.
        $params = ($constructor) ? $this->getParams($constructor, $rule) : null;

        // Get a closure based on the type of object being created: shared, normal, or constructorless
        if (isset($rule['shared']) && $rule['shared'] === true) {
            return function (array $args, array $share) use ($name, $class, $constructor, $params) {
                if ($constructor) {
                    try {
                        // Shared instance: create without calling constructor (and write to \$name and $name, see issue #68)
                        $this->instances[$name] = $class->newInstanceWithoutConstructor();
                        // Now call constructor after constructing all dependencies. Avoids problems with cyclic references (issue #7)
                        $constructor->invokeArgs($this->instances[$name], $params($args, $share));
                    } catch (\ReflectionException $e) {
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
            // This class has depenencies, call the $params closure to generate them based on $args and $share
            return function (array $args, array $share) use ($class, $params) {
                return $class->newInstanceArgs($params($args, $share));
            };
        }

        return function () use ($class) {
            // No constructor arguments, just instantiate the class
            return new $class->name();
        };
    }

    /**
     * Returns a closure that generates arguments for $method based on $rule and any $args passed into the closure
     */
    private function getParams(\ReflectionMethod $method, array $rule)
    {
        $paramInfo = []; // Caches some information about the parameter so (slow) reflection isn't needed every time
        foreach ($method->getParameters() as $param) {
            // get the class hint of each param, if there is one
            $class = ($class = $param->getClass()) ? $class->name : null;
            // determine if the param can be null, if we need to substitute a
            // different class, or if we need to force a new instance for it
            $paramInfo[] = [
                $class,
                $param,
                isset($rule['substitutions']) && \array_key_exists($class, $rule['substitutions']),
            ];
        }

        // Return a closure that uses the cached information to generate the arguments for the method
        return function (array $args, array $share = []) use ($paramInfo, $rule) {
            // If there are shared instances, create them and merge them with shared instances higher up the object graph
            if (isset($rule['shareInstances'])) {
                $share = \array_merge(
                    $share,
                    \array_map([$this, 'create'], $rule['shareInstances'])
                );
            }

            // Now merge all the possible parameters: user-defined in the rule via constructParams,
            // shared instances, and the $args argument from $dice->create()
            if (!empty($share) || isset($rule['constructParams'])) {
                $args = \array_merge(
                    $args,
                    (isset($rule['constructParams'])) ? $this->expand($rule['constructParams'], $share) : [],
                    $share
                );
            }

            $parameters = [];

            // Now find a value for each method parameter
            foreach ($paramInfo as $pi) {
                list($class, $param, $sub) = $pi;

                // First, loop through $args and see if each value can match the current parameter based on type hint
                if (!empty($args)) { // This if statement actually gives a ~10% speed increase when $args isn't set
                    foreach ($args as $i => $arg) {
                        if ($class !== null
                            && ($arg instanceof $class || ($arg === null && $param->allowsNull()))
                        ) {
                            // The argument matches, store and remove from $args so it won't wrongly match another parameter
                            $parameters[] = \array_splice($args, $i, 1)[0];
                            continue 2; //Move on to the next parameter
                        }
                    }
                }

                // When nothing from $args matches but a class is type hinted, create an instance to use, using a substitution if set
                if ($class !== null) {
                    $parameters[] = ($sub)
                        ? $this->expand($rule['substitutions'][$class], $share, true)
                        : $this->create($class, [], $share);
                    continue;
                }

                // There is no type hint, so take the next available value from $args (and remove from $args to stop it being reused)
                if (!empty($args)) {
                    $parameters[] = $this->expand(\array_shift($args));
                    continue;
                }

                // There's no type hint and nothing left in $args, so provide the default value or null
                $parameters[] = ($param->isDefaultValueAvailable()) ? $param->getDefaultValue() : null;
            }

            //variadic functions will only have one argument. To account for those, append any remaining arguments to the list
			return array_merge($parameters, $args);
        };
    }

    /**
     * Looks for 'instance' array keys in $param, and when found, returns an object based on the value.
     * See https://r.je/dice.html#example3-1
     */
    private function expand($param, array $share = [], $createFromString = false)
    {
        if (!\is_array($param)) {
            // doesn't need any processing
            return (is_string($param) && $createFromString) ? $this->create($param) : $param;
        }

        if (!isset($param['instance'])) {
            // not a lazy instance, so recursively search for any 'instance' keys on deeper levels
            foreach ($param as &$value) {
                $value = $this->expand($value, $share);
            }

            return $param;
        }

        // for ['instance' => ['className', 'methodName'] construct the instance before calling it
        if (is_array($param['instance'])) {
            $param['instance'][0] = $this->expand($param['instance'][0], $share, true);
        }

        if (\is_callable($param['instance'])) {
            // it's a lazy instance formed by a function. Call or return the value stored under the key 'instance'
            if (isset($param['params'])) {
                return \call_user_func_array($param['instance'], $this->expand($param['params']));
            }

            return \call_user_func($param['instance']);
        }

        // it's a lazy instance's class name string
        return $this->create($param['instance'], $share);
    }

    /**
     *
     */
    private static function normalizeName($name)
    {
        return \strtolower(self::normalizeNamespace($name));
    }

    /**
     *
     */
    private static function normalizeNamespace($name)
    {
        return \ltrim($name, '\\');
    }
}
