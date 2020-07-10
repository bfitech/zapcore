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
	 * @if TRUE
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 * @endif
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
	 * Expiration is just >0 or not. If >0, cookie is set. Otherwise,
	 * cookie is considered stale and unset if exists. Expiration
	 * still means Un\*x epoch, not the Max-Age. You still need to use
	 * `time() + ...` or the like on your tested code.
	 *
	 * @if TRUE
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 * @endif
	 */
	public static function send_cookie(
		string $name, string $value='', int $expires=0,
		string $path='', string $domain='',
		bool $secure=false, bool $httponly=false
	) {
		$COOKIE = $COOKIE ?? [];
		if ($expires > 0) {
			$_COOKIE[$name] = $value;
			return;
		}
		if (isset($_COOKIE[$name]))
			unset($_COOKIE[$name]);
	}

	/**
	 * Patched Header::send_cookie_with_opts().
	 *
	 * Only 'expire' key on the third paramater is processed. Unlike
	 * the real static method, this doesn't care if we run on
	 * PHP<7.3 or not.
	 */
	public static function send_cookie_with_opts(
		string $name, string $value='', array $opts=[]
	) {
		$expires = 1;
		extract(Common::extract_kwargs($opts, [
			'expires' => 1,
		]));
		static::send_cookie($name, $value, $expires);
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
	 * @param array $args Artificial HTTP variables. All values
	 *     collected from environment are ignored except request headers
	 *     which are merged with $args['header'].
	 */
	public function wrap_callback(callable $callback, array $args=[]) {
		foreach (self::$override_args as $key => $val) {
			if ($key == 'header') {
				$args['header'] = array_merge($args['header'], $val);
				continue;
			}
			$args[$key] = $val;
		}

		ob_start();
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
				'get', 'post', 'files', 'put', 'patch', 'delete',
				'header',
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
	 * @param int $code Redirect status code.
	 */
	public function redirect_custom(string $url, int $code=301) {
		self::$code = $code;
		self::$head = ["Location: $url"];
		self::$body_raw = self::$body = "Location: $url";
		self::$errno = 0;
		self::$data = [$url];
	}

	/**
	 * Custom static file serving for testing.
	 *
	 * @param string $path Absolute path to file.
	 * @param array $kwargs Additional parameters. See
	 *     Router::static_file.
	 */
	public function static_file_custom(
		string $path, array $kwargs=[]
	) {
		$callback_notfound = null;
		$headers = [];
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
			$callback_notfound();
		}
	}

}
