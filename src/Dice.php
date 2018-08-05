<?php

/**
 * @description Dice - A minimal Dependency Injection Container for PHP
 *
 * @author      Tom Butler tom@r.je
 * @author      Garrett Whitehorn http://garrettw.net/
 * @copyright   2012-2018 Tom Butler <tom@r.je> | https://r.je/dice.html
 * @license     http://www.opensource.org/licenses/bsd-license.php  BSD License
 *
 * @version     3.0
 */

namespace Dice;

class Dice
{
    const CONSTANT = 'Dice::CONSTANT';
    const INSTANCE = 'Dice::INSTANCE';

    /**
     * @var array $rules Rules which have been set using addRule()
     */
    private $rules = [];

    /**
     * @var array $cache A cache of closures based on class name so each class is only reflected once
     */
    private $cache = [];

    /**
     * @var array $instances Stores any instances marked as 'shared' so create() can return the same instance
     */
    private $instances = [];

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
     *
     * The container can be fully configured using rules provided by associative arrays.
     * See {@link https://r.je/dice.html#example3} for a description of the rules.
     *
     * @param string $classname The name of the class to add the rule for
     * @param array $rule The rule to add to it
     */
    public function addRule($classname, $rule)
    {
        if (isset($rule['instanceOf'])
            && \is_string($rule['instanceOf'])
            && (!\array_key_exists('inherit', $rule) || $rule['inherit'] === true)
        ) {
            $rule = \array_replace_recursive($this->getRule($rule['instanceOf']), $rule);
        }

        // Allow substitutions rules to be defined with a leading a slash
        if (isset($rule['substitutions'])) {
            foreach ($rule['substitutions'] as $key => $value) {
                $rule[ltrim($key,  '\\')] = $value;
            }
        }
        $this->rules[self::normalizeName($classname)] = \array_replace_recursive($this->getRule($classname), $rule);
    }

    /**
     * Add rules as array. Useful for JSON loading
     * $dice->addRules(json_decode(file_get_contents('foo.json'));
     *
     * @param array Rules in a single array [name => $rule] format
     */
    public function addRules(array $rules)
    {
        foreach ($rules as $name => $rule) {
            $this->addRule($name, $rule);
        }
    }

    /**
     * Returns the rule that will be applied to the class $matching during create().
     *
     * @param string $name The name of the ruleset to get - can be a class or not
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
                && (!array_key_exists('inherit', $rule) || $rule['inherit'] === true) // and it applies to subclasses
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
     * @param string $classname The name of the class to instantiate
     * @param array $args An array with any additional arguments to be passed into the constructor
     * @param array $share Whether the same class instance should be passed around each time
     * @return object A fully constructed object based on the specified input arguments
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
        $constructor = $class->getConstructor();
        // Create parameter-generating closure in order to cache reflection on the parameters.
        // This way $reflectmethod->getParameters() only ever gets called once.
        $params = ($constructor) ? $this->getParams($constructor, $rule) : null;

        $closure = $this->getClosure($class, $params);

        // Get a closure based on the type of object being created: shared, normal, or constructorless
        if (isset($rule['shared']) && $rule['shared'] === true) {
            $closure = function(array $args, array $share) use ($classname, $class, $constructor, $params, $closure) {
                if ($class->isInternal() === true) {
                    $this->instances[$classname] = $closure($args, $share);
                } else if ($constructor) {
                    try {
                        // Shared instance: create without calling constructor (and write to \$classname and $classname, see issue #68)
                        $this->instances[$classname] = $class->newInstanceWithoutConstructor();
                        // Now call constructor after constructing all dependencies. Avoids problems with cyclic references (issue #7)
                        $constructor->invokeArgs($this->instances[$classname], $params($args, $share));
                    } catch (\ReflectionException $e) {
                        $this->instances[$classname] = $class->newInstanceArgs($params($args, $share));
                    }
                } else {
                    $this->instances[$classname] = $class->newInstanceWithoutConstructor();
                }

                $this->instances[self::normalizeNamespace($classname)] = $this->instances[$classname];

                return $this->instances[$classname];
            };
        }

        //If there are shared instances, create them and merge them with shared instances higher up the object graph
        if (isset($rule['shareInstances'])) {
            $closure = function(array $args, array $share) use ($closure, $rule) {
                foreach ($rule['shareInstances'] as $instance) {
                    $share[] = $this->create($instance, [], $share);
                }
                return $closure($args, $share);
            };
        }

        // When $rule['call'] is set, wrap the closure in another closure which calls the required methods after constructing the object.
        // By putting this in a closure, the loop is never executed unless call is actually set.
        if (isset($rule['call'])) {
            $closure = function(array $args, array $share) use ($closure, $class, $rule) {
                // Construct the object using the original closure
                $object = $closure($args, $share);

                foreach ($rule['call'] as $call) {
                    // Generate the method arguments using getParams() and call the returned closure
                    // (in php7 it will be ()() rather than __invoke)
                    $shareRule = ['shareInstances' => isset($rule['shareInstances']) ? $rule['shareInstances'] : []];
                    $callMeMaybe = isset($call[1]) ? $call[1] : [];
                    $return = call_user_func_array(
                        [$object, $call[0]],
                        $this->getParams($class->getMethod($call[0]), $shareRule)
                            ->__invoke($this->expand($callMeMaybe))
                    );

                    if (isset($call[2]) && is_callable($call[2])) {
                        call_user_func($call[2], $return);
                    }
                }

                return $object;
            };
        }

        $this->cache[$classname] = $closure;

        return $this->cache[$classname]($args, $share);
    }

    /**
     * Returns a closure for creating object, caching the reflection object for later use.
     *
     * The container can be fully configured using rules provided by associative arrays.
     * See {@link https://r.je/dice.html#example3} for a description of the rules.
     *
     * @param \ReflectionClass $class The reflection object used to inspect the class
     * @param \Closure|null $params The output of getParams()
     * @return \Closure A closure that will create the appropriate object when called
     */
    private function getClosure(\ReflectionClass $class, $params)
    {
        // PHP throws a fatal error rather than an exception when trying to
        // instantiate an interface - detect it and throw an exception instead
        if ($class->isInterface()) {
            return function() {
                throw new \InvalidArgumentException('Cannot instantiate interface');
            };
        }

        if ($params) {
            // This class has dependencies, call the $params closure to generate them based on $args and $share
            return function(array $args, array $share) use ($class, $params) {
                return $class->newInstanceArgs($params($args, $share));
            };
        }

        return function() use ($class) {
            // No constructor arguments, just instantiate the class
            return new $class->name();
        };
    }

    /**
     * Returns a closure that generates arguments for $method based on $rule and any $args passed into the closure
     *
     * @param \ReflectionMethod $method A reflection of the method to inspect
     * @param array $rule The ruleset to use in interpreting what the params should be
     * @return \Closure A closure that uses the cached information to generate the method's arguments
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
        $php56 = \method_exists('ReflectionParameter', 'isVariadic'); // marginally faster than checking PHP_VERSION

        // Return a closure that uses the cached information to generate the arguments for the method
        return function(array $args, array $share = []) use ($paramInfo, $rule, $php56) {
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
                // This if statement actually gives a ~10% speed increase when $args isn't set
                if (!empty($args)) {
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
                    try {
                        $parameters[] = ($sub)
                            ? $this->expand($rule['substitutions'][$class], $share, true)
                            : $this->create($class, [], $share);
                    } catch (\InvalidArgumentException $e) {
                        // Squash this exception
                    }
                    continue;
                }

                // Variadic functions will only have one argument. To account for those, append any remaining arguments to the list
                if ($php56 && $param->isVariadic()) {
                    $parameters = array_merge($parameters, $args);
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

            return ($php56) ? $parameters : array_merge($parameters, $args);
        };
    }

    /**
     * Looks for Dice::INSTANCE or Dice::CONSTANT array keys in $param, and when found, returns an object based on the value.
     * See {@link https:// r.je/dice.html#example3-1}
     *
     * @param string|array $param
     * @param array $share Array of instances from 'shareInstances', required for calls to `create`
     * @param bool $createFromString
     * @return mixed
     */
    private function expand($param, array $share = [], $createFromString = false)
    {
        if (!\is_array($param)) {
            // doesn't need any processing
            return (is_string($param) && $createFromString) ? $this->create($param) : $param;
        }

        if (isset($param[self::CONSTANT])) {
            return constant($param[self::CONSTANT]);
        }

        if (!isset($param[self::INSTANCE])) {
            // not a lazy instance, so recursively search for any self::INSTANCE keys on deeper levels
            foreach ($param as $name => $value) {
                $param[$name] = $this->expand($value, $share);
            }

            return $param;
        }

        // Check for 'params' which allows parameters to be sent to the instance when it's created
        // Either as a callback method or to the constructor of the instance
        $args = isset($param['params']) ? $this->expand($param['params']) : [];

        // for [self::INSTANCE => ['className', 'methodName'] construct the instance before calling it
        if (\is_array($param[self::INSTANCE])) {
            $param[self::INSTANCE][0] = $this->expand($param[self::INSTANCE][0], $share, true);
        }

        if (\is_callable($param[self::INSTANCE])) {
            // it's a lazy instance formed by a function. Call or return the value stored under the key self::INSTANCE
            if (isset($param['params'])) {
                return \call_user_func_array($param[self::INSTANCE], $args);
            }

            return \call_user_func($param[self::INSTANCE]);
        }

        if (\is_string($param[self::INSTANCE])) {
            // it's a lazy instance's class name string
            return $this->create($param[self::INSTANCE], \array_merge($args, $share));
        }
        // if it's not a string, it's malformed. *shrug*
    }

    /**
     * @param string $name
     */
    private static function normalizeName($name)
    {
        return \strtolower(self::normalizeNamespace($name));
    }

    /**
     * @param string $name
     */
    private static function normalizeNamespace($name)
    {
        return \ltrim($name, '\\');
    }
}
