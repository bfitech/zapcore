<?php declare(strict_types=1);


namespace BFITech\ZapCore;


/**
 * Router class.
 *
 * @cond
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @endcond
 */
class Router extends RouteDefault {

	private $request_path = null;

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
		$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
		$port = $_SERVER['SERVER_PORT'] ?? '80';
		$sport = in_array($port, ['80', '443']) ? '' : ":$port";
		$proto = ($_SERVER['HTTPS'] ?? false) ? 'https': 'http';
		$this->host = sprintf("%s://%s%s%s",
			$proto, $host, $sport, $this->home);
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
			? array_merge($path_method, ['HEAD'])
			: [$path_method, 'HEAD'];
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
	 * Execute callback of a matched route.
	 *
	 * @param callable $callback Route callback.
	 * @param array $args Route callback parameter.
	 * @param bool $is_raw If true, expect raw request body.
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
	 * Obtain request content type from headers.
	 *
	 * Request header keys must be in lower case.
	 *
	 * @param array $headers List of request headers.
	 * @return string MIME type if header is found, null otherwise.
	 */
	public static function get_request_mime(array $headers) {
		foreach ($headers as $key => $val) {
			if ($key !== 'content_type')
				continue;
			return strtolower($val);
		}
		return null;
	}

	/**
	 * Obtain array from JSON-encoded request body.
	 *
	 * To be used from within router callback method. In case of POST
	 * method, $is_raw parameter of Router::route must be set to true.
	 * Appropriate request content type must be set by client.
	 *
	 * @param array $args Callback parameters.
	 * @return array Decoded JSON body or empty array on failure.
	 */
	public static function get_json(array $args) {
		if (!isset($args['header']))
			return [];
		$mime = static::get_request_mime($args['header']);
		if (strpos((string)$mime, 'application/json') !== 0)
			return [];
		if (!isset($args['method']))
			return [];
		$method = strtolower($args['method']);
		$body = $args[$method] ?? null;
		if (!$body || !is_string($body))
			return [];
		$json = Config::djson($body);
		return $json ?? [];
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
	 *     collected by route processor with keys:
	 *     - `string` **method**: request method
	 *     - `array` **params**: route parameters
	 *     - `array` **get**: query, not necesssarily empty even though
	 *       request method is not GET
	 *     - `array|string` **post**: post request body, see $is_raw
	 *       below
	 *     - `array` **files**: files coming from an upload
	 *     - `string` **put**: put request body
	 *     - `string` **patch**: patch request body
	 *     - `string` **delete**: delete request body, should not be
	 *       used as client is expected to never send this
	 *     - `array` **cookie**: cookie
	 *     - `array` **header**: request headers, with keys always in
	 *       lower case separated by underscore, e.g. 'content_type'
	 *       instead of 'content-type' or 'Content-Type'.
	 * @param string|array $method One or more HTTP request methods.
	 * @param bool $is_raw If true, request body is expected to have
	 *     content type other than `multipart/form-data` or
	 *     `application/x-www-form-urlencoded` which are
	 *     internally pre-processed by PHP. For POST only.
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

		# match route path with request path; load $params while at it
		$params = Parser::match($path, $this->request_path);
		if ($params === false)
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
	 * Redirect.
	 *
	 * All redirects are never cached. Create a method called
	 * redirect_custom() to customize this in a subclass.
	 *
	 * @param string $destination Destination URL.
	 * @param int $code Status code. Use this to change status code
	 *     to, e.g. 307 for temporary redirect. Do not change to other
	 *     range of status codes or the redirection will break.
	 */
	final public function redirect(string $destination, int $code=301) {
		$this->request_handled = true;
		self::$logger->info(sprintf(
			"Router: redirect: '%s' -> '%s'.",
			$this->request_path, $destination));
		if (!method_exists($this, 'redirect_custom'))
			$this->redirect_default($destination, $code);
		else
			$this->redirect_custom($destination, $code);
		static::halt();
	}

	/**
	 * Static file.
	 *
	 * Create method called static_file_custom() to customize this in
	 * a subclass. Otherwise, RouteDefault::static_file_default is used.
	 *
	 * @param string $path Absolute path to file.
	 * @param array $kwargs Additional arguments, a dict with keys:
	 *     - `int` **cache**: Cache age, in seconds. Default: `0`.
	 *     - `string|true|null` **disposition**: Content disposition. If
	 *       true, disposition is inferred from filename. Default:
	 *       `null`.
	 *     - `array` **headers**: Additional response headers, e.g.
	 *       `X-Sendfile`. Default: `[]`.
	 *     - `array` **reqheaders**: Request headers passed from router.
	 *       Default: `[]`.
	 *     - `bool` **noread**: Don't read the file, send headers only
	 *       if true. Default: `false`.
	 *     - `callable` **callback_notfound**: Callback when the file is
	 *       missing, defaults to `Router::abort(404)`.
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

}
