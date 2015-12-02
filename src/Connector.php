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
 * Base connector class
 *
 * Base class used as a interface to building XML command for API
 *
 * @category API
 * @package  Iszt
 * @author   Tomáš Tatarko <tomas.tatarko@websupport.sk>
 * @license  http://choosealicense.com/licenses/mit/ MIT
 * @link     https://github.com/websupport-sk/iszt-api Official repository
 */
class Connector
{
    /**
     * Base domain state
     */
    const STATE_OK = 8;

    /**
     * State used for non-automatic domain prolong
     */
    const STATE_DEACTIVATED = 30;

    /**
     * State used for non-automatic domain prolong (with zone disfunctional)
     */
    const STATE_ZONE_DEACTIVATED = 31;

    /**
     * First state when domain is fully working but in conditional use
     */
    const STATE_CONDITIONAL_USE = 35;

    /**
     * Live environment URL
     */
    const API_URL_LIVE = 'https://huregxml.nic.hu/servlet/api';

    /**
     * Tryout environment URL
     */
    const API_URL_TRYOUT = 'https://huregx.nic.hu:444/servlet/api';

    /**
     * API's url to use
     * @var string
     */
    public $url = self::API_URL_LIVE;

    /**
     * Registrar's name for auth
     * @var string
     */
    public $name;

    /**
     * Registrar's pasword for auth
     * @var string
     */
    public $password;

    /**
     * Path to the gnupg keys
     * @var string
     */
    public $keyPath;

    /**
     * GNUPG key's ID to use
     * @var string
     */
    public $keyId;

    /**
     * Key's passphrase
     * @var string
     */
    public $passphrase;

    /**
     * Proxy URL (if proxy is needed)
     * @var string
     */
    public $proxy;

    /**
     * Proxy's name/password to use
     * @var string
     */
    public $proxyAuth;

    /**
     * Default timeout for request execution time
     * @var string
     */
    public $timeout = 30;

    /**
     * Default NS server to use
     * @var string
     */
    public $nsServer;

    /**
     * Instance of gnupg library
     * @var resource
     */
    private $_gpg;

    /**
     * CURL instance of connection to API
     * @var resource
     */
    private $_stream;
    
    /**
     * Local cache for domain meta data
     * @var array
     */
    protected $cache = array();

    /**
     * Building connection's instance
     * @param string $name     Registrar's name (for auth)
     * @param type   $password Registrar's password (for auth)
     * @param array  $options  Additional options (keys as class properties)
     */
    public function __construct($name, $password, array $options = array()) 
    {
        $this->name = $name;
        $this->password = $password;
        foreach ($options as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Builds gnupg library resource or return already created one
     * @return resouce
     * @throws InternalError
     */
    private function _getGpg() 
    {
        if (!$this->_gpg) {
            $this->_gpg = gnupg_init();

            if ($this->keyPath) {
                putenv('GNUPGHOME=' . $this->keyPath);
            }

            if (!gnupg_addsignkey(
                $this->_gpg,
                $this->keyId,
                $this->passphrase
            )) {
                throw new InternalError('Cannot find gpg key');
            }

            gnupg_setsignmode($this->_gpg, GNUPG_SIG_MODE_DETACH);
        }

        return $this->_gpg;
    }

    /**
     * Builds curl connection resource or return already created one
     * @return resource
     */
    private function _getConnection() 
    {
        $this->_stream = curl_init($this->url);
        curl_setopt($this->_stream, CURLOPT_USERAGENT, 'DRR PHP kliens');
        curl_setopt($this->_stream, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($this->_stream, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->_stream, CURLOPT_TIMEOUT, $this->timeout);
        if ($this->proxy) {
            curl_setopt($this->_stream, CURLOPT_PROXY, $this->proxy);
            curl_setopt($this->_stream, CURLOPT_FOLLOWLOCATION, 1);
            if ($this->proxyAuth) {
                curl_setopt(
                    $this->_stream,
                    CURLOPT_PROXYUSERPWD,
                    $this->proxyAuth
                );
            }
        }
        return $this->_stream;
    }

    /**
     * Common method for building and executing api commands
     * @param string  $name        Command name to execute
     * @param string  $data        Command data
     * @param string  $domainName  Domain name that command relates to
     * @param boolean $multiResult Multi-rows results are expected
     * @return array
     * @throws RequestError In case that connection fails or return unexpected
     *  data format
     * @throws ResponseError In case that response represents some error
     */
    protected function sendRequest(
        $name,
        $data,
        $domainName = null,
        $multiResult = false
    ) {
        $command = '<COMMAND TODO="' . $name . '"><ID>' . microtime(true)
        . '</ID><ATTRIBUTES>' . $data . '</ATTRIBUTES></COMMAND>' . PHP_EOL;
        $signature = gnupg_sign($this->_getGpg(), $command);
        $request = "<DAPI>"
        . "<USERNAME>{$this->name}</USERNAME>"
        . "<PASSWORD>{$this->password}</PASSWORD>"
        . "\n{$command}<SIGNATURE>{$signature}</SIGNATURE>\n</DAPI>\n";
        $connection = $this->_getConnection();

        curl_setopt(
            $connection, CURLOPT_POSTFIELDS, http_build_query(
                array('command' => $request)
            )
        );
        $response = curl_exec($connection);
        if (curl_errno($connection)) {
            throw new RequestError(
                curl_error($connection),
                $domainName,
                curl_errno($connection)
            );
        }

        $xml = simplexml_load_string(
            str_replace(
                array(
                '<?xml version="1.0" encoding="UTF-8"?>',
                '<!DOCTYPE DAPIR SYSTEM "https://hureg.nic.hu/reply.dtd">'
                ), '', $response
            )
        );
        if ($multiResult && isset($xml->COMMAND)) {
            return $xml->COMMAND;
        } elseif (isset($xml->COMMAND['STATUS'])
            && $xml->COMMAND['STATUS']->__toString() == 0
        ) {
            return (array)$xml->COMMAND->ATTRIBUTES;
        } elseif (isset($xml->COMMAND['STATUS'])) {
            throw new ResponseError(
                trim(
                    preg_replace(
                        '/\s+/',
                        ' ',
                        $xml->COMMAND->ATTRIBUTES->MESSAGE->text->__toString()
                    )
                ),
                $domainName, $xml->COMMAND['STATUS']->__toString()
            );
        }

        throw new RequestError('Unable to read xml response', $domainName);
    }

    /**
     * Verify common (boolean) response from API
     * @param string $name             Command name to execute
     * @param string $data             Command data
     * @param string $domainName       Domain name that command relates to
     * @param string $expectedResponse Default (expexted) response message
     * @return boolean
     */
    protected function verifyBasicResponse(
        $name,
        $data,
        $domainName = null,
        $expectedResponse = 'PGP aláírás helyes'
    ) {
        $response = $this->sendRequest($name, $data, $domainName);
        if (isset($response['MESSAGE']->text)
            && is_string($response['MESSAGE']->text)
            && $response['MESSAGE']->text == $expectedResponse
        ) {
            return true;
        }

        return isset($response['MESSAGE']->text)
        && trim($response['MESSAGE']->text->__toString()) == $expectedResponse;
    }

    /**
     * Validates domain name
     * @param string $domainName Domain name to validate
     * @throws InvalidArgument In case that domain name is not valid
     * @return boolean
     */
    protected function validateDomainName($domainName) 
    {
        if (!preg_match('/^[^.]+(\.co)?\.hu$/', $domainName)) {
            throw new InvalidArgument('Invalid domain name', $domainName);
        }
        
        return true;
    }

    /**
     * Register new domain by given contact IDs
     * @param string $domainName   Domain name to register
     * @param string $contactId    Domain onwer's contact ID
     * @param string $techContact  Domain technical contact ID
     * @param string $adminContact Domain admin contact ID
     * @param string $zoneContact  Domain zone contact ID
     * @return integer ID of the created domain
     * @throws InvalidArgument In case that domain is not free (able to register)
     * @throws ResponseError In case that domain can not be registered
     */
    public function registerNewDomainByContactId(
        $domainName,
        $contactId,
        $techContact,
        $adminContact,
        $zoneContact
    ) {
        if (!$this->checkDomainAvailability($domainName)) {
            throw new InvalidArgument('Domain is not free', $domainName);
        }

        $response = $this->sendRequest(
            'uj_domain', '<DOMAIN DSTATE="BEJEGYZES" RIGHT="NON_PRI"'
            . ' OWNER="nic-hdl" ADMIN_C="nic-hdl" TECH_C="nic-hdl"'
            . ' ZONE_C="nic-hdl" ELECTRONIC="yes"><DNAME>' . $domainName
            . '</DNAME><DNS>' . $this->nsServer . '</DNS><ORG><IDENT>'
            . $contactId . '</IDENT></ORG><PERSON ROLE="admin-c"><IDENT>'
            . $adminContact . '</IDENT></PERSON><PERSON ROLE="tech-c">'
            . '<IDENT>' . $techContact . '</IDENT></PERSON><PERSON'
            . ' ROLE="zone-c"><IDENT>' . $zoneContact . '</IDENT></PERSON>'
            . '</DOMAIN>',
            $domainName
        );

        if (isset($response['MESSAGE']->text)
            && preg_match('/(\d+)/', (string)$response['MESSAGE']->text, $match)
        ) {
            return $match[1];
        }

        throw new ResponseError(
            'Unable to create domain: ' . $response['MESSAGE']->text,
            $domainName
        );
    }

    /**
     * Renews domain (set to active state)
     * @param string $domainName         Domain name to register
     * @param string $registrarIdToCheck Registrar ID (checking that domain
     * belongs to us)
     * @return boolean
     * @throws RequestError In case that domain does not belongs to us
     */
    public function renewDomain($domainName, $registrarIdToCheck = null) 
    {
        if ($registrarIdToCheck
            && !$this->isDomainOurs($domainName, $registrarIdToCheck)
        ) {
            throw new RequestError(
                'Domain does not belongs to us', $domainName
            );
        }

        return $this->activateDomain($domainName);
    }

    /**
     * Puts domain state into another state
     * @param string  $domainName Domain name to manipulate with
     * @param integer $status     Domain state to set
     * @param boolean $cached     Use cached domain meta data?
     * @return boolean
     */
    public function changeDomainStatus($domainName, $status, $cached = true) 
    {
        if ($this->domainState($domainName, $cached) == $status) {
            return true;
        }

        $info = $this->domainInfo($domainName);
        return $this->verifyBasicResponse(
            'domain',
            '<OBJ><OBJID>' . $info['domain_hun_id'] . '</OBJID><VALUE>'
            . $status . '</VALUE></OBJ>',
            $domainName,
            'A státuszmódosítás sikeresen végrehatjásra került'
        );
    }

    /**
     * Put domain into base active state
     * @param string  $domainName Domain name to manipulate
     * @param boolean $cached     Use cached domain meta data?
     * @return boolean
     */
    public function activateDomain($domainName, $cached = true) 
    {
        return $this->changeDomainStatus($domainName, self::STATE_OK, $cached);
    }

    /**
     * Put domain into state without automatic prolonging
     * @param string  $domainName Domain name to manipulate
     * @param boolean $cached     Use cached domain meta data?
     * @return boolean
     */
    public function deactivateDomain($domainName, $cached = true) 
    {
        return $this->changeDomainStatus(
            $domainName,
            self::STATE_DEACTIVATED,
            $cached
        );
    }

    /**
     * Put domain into state where in does not exists in the zone no more
     * @param string  $domainName Domain name to manipulate
     * @param boolean $cached     Use cached domain meta data?
     * @return boolean
     */
    public function deactivateZoneDomain($domainName, $cached = true)
    {
        return $this->changeDomainStatus(
            $domainName,
            self::STATE_ZONE_DEACTIVATED,
            $cached
        );
    }

    /**
     * Requests domain transfer
     * @param string  $domainName  Domain name to manipulate
     * @param integer $registrarId Our registrar ID to check
     * @param string  $nsServer    NS server to set (with performing regcheck)
     * @param boolean $cached      Use cached domain meta data?
     * @return boolean
     * @throws RequestError
     */
    public function transferDomain(
        $domainName,
        $registrarId,
        $nsServer = null,
        $cached = true
    ) {
        if ($this->isDomainOurs($domainName, $registrarId, $cached)) {
            throw new RequestError(
                'Domain transfer is not posible - domain belongs to us',
                $domainName
            );
        }

        $info = $this->domainInfo($domainName);
        $request = '<OBJ><OBJID>' . $info['domain_hun_id'] . '</OBJID>'
        . '<ATTRNAME>domain_mnt_org_id</ATTRNAME><VALUE>' . $registrarId
        . '</VALUE></OBJ>';

        if ($nsServer) {
            $request .= '</ATTRIBUTES><ATTRIBUTES><OBJ><OBJID>'
            . $info['domain_hun_id'] . '</OBJID><ATTRNAME>domain_pri_ns'
            . '</ATTRNAME><VALUE>' . $nsServer . '</VALUE></OBJ>';
        }

        return $this->verifyBasicResponse(
            'objektum_attributum_modositas', $request, $domainName
        );
    }

    /**
     * Checks if domain belongs to given registrar ID
     * @param string  $domainName  Domain name to check
     * @param integer $registrarId Our registrar ID to check
     * @param boolean $cached      Use cached domain meta data?
     * @return type
     */
    public function isDomainOurs($domainName, $registrarId, $cached = true) 
    {
        $info = $this->domainInfo($domainName, $cached);
        return $info['domain_mnt_org_id'] == $registrarId;
    }

    /**
     * Gets domain current state
     * @param string  $domainName Domain name to check
     * @param boolean $cached     Use cached domain meta data?
     * @return type
     */
    public function domainState($domainName, $cached = true) 
    {
        $info = $this->domainInfo($domainName, $cached);
        return trim($info['domain_state_id']);
    }

    /**
     * Creates domain owner contact ID by given model
     * @param DomainOwnerContact $model Contact model
     * @return integer Create contact ID
     * @throws InvalidArgument If given model is not valid
     * @throws ResponseError In case that no contact ID is created
     */
    public function createOwnerConcactId(DomainOwnerContact $model) 
    {
        if (!$model->validate()) {
            throw new InvalidArgument('Invalid contact model');
        }

        $response = $this->sendRequest('tulajdonos_felvitel', $model->export());
        if (isset($response['MESSAGE']->text)
            && is_numeric($response['MESSAGE']->text->__toString())
        ) {
            return $response['MESSAGE']->text->__toString();
        }

        throw new ResponseError('Unable to create owner contact');
    }

    /**
     * Creates technical contact ID by given model
     * @param TechnicalContact $model Contact model
     * @return integer Create contact ID
     * @throws InvalidArgument If given model is not valid
     * @throws ResponseError In case that no contact ID is created
     */
    public function createTechnicalConcactId(TechnicalContact $model) 
    {
        if (!$model->validate()) {
            throw new InvalidArgument('Invalid contact model');
        }

        $response = $this->sendRequest('szemely_felvitel', $model->export());
        if (isset($response['MESSAGE']->text)
            && is_numeric($response['MESSAGE']->text->__toString())
        ) {
            return $response['MESSAGE']->text->__toString();
        }

        throw new ResponseError('Unable to create technical contact');
    }

    /**
     * Gets domain's common info
     * @param string  $domainName Domain name to look up
     * @param boolean $cached     Use cached domain meta data?
     * @return array
     * @throws DomainNotFound In case that domain does not exists
     * @throws ResponseError If unexpected result is fetched
     */
    public function domainInfo($domainName, $cached = true) 
    {
        if ($cached && key_exists($domainName, $this->cache)) {
            return $this->cache[$domainName];
        }

        $this->validateDomainName($domainName);
        $response = $this->sendRequest(
            'altalanos_kereses',
            '<OBJ><VALUE>' . $domainName . '</VALUE>'
            . '<ATTRNAME>domain_name</ATTRNAME>'
            . '<OPERATOR>=</OPERATOR></OBJ>',
            $domainName
        );

        if (!isset($response['DOMAIN'])) {
            if (isset($response['MESSAGE']->text)
                && trim(
                    (string)$response['MESSAGE']->text
                ) == 'PGP aláírás helyes'
            ) {
                throw new DomainNotFound('Domain does not exists', $domainName);
            }

            throw new ResponseError('Unable to get domain info', $domainName);
        }

        return $this->cache[$domainName] = (array)$response['DOMAIN'];
    }

    /**
     * Checks if domain is free
     * @param string  $domainName Domain name to look up
     * @param boolean $cached     Use cached domain meta data?
     * @return boolean
     */
    public function checkDomainAvailability($domainName, $cached = true) 
    {
        try {
            $this->domainInfo($domainName, $cached);
            return false;
        } catch(DomainNotFound $ex) {
            return true;
        }
    }

    /**
     * Calculates the date when domain will be expiring
     * @param string  $domainName Domain name to look up
     * @param boolean $cached     Use cached domain meta data?
     * @return integer Calculated timestamp
     * @throws RequestError In case that domain registration
     *  process is not completed
     */
    public function getExpirationTime($domainName, $cached = true) 
    {
        $data = $this->domainInfo($domainName, $cached);
        if (!key_exists('domain_reg_date', $data)) {
            throw new RequestError(
                'Domain has not been fully registered yet',
                $domainName
            );
        }

        $time = strtotime($data['domain_reg_date']);
        $year = 0;

        while ($time < time()) {
            $time = strtotime($year++ ? '+1year' : '+2year', $time);
        }

        return $time;
    }

    /**
     * Gets captcha code
     * @param string  $domainName Domain name to look up
     * @param boolean $cached     Use cached domain meta data?
     * @return array
     */
    public function getVerificationData($domainName, $cached = true) 
    {
        $info = $this->domainInfo($domainName, $cached);
        return $this->sendRequest(
            'nyilatkozat_keres',
            '<DECL_REQ OWNER="' . $info['domain_owner_org_id']
            . '"><OBJ><VALUE>' . $info['domain_hun_id']
            . '</VALUE></OBJ></DECL_REQ>',
            $domainName
        );
    }

    /**
     * Submit declaration confirmed by domain owner
     * @param integer $requestId Declaration request ID
     * @param string  $ipAddress Client's IP address
     * @param string  $captcha   Captcha code
     * @param string  $hash      Declaration md5 hash
     * @return boolean
     */
    public function sendVerificationData(
        $requestId,
        $ipAddress,
        $captcha,
        $hash
    ) {
        return $this->verifyBasicResponse(
            'nyilatkozat_bekuldes',
            sprintf(
                '<DECL_REPLY ID="%\'010d" IP="%s" CAPTCHA="%s" TIMESTAMP="%s"'
                . ' HASH="%s" COMMENT=""></DECL_REPLY>',
                $requestId,
                $ipAddress,
                htmlspecialchars($captcha, ENT_QUOTES, 'UTF-8'),
                date('Y-m-d H:i:s'),
                $hash
            )
        );
    }

    /**
     * Checks declaration confirmation
     * @param integer $requestId Declaration request ID
     * @param string  $ipAddress Client's IP address
     * @param string  $captcha   Captcha code
     * @param string  $hash      Declaration md5 hash
     * @return boolean
     */
    public function tryCaptchaCode(
        $requestId,
        $ipAddress,
        $captcha,
        $hash
    ) {
        return $this->verifyBasicResponse(
            'nyilatkozat_ellenorzes',
            sprintf(
                '<DECL_REPLY ID="%\'010d" IP="%s" CAPTCHA="%s" TIMESTAMP="%s"'
                . ' HASH="%s" COMMENT=""></DECL_REPLY>',
                $requestId,
                $ipAddress,
                htmlspecialchars($captcha, ENT_QUOTES, 'UTF-8'),
                date('Y-m-d H:i:s'),
                $hash
            )
        );
    }

    /**
     * Search for domains
     * @param array $params List of search conditions
     * @return array
     */
    public function searchDomains(array $params) 
    {
        $request = '';
        $domains = array();

        foreach ($params as $p => $v) {
            $request .= sprintf(
                '<OBJ><VALUE>%s</VALUE><ATTRNAME>%s</ATTRNAME>'
                . '<OPERATOR>=</OPERATOR></OBJ>',
                htmlspecialchars($v, ENT_QUOTES, 'UTF-8'),
                $p
            );
        }

        $response = $this->sendRequest(
            'altalanos_kereses',
            $request,
            null,
            true
        )->ATTRIBUTES;
        foreach ($response as $row) {
            if (isset($row->DOMAIN)) {
                $domainName = $row->DOMAIN->domain_name->__toString();
                $domains[] = $this->cache[$domainName] = (array)$row->DOMAIN;
            }
        }

        return $domains;
    }

    /**
     * Sets new NS records
     * @param string  $domainName Domain name to manipulate
     * @param string  $nsServer   IP address of NS server to set
     * @param boolean $cached     Use cached domain meta data?
     * @return boolean
     */
    public function setNsRecord($domainName, $nsServer, $cached = true) 
    {
        $info = $this->domainInfo($domainName, $cached);
        return $this->verifyBasicResponse(
            'objektum_attributum_modositas',
            '<OBJ><OBJID>' . $info['domain_hun_id'] . '</OBJID><ATTRNAME>'
            . 'domain_pri_ns</ATTRNAME><VALUE>' . $nsServer
            . '</VALUE></OBJ>',
            $domainName
        );
    }

    /**
     * Gets NS records
     * @param string  $domainName Domain name to look up
     * @param boolean $cached     Use cached domain meta data?
     * @return array
     */
    public function getNsRecords($domainName, $cached = true) 
    {
        $info = $this->domainInfo($domainName, $cached);
        $nsServers = '';
        $results = array();

        if (isset($info['domain_pri_ns']) && $info['domain_pri_ns']) {
            $nsServers .= $info['domain_pri_ns'];
        }

        if (isset($info['domain_sec_ns']) && $info['domain_sec_ns']) {
            $nsServers .= $info['domain_sec_ns'];
        }

        preg_match_all(
            '#([^|\[\]]+)(\[([\d\.]+)\])?#',
            $nsServers,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $results[$match[3]] = $match[1];
        }

        return $results;
    }

    /**
     * Uploads document for specific domain
     * @param string  $domainName Domain name to upload document for
     * @param type    $subject    Subject of the document entry
     * @param type    $filePath   Path to the existing file document
     * @param type    $text       Note for document
     * @param boolean $cached     Use cached domain meta data?
     * @return boolean
     */
    public function uploadDocument(
        $domainName,
        $subject,
        $filePath,
        $text = null,
        $cached = true
    ) {
        $info = $this->domainInfo($domainName, $cached);
        $parts = explode('.', $filePath);
        $this->verifyBasicResponse(
            'megjegyzes_felvitel', '<REMARK><OBJID>'
            . $info['domain_hun_id'] . '</OBJID><SUBJECT>'
            . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</SUBJECT>'
            . '<TEXT>' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
            . '</TEXT><FILE>' . base64_encode(file_get_contents($filePath))
            . '</FILE><FILETYPE>' . end($parts) . '</FILETYPE></REMARK>'
        );
    }
}
