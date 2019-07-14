
zapcore
=======

A very simple PHP router and other utilities.

[![Latest Stable Version](https://poser.pugx.org/bfitech/zapcore/v/stable)](https://packagist.org/packages/bfitech/zapcore)
[![Latest Unstable Version](https://poser.pugx.org/bfitech/zapcore/v/unstable)](https://packagist.org/packages/bfitech/zapcore)
[![Build Status](https://travis-ci.org/bfitech/zapcore.svg?branch=master)](https://travis-ci.org/bfitech/zapcore)
[![Code Coverage](https://codecov.io/gh/bfitech/zapcore/branch/master/graph/badge.svg)](https://codecov.io/gh/bfitech/zapcore)
[![GitHub license](https://img.shields.io/badge/license-MIT-blue.svg)](https://raw.githubusercontent.com/bfitech/zapcore/master/LICENSE)

----

**This is a backport branch for PHP 5, released as version &gt;= 1.4,
&lt; 2.0. If you're running PHP 7, please use the `master` branch.**

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
function my_callback($args) {
	$name = $args['put'];
	file_put_contents('name.txt', $name);
	die(sprintf("Hello, %s.", $name));
}

$core = new Router();

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

Instead of letting globals floating around, we can use closure and
inherited variable for the callback:

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
$ curl -si http://localhost:9999/hello | head -n1
HTTP/1.1 404 Not Found
```

### 3.1 Dynamic Path

Apart from static path of the form `/path/to/some/where`, there are
also two types of dynamic path built with enclosing pairs of symbols
`'<>'` and `'{}'` that will capture matching strings from request URI
and store them under `$args['params']`:

```php
class MyPath extends Router {
	public function my_short_param($args) {
		printf("Showing profile for user '%s'.\n",
			$args['params']['short']);
	}
	public function my_long_param($args) {
		printf("Showing version 1 of file '%s'.\n",
			$args['params']['long']);
	}
	public function my_compound_param($args) {
		extract($args['params']);
		printf("Showing revision %s of file '%s'.\n",
			$short, $long);
	}
}

$core = new MyPath();

// short parameter with '<>', no slash captured
$core->route('/user/<short>/profile', [$core, 'my_short_param']);

// long parameter with '{}', slashes captured
$core->route('/file/{long}/v1',       [$core, 'my_long_param']);

// short and long parameters combined
$core->route('/rev/{long}/v/<short>', [$core, 'my_compound_param']);
```

which will produce:

```bash
$ curl localhost:9999/user/Johnny/profile
Showing profile for user 'Johnny'.
$ curl localhost:9999/file/in/the/cupboard/v1
Showing version 1 of file 'in/the/cupboard'.
$ curl localhost:9999/rev/in/the/cupboard/v/3
Showing revision 3 of file 'in/the/cupboard'.
```

### 3.2 Request Headers

All request headers are available under `$args['header']`. These
include custom headers:


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

You can send all kinds of response headers easily with the static
method `Header::header` from the parent class:

```php
class MyName extends Router {
	public function my_response($args) {
		if (!isset($args['get']['name']))
			self::halt("Oh noe!");
		self:header(sprintf("X-Name: %s",
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
`Header::start_header` static method:

```php
class MyName extends Router {
	public function my_response($args) {
		if (isset($args['get']['name']))
			self::start_header(200);
		else
			self::start_header(404);
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
		if (!file_exists('Johnny.txt'))
			file_put_contents('Johnny.txt', "Here's Johnny.\n");
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

```txt
$ curl -siL localhost:9999/file | grep HTTP
HTTP/1.1 403 Forbidden
$ curl -siL localhost:9999/file?name=Jack | grep HTTP
HTTP/1.1 404 Not Found
$ curl -siL localhost:9999/file?name=John | grep HTTP
HTTP/1.1 301 Moved Permanently
HTTP/1.1 200 OK
$ curl -L localhost:9999/file?name=Johnny
Here's Johnny.
```

### 3.5 Advanced

`Router::config` is a special method to finetune the router behavior,
e.g.:

```php
$core = (new Router())
    ->config('shutdown', false)
    ->config('logger', new Logger());
```
Available configuration items are:

-   `home` and `host`

    `Router` attempts to infer your application root path from
    `$_SERVER['SCRIPT_NAME']` which is mostly accurate when you
    deploy your application via Apache `mod_php` with `mod_rewrite`
    enabled. This most likely fails when `$_SERVER['SCRIPT_NAME']` is
    no longer reliable, e.g. when you deploy your application
    under Apache `Alias` or Nginx `location` directives; or when you
    make it world-visible after a reverse-proxying. This is where
    `home` and `host` manual setup comes to the rescue.

    ```txt
    # your nginx configuration
    location @app {
            set             $app_dir /var/www/myapp;
            fastcgi_pass    unix:/var/run/php5-fpm.sock;
            fastcgi_index   index.php;
            fastcgi_buffers 256 4k;
            include         fastcgi_params;
            fastcgi_param   SCRIPT_FILENAME $app_dir/index.php;
            # an inaccurate setting of SCRIPT_NAME
            fastcgi_param   SCRIPT_NAME index.php;
    }
    location /app {
            try_files $uri @app;
    }
    ```

    ```php
    # your index.php
    $core = (new Router())
        ->config('home', '/app')
        ->config('host', 'https://example.org/app');

    // No matter where you put your app in the filesystem, it should
    // only be world-visible via https://example.org/app.
    ```

-   `shutdown`

    `zapcore` allows more than one `Router` instances in a single file.
    However, each instance executes a series of methods on shutdown if
    there is no matched route to ensure the routing doesn't end up in a
    blank page. In a multiple router situation, set `shutdown` config
    to false except for the last `Router` instance.

    ```php
    $core1 = new Router();
    $core1->config('shutdown', false);
    $core1->route('/page', ...);
    $core1->route('/post', ...);

    $core2 = new Router();
    $core2->route('/post', ...); # this route will never be executed,
                                 # see above
    $core2->route('/usr', ...);
    $core2->route('/usr/profile', ...);
    $core2->route('/usr/login', ...);
    $core2->route('/usr/logout', ...);

    // $core2 is the one responsible to internally call abort(404) at
    // the end of script execution when there's no matching route found.
    ```

-   `logger`

    All `zap*` packages use the same logging service provided by
    `Logger` class. By default, each `Router` instance has its own
    `Logger` instance, but you can share instance between `Router`s to
    avoid multiple log files.

    ```php
    $logger = new Logger(Logger::DEBUG, '/tmp/myapp.log');

    $core1 = (new Router())
        ->config('logger', $logger);

    $core2 = (new Router())
        ->config('logger', $logger);

    // Both $core1 and $core2 write to the same log file /tmp/myapp.log.
    ```

**NOTE**: All configuration items above are available as constructor
parameters, but they are deprecated in favor of using `Router::config`
method.

## 4. Contributing

See CONTRIBUTING.md.

## 5. Documentation

If you have [Doxygen](http://www.stack.nl/~dimitri/doxygen/) installed,
detailed generated documentation is available with:

```bash
$ doxygen
$ x-www-browser docs/html/index.html
```

