# iszt api
Basic api connector for iszt.hu (official hungarian domain registry).

[![Latest Stable Version](https://poser.pugx.org/websupport/iszt-api/v/stable.png)](https://packagist.org/packages/websupport/iszt-api)

## Installation

**ISZT api** is composer library so you can install the latest version with:

```shell
php composer.phar require websupport/iszt-api
```

## Usage

```php
$api = new Websupport\Iszt\Connector('username', 'password');
var_dump($api->domainInfo('domain.hu'));
```

Few addiotional configuration options can passed to constructor as third (array) argument:

- **url** - address of api (see also class' constants `API_URL_LIVE` and `API_URL_TRYOUT`)
- **keyPath** - Location of stored gnupg keys
- **keyId** - Which gnupg key to use for signing request
- **passphrase** - Password for that key
- **proxy** - proxy url to use for connecting to API
- **proxyAuth** - proxy authentication data `"username:password"`
- **timeout** - default timeout for connection requests (defaults to 30 seconds)
- **nsServer** - NS server to use

```php
$api = new Websupport\Iszt\Connector('username', 'password', array(
	'url' => Websupport\Iszt\Connector::API_URL_TRYOUT,
	'nsServer' => 'ns1.websupport.sk',
));
```