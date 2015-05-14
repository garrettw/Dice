<?php
/* @description 		Dice - A minimal Dependency Injection Container for PHP
 * @author				Tom Butler tom@r.je
* @copyright			2012-2014 Tom Butler <tom@r.je>
* @link					http://r.je/dice.html
* @license				http://www.opensource.org/licenses/bsd-license.php  BSD License
* @version				2.0
*/
namespace Dice\Loader;

class Json
{
    public function load($json, \Dice\Dice $dice = null)
    {
        if ($dice === null):
            $dice = new \Dice\Dice;
        endif;

        if (!is_array($map = json_decode($json, true))): // intentional assignment
            throw new \Exception('Could not decode json: ' . json_last_error_msg());
        endif;

        foreach ($map['rules'] as $rule):
            $name = $rule['name'];
            unset($rule['name']);
            $dice->addRule($name, $rule);
        endforeach;

        return $dice;
    }
}
