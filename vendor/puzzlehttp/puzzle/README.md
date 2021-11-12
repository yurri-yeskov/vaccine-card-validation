# puzzlehttp/puzzle [![Build Status](https://secure.travis-ci.org/puzzlehttp/puzzle.png)](http://travis-ci.org/puzzlehttp/puzzle)

Fork of [guzzle/guzzle](https://github.com/guzzle/guzzle) compatible with PHP 5.2+.

### Motivation

`guzzle/guzzle` is a mature and advanced HTTP client library, but it's only compatible with PHP 5.4+. Unfortunately,
**only 25% of all servers are running PHP 5.4+** ([source](http://w3techs.com/technologies/details/pl-php/5/all)).
It would be a shame to exempt this library from most of the world's servers just because of a few version incompatibilities.

Once PHP 5.4+ adoption levels near closer to 100%, this library will be retired.

### Differences from [guzzle/guzzle](https://github.com/guzzle/guzzle)

The primary difference is naming conventions of the Guzzle classes.
Instead of the `\GuzzleHttp\` namespace (and sub-namespaces), prefix the Guzzle class names
with `puzzle` and follow the [PEAR naming convention](http://pear.php.net/manual/en/standards.php).

A few examples of class naming conversions:

    \GuzzleHttp\Client                     ----->    puzzle_Client
    \GuzzleHttp\Stream\StreamInterface     ----->    puzzle_stream_StreamInterface
    \GuzzleHttp\Adapter\Curl\CurlFactory   ----->    puzzle_adapter_curl_CurlFactory

### Installation

This package can be installed easily using [Composer](http://getcomposer.org).
Simply add the following to the `composer.json` file at the root of your project:

```javascript

    {
      "require": {
        "puzzlehttp/puzzle": "~4.0"
      }
    }
```

Then install your dependencies using ``composer install``.

### Releases and Versioning

Releases are synchronized with the upstream Guzzle repository. e.g. `puzzlehttp/puzzle v4.0.1` has merged the code
from `guzzle/guzzle v4.0.1`.