# Tuenti PHP SDK for Applications

_version 1.0_

Tuenti provides a set of application programming interfaces for trusted partners. Since early 2013, Tuenti offers a new platform for applications, both for applications embedded on Tuenti, integrated into the core user experience, and external websites wanting to provide a richer experience for Tuenti users. The application platform consists of a set of dialogs and a web service following the REST architectural style, protected by the industry-standard [OAuth 2.0][OAuth2] protocol used for user authorization of applications. The PHP SDK for Applications provides a set of server-side functionality to facilitate use of platform API's from any context.

## Features

The PHP SDK provides the following functionality.

* Making requests to the REST API
* Completing the [OAuth 2.0][OAuth2] [Authorization Code Grant][OAuth2 Auth Code Grant] flow to obtain [resource owner credentials][OAuth2 Access Token] for including when making requests to the REST API
* Verifying the integrity and origin of the context data passed to embedded applications

## Requirements

The PHP SDK requires the following PHP extensions.

* [cURL][]
* [JSON][]

## Usage

In order to use the PHP SDK for Applications, the source code for the SDK must be downloaded from Github and included in your application. For convenience, all classes contained in the SDK are located in the file Tuenti.php. The main entrypoint to the SDK is the Tuenti class.

The following code snippet instantiates the SDK and configures it for use with your application.

```php
require_once('Tuenti.php');

$tuenti = new Tuenti(array(
  'restApiBaseUrl' => '...',
  'clientId' => 'YOUR_CLIENT_ID',
  'clientSecret' => 'YOUR_CLIENT_SECRET'
));
```

In order to complete the OAuth 2.0 Authorization Code Grant flow by exchanging the obtained authorization code for an access token, typical  applications will do the following.

```php
$tuenti->setAccessTokenFromAuthorizationResponse();
```

Once an access token has been obtained, invoking the REST API is done in the following way.


```php
if ($tuenti->hasAccessToken()) {
  $currentUser = $tuenti->api('/users/current')->get();
}
```

See the examples of [an application embedded on Tuenti](examples/embedded) and that of [an external website integrated with our platform](examples/external) to get started.

## Documentation

In addition to the developer documentation available to trusted partners, complete documentation is provided with the SDK source code.

The following command, to be run from the base directory of the distribution, generates documentation in HTML format of all classes included in the SDK under the `doc` folder.

```tcsh
phpdoc -d src -t doc
```

## Tests

The PHP SDK distribution includes a set of PHPUnit tests for ensuring its quality. A custom bootstrap is provided that must be run before the tests.

The following command, to be run from the base directory of the distribution, executes the full test suite and produces a code coverage report under the `coverage` folder.

```tcsh
phpunit --coverage-html coverage --stderr --bootstrap tests/bootstrap.php tests
```

## Authors

* Carlos Perez Arroyo
* Einar Pehrson
* Juan Jose Coello

## License

Tuenti PHP SDK for Applications is available under the Apache License, version 2.0. See [LICENSE](LICENSE) for more information.

[OAuth2]: http://tools.ietf.org/html/rfc6749 "RFC 6749: The OAuth 2.0 Authorization Framework"
[OAuth2 Auth Code Grant]: http://tools.ietf.org/html/rfc6749#section-4.1 "RFC 6749: The OAuth 2.0 Authorization Framework, 4.1. Authorization Code Grant"
[OAuth2 Access Token]: http://tools.ietf.org/html/rfc6749#section-1.4 "RFC 6749: The OAuth 2.0 Authorization Framework, 1.4. Access Token"
[cURL]: http://php.net/manual/en/book.curl.php "Client URL Library PHP Extension"
[JSON]: http://php.net/manual/en/book.json.php "JavaScript Object Notation PHP Extension"
