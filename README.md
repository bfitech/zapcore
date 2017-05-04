
zapcore
=======

A very simple PHP router and other utilities.

[![Latest Stable Version](https://poser.pugx.org/bfitech/zapcore/v/stable)](https://packagist.org/packages/bfitech/zapcore)
[![Latest Unstable Version](https://poser.pugx.org/bfitech/zapcore/v/unstable)](https://packagist.org/packages/bfitech/zapcore)
[![Build Status](https://travis-ci.org/bfitech/zapcore.svg?branch=master)](https://travis-ci.org/bfitech/zapcore)
[![Codecov](https://codecov.io/gh/bfitech/zapcore/branch/master/graph/badge.svg)](https://codecov.io/gh/bfitech/zapcore)
[![GitHub license](https://img.shields.io/badge/license-MIT-blue.svg)](https://raw.githubusercontent.com/bfitech/zapcore/master/LICENSE)

----

## 0. Reason

[![but why](http://gph.to/2pMcVKp)](https://en.wikipedia.org/wiki/Category:PHP_frameworks)

Yeah, why another framework?

These are a few (hopefully good enough) reasons:

0.  web service-oriented

    `zapcore` and in general `zap*` packages are geared towards HTTP
    RESTful APIs with very little emphasis on traditional HTML document
    serving. If you are building the back end of a single page web
    application, you'll feel immediately at home.

1.  performance

    `zapcore` performance is guaranteed to be much faster than any
    popular batteries-included monolithic frameworks, and is at least
    on par with other microframeworks. **TODO**: Benchmark result.

2.  idiosyncracy

    This is just a fancy way of saying, "Because that's the way we like
    it."

## 1. Installation

Install it from Packagist:

```bash
$ composer -vvv require bfitech/zapcore
```

## 2. Hello, World

Here's a bare-minimum `index.php` file:

```php
<?php

require __DIR__ . '/vendor/autoload.php';
use BFITech\ZapCore\Router;
(new Router())->route('/', function($args){
	echo "Hello, World!";
});
```
Run it with PHP builtin web server and see it from your default browser:

```bash
$ php -S 0.0.0.0:9999 &
$ x-www-browser http://localhost:9999
```

## 3. Contribute

0.  Fork from Github web interface.

1.  Clone your fork and load dependencies:

    ```bash
    $ git clone git@github.com:${YOUR_GITHUB_USERNAME}/zapcore.git
    $ cd zapcore
    $ composer -vvv install -no
    ```

2.  Make your changes. Sources are in `./src` and `./dev` directories.

    ```bash
    $ # do your thing, e.g.:
    $ vim src/Router.php
    ```

3.  Adjust the tests. Make sure there's no failure. Tests are in
    `./tests` directory.

    ```bash
    $ # adjust tests, e.g.:
    $ vim tests/RouterTest.php
    $ # run tests
    $ phpunit || echo 'Boo!'
    ```

4.  Make sure code coverage is at 100% or close. If you have
    [Xdebug](https://xdebug.org/) installed, coverage report is
    available with:

    ```bash
    $ x-www-browser docs/coverage/index.html
    ```

5. Submit a Pull Request.

## 4. Documentation

If you have [Doxygen](http://www.stack.nl/~dimitri/doxygen/) installed,
generated documentation is available with:

```bash
$ doxygen
$ x-www-browser docs/html/index.html
```

