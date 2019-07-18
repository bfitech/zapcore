<?php declare(strict_types=1);


namespace BFITech\ZapCore;


class RouterError extends \Exception {
}

/**
 * Router class.
 *
 * @cond
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @endcond
 */
class Router extends Header {

	private $request_path = null;
	private $request_comp = [];

	private $home = null;
	private $host = null;
	private $auto_shutdown = true;

	private $request_initted = false;
	private $request_handled = false;

	private $request_method = null;
	private $method_collection = [];

	/** Logging service. */
	public static $logger = null;

	/**
	 * Constructor.
	 *
	 * To finetune properties, use Router->config().
	 *
	 * @param Logger $logger Logging service, an instance of Logger
	 *     class. Can also be set via Router->config().
	 */
	public function __construct(Logger $logger=null) {
		self::$logger = $logger ?? new Logger();
		self::$logger->debug('Router: started.');
	}

	/**
	 * Home configuration validation.
	 */
	private function config_home($home) {
		if (!is_string($home) || !$home || $home[0] != '/')
			return;
		$home = rtrim($home, '/') . '/';
		$this->home = $home;
	}

	/**
	 * Host configuration validation.
	 */
	private function config_host(string $host=null) {
		if (filter_var($host, FILTER_VALIDATE_URL,
				FILTER_FLAG_PATH_REQUIRED) === false)
			return;
		$host = rtrim($host, '/') . '/';
		$home = $this->home;
		if ($home == '/') {
			$this->host = $host;
			return;
		}
		# home must be at the end of host
		if (substr($host, -strlen($home)) != $home)
			return;
		$this->host = $host;
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
	final public function config(string $key, $val) {
		if ($this->request_initted)
			return $this;
		switch ($key) {
			case 'home':
				$this->config_home($val);
				return $this;
			case 'host':
				$this->config_host($val);
				return $this;
			case 'shutdown':
				$this->auto_shutdown = (bool)$val;
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
		if ($this->request_initted)
			return;
		$this->init_request();
		if ($this->auto_shutdown)
			register_shutdown_function([$this, 'shutdown']);
		$this->request_initted = true;
		return $this;
	}

	/**
	 * Autodetect home.
	 *
	 * Naive home detection. Works on standard mod_php, mod_fcgid,
	 * or Nginx PHP-FPM. Fails miserably when Alias directive or
	 * mod_proxy is involved, in which case, manual config should be
	 * used. Untested on lighty and other servers.
	 *
	 * @fixme This becomes untestable after SAPI detection, since all
	 *     tests run from CLI.
	 * @codeCoverageIgnore
	 */
	private function autodetect_home() {
		if ($this->home !== null)
			return;
		if (php_sapi_name() == 'cli')
			return $this->home = '/';
		$home = dirname($_SERVER['SCRIPT_NAME']);
		$home = !$home || $home[0] != '/'
			? '/' : rtrim($home, '/') . '/';
		$this->home = $home;
	}

	/**
	 * Autodetect host.
	 *
	 * Naive host detection. This relies on super-global $_SERVER
	 * variable which varies from one web server to another, and
	 * especially inaccurate when aliasing or reverse-proxying is
	 * involved, in which case, manual config should be used.
	 */
	private function autodetect_host() {
		if ($this->host !== null)
			return;
		$proto = isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS'])
			? 'https://' : 'http://';
		$host = isset($_SERVER['SERVER_NAME'])
			? $_SERVER['HTTP_HOST'] : 'localhost';
		$port = isset($_SERVER['SERVER_PORT'])
			? (int)$_SERVER['SERVER_PORT'] : null;
		// @codeCoverageIgnoreStart
		$port = $this->verify_port($port, $proto);
		if ($port && (strpos($host, ':') === false))
			$host .= ':' . $port;
		// @codeCoverageIgnoreEnd
		$host = str_replace([':80', ':443'], '', $host);
		$this->host = $proto . $host . $this->home;
	}

	/**
	 * Verify if port number is valid and not redundant with protocol.
	 *
	 * @codeCoverageIgnore
	 */
	private function verify_port(int $port=null, string $proto) {
		if (!$port)
			return $port;
		if ($port < 0 || $port > pow(2, 16))
			return null;
		if ($port == 80 && $proto == 'http')
			return null;
		if ($port == 443 && $proto == 'https')
			return null;
		return $port;
	}

	/**
	 * Initialize request processing.
	 *
	 * This sets home, host, and other request-related properties
	 * based on current request URI.
	 */
	private function init_request() {

		$this->autodetect_home();

		$this->autodetect_host();

		# initialize from request uri
		$url = $_SERVER['REQUEST_URI'] ?? '';

		# remove query string
		$rpath = parse_url($url)['path'];

		# remove home
		if ($rpath != '/')
			$rpath = substr($rpath, strlen($this->home) - 1);

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
	 * Verify route method.
	 *
	 * This validates route method, i.e. method parameter of
	 * Router::route, collects it into method collection for accurate
	 * abort code on shutdown, and finally matches it against current
	 * request method.
	 */
	private function verify_route_method($path_method) {
		$this->request_method = strtoupper(
			$_SERVER['REQUEST_METHOD'] ?? 'GET');

		# always allow HEAD
		$methods = is_array($path_method)
			? $methods = array_merge($path_method, ['HEAD'])
			: $methods = [$path_method, 'HEAD'];
		$methods = array_unique($methods);
		foreach ($methods as $method) {
			if (!in_array($method, $this->method_collection))
				$this->method_collection[] = $method;
		}
		if (!in_array($this->request_method, $methods))
			return false;
		return true;
	}

	/**
	 * Parse request path.
	 *
	 * This generates path parser from route path to use against
	 * request path.
	 *
	 * @param string $path Route path.
	 * @return array|bool False if current request URI doesn't match,
	 *     a dict otherwise. The dict will be assigned to
	 *     $args['params'] of router callback, which is empty in case
	 *     of non-compound route path.
	 */
	private function parse_request_path(string $path) {

		# route path and request path is the same
		if ($path == $this->request_path)
			return [];

		# generate parser
		$parser = $this->path_parser($path);
		if (!$parser[1])
			return false;

		# parse request
		$pattern = '!^' . $parser[0] . '$!';
		$matched = preg_match_all(
			$pattern, $this->request_path,
			$result, PREG_SET_ORDER);
		if (!$matched)
			return false;

		unset($result[0][0]);
		return array_combine($parser[1], $result[0]);
	}

	/**
	 * Execute callback of a matched route.
	 *
	 * @param callable $callback Route callback.
	 * @param array $args Route callback parameter.
	 * @param bool $is_raw If true, request body is not treated as
	 *     HTTP query. Applicable for POST only.
	 */
	private function execute_callback(
		callable $callback, array $args, bool $is_raw=null
	) {
		$method = strtolower($this->request_method);

		if (in_array($method, ['head', 'get', 'options']))
			return $this->finish_callback($callback, $args);

		if ($method == 'post') {
			$args['post'] = $is_raw ?
				file_get_contents("php://input") : $_POST;
			$args['files'] = $_FILES ?? [];
			return $this->finish_callback($callback, $args);
		}

		if (in_array($method, ['put', 'delete', 'patch'])) {
			$args[$method] = file_get_contents("php://input");
			return $this->finish_callback($callback, $args);
		}

		# TRACE, CONNECT, etc. is not supported, in case web server
		# hasn't disabled them
		self::$logger->warning(sprintf(
			"Router: %s not supported in '%s'.",
			$this->request_method, $this->request_path));
		$this->abort(405);

		# always return self for chaining even if aborted
		return $this;
	}

	/**
	 * Finish callback.
	 */
	private function finish_callback(
		callable $callback, array $args
	) {
		$this->request_handled = true;
		$this->wrap_callback($callback, $args);
		return $this;
	}

	/**
	 * Write to log and throw exception on error.
	 */
	private static function throw_error(string $msg) {
		self::$logger->error($msg);
		throw new RouterError($msg);
	}

	/**
	 * Path parser.
	 *
	 * This parses route path and returns arrays that will parse
	 * request path.
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
	 * @see Router::route() for usage.
	 *
	 * @if TRUE
	 * @SuppressWarnings(PHPMD.UnusedLocalVariable)
	 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
	 * @SuppressWarnings(PHPMD.NPathComplexity)
	 * @endif
	 */
	final public static function path_parser(string $path) {
		# path must start with slash
		if ($path[0] != '/')
			self::throw_error("Router: path invalid in '$path'.");

		# ignore trailing slash
		if ($path != '/')
			$path = rtrim($path, '/');

		# allowed characters in path
		$valid_chars = 'a-zA-Z0-9\_\.\-@%:';
		# param left delimiter
		$elf = '<\{';
		# param right delimiter
		$erg = '>\}';
		# param delimiter
		$delims = "\/${elf}${erg}";
		# non-delimiter
		$non_delims = "[^${delims}]";
		# valid param key
		$valid_key = "[a-z][a-z0-9\_]*";

		if (!preg_match("!^[${valid_chars}${delims}]+$!", $path)) {
			# invalid characters
			self::throw_error(
				"Router: invalid characters in path: '$path'.");
		}

		if (
			preg_match("!${non_delims}[${elf}]!", $path) ||
			preg_match("![${erg}]${non_delims}!", $path)
		)
			# invalid dynamic path pattern
			self::throw_error(
				"Router: dynamic path not well-formed: '$path'.");

		preg_match_all("!/([$elf][^$erg]+[$erg])!", $path, $tokens,
			PREG_OFFSET_CAPTURE);

		$keys = $symbols = [];
		foreach ($tokens[1] as $token) {

			$key = str_replace(['{','}','<','>'], '', $token[0]);
			if (!preg_match("!^${valid_key}\$!i", $key)) {
				# invalid param key
				self::throw_error(
					"Router: invalid param key: '$path'.");
			}

			$keys[] = $key;
			$replacement = $valid_chars;
			if (strpos($token[0], '{') !== false)
				$replacement .= '/';
			$replacement = '([' . $replacement . ']+)';
			$symbols[] = [$replacement, $token[1], strlen($token[0])];
		}

		if (count($keys) > count(array_unique($keys)))
			# never allow key reuse to prevent unexpected overrides
			self::throw_error("Router: param key reused: '$path'.");

		# construct regex pattern for all capturing keys
		$idx = 0;
		$pattern = '';
		while ($idx < strlen($path)) {
			$matched = false;
			foreach ($symbols as $symbol) {
				if ($idx < $symbol[1])
					continue;
				if ($idx == $symbol[1]) {
					$matched = true;
					$pattern .= $symbol[0];
					$idx++;
					$idx += $symbol[2] - 1;
				}
			}
			if (!$matched) {
				$pattern .= $path[$idx];
				$idx++;
			}
		}

		return [$pattern, $keys];
	}

	/**
	 * Callback wrapper.
	 *
	 * Override this for more decorator-like processing. Make sure
	 * the override always ends with halt().
	 *
	 * @param callable $callback Callback method.
	 * @param array $args HTTP variables collected by router.
	 */
	public function wrap_callback(callable $callback, array $args=[]) {
		self::$logger->debug(sprintf("Router: %s '%s'.",
			$this->request_method, $this->request_path));
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
		string $path, callable $callback, $method='GET',
		bool $is_raw=null
	) {

		# route always initializes
		$this->init();

		# check if request has been handled
		if ($this->request_handled)
			return $this;

		# verify route method
		if (!$this->verify_route_method($method))
			return $this;

		# match route path with request path
		if (false === $params = $this->parse_request_path($path))
			return $this;

		# we have a match at this point; initialize callback args
		$args = [
			'method' => $this->request_method,
			'params' => $params,
			'get' => $_GET,
			'post' => [],
			'files' => [],
			'put' => null,
			'delete' => null,
			'patch' => null,
			'cookie' => $_COOKIE,
			'header' => [],
		];
		# custom headers
		foreach ($_SERVER as $key => $val) {
			if (strpos($key, 'HTTP_') === 0) {
				$key = substr($key, 5, strlen($key));
				$key = strtolower($key);
				$args['header'][$key] = $val;
			}
		}

		# populate args and let the callback executes
		return $this->execute_callback($callback, $args, $is_raw);
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
			content='width=device-width, initial-scale=1.0,
				user-scalable=yes'>
		<title>$code $msg</title>
		<style>
			body {background-color: #eee; font-family: sans-serif;}
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
	final public function abort(int $code) {
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
	private function redirect_default(string $destination) {
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
			content='width=device-width, initial-scale=1.0,
				user-scalable=yes'>
		<title>$code $msg</title>
		<style>
			body {background-color: #eee; font-family: sans-serif;}
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
	final public function redirect(string $destination) {
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
	 *
	 * @SuppressWarnings(PHPMD.UnusedLocalVariable)
	 */
	private function static_file_default(string $path, array $kwargs) {
		extract(Common::extract_kwargs($kwargs, [
			'cache' => 0,
			'disposition' => null,
			'headers' => [],
			'reqheaders' => [],
			'noread' => false,
			'callback_notfound' => function() {
				return $this->abort(404);
			},
		]));
		static::send_file($path, $cache, $disposition, $headers,
			$reqheaders, $noread, $callback_notfound);
		static::halt();
	}

	/**
	 * Static file.
	 *
	 * Create method called static_file_custom() to customize this in
	 * a subclass.
	 *
	 * @param string $path Absolute path to file.
	 * @param array $kwargs Additional arguments, a dict with keys:
	 *     - cache: int Cache age, in seconds. Default: 0.
	 *     - disposition: string|true|null Content disposition. If true,
	 *         disposition is inferred from filename. Default: null.
	 *     - headers: array Additional response headers, e.g. X-Sendfile
	 *         header, default: [].
	 *     - reqheaders: array Request headers passed from router.
	 *     - noread: bool Don't read the file. Send headers only.
	 *     - callback_notfound: callable Callback when the file is
	 *         missing, defaults to Router::abort(404).
	 */
	final public function static_file(string $path, array $kwargs=[]) {
		self::$logger->info("Router: static: '$path'.");
		if (method_exists($this, 'static_file_custom'))
			return $this->static_file_custom($path, $kwargs);
		return $this->static_file_default($path, $kwargs);
	}

	/**
	 * Shutdown function.
	 *
	 * If no request is handled at this point, show a 501 or 404.
	 */
	final public function shutdown() {
		if ($this->request_handled)
			return;
		$code = 501;
		if (in_array($this->request_method, $this->method_collection))
			$code = 404;
		self::$logger->info(sprintf(
			"Router: shutdown %s in %s '%s'.",
			$code, $this->request_method, $this->request_path));
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
		return $this->host;
	}

	/**
	 * Show current request method.
	 */
	public function get_request_method() {
		return $this->request_method;
	}

	/**
	 * Show request path.
	 */
	public function get_request_path() {
		return $this->request_path;
	}

	/**
	 * Get request component.
	 *
	 * @param int $index Index of component array. Set to null
	 *     to return the whole array.
	 * @return mixed If no index is set, the whole component array is
	 *     returned. Otherwise, indexed element is returned or null if
	 *     index falls out of range.
	 */
	public function get_request_comp(int $index=null) {
		$comp = $this->request_comp;
		if ($index === null)
			return $comp;
		if (isset($comp[$index]))
			return $comp[$index];
		return null;
	}

}
