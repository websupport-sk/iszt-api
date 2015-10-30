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
 * Base exception
 *
 * Wrapper above common PHP-platform's exception
 *
 * @category API
 * @package  Iszt
 * @author   Tomáš Tatarko <tomas.tatarko@websupport.sk>
 * @license  http://choosealicense.com/licenses/mit/ MIT
 * @link     https://github.com/websupport-sk/iszt-api Official repository
 */

class Exception extends \Exception
{
    public $domain;

    /**
     * Building instance of exception
     * @param string     $message  Exception message
     * @param string     $domain   Name of the domain that exception relates to
     * @param integer    $code     Exception error code
     * @param \Exception $previous Previous exception
     */
    public function __construct(
        $message,
        $domain = null,
        $code = 0,
        $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->domain = $domain;
    }
}
