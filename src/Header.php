<?php


namespace BFITech\ZapCore;


/**
 * Header class.
 */
class Header {

	/**
	 * HTTP response header strings.
	 */
	public static $header_string = [
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

	/**
	 * Get HTTP code.
	 *
	 * @param int $code HTTP code.
	 * @return array A dict containing code and message if
	 *     $code is valid, otherwise 404 dict.
	 */
	final public static function get_header_string($code) {
		if (self::$header_string[$code] === null)
			$code = 404;
		return [
			'code' => $code,
			'msg'  => self::$header_string[$code],
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
	 */
	public static function header($header_string, $replace=false) {
		header($header_string, $replace);
	}

	/**
	 * Wrapper for die().
	 *
	 * Override this for non-web context, e.g. for testing.
	 *
	 * @param mixed $arg What to print on halt. If null, nothing
	 *     is printed. If it's a numeric or string, it will be
	 *     immediately printed. For other types, it's completely at
	 *     the mercy of print_r(): formatted array, true becomes '1',
	 *     etc. Use with care.
	 */
	public static function halt($arg=null) {
		if ($arg === null)
			die();
		if (is_string($arg) || is_numeric($arg))
			echo $arg;
		else
			print_r($arg);
		die();
	}

	/**
	 * Start sending response headers.
	 *
	 * @param int $code HTTP code.
	 * @param int $cache Cache age, 0 for no cache.
	 * @param array $headers Additional headers.
	 */
	public static function start_header(
		$code=200, $cache=0, $headers=[]
	) {
		extract(self::get_header_string($code));

		$prot = isset($_SERVER['SERVER_PROTOCOL'])
			? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
		static::header("$prot $code $msg");
		if ($cache) {
			$cache = intval($cache);
			$expire = time() + $cache;
			static::header("Expires: " .
				gmdate("D, d M Y H:i:s", $expire)." GMT");
			static::header("Cache-Control: must-revalidate");
		} else {
			static::header("Expires: Mon, 27 Jul 1996 07:00:00 GMT");
			static::header(
				"Cache-Control: no-store, no-cache, must-revalidate");
			static::header("Cache-Control: post-check=0, pre-check=0");
			static::header("Pragma: no-cache");
		}
		static::header("Last-Modified: " .
			gmdate("D, d M Y H:i:s")." GMT");
		static::header("X-Powered-By: Zap!", true);

		if (!$headers)
			return;
		foreach ($headers as $header)
			static::header($header);
	}

	/**
	 * Send file.
	 *
	 * @param string $fpath Path to file.
	 * @param mixed $disposition If set as string, this will be used
	 *     as filename on content disposition. If set to true, content
	 *     disposition is inferred from basename. Otherwise, no
	 *     content disposition header is sent.
	 * @param int $code HTTP code. Typically it's 200, but this can
	 *     be anything since we can serve, e.g. 404 with a text file.
	 * @param int $cache Cache age, 0 for no cache.
	 * @param array $headers Additional headers.
	 * @param string $sendfile_header If not null, this will be used
	 *     and sending file is left to the web server.
	 * @param callable $callback_notfound What to do when the file
	 *     is not found. If no callback is provided, the method will
	 *     just immediately halt.
	 */
	public static function send_file(
		$fpath, $disposition=null, $code=200, $cache=0,
		$headers=[], $sendfile_header=null,
		$callback_notfound=null
	) {

		if (!file_exists($fpath) || is_dir($fpath)) {
			static::start_header(404, 0, $headers);
			if (is_callable($callback_notfound)) {
				$callback_notfound();
				static::halt();
			}
			return;
		}

		static::start_header($code, $cache, $headers);

		static::header('Content-Length: ' .
			filesize($fpath));
		static::header("Content-Type: " .
			Common::get_mimetype($fpath));

		if ($disposition) {
			if ($disposition === true)
				$disposition = htmlspecialchars(
					basename($fpath), ENT_QUOTES);
			static::header(sprintf(
				'Content-Disposition: attachment; filename="%s"',
				$disposition));
		}

		if ($sendfile_header)
			static::header($sendfile_header);
		else
			readfile($fpath);

		static::halt();
	}

	/**
	 * Send response headers and read a file if applicable.
	 *
	 * @deprecated Use $this->start_header() and $this->send_file()
	 *     instead.
	 * @param string|false $fname Filename to read or false.
	 * @param int|false $cache Cache age or no cache at all.
	 * @param bool $echo If true and $fname exists, print it and die.
	 * @param int $code HTTP code.
	 * @param bool|string $disposition Whether Content-Disposition
	 *     header is to be sent. If a string is set, it will be used
	 *     in the header.
	 * @param string $xsendfile_header Header containing xsendfile
	 *     directive for Apache or the equivalent for other webservers.
	 *     May be set externally with webserver internal directive.
	 */
	final public static function send_header(
		$fname=false, $cache=false, $echo=true,
		$code=200, $disposition=false, $xsendfile_header=null
	) {

		if ($fname)
			return static::send_file($fname, $disposition,
				$code, $cache, [], $xsendfile_header);
		static::start_header($code, $cache);
		if ($echo)
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
		$errno=0, $data=[], $http_code=200, $cache=0
	) {
		self::send_header(0, $cache, false, $http_code);
		$js = json_encode(compact('errno', 'data'));
		static::header("Content-Length: " . strlen($js));
		static::header('Content-Type: application/json');
		static::halt($js);
	}

	/**
	 * Even shorter JSON response formatter.
	 *
	 * @param array $retval Return value of typical Zap HTTP response.
	 * @param int $forbidden_code If $retval[0] == 0, HTTP code is 200.
	 *     Otherwise it defaults to 401 which we can override with
	 *     this parameter, e.g. 403.
	 * @param int $cache Cache duration in seconds. 0 for no cache.
	 */
	final public static function pj(
		$retval, $forbidden_code=null, $cache=0
	) {
		if (count($retval) < 2)
			$retval[] = [];
		$http_code = 200;
		if ($retval[0] !== 0) {
			$http_code = 401;
			if ($forbidden_code)
				$http_code = $forbidden_code;
		}
		self::print_json($retval[0], $retval[1], $http_code);
	}

}

