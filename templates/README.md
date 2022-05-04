# (Unofficial) eBay Selling Partner API client (PHP) - {description}

Code generated from eBay's Swagger file
using [allansun/openapi-code-generator](https://github.com/allansun/openapi-code-generator).

Generated code is well self-documented with proper PHPDoc annotations.

Please refer to [eBay's documentation](https://developer.ebay.com/develop/apis) for detailed API behaviour
explanation.

## Installation

```shell
composer require bricre/ebay-api-{package}
```

You will also need a [PSR-18 compatible client](https://www.php-fig.org/psr/psr-18/) see
[https://docs.php-http.org/en/latest/clients.html](https://docs.php-http.org/en/latest/clients.html)

So either use Guzzle (or any other PSR-18 compatible clients)

```shell
composer require php-http/guzzle7-adapter
```

## Versioning

This project matches eBay's API versioning.

Due to the [restriction of Composer](https://getcomposer.org/doc/articles/versions.md), version number like
'2021-01-01' will be changed to '2021.01.01'.

Should you found a matching version not being available. Please contact the author to generate against correct version.

## Usage

First you need to create a PSR-18. Then in your business logic you can call API operations directly.

You may also want to check out eBay's document
about [how to authorize your request](https://developer.ebay.com/api-docs/static/oauth-scopes.html).

```php
<?php
use Ebay\Sell\Account\Api\Program;
$httpClient = new \GuzzleHttp\Client([
    'base_uri' => 'https://api.ebay.com/sell/account/v1/',
    'headers'=>[
        'Authorization'=> 'Bearer <accessToken>'
    ]
]);
$api = new Program($httpClient);

$programs = $api->getOptedIns();

```

## Author

* [Allan Sun](https://github.com/allansun) - *Initial work*
