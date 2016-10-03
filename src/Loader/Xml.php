<?php

/* @description 		Dice - A minimal Dependency Injection Container for PHP
 * @author				Tom Butler tom@r.je
* @copyright			2012-2014 Tom Butler <tom@r.je>
* @link					http://r.je/dice.html
* @license				http://www.opensource.org/licenses/bsd-license.php  BSD License
* @version				2.0
*/

namespace Dice\Loader;

class Xml
{
    public function load($xml, \Dice\Dice $dice = null)
    {
        if ($dice === null) {
            $dice = new \Dice\Dice();
        }

        if (!($xml instanceof \SimpleXmlElement)) {
            $xml = simplexml_load_file($xml);
        }

        $ns = $xml->getNamespaces();

        if (isset($ns['']) && $ns[''] == 'https://r.je/dice/2.0') {
            return $this->loadV2($xml, $dice);
        }

        return $this->loadV1($xml, $dice);
    }

    private function doConstructParams(\SimpleXmlElement $value, $rule)
    {
        foreach ($value->constructParams->children() as $child) {
            $rule['constructParams'][] = $this->getComponent($child);
        }
        return $rule;
    }

    private function getComponent(\SimpleXmlElement $element, $forceInstance = false)
    {
        if ($forceInstance) {
            return ['instance' => (string) $element];
        }

        if ($element->instance) {
            return ['instance' => (string) $element->instance];
        }

        return (string) $element;
    }

    private function loadV1(\SimpleXmlElement $xml, \Dice\Dice $dice)
    {
        foreach ($xml as $key => $value) {
            $rule = $dice->getRule((string) $value->name);

            if (isset($value->shared)) {
                $rule['shared'] = ((string) $value->shared === 'true');
            }

            if (isset($value->inherit)) {
                $rule['inherit'] = (bool) $value->inherit;
            }

            if ($value->call) {
                foreach ($value->call as $name => $call) {
                    $callArgs = [];
                    if ($call->params) {
                        foreach ($call->params->children() as $key => $param) {
                            $callArgs[] = $this->getComponent($param);
                        }
                    }
                    $rule['call'][] = [(string) $call->method, $callArgs];
                }
            }

            if ($value->instanceOf) {
                $rule['instanceOf'] = (string) $value->instanceOf;
            }

            if ($value->newInstances) {
                foreach ($value->newInstances as $ni) {
                    $rule['newInstances'][] = (string) $ni;
                }
            }

            if ($value->substitutions) {
                foreach ($value->substitutions as $use) {
                    $rule['substitutions'][(string) $use->as] = $this->getComponent($use->use, true);
                }
            }

            if ($value->constructParams) {
                $rule = $this->doConstructParams($value, $rule);
            }

            if ($value->shareInstances) {
                foreach ($value->shareInstances->children() as $share) {
                    $rule['shareInstances'][] = $this->getComponent($share);
                }
            }

            $dice->addRule((string) $value->name, $rule);
        }

        return $dice;
    }

    private function loadV2(\SimpleXmlElement $xml, \Dice\Dice $dice)
    {
        foreach ($xml as $key => $value) {
            $rule = $dice->getRule((string) $value->name);

            if ($value->call) {
                foreach ($value->call as $name => $call) {
                    $callArgs = [];
                    foreach ($call->children() as $key => $param) {
                        $callArgs[] = $this->getComponent($param);
                    }
                    $rule['call'][] = [(string) $call['method'], $callArgs];
                }
            }

            if (isset($value['inherit'])) {
                $rule['inherit'] = (bool) $value['inherit'];
            }

            if ($value['instanceOf']) {
                $rule['instanceOf'] = (string) $value['instanceOf'];
            }

            if (isset($value['shared'])) {
                $rule['shared'] = ((string) $value['shared'] === 'true');
            }

            if ($value->constructParams) {
                $rule = $this->doConstructParams($value, $rule);
            }

            if ($value->substitute) {
                foreach ($value->substitute as $use) {
                    $rule['substitutions'][(string) $use['as']] = $this->getComponent($use['use'], true);
                }
            }

            if ($value->shareInstances) {
                foreach ($value->shareInstances->children() as $share) {
                    $rule['shareInstances'][] = $this->getComponent($share);
                }
            }

            $dice->addRule((string) $value['name'], $rule);
        }

        return $dice;
    }
}
