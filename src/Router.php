<?php


namespace BFITech\ZapCore;


/**
 * Router class.
 */
class Router extends Header {

	private $request_path = null;
	private $request_comp = [];

	private $home = null;
	private $host = null;
	private $auto_shutdown = true;

	private $request_parsed = false;
	private $request_handled = false;

	private $method_collection = [];
	private $current_method = null;

	/** Logging service. */
	public static $logger = null;

	/**
	 * Constructor.
	 *
	 * @param string|null $home Override home path autodetection if
	 *     it's a string.
	 * @param string|null $host Override host path autodetection if
	 *     it's a string.
	 * @param bool $shutdown Whether shutdown function should be
	 *     invoked at the end. Useful for multiple routers in one
	 *     project.
	 * @param Logger $logger Logging service, an instance of Logger
	 *     class.
	 */
	public function __construct(
		$home=null, $host=null, $shutdown=true, Logger $logger=null
	) {
		self::$logger = $logger ? $logger : new Logger();
		self::$logger->debug('Router: started.');
		$this->home = $home;
		$this->host = $host;
		$this->auto_shutdown = $shutdown;
	}

	/**
	 * Configure.
	 *
	 * If using constructor is too verbose or cumbersome, use this to
	 * finetune properties.
	 *
	 * @param string $key Configuration key.
	 * @param mixed $val Configuration value.
	 */
	final public function config($key, $val) {
		if ($this->request_parsed)
			return $this;
		switch ($key) {
			case 'home':
				$this->home = $val;
				return $this;
			case 'host':
				$this->host = $val;
				return $this;
			case 'shutdown':
				$this->auto_shutdown = $val;
				return $this;
			case 'logger':
				if ($val instanceof Logger) {
					self::$logger = $val;
					self::$logger->debug('Router: started.');
				}
				return $this;
		}
		self::$logger->warning(
			"Router: invalid configuration key: '$key'.");
		return $this;
	}

	/**
	 * Initialize parser and shutdown handler.
	 *
	 * Only manually call this in case you need to do something prior
	 * to calling $this->route() as that method will internally call
	 * this.
	 */
	final public function init() {
		if ($this->request_parsed)
			return;
		$this->request_parse();
		if ($this->auto_shutdown)
			register_shutdown_function([$this, 'shutdown']);
		$this->request_parsed = true;
		return $this;
	}

	/**
	 * Reset properties to default values.
	 *
	 * Mostly useful for testing, so that you don't have to repeatedly
	 * instantiate the object, especially when constructor parameters
	 * are considerably verbose.
	 */
	final public function deinit() {
		$this->request_path = null;
		$this->request_comp = [];

		$this->request_parsed = false;
		$this->request_handled = false;

		$this->method_collection = [];
		$this->current_method = null;

		return $this;
	}

	/**
	 * Request parser.
	 */
	private function request_parse() {

		if ($this->request_path)
			return;

		if ($this->home === null) {
			// @warning When run on CLI and script name becomes path to
			// executing script, use $this->home to override
			$home = dirname($_SERVER['SCRIPT_NAME']);
			if ($home === '.')
				# sometimes happens on CLI
				$home = '/';
			if ($home != '/')
				$home = rtrim($home, '/'). '/';
			$this->home = $home;
		}

		if ($this->host === null) {
			$proto = isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS'])
				? 'https://' : 'http://';
			$host = isset($_SERVER['SERVER_NAME'])
				? $_SERVER['HTTP_HOST'] : 'localhost';
			$port = isset($_SERVER['SERVER_PORT'])
				? @(int)$_SERVER['SERVER_PORT'] : 80;
			if ($port < 0 || $port > 65535)
				$port = null;
			if ($port == 80)
				$port = null;
			if ($port == 443 && $proto == 'https')
				$port = null;
			if ($port && strpos($host, ':') === false)
				$host .= ':' . $port;
			$host = $proto . $host . $this->home;
			$this->host = $host;
		}

		# initialize from request uri
		$url = isset($_SERVER['REQUEST_URI'])
			? $_SERVER['REQUEST_URI'] : '';

		# remove query string
		$rpath = parse_url($url)['path'];

		# remove script name, won't take effect when run from CLI
		# and script name is /usr/local/bin/php or the like
		if ($this->home != '/' && strpos($rpath, $this->home) === 0)
			$rpath = substr($rpath, strlen($this->home));

		# trim slashes
		$rpath = trim($rpath, "/");

		# store in private properties
		$this->request_path = '/' . $rpath;
		$this->request_comp = explode('/', $rpath);
		self::$logger->debug(sprintf(
			"Router: request path: '%s'.",
			$this->request_path));
	}

