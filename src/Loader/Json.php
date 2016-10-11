<?php

/* @description Dice - A minimal Dependency Injection Container for PHP
 * @author      Tom Butler tom@r.je
 * @copyright   2012-2014 Tom Butler <tom@r.je>
 * @link        http://r.je/dice.html
 * @license     http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version     2.0
 */

namespace Dice\Loader;

class Json
{
    public function load($json, \Dice\Dice $dice = null)
    {
        if ($dice === null) {
            $dice = new \Dice\Dice();
        }

        if (is_array($json)) {
            foreach ($json as $file) {
                $dice = $this->load($file, $dice);
            }
            return $dice;
        }

        if (trim($json)[0] != '{') {
            $path = dirname(realpath($json));
            $json = str_replace('__DIR__', $path, file_get_contents($json));
        }

        $map = json_decode($json, true);
        if (!is_array($map)) {
            throw new \Exception('Could not decode json: ' . json_last_error_msg());
        }

        if (isset($map['rules'])) {
            foreach ($map['rules'] as $rule) {
                $name = $rule['name'];
                unset($rule['name']);
                $dice->addRule($name, $rule);
            }
            return $dice;
        }

        foreach ($map as $name => $rule) {
            $dice->addRule($name, $rule);
        }
        return $dice;
    }
}
