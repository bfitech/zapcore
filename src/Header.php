<?php declare(strict_types=1);


namespace BFITech\ZapCore;


/**
 * Response header class.
 */
class Header {

	/**
	 * HTTP response header strings.
	 */
	final public static function header_strings() {
		return [
			100 => 'Continue',
			101 => 'Switching Protocols',
			102 => 'Processing',
			200 => 'OK',
			201 => 'Created',
			202 => 'Accepted',
			203 => 'Non-Authoritative Information',
			204 => 'No Content',
			205 => 'Reset Content',
			206 => 'Partial Content',
			207 => 'Multi-Status',
			226 => 'IM Used',
			300 => 'Multiple Choices',
			301 => 'Moved Permanently',
			302 => 'Found',
			303 => 'See Other',
			304 => 'Not Modified',
			305 => 'Use Proxy',
			306 => 'Reserved',
			307 => 'Temporary Redirect',
			400 => 'Bad Request',
			401 => 'Unauthorized',
			402 => 'Payment Required',
			403 => 'Forbidden',
			404 => 'Not Found',
			405 => 'Method Not Allowed',
			406 => 'Not Acceptable',
			407 => 'Proxy Authentication Required',
			408 => 'Request Timeout',
			409 => 'Conflict',
			410 => 'Gone',
			411 => 'Length Required',
			412 => 'Precondition Failed',
			413 => 'Request Entity Too Large',
			414 => 'Request-URI Too Long',
			415 => 'Unsupported Media Type',
			416 => 'Requested Range Not Satisfiable',
			417 => 'Expectation Failed',
			422 => 'Unprocessable Entity',
			423 => 'Locked',
			424 => 'Failed Dependency',
			426 => 'Upgrade Required',
			500 => 'Internal Server Error',
			501 => 'Not Implemented',
			502 => 'Bad Gateway',
			503 => 'Service Unavailable',
			504 => 'Gateway Timeout',
			505 => 'HTTP Version Not Supported',
			506 => 'Variant Also Negotiates',
			507 => 'Insufficient Storage',
			510 => 'Not Extended'
		];
	}

	/**
	 * Get HTTP code.
	 *
	 * @param int $code HTTP code.
	 * @return array A dict containing code and message if
	 *     `$code` is valid, 404 dict otherwise.
	 */
	final public static function get_header_string(int $code) {
		$lookup = self::header_strings();
		if (!isset($lookup[$code]))
			$code = 404;
		return [
			'code' => $code,
			'msg'  => $lookup[$code],
		];
	}

	/**
	 * Wrapper for header().
	 *
	 * Override this for non-web context, e.g. for testing.
	 *
	 * @param string $header_string Header string.
	 * @param bool $replace The 'replace' option for standard
	 *     header() function.
	 * @codeCoverageIgnore
	 */
	public static function header(
		string $header_string, bool $replace=false
	) {
		@header($header_string, $replace);
	}

	/**
	 * Wrapper for die().
	 *
	 * Override this for non-web context, e.g. for testing.
	 *
	 * @param string $arg What to print on halt. If null, nothing
	 *     is printed. If it's a string, it will be immediately printed.
	 * @codeCoverageIgnore
	 *
	 * @cond
	 * @SuppressWarnings(PHPMD.ExitExpression)
	 * @endcond
	 */
	public static function halt(string $arg=null) {
		if ($arg === null)
			die();
		die($arg);
	}

	/**
	 * Wrapper for setcookie().
	 *
	 * Override this for non-web context, e.g. for testing.
	 * Parameters are exactly the same with the wrapped
	 * function.
	 *
	 * @codeCoverageIgnore
	 */
	public static function send_cookie(
		string $name, string $value='', int $expire=0,
		string $path='', string $domain='',
		bool $secure=false, bool $httponly=false
	) {
		@setcookie($name, $value, $expire, $path, $domain,
			$secure, $httponly);
	}

	/**
	 * Start sending response headers.
	 *
	 * @param int $code HTTP code.
	 * @param int $cache Cache age, 0 for no cache.
	 * @param array $headers Additional headers.
	 */
	public static function start_header(
		int $code=200, int $cache=0, array $headers=[]
	) {
		$msg = 'OK';
		extract(self::get_header_string($code));

		$hdr = function($header) {
			return static::header($header);
		};

		$proto = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
		$hdr("$proto $code $msg");
		if ($cache) {
			$hdr("Expires: " .
				gmdate("D, d M Y H:i:s", time() + $cache) . " GMT");
			$hdr("Cache-Control: must-revalidate");
		} else {
			$hdr("Expires: Mon, 27 Jul 1996 07:00:00 GMT");
			$hdr("Cache-Control: no-store, no-cache, must-revalidate");
			$hdr("Cache-Control: post-check=0, pre-check=0");
			$hdr("Pragma: no-cache");
		}
		$hdr("Last-Modified: " . gmdate("D, d M Y H:i:s")." GMT");
		$hdr("X-Powered-By: Zap!", true);

		if (!$headers)
			return;
		foreach ($headers as $header)
			$hdr($header);
	}