	/**
	 * Path parser.
	 *
	 * This parses path and returns arrays that will parse
	 * requests.
	 *
	 * @param string $path Route path with special enclosing
	 *     characters:
	 *     - `< >` for dynamic URL parameter without `/`
	 *     - `{ }` for dynamic URL parameter with `/`
	 * @return array A duple with values:
	 *     - a regular expression to match against request path
	 *     - an array containing keys that will be used to create
	 *       dynamic variables with whatever matches the previous
	 *       regex
	 * @see $this->route() for usage.
	 */
	final public static function path_parser($path) {
		$valid_chars = 'a-zA-Z0-9\_\.\-@%';

		$valid_chardelims = $valid_chars . '\/<>\{\}';
		if (!preg_match('!^[' . $valid_chardelims . ']+$!', $path)) {
			# never allow invalid characters
			self::$logger->error("Router: path invalid: '$path'.");
			return [[], []];
		}

		preg_match_all(
			'!(' .
				'<[a-zA-Z][a-zA-Z0-9\_]*>' .
			'|' .
				'\{[a-zA-Z][a-zA-Z0-9\_/]*\}' .
			')!',
			$path, $tokens, PREG_OFFSET_CAPTURE);

		$keys = [];
		$symbols = [];
		foreach ($tokens[0] as $t) {
			$keys[] = str_replace(['{','}','<','>'], '', $t[0]);
			$replacement = $valid_chars;
			if (strpos($t[0], '{') !== false)
				$replacement .= '/';
			$replacement = '([' . $replacement . ']+)';
			$symbols[] = [$replacement, $t[1], strlen($t[0])];
		}
		if (count($keys) > count(array_unique($keys))) {
			# never allow key reuse to prevent unexpected overrides
			self::$logger->error("Router: param keys reused: '$path'.");
			return [[], []];
		}

		$pattern = '';
		$n = 0;
		while ($n < strlen($path)) {
			$matched = false;
			foreach ($symbols as $s) {
				if ($n < $s[1]) {
					continue;
				}
				if ($n == $s[1]) {
					$matched = true;
					$pattern .= $s[0];
					$n++;
					$n += $s[2] - 1;
				}
			}
			if (!$matched) {
				$pattern .= $path[$n];
				$n++;
			}
		}
		return [$pattern, $keys];
	}

	/**
	 * Callback wrapper.
	 *
	 * Override this for more decorator-like processing. Make sure
	 * the override always ends with halt().
	 */
	public function wrap_callback($callback, $args=[]) {
		self::$logger->info(sprintf("Router: %s '%s'.",
			$this->current_method, $this->request_path));
		$callback($args);
		static::halt();
	}

	/**
	 * Route.
	 *
	 * @param string $path The path, not including the leading
	 *     path provided by e.g. script name.
	 * @param callable $callback Callback function for the path.
	 *     Callback takes one argument containing HTTP variables
	 *     collected by route processor.
	 * @param string $method HTTP request method.
	 * @param bool $is_raw If true, accept raw data instead of parsed
	 *     HTTP query. Only applicable for POST method. Useful in,
	 *     e.g. JSON request body.
	 * @return object|mixed Router instance for easier chaining.
	 */
	final public function route(
		string $path, $callback, $method='GET', $is_raw=null
	) {

		# check if parser has been initialized
		$this->init();

		# request has been handled
		if ($this->request_handled)
			return $this;

		// verify path

		if ($path[0] != '/') {
			self::$logger->error(
				"Router: path invalid in '$path'.");
			return $this;
		}
		if ($path != '/')
			# ignore trailing slash
			$path = rtrim($path, '/');

		// verify callback

		if (!is_callable($callback)) {
			self::$logger->error(
				"Router: callback invalid in '$path'.");
			return $this;
		}

		// verify request method

		$request_method = (
			isset($_SERVER['REQUEST_METHOD']) &&
			!empty($_SERVER['REQUEST_METHOD'])
		) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
		$this->current_method = $request_method;

		# always allow HEAD
		if (!is_array($method)) {
			$methods = [$method, 'HEAD'];
		} else {
			$methods = array_merge($method, ['HEAD']);
		}
		$methods = array_unique($methods);
		# keep methods in collection for later deciding whether
		# it's 404 or 501 on shutdown function
		foreach ($methods as $m) {
			if (!in_array($m, $this->method_collection))
				$this->method_collection[] = $m;
		}
		if (!in_array($request_method, $methods))
			return $this;

		// initialize router callback arguments

		$args = [];
		$args['method'] = $request_method;
		$args['params'] = [];

		if ($path != $this->request_path) {
			$parser = $this->path_parser($path);
			if (!$parser[1])
				return $this;
			$pattern = '!^' . $parser[0] . '$!';
			$matched = preg_match_all(
				$pattern, $this->request_path,
				$result, PREG_SET_ORDER);
			if (!$matched)
				return $this;
			unset($result[0][0]);
			$args['params'] = array_combine(
				$parser[1], $result[0]);
		}

		# we have a match at this point

		// initialize HTTP variables

		$args['get'] = $_GET;
		$args['post'] = [];
		$args['files'] = [];
		$args['put'] = null;
		$args['delete'] = null;
		$args['patch'] = null;
		$args['cookie'] = $_COOKIE;
		$args['header'] = [];

		// populate custom headers

		foreach ($_SERVER as $key => $val) {
			if (strpos($key, 'HTTP_') === 0) {
				$key = substr($key, 5, strlen($key));
				$key = strtolower($key);
				$args['header'][$key] = $val;
			}
		}

		// collect router callback arguments from HTTP variables

		if (in_array($request_method, ['HEAD', 'GET', 'OPTIONS'])) {
			# HEAD, GET, OPTIONS execute immediately
			$this->request_handled = true;
			$this->wrap_callback($callback, $args);
			return $this;
		}
		if ($request_method == 'POST') {
			# POST, FILES
			$args['post'] = $is_raw ?
				file_get_contents("php://input") : $_POST;
			if (isset($_FILES) && !empty($_FILES))
				$args['files'] = $_FILES;
		} elseif (in_array($request_method, [
			'PUT', 'DELETE', 'PATCH'
		])) {
			$args[strtolower($request_method)] = file_get_contents(
				"php://input");
		} else {
			# TRACE, CONNECT, etc. In case webserver haven't disabled them.
			self::$logger->warning(sprintf(
				"Router: %s not supported in '%s'.",
				$request_method, $this->request_path));
			$this->abort(405);
			# abort() can be patched so it doesn't always internally
			# call die(). Let's keep the return value consistent.
			return $this;
		}

		// execute callback

		$this->request_handled = true;
		$this->wrap_callback($callback, $args);

		return $this;
	}

	/**
	 * Default abort method.
	 */
	private function abort_default($code) {
		extract(self::get_header_string($code));
		static::start_header($code);
		$uri = htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES);
		echo "<!doctype html>
<html>
	<head>
		<meta charset=utf-8>
		<meta name=viewport
			content='width=device-width, initial-scale=1.0, user-scalable=yes'>
		<title>$code $msg</title>
		<style>
			body {background-color: #eee; font-family: sans;}
			div  {background-color: #fff; border: 1px solid #ddd;
				  padding: 25px; max-width:800px;
				  margin:20vh auto 0 auto; text-align:center;}
		</style>
	</head>
	<body>
		<div>
			<h1>$code $msg</h1>
			<p>The URL <tt>&#039;<a href='$uri'>$uri</a>&#039;</tt>
			   caused an error.</p>
		</div>
	</body>
</html>";
		static::halt();
	}

