<?php


namespace BFITech\ZapCoreDev;


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
	}

	/**
	 * Patched Header::header().
	 */
	public static function header($header_string, $replace=false) {
		if (strpos($header_string, 'HTTP/1') !== false) {
			self::$code = explode(' ', $header_string)[1];
		} else {
			self::$head[] = $header_string;
		}
	}

	/**
	 * Patched Header::send_cookie().
	 *
	 * @codeCoverageIgnore
	 */
	public static function send_cookie(
		$name, $value='', $expire=0, $path='', $domain='',
		$secure=false, $httponly=false
	) {
		// do nothing
	}

	/**
	 * Patched Header::halt().
	 */
	public static function halt($arg=null) {
		if (!$arg)
			return;
		echo $arg;
	}

	/**
	 * Patched Router::wrap_callback().
	 */
	public function wrap_callback($callback, $args=[]) {
		ob_start();
		$callback($args);
		self::$body_raw = ob_get_clean();
		self::$body = json_decode(self::$body_raw, true);
		if (self::$body) {
			self::$errno = self::$body['errno'];
			self::$data = self::$body['data'];
		} else {
			self::$errno = 0;
			self::$data = [];
		}
	}

	/**
	 * Custom abort for testing.
	 */
	public function abort_custom($code) {
		self::$code = $code;
		self::$body = "ERROR: $code";
		self::$errno = $code;
		self::$data = [];
	}

	/**
	 * Custom redirect for testing.
	 */
	public function redirect_custom($url) {
		self::$code = 301;
		self::$head = ["Location: $url"];
		self::$body_raw = self::$body = "Location: $url";
		self::$errno = 0;
		self::$data = [$url];
	}

	/**
	 * Custom static file serving for testing.
	 */
	public function static_file_custom(
		$path, $cache=0, $disposition=false
	) {
		self::reset();
		if (file_exists($path)) {
			self::$code = 200;
			self::$body_raw = file_get_contents($path);
			self::$body = "Path: $path";
			self::$errno = 0;
			self::$data = [$path];
		} else {
			self::$code = 404;
		}
	}

}
