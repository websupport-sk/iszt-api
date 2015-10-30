<?php

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * PHP Version 5.3
 *
 * @category API
 * @package  Iszt
 * @author   Tomáš Tatarko <tomas.tatarko@websupport.sk>
 * @license  http://choosealicense.com/licenses/mit/ MIT
 * @link     https://github.com/websupport-sk/iszt-api Official repository
 */

namespace Websupport\Iszt;

/**
 * Base entity (model) class
 *
 * Abstract class for data models used in API connector
 *
 * @category API
 * @package  Iszt
 * @author   Tomáš Tatarko <tomas.tatarko@websupport.sk>
 * @license  http://choosealicense.com/licenses/mit/ MIT
 * @link     https://github.com/websupport-sk/iszt-api Official repository
 */
abstract class Entity extends \ArrayObject
{
    protected static $map = array();
    protected static $entityName;

    /**
     * Validates model's data
     * @return boolean
     */
    abstract public function validate();

    /**
     * Building model instance
     * @param array $data Model data
     */
    public function __construct(array $data = array()) 
    {
        $array = array_combine(
            static::$map,
            array_fill(0, count(static::$map), null)
        );
        foreach ($data as $k => $v) {
            if (key_exists($k, static::$map)) {
                $array[static::$map[$k]] = $v;
            } elseif (in_array($k, static::$map)) {
                $array[$k] = $v;
            }
        }

        parent::__construct(
            $array,
            \ArrayObject::STD_PROP_LIST | \ArrayObject::ARRAY_AS_PROPS
        );
    }

    /**
     * Export model data into XML
     * @return string
     */
    public function export() 
    {
        $response = sprintf('<%s>', static::$entityName);
        foreach (static::$map as $k => $v) {
            $response .= sprintf(
                '<%s>%s</%s>',
                $k,
                htmlspecialchars($this->offsetGet($v), ENT_QUOTES, 'UTF-8'),
                $k
            );
        }
        return $response . sprintf('</%s>', static::$entityName);
    }
}