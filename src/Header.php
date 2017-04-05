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

	public static function header($val) {
		header($val);
	}

	public static function header_halt($str=null) {
		if ($str)
			die((string)$str);
		die();
	}

	/**
	 * Send response headers and read a file if applicable.
	 *
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

		if ($fname && (!file_exists($fname) || is_dir($fname)))
			$code = 404;

		extract(self::get_header_string($code));

		static::header("HTTP/1.1 $code $msg");
		if ($cache) {
			$cache = intval($cache);
			$expire = time() + $cache;
			static::header(
				"Expires: " . gmdate("D, d M Y H:i:s", $expire)." GMT");
			static::header(
				"Cache-Control: must-revalidate");
		} else {
			static::header(
				"Expires: Mon, 27 Jul 1996 07:00:00 GMT");
			static::header(
				"Cache-Control: no-store, no-cache, must-revalidate");
			static::header(
				"Cache-Control: post-check=0, pre-check=0", false);
			static::header("Pragma: no-cache");
		}
		static::header("Last-Modified: " . gmdate("D, d M Y H:i:s")." GMT");
		static::header("X-Powered-By: Zap!");

		if (!$echo)
			return;

		if (!$fname && $echo)
			# Cannot echo anything if fname doesn't exist.
			return;

		if ($code != 200) {
			# Echoing error page, i.e. serving non-text as error page
			# makes little sense. Error pages must always be generated
			# and not cached.
			static::header_halt();
			return;
		}

		static::header('Content-Length: ' . filesize($fname));

		$mime = Common::get_mimetype($fname);
		static::header("Content-Type: $mime");

		if ($disposition) {
			if ($disposition === true)
				$disposition = basename($fname);
			static::header(sprintf(
				'Content-Disposition: attachment; filename="%s"',
				$disposition));
		}

		if ($xsendfile_header !== null)
			static::header($xsendfile_header);
		else
			readfile($fname);
		static::header_halt();
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
		static::header_halt($js);
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

