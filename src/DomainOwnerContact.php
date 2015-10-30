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
 * Domain owner - entity model
 *
 * Model used for domain owner contact data validation
 *
 * @category API
 * @package  Iszt
 * @author   Tomáš Tatarko <tomas.tatarko@websupport.sk>
 * @license  http://choosealicense.com/licenses/mit/ MIT
 * @link     https://github.com/websupport-sk/iszt-api Official repository
 * @property string $localName
 * @property string $englishName
 * @property string $identification
 * @property string $country
 * @property string $zip
 * @property string $city
 * @property string $street
 * @property string $streetNumber
 * @property string $email
 * @property string $phone
 * @property string $fax
 */
class DomainOwnerContact extends Entity
{
    protected static $entityName = 'ORG';
    protected static $map = array(
    'NAME_HUN' => 'localName',
    'NAME_ENG' => 'englishName',
    'IDENT' => 'identification',
    'COUNTRY' => 'country',
    'ZIPCODE' => 'zip',
    'CITY' => 'city',
    'STREET' => 'street',
    'STREET_NUMBER' => 'streetNumber',
    'E_MAIL' => 'email',
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