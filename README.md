
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

[![but why?](http://s.bandungfe.net/i/but-why.gif)](https://en.wikipedia.org/wiki/Category:PHP_frameworks)

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

## 3. Usage

### 3.0 Routing

Routing in `zapcore` is the responsibility of the method `Router::route`.
Here's a simple route with `/hello` path, a regular function as the
callback to handle the request data, applied to `PUT` request method.

```php
$core = new Router();

function my_callback($args) {
	global $core;
	$name = $args['put'];
	file_put_contents('name.txt', $name);
	die(sprintf("Hello, %s.", $name));
}

$core->route('/hello', 'my_callback', 'PUT');
```

which will produce:

```bash
$ curl -XPUT -d Johnny localhost:9999/hello
Hello, Johnny.
```

We can use multiple methods for the same path:

```php
$core = new Router();

function my_callback($args) {
	global $core;
	if ($core->get_request_method() == 'PUT') {
		$name = $args['put'];
	} else {
		if (!isset($args['post']['name']))
			die("Who are you?");
		$name = $args['post']['name'];
	}
	file_put_contents('name.txt', $name);
	die(sprintf("Hello, %s.", $name));
}

$core->route('/hello', 'my_callback', ['PUT', 'POST']);
```

Instead of using global, we can use closure for the
callback:

```php
function my_callback($args, $core) {
	if ($core->get_request_method() == 'PUT') {
		$name = $args['put'];
	} else {
		if (!isset($args['post']['name']))
			die("Who are you?");
		$name = $args['post']['name'];
	}
	file_put_contents('name.txt', $name);
	die(sprintf("Hello, %s.", $name));
}

$core = new Router();

$core->route('/hello', function($args) use($core) {
	my_callback($args, $core);
}, ['PUT', 'POST']);
```

Callback can be a method instead of function:

```php
$core = new Router();

class MyName {
	public function my_callback($args) {
		global $core;
		if ($core->get_request_method() == 'PUT') {
			$name = $args['put'];
		} else {
			if (!isset($args['post']['name']))
				die("Who are you?");
			$name = $args['post']['name'];
		}
		file_put_contents('name.txt', $name);
		die(sprintf("Hello, %s.", $name));
	}
}

$myname = new MyName();

$core->route('/hello', [$myname, 'my_callback'],
	['PUT', 'POST']);
```

And finally, you can subclass Router:

```php
class MyName extends Router {
	public function my_callback($args) {
		if ($this->get_request_method() == 'PUT') {
			$name = $args['put'];
		} else {
			if (!isset($args['post']['name']))
				die("Who are you?");
			$name = $args['post']['name'];
		}
		file_put_contents('name.txt', $name);
		die(sprintf("Hello, %s.", $name));
	}
	public function my_home($args) {
		if (!file_exists('name.txt'))
			die("Hello, stranger.");
		$name = file_get_contents('name.txt');
		die(sprintf("You're home, %s.", $name));
	}
}

$core = new MyName();
$core->route('/hello', [$core, 'my_callback'], ['PUT', 'POST']);
$core->route('/',      [$core, 'my_home']);
```

When request URI and request method do not match any route, a
default 404 error page will be sent unless you set `shutdown`
parameter to `false` in the constructor.

```bash
$ curl -si http://localhost/hello | head -n1
HTTP/1.1 404 Not Found
```

### 3.1 Dynamic Path

Apart from static path of the form `/path/to/some/where`, there
are also two types of construct which will make a dynamic path:

```php
class MyPath extends Router {
	public function my_short_param($args) {
		printf("Showing profile for user '%s'.\n",
			$args['params']['short_name']);
	}
	public function my_long_param($args) {
		printf("Showing revision 1 of file '%s'.\n",
			$args['params']['long_name']);
	}
}

$core = new MyPath();
// short parameter with '<>'
$core->route('/user/<short_name>/profile',  [$core, 'my_short_param']);
// long parameter with '{}'
$core->route('/file/{long_name}/v1',        [$core, 'my_long_param']);
```

which will produce:

```bash
$ curl localhost:9999/user/Johnny/profile
Showing profile for user 'Johnny'.
$ curl localhost:9999/file/in/the/cupboard/v1
Showing revision 1 of file 'in/the/cupboard'.
```


### 3.2 Request Headers

All request headers are available under `$args['header']`. This
includes custom headers:


```php
class MyToken extends MyName {
	public function my_token($args) {
		if (!isset($args['header']['my_token']))
			die("No token sent.");
		die(sprintf("Your token is '%s'.",
			$args['header']['my_token']));
	}
}

$core = new MyToken();
$core->route('/token', [$core, 'my_token']);
```

which will produce:

```bash
$ curl -H "My-Token: somerandomstring" localhost:9999/token
Your token is 'somerandomstring'.
```

**NOTE:** Custom request header keys will always be received in
lower case, with all '-' changed into '\_'.

### 3.3 Response Headers

You can send all kinds of response headers easily with the method
`Header::header` from the parent class:

```php
use BFITech\ZapCore\Header;
use BFITech\ZapCore\Router;

class MyName extends Router {
	public function my_response($args) {
		if (isset($args['get']['name']))
			Header::header(sprintf("X-Name: %s",
				$args['get']['name']));
	}
}

$core = new MyName();
$core->route('/response', [$core, 'my_response']);
```

which will produce:

```bash
$ curl -si localhost:9999/response?name=Johnny | grep -i name
X-Name: Johnny
```

For a more proper sequence of response headers, you can use
`Header::start_header` method:

```php
class MyName extends Router {
	public function my_response($args) {
		if (isset($args['get']['name']))
			Header::start_header(200);
		else
			Header::start_header(404);
	}
}

$core = new MyName();
$core->route('/response', [$core, 'my_response']);
```

which will produce:

```bash
$ curl -si localhost:9999/response?name=Johnny | head -n1
HTTP/1.1 200 OK
$ curl -si localhost:9999/response | head -n1
HTTP/1.1 404 Not Found
```

### 3.4 Special Responses

There are wrappers specifically-tailored for error pages, redirect and
static file serving:

```php
class MyFile extends Router {
	public function my_file($args) {
		if (!isset($args['get']['name']))
			// show a 403 immediately
			return $this->abort(403);
		$name = $args['get']['name'];
		if ($name == 'John')
			// redirect to another query string
			return $this->redirect('?name=Johnny');
		// a dummy file
		file_put_contents('Johnny.txt', "I'm Johnny.\n");
		// serve a static file, will call $this->abort(404)
		// internally if the file is not found
		$file_name = $name . '.txt';
		$this->static_file($file_name);
	}
}

$core = new MyFile();
$core->route('/file', [$core, 'my_file']);
```

which will produce:

```bash
$ curl -siL localhost:9999/file | grep HTTP
HTTP/1.1 403 Forbidden
$ curl -siL localhost:9999/file?name=Jack | grep HTTP
HTTP/1.1 404 Not Found
$ curl -siL localhost:9999/file?name=John | grep HTTP
HTTP/1.1 301 Moved Permanently
HTTP/1.1 200 OK
$ curl -L localhost:9999/file?name=Johnny
I'm Johnny.
```

## 4. Contribution

Found a bug? Help us fix it.

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
    $ phpunit
    $ x-www-browser docs/coverage/index.html
    ```

5. Push to your fork and submit a Pull Request.

## 5. Documentation

If you have [Doxygen](http://www.stack.nl/~dimitri/doxygen/) installed,
detailed generated documentation is available with:

```bash
$ doxygen
$ x-www-browser docs/html/index.html
```