	/**
	 * Abort.
	 *
	 * Create a method called abort_custom() to customize this in a
	 * subclass. Too many unguarded bells and whistles are at risk
	 * if we just allow overriding this.
	 *
	 * @param int $code HTTP error code.
	 */
	final public function abort($code) {
		$this->request_handled = true;
		self::$logger->info(sprintf(
			"Router: abort %s: '%s'.",
			$code, $this->request_path));
		if (!method_exists($this, 'abort_custom'))
			$this->abort_default($code);
		else
			$this->abort_custom($code);
		static::halt();
	}

	/**
	 * Default redirect.
	 */
	private function redirect_default($destination) {
		extract(self::get_header_string(301));
		static::start_header($code, 0, [
			"Location: $destination",
		]);
		$dst = htmlspecialchars($destination, ENT_QUOTES);
		echo "<!doctype html>
<html>
	<head>
		<meta charset='utf-8'/>
		<meta name=viewport
			content='width=device-width, initial-scale=1.0, user-scalable=yes'>
		<title>$code $msg</title>
		<style>
			body {background-color: #eee; font-family: sans;}
			div  {background-color: #fff; border: 1px solid #ddd;
				  padding: 25px; max-width:800px;
				  margin:20vh auto 0 auto; text-align:center;}
		</style>
	</head>
	<body>
		<div>
			<h1>$code $msg</h1>
			<p>See <tt>&#039;<a href='$dst'>$dst</a>&#039;</tt>.</p>
		</div>
	</body>
</html>";
		static::halt();
	}

	/**
	 * Redirect.
	 *
	 * Create method called redirect_custom() to customize this in
	 * a subclass.
	 *
	 * @param string $destination Destination URL.
	 */
	final public function redirect($destination) {
		$this->request_handled = true;
		self::$logger->info(sprintf(
			"Router: redirect: '%s' -> '%s'.",
			$this->request_path, $destination));
		if (!method_exists($this, 'redirect_custom'))
			$this->redirect_default($destination);
		else
			$this->redirect_custom($destination);
		static::halt();
	}

	/**
	 * Default static file.
	 */
	private function static_file_default(
		$path, $cache=0, $disposition=null
	) {
		if (file_exists($path))
			static::send_file($path, $disposition, 200, $cache);
		$this->abort(404);
	}

	/**
	 * Static file.
	 *
	 * Create method called static_file_custom() to customize this in
	 * a subclass.
	 *
	 * @param string $path Absolute path to file.
	 * @param int $cache Cache age in seconds.
	 * @param string $disposition Set basename in a content-disposition
	 *     in header. If true, basename if inferred from path. If null,
	 *     no content-disposition header will be sent.
	 */
	final public function static_file(
		$path, $cache=0, $disposition=null
	) {
		self::$logger->info("Router: static: '$path'.");
		if (!method_exists($this, 'static_file_custom'))
			return $this->static_file_default($path, $cache, $disposition);
		return $this->static_file_custom($path, $cache, $disposition);
	}

	/**
	 * Shutdown function.
	 *
	 * If no request is handled at this point, show a 501.
	 */
	final public function shutdown() {
		if ($this->request_handled)
			return;
		$code = 501;
		if (in_array($this->current_method, $this->method_collection))
			$code = 404;
		self::$logger->warning(sprintf(
			"Router: shutdown %s in %s '%s'.",
			$code, $this->current_method, $this->request_path));
		$this->abort($code);
	}

	/* getters */

	/**
	 * Show home.
	 */
	public function get_home() {
		return $this->home;
	}

	/**
	 * Show host.
	 */
	public function get_host() {
		return str_replace([':443', ':80'], '', $this->host);
	}

	/**
	 * Show current request method.
	 */
	public function get_request_method() {
		return $this->current_method;
	}

	/**
	 * Show request path without leading slash.
	 */
	public function get_request_path() {
		return $this->request_path;
	}

	/**
	 * Get request component.
	 *
	 * @param int|null $index Index of component array. Set to null
	 *     to return the whole array.
	 * @return array|string|null If no index is set, the whole
	 *     component array is returned. Otherwise, indexed element
	 *     is returned or null if index falls out of range.
	 */
	public function get_request_comp($index=null) {
		$comp = $this->request_comp;
		if ($index === null)
			return $comp;
		if (isset($comp[$index]))
			return $comp[$index];
		return null;
	}

}

