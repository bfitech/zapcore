<?php declare(strict_types=1);


namespace BFITech\ZapCoreDev;


use BFITech\ZapCore\Common;
use BFITech\ZapCore\Router;


/**
 * Mock router.
 *
 * Use this class to try out routing without running it through HTTP.
 */
class RouterDev extends Router {

	/** HTTP code. */
	public static $code = 200;
	/** Response HTTP headers. */
	public static $head = [];
	/** Raw response body. */
	public static $body_raw = null;
	/** Response body parsed as JSON. */
	public static $body = null;
	/** Default errno for JSON response. */
	public static $errno = 0;
	/** Default data for JSON response. */
	public static $data = [];

	private static $override_args = [];

	/**
	 * Reset fake HTTP variables and properties.
	 */
	public static function reset() {
		$_SERVER['REQUEST_URI'] = '/';
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_GET = $_POST = $_FILES = $_REQUEST = [];
		self::$code = 200;
		self::$head = [];
		self::$body_raw = null;
		self::$body = null;
		self::$errno = 0;
		self::$data = [];
		self::$override_args = [];
	}

	/**
	 * Patched Header::header().
	 *
	 * @param string $header_string Header string.
	 * @param bool $replace The 'replace' option for standard
	 *     header() function.
	 *
	 * @cond
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 * @endcond
	 */
	public static function header(
		string $header_string, bool $replace=false
	) {
		if (strpos($header_string, 'HTTP/1') !== false) {
			self::$code = explode(' ', $header_string)[1];
		} else {
			self::$head[] = $header_string;
		}
	}

	/**
	 * Patched Header::send_cookie().
	 *
	 * Expiration is just 0 or 1. If 1, cookie is set, otherwise, cookie
	 * is considered stale and unset if exists.
	 *
	 * @cond
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 * @endcond
	 */
	public static function send_cookie(
		string $name, string $value='', int $expire=1,
		string $path='', string $domain='',
		bool $secure=false, bool $httponly=false
	) {
		if (!isset($_COOKIE))
			$_COOKIE = [];
		if ($expire > 0) {
			$_COOKIE[$name] = $value;
			return;
		}
		if (isset($_COOKIE[$name]))
			unset($_COOKIE[$name]);
	}

	/**
	 * Patched Header::halt().
	 *
	 * @param string $arg What to print on halt.
	 */
	public static function halt(string $arg=null) {
		if (!$arg)
			return;
		echo $arg;
	}

	/**
	 * Patched Router::wrap_callback().
	 *
	 * @param callable $callback Callback method.
	 * @param array $args HTTP variables collected by router.
	 */
	public function wrap_callback(callable $callback, array $args=[]) {
		ob_start();
		foreach (self::$override_args as $key => $val)
			$args[$key] = $val;
		$callback($args);
		self::$body_raw = ob_get_clean();
		self::$body = @json_decode(self::$body_raw, true);
		if (self::$body) {
			self::$errno = self::$body['errno'];
			self::$data = self::$body['data'];
		} else {
			self::$errno = 0;
			self::$data = [];
		}
	}

	/**
	 * Overrides callback args.
	 *
	 * Use this in case you want to manipulate one or more collected
	 * HTTP variables without tweaking $_GET, $_POST and other globals.
	 *
	 * @param array $args Dict of HTTP variables for overriding
	 *     existing args. Key must be one or more of: 'get',
	 *     'post', 'files', 'put', 'patch', 'delete', 'header'.
	 *     Invalid keys are ignored.
	 */
	public function override_callback_args(array $args=[]) {
		foreach ($args as $key => $val) {
			if (!in_array($key, [
				'get', 'post', 'files', 'put', 'patch', 'delete'
			]))
				continue;
			self::$override_args[$key] = $val;
		}
	}

	/**
	 * Custom abort for testing.
	 *
	 * @param int $code HTTP error code.
	 */
	public function abort_custom(int $code) {
		self::$code = $code;
		static::$body_raw = "ERROR: $code";
		self::$body = "ERROR: $code";
		self::$errno = $code;
		self::$data = [];
	}

	/**
	 * Custom redirect for testing.
	 *
	 * @param string $url Destination URL.
	 */
	public function redirect_custom(string $url) {
		self::$code = 301;
		self::$head = ["Location: $url"];
		self::$body_raw = self::$body = "Location: $url";
		self::$errno = 0;
		self::$data = [$url];
	}

	/**
	 * Custom static file serving for testing.
	 *
	 * @param string $path Absolute path to file.
	 * @param array $kwargs Additional arguments. See
	 *     BFITech\\ZapCore\\Router::static_file.
	 *
	 * @cond
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 * @endcond
	 */
	public function static_file_custom(
		string $path, array $kwargs=[]
	) {
		extract(Common::extract_kwargs($kwargs, [
			'cache' => 0,
			'disposition' => null,
			'headers' => [],
			'reqheaders' => [],
			'xsendfile' => false,
			'callback_notfound' => function() {
				return $this->abort(404);
			},
		]));
		self::reset();
		if (file_exists($path)) {
			self::$code = 200;
			self::$body_raw = file_get_contents($path);
			self::$body = "Path: $path";
			self::$head = $headers;
			self::$errno = 0;
			self::$data = [$path];
		} else {
			self::$code = 404;
		}
	}

}
