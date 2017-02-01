<?php


namespace BFITech\ZapCore;


class Router extends Header {

	private $request_path = null;
	private $request_comp = [];
	private $request_routes = [];

	private $_home = null;
	private $_host = null;

	private $request_handled = false;

	private $method_collection = [];
	private $current_method = null;


	/**
	 * Constructor.
	 *
	 * @param string|null $home Override home path autodetection.
	 * @param string|null $host Override host path autodetection.
	 */
	public function __construct($home=null, $host=null) {
		$this->_home = $home;
		$this->_host = $host;
		$this->_request_parse();
		register_shutdown_function([$this, 'shutdown']);
	}

	/**
	 * Generic method 'overloading' caller.
	 *
	 * Without this, methods added to instance can't be called.
	 */
	public function __call($method, $args) {
		if (isset($this->$method)) {
			$fn = $this->$method;
			return call_user_func_array($fn, $args);
		}
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
			if ($port)
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
		$this->request_path = $req;
		$this->request_comp = explode('/', $req);
	}

	/**
	 * Path parser.
	 */
	private function _path_parse($valid=true, $params=[], $frac, $reqs) {

		// fraction and request run out
		if (!$frac && !$reqs)
			return [$valid, $params, $frac, $reqs];
		if (!isset($frac[0]) && !isset($reqs[0]))
			return [$valid, $params, $frac, $reqs];

		// either fraction or request runs out
		if (!isset($frac[0]) && isset($reqs[0]))
			return [false];
		if (isset($frac[0]) && !isset($reqs[0]))
			return [false];

		$key = $frac[0];
		if ($key[0] == '<' && $key[strlen($key)-1] == '>') {
			// parse <key> to $params
			$key = substr($key, 1, strlen($key) - 2);
			$params[$key] = $reqs[0];
		} else {
			// fraction and request don't match
			if ($key != $reqs[0])
				return [false];
		}

		// shift
		array_shift($frac);
		array_shift($reqs);

		// recurse
		return $this->_path_parse(true, $params, $frac, $reqs);
	}

	/**
	 * Callback wrapper.
	 *
	 * Override this for more decorator-like processing. Make sure
	 * the patch always ends with die().
	 */
	public function wrap_callback($callback, $args=[]) {
		$callback($args);
		die();
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
	 */
	public function route($path, $callback, $method='GET') {

		/* ******** */
		/* callback */
		/* ******** */

		if (is_string($callback)) {
			if (!function_exists($callback))
				return $this->abort(501);
		} elseif (!is_object($callback)) {
			return $this->abort(501);
		}

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
		foreach ($methods as $m) {
			if (!in_array($m, $this->method_collection))
				$this->method_collection[] = $m;
		}
		if (!in_array($request_method, $methods)) {
			return;
		}

		/* ***** */
		/* route */
		/* ***** */

		# path is empty
		if (!$path || $path[0] != '/')
			return;
		# ignore trailing slash
		$path = rtrim($path, '/');

		# init variables
		$arg = [];
		$arg['params'] = [];

		# parse URL
		$frac = explode('/', substr($path, 1, strlen($path)));
		$reqs = explode('/', $this->request_path);
		if ($frac != $reqs) {
			if (!$frac[0])
				return;
			$parsed = $this->_path_parse(true, [], $frac, $reqs);
			if ($parsed[0] === false)
				# path doesn't match
				return;
			$arg['params'] = $parsed[1];
		}

		/* ************ */
		/* route method */
		/* ************ */


		# process method-path pair only once
		$method_path = strtolower($request_method) . ':' . $path;
		if (in_array($method_path, $this->request_routes))
			return;
		$this->request_routes[] = $method_path;

		/* ********* */
		/* http vars */
		/* ********* */

		$arg['get'] = $_GET;
		$arg['post'] = $_POST;
		$arg['files'] = [];
		$arg['put'] = null;
		$arg['delete'] = null;
		$arg['cookie'] = $_COOKIE;
		$args['header'] = [];
		foreach ($_SERVER as $key => $val) {
			if (strpos($key, 'HTTP_') === 0) {
				$key = substr($key, 5, strlen($key));
				$key = strtolower($key);
				$args['header'][$key] = $val;
			}
		}

		/* ************** */
		/* request method */
		/* ************** */

		$arg['method'] = $request_method;
		if (in_array($request_method, ['HEAD', 'GET', 'OPTIONS'])) {
			# HEAD, GET
			$this->request_handled = true;
			$this->wrap_callback($callback, $arg);
			return;
		}
		if ($request_method == 'POST') {
			# POST, FILES
			if (isset($_FILES) && !empty($_FILES))
				$arg['files'] = $_FILES;
		} elseif ($request_method == 'PUT') {
			# PUT
			$arg['put'] = file_get_contents("php://input");
		} elseif ($request_method == 'DELETE') {
			# DELETE
			$arg['delete'] = file_get_contents("php://input");
		} else {
			# PATCH, TRACE
			return $this->abort(501);
		}

		/* **************** */
		/* execute callback */
		/* **************** */

		$this->request_handled = true;
		$this->wrap_callback($callback, $arg);
	}

	/**
	 * Default abort method.
	 */
	private function _abort_default($code) {
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

		die();
	}

	/**
	 * Abort method.
	 *
	 * @param int $code HTTP error code.
	 */
	public function abort($code) {
		$this->request_handled = true;
		if (!isset($this->abort_custom))
			$this->_abort_default($code);
		else
			$this->abort_custom($code);
		die();
	}

	/**
	 * Default redirect.
	 */
	private function _redirect_default($destination) {
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
		die();
	}

	/**
	 * Redirect method.
	 *
	 * Patch this or set $this->redirect_custom for custom
	 * redirect page.
	 *
	 * @param string $destination Destination URL.
	 */
	public function redirect($destination) {
		$this->request_handled = true;
		if (!isset($this->redirect_custom))
			$this->_redirect_default($destination);
		else
			$this->redirect_custom($destination);
		die();
	}

	/**
	 * Default static file.
	 */
	private function _static_file_default($path, $disposition=false) {
		if (file_exists($path))
			$this->send_header($path, 3600, 1, 200, $disposition);
		$this->abort(404);
	}

	/**
	 * Static file.
	 *
	 * This can be changed in a subclass but it can be more convenient
	 * if we just replace it with $this->static_file_custom().
	 *
	 * @param string $path Absolute path to file.
	 * @param bool|string $disposition Set content-disposition in header.
	 *     See $this->send_header().
	 * @example
	 *
	 * $app = new Route();
	 * $custom = function($path, $disp=false) using($app) {
	 *     $app->abort(503);
	 * };
	 * // $app->static_file = $custom; # this doesn't work
	 * $app->static_file_custom = $custom;
	 * $app->route('/static/<path>/currently/unavailable/<path>');
	 */
	public function static_file($path, $disposition=false) {
		if (!isset($this->static_file_custom))
			return $this->_static_file_default($path, $disposition);
		return $this->static_file_custom($path, $disposition);
	}

	/**
	 * Shutdown function.
	 *
	 * If no request is handled at this point, show a 501.
	 */
	public function shutdown() {
		if ($this->request_handled)
			return;
		$code = 501;
		if (in_array($this->current_method, $this->method_collection))
			$code = 404;
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
	 * Show request path without leading slash.
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

