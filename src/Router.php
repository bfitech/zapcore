<?php


namespace BFITech\ZapCore;


/**
 * Router class.
 */
class Router extends Header {

	private $request_path = null;
	private $request_comp = [];
	private $request_routes = [];

	private $_home = null;
	private $_host = null;

	private $request_handled = false;

	private $method_collection = [];
	private $current_method = null;

	/** Logging service. */
	public static $logger = null;

	/**
	 * Constructor.
	 *
	 * @param string|null $home Override home path autodetection.
	 * @param string|null $host Override host path autodetection.
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

		$this->_home = $home;
		$this->_host = $host;
		$this->_request_parse();

		if ($shutdown)
			register_shutdown_function([$this, 'shutdown']);
	}

	/**
	 * Request parser.
	 */
	private function _request_parse() {

		if ($this->request_path)
			return;

		if ($this->_home === null) {
			$home = dirname($_SERVER['SCRIPT_NAME']);
			if ($home === '.')
				# happens on CLI
				$home = '/';
			if ($home != '/')
				$home = rtrim($home, '/'). '/';
			$this->_home = $home;
		}

		if ($this->_host === null) {
			$prot = isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS'])
				? 'https://' : 'http://';
			$host = isset($_SERVER['SERVER_NAME'])
				? $_SERVER['HTTP_HOST'] : 'localhost';
			$port = isset($_SERVER['SERVER_PORT'])
				? @(int)$_SERVER['SERVER_PORT'] : 80;
			if ($port < 0 || $port > 65535)
				$port = null;
			if ($port == 80)
				$port = null;
			if ($port == 443 && $prot == 'https')
				$port = null;
			if ($port && strpos($host, ':') === false)
				$host .= ':' . $port;
			$host = $prot . $host . $this->_home;
			$this->_host = $host;
		}

		# initialize from request uri
		$req = isset($_SERVER['REQUEST_URI'])
			? $_SERVER['REQUEST_URI'] : '';
		# remove query string
		$req = preg_replace("!\?.+$!", '', $req);
		# remove script name
		$home = "/^" . str_replace("/", "\\/", quotemeta($this->_home)) . "/";
		$req = preg_replace($home, '', $req);
		# trim slashes
		$req = trim($req, "/");

		# store in private variables
		$this->request_path = '/' . $req;
		$this->request_comp = explode('/', $req);
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
			self::$logger->error(
				sprintf("Router: path invalid: '%s'.", $path));
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
			self::$logger->error(sprintf(
				"Router: param keys reused: '%s'.", $path));
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

	protected function halt() {
		die();
	}

	/**
	 * Callback wrapper.
	 *
	 * Override this for more decorator-like processing. Make sure
	 * the override always ends with die().
	 */
	public function wrap_callback($callback, $args=[]) {
		self::$logger->info(sprintf("Router: %s '%s'.",
			$this->current_method, $this->request_path));
		$callback($args);
		$this->halt();
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
	 * @param bool $is_raw Accept raw data instead of parsed
	 *     HTTP query. Only applicable for POST method. Useful in,
	 *     e.g. JSON request body.
	 */
	final public function route(
		$path, $callback, $method='GET', $is_raw=false
	) {

		# request has been handled
		if ($this->request_handled)
			return;

		// verify callback

		if (!is_callable($callback)) {
			self::$logger->error(
				"Router: callback invalid in '$path'.");
			return;
		}

		// verify request method

		$request_method = isset($_SERVER['REQUEST_METHOD'])
			? $_SERVER['REQUEST_METHOD'] : 'GET';
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
			return;

		// verify path

		# path is empty
		if (!$path || $path[0] != '/')
			return;
		# ignore trailing slash
		if ($path != '/')
			$path = rtrim($path, '/');

		# init variables
		$args = [];
		$args['method'] = $request_method;
		$args['params'] = [];

		if ($path != $this->request_path) {
			$parser = $this->path_parser($path);
			if (!$parser[1])
				return;
			$pattern = '!^' . $parser[0] . '$!';
			$matched = preg_match_all(
				$pattern, $this->request_path,
				$result, PREG_SET_ORDER);
			if (!$matched)
				return;
			unset($result[0][0]);
			$args['params'] = array_combine(
				$parser[1], $result[0]);
		}

		// collect method-path pair

		$method_path = strtolower($request_method) . ':' . $path;
		if (in_array($method_path, $this->request_routes))
			# process method-path pair only once
			return;
		$this->request_routes[] = $method_path;

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

		// populate HTTP variables

		if (in_array($request_method, ['HEAD', 'GET', 'OPTIONS'])) {
			# HEAD, GET, OPTIONS execute immediately
			$this->request_handled = true;
			$this->wrap_callback($callback, $args);
			return;
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
			return $this->abort(405);
		}

		// execute callback

		$this->request_handled = true;
		$this->wrap_callback($callback, $args);
	}

	/**
	 * Default abort method.
	 */
	private function abort_default($code) {
		extract(self::get_header_string($code));
		$this->send_header(0, 0, 0, $code);
		$html = <<<EOD
<!doctype html>
<html>
	<head>
		<meta charset='utf-8'/>
		<title>%s %s</title>
		<style type="text/css">
			body {background-color: #eee; font-family: sans;}
			div  {background-color: #fff; border: 1px solid #ddd;
				  padding: 25px; max-width:800px;
				  margin:20vh auto 0 auto; text-align:center;}
		</style>
	</head>
	<body>
		<div>
			<h1>%s %s</h1>
			<p>The URL <tt>&#039;<a href='%s'>%s</a>&#039;</tt>
			   caused an error.</p>
		</div>
	</body>
</html>
EOD;
		$uri = $_SERVER['REQUEST_URI'];
		printf($html, $code, $msg, $code, $msg, $uri, $uri);

		$this->halt();
	}

	/**
	 * Abort.
	 *
	 * Use $this->abort_custom() to customize in a subclass. Too
	 * many unguarded bells and whistles if we are to directly
	 * override this.
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
		$this->halt();
	}

	/**
	 * Default redirect.
	 */
	private function redirect_default($destination) {
		extract(self::get_header_string(301));
		$this->send_header(0, 0, 0, $code);
		@header("Location: $destination");
		$html = <<<EOD
<!doctype html>
<html>
	<head>
		<meta charset='utf-8'/>
		<title>%s %s</title>
		<style type="text/css">
			body {background-color: #eee; font-family: sans;}
			div  {background-color: #fff; border: 1px solid #ddd;
				  padding: 25px; max-width:800px;
				  margin:20vh auto 0 auto; text-align:center;}
		</style>
	</head>
	<body>
		<div>
			<h1>%s %s</h1>
			<p>See <tt>&#039;<a href='%s'>%s</a>&#039;</tt>.</p>
		</div>
	</body>
</html>
EOD;
		printf($html, $code, $msg, $code, $msg,
			$destination, $destination);
		$this->halt();
	}

	/**
	 * Redirect.
	 *
	 * Use $this->redirect_custom() to customize in a subclass.
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
		$this->halt();
	}

	/**
	 * Default static file.
	 */
	private function static_file_default(
		$path, $cache=0, $disposition=false
	) {
		if (file_exists($path))
			$this->send_header($path, $cache, 1, 200, $disposition);
		$this->abort(404);
	}

	/**
	 * Static file.
	 *
	 * Use $this->static_file_custom() to customize in a subclass.
	 *
	 * @param string $path Absolute path to file.
	 * @param bool|string $disposition Set content-disposition in header.
	 *     See $this->send_header().
	 */
	final public function static_file($path, $cache=0, $disposition=false) {
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
		return $this->_home;
	}

	/**
	 * Show host.
	 */
	public function get_host() {
		return $this->_host;
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

