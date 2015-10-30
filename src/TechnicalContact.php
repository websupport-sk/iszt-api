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
 * Technical contact model
 *
 * Model used for technical contact data
 *
 * @category API
 * @package  Iszt
 * @author   Tomáš Tatarko <tomas.tatarko@websupport.sk>
 * @license  http://choosealicense.com/licenses/mit/ MIT
 * @link     https://github.com/websupport-sk/iszt-api Official repository
 * @property string $lastName
 * @property string $firstName
 * @property string $email
 * @property string $country
 * @property string $zip
 * @property string $city
 * @property string $street
 * @property string $streetNumber
 * @property string $phone
 * @property string $fax
 */
class TechnicalContact extends Entity
{
    protected static $entityName = 'PERSON';
    protected static $map = array(
    'LAST_NAME' => 'lastName',
    'FIRST_NAME' => 'firstName',
    'E_MAIL' => 'email',
    'COUNTRY' => 'country',
    'ZIPCODE' => 'zip',
    'CITY' => 'city',
    'STREET' => 'street',
    'STREET_NUMBER' => 'streetNumber',
    'PHONE' => 'phone',
    'FAX_NO' => 'fax',
    );

    /**
     * Validates model's data
     * @return boolean
     */
    public function validate() 
    {
        return true && filter_var($this->email, FILTER_VALIDATE_EMAIL);
    }
}