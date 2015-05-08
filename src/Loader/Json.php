<?php
/* @description 		Dice - A minimal Dependency Injection Container for PHP
 * @author				Tom Butler tom@r.je
* @copyright			2012-2014 Tom Butler <tom@r.je>
* @link					http://r.je/dice.html
* @license				http://www.opensource.org/licenses/bsd-license.php  BSD License
* @version				1.1.2
*/
namespace Dice\Loader;

class Json
{
    public function load($json, \Dice\Dice $dice = null)
    {
        if ($dice === null):
            $dice = new \Dice\Dice;
        endif;

        if (!($map = json_decode($json))): // intentional assignment
            throw new \Exception('Could not decode json: ' . json_last_error_msg());
        endif;

        foreach ($map->rules as $value) {
            $rule = $dice->getRule($value->name);

            isset($value->shared) and $rule['shared'] = $value->shared;
            isset($value->inherit) and $rule['inherit'] = $value->inherit;
            isset($value->instanceOf) and $rule['instanceOf'] = $value->instanceOf;

            if (isset($value->newInstances)):
                foreach ($value->newInstances as $ni):
                    $rule['newInstances'][] =  $ni;
                endforeach;
            endif;

            foreach (['call', 'constructParams', 'shareInstances'] as $param):
                if (!isset($value->$param)) continue;

                foreach ($value->$param as $val):
                    $rule[$param][] = $this->getComponent($val);
                endforeach;
            endforeach;


            if (isset($value->substitutions)):
                foreach ($value->substitutions as $as => $use):
                    $rule['substitutions'][$as] = $this->getComponent($use);
                endforeach;
            endif;

            $dice->addRule($value->name, $rule);
        }
        return $dice;
    }

    private function getComponent($input)
    {
        if (is_array($input)):
            foreach ($input as &$value):
                $value = $this->getComponent($value);
            endforeach;
        endif;

        if (is_object($input)):
            if (isset($input->instance)):
                $input = ['instance' => $input->instance];

            elseif (isset($input->call)):
                $input = ['instance' => [new Callback($input->call), 'run']];
            endif;
        endif;

        return $input;
    }
}