	/**
	 * Generate Etag.
	 *
	 * This is a very basic Etag generation. Patch this with your more
	 * collision-resistant implementation.
	 *
	 * @param string $path File path.
	 * @return string Etag.
	 */
	public static function gen_etag(string $path) {
		$fph = fopen($path, 'r');
		$cnt = fread($fph, 1024 ** 2);
		fclose($fph);
		return crc32($cnt);
	}

	private static function check_etag(
		string $path, array $reqheaders
	) {
		$etag = $reqheaders['if_none_match'] ?? null;
		if (!$etag)
			return false;
		return static::gen_etag($path) === $etag;
	}

	/**
	 * Send file.
	 *
	 * Use higher-level Router::static_file for integration with
	 * a router.
	 *
	 * @param string $path Path to file.
	 * @param mixed $disposition If set as string, this will be used
	 *     as filename on content disposition. If true, content
	 *     disposition is inferred from basename. Otherwise, no
	 *     content disposition header is sent.
	 * @param int $cache Cache age, 0 for no cache.
	 * @param array $headers Additional headers.
	 * @param array $reqheaders Request headers passed from router.
	 *     Useful to process Etag and other things.
	 * @param bool $noread If true, file is not read. Useful when file
	 *     is served by other means such as sending $header
	 *     `X-Accel-Redirect` on Nginx or `X-Sendfile` on Apache.
	 * @param callable $callback_notfound What to do when the file
	 *     is not found. If no callback is provided, the method will
	 *     just immediately halt.
	 */
	public static function send_file(
		string $path, int $cache=0, $disposition=null,
		array $headers=[], array $reqheaders=[],
		bool $noread=null, callable $callback_notfound=null
	) {

		if (!file_exists($path) || is_dir($path)) {
			static::start_header(404, 0, $headers);
			if (is_callable($callback_notfound))
				$callback_notfound();
			return static::halt();
		}

		if (self::check_etag($path, $reqheaders)) {
			static::start_header(304, $cache, $headers);
			return static::halt();
		}

		$hdr = function($header) {
			return static::header($header);
		};

		static::start_header(200, $cache, $headers);
		$hdr('Content-Length: ' . filesize($path));
		$hdr("Content-Type: " . Common::get_mimetype($path));
		$hdr('Etag: ' . static::gen_etag($path));
		if ($disposition) {
			if ($disposition === true)
				$disposition = basename($path);
			$disposition = htmlspecialchars($disposition, ENT_QUOTES);
			$hdr("Content-Disposition: attachment; " .
				"filename=\"$disposition\"");
		}
		if (!$noread)
			readfile($path);
		static::halt();
	}

	/**
	 * Convenience method for JSON response.
	 *
	 * @param int $errno Error number to return.
	 * @param array $data Data to return.
	 * @param int $http_code Valid HTTP response code.
	 * @param int $cache Cache duration in seconds. 0 for no cache.
	 */
	final public static function print_json(
		int $errno=0, $data=null, int $http_code=200, int $cache=0
	) {
		$json = json_encode(compact('errno', 'data'));
		static::start_header($http_code, $cache, [
			'Content-Length: ' . strlen($json),
			'Content-Type: application/json',
		]);
		static::halt($json);
	}

	/**
	 * Even shorter JSON response formatter.
	 *
	 * @param array $retval Return value of typical Zap HTTP response.
	 *     Invalid format will send 500 HTTP error.
	 * @param int $forbidden_code If `$retval[0] == 0`, HTTP code
	 *     is 200. Otherwise it defaults to 401 which we can override
	 *     with this parameter, e.g. 403.
	 * @param int $cache Cache duration in seconds. 0 for no cache.
	 * @see Header::print_json.
	 *
	 * @cond
	 * @SuppressWarnings(PHPMD.ShortMethodName)
	 * @endcond
	 */
	final public static function pj(
		$retval, int $forbidden_code=null, int $cache=0
	) {
		$http_code = 200;
		if (!is_array($retval)) {
			$retval = [-1, null];
			$forbidden_code = 500;
		}
		if ($retval[0] !== 0) {
			$http_code = 401;
			if ($forbidden_code)
				$http_code = $forbidden_code;
		}
		$data = $retval[1] ?? null;
		static::print_json($retval[0], $data, $http_code, $cache);
	}

}
