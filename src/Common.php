<?php


namespace BFITech\ZapCore;


class CommonError extends \Exception {
}


/**
 * Common class.
 */
class Common {

	/**
	 * Execute arbitrary shell commands. Use with care.
	 *
	 * @param string $cmd Command with '%s' as parameter placeholders.
	 * @param array $args List of parameters to replace the
	 *     placeholders in command.
	 * @return bool|array False on failure, stdout lines otherwise.
	 */
	final public static function exec($cmd, $args=[]) {
		foreach ($args as $key => $val)
			$args[$key] = escapeshellarg($val);
		$cmd = vsprintf($cmd, $args);
		exec($cmd, $output, $retcode);
		if ($retcode !== 0)
			return false;
		return $output;
	}

	/**
	 * Find a mime type.
	 *
	 * @param string $fname The file name.
	 * @param string $path_to_file Path to `file`. Useful if
	 *     you have it outside PATH.
	 * @return string The MIME type or application/octet-stream.
	 */
	final public static function get_mimetype(
		$fname, $path_to_file=null
	) {

		if (null === $mime = self::_mime_extension($fname))
			return self::_mime_magic($fname, $path_to_file);
		return $mime;
	}

	/**
	 * Get MIME by extension.
	 *
	 * Useful for serving typical text files that don't have unique
	 * magic numbers.
	 */
	private static function _mime_extension($fname) {
		$pinfo = pathinfo($fname);
		if (!isset($pinfo['extension']))
			return null;
		# Because these things are magically ambiguous, we'll
		# resort to extension.
		switch (strtolower($pinfo['extension'])) {
			case 'css':
				return 'text/css';
			case 'js':
				return 'application/javascript';
			case 'json':
				return 'application/json';
			case 'htm':
			case 'html':
				# always assume UTF-8
				return 'text/html; charset=utf-8';
		}
		return null;
	}

	/**
	 * Get MIME type with `mime_content_type` or `file`.
	 */
	private static function _mime_magic($fname, $path_to_file=null) {
		# with builtin
		if (
			function_exists('mime_content_type') &&
			($mime = @mime_content_type($fname)) &&
			$mime != 'application/octet-stream'
		)
			return $mime;

		# with `file`
		if ($path_to_file && is_executable($path_to_file)) {
			$bin = $path_to_file;
		} elseif (!($bin = self::exec("bash -c 'type -p file'")[0])) {
			// @codeCoverageIgnoreStart
			return 'application/octet-stream';
			// @codeCoverageIgnoreEnd
		}

		if (
			($mimes = self::exec('%s -bip %s', [$bin, $fname])) &&
			preg_match('!^[a-z0-9\-]+/!i', $mimes[0])
		)
			return $mimes[0];

		# giving up
		// @codeCoverageIgnoreStart
		return 'application/octet-stream';
		// @codeCoverageIgnoreEnd
	}

	/**
	 * cURL-based HTTP client.
	 *
	 * @param array $kwargs Dict with key-value:
	 * - `url`     : (string) the URL
	 * - `method`  : (string) HTTP request method
	 * - `headers` : (array) optional request headers, useful for
	 *               setting MIME type, user agent, etc.
	 * - `get`     : (dict) query string will be built off of
	 *               this; leave empty if you already have
	 *               query string in URL, unless you have to
	 * - `post`    : (dict|string) POST, PUT, or other request
	 *               body; if it's a string, it won't be formatted
	 *               as a query string
	 * - `custom_opts` :
	 *               (dict) custom cURL options to add or override
	 *               defaults
	 * - `expect_json` :
	 *               (bool) JSON-decode response if true, whether
	 *               server honors `Accept: application/json`
	 *               request header or not; response data is null
	 *               if response body is not valid JSON
	 * @return array A list of the form `[HTTP code, response body]`.
	 *     HTTP code is -1 for invalid method, 0 for failing connection,
	 *     and any of standard code for successful connection.
	 */
	public static function http_client($kwargs) {
		$url = $method = null;
		$headers = $get = $post = $custom_opts = [];
		$expect_json = false;
		extract(self::extract_kwargs($kwargs, [
			'url' => null,
			'method' => 'GET',
			'headers'=> [],
			'get' => [],
			'post' => [],
			'custom_opts' => [],
			'expect_json' => false,
		]));

		if (!$url)
			throw new CommonError("URL not set.");

		$opts = [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_CONNECTTIMEOUT => 16,
			CURLOPT_TIMEOUT        => 16,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS      => 8,
			CURLOPT_HEADER         => false,
		];
		foreach ($custom_opts as $key => $val)
			$opts[$key] = $val;

		$conn = curl_init();
		foreach ($opts as $key => $val)
			curl_setopt($conn, $key, $val);

		if ($headers)
			curl_setopt($conn, CURLOPT_HTTPHEADER, $headers);

		if ($get) {
			$url .= strpos($url, '?') !== false ? '&' : '?';
			$url .= http_build_query($get);
		}
		curl_setopt($conn, CURLOPT_URL, $url);

		if (is_array($post))
			$post = http_build_query($post);

		switch ($method) {
			case 'GET':
				break;
			case 'HEAD':
			case 'OPTIONS':
				curl_setopt($conn, CURLOPT_NOBODY, true);
				curl_setopt($conn, CURLOPT_HEADER, true);
				break;
			case 'POST':
			case 'PUT':
			case 'DELETE':
			case 'PATCH':
			case 'TRACE':
				curl_setopt($conn, CURLOPT_CUSTOMREQUEST, $method);
				curl_setopt($conn, CURLOPT_POSTFIELDS, $post);
				break;
			default:
				# CONNECT etc. are not supported ... yet?
				return [-1, null];
		}

		$body = curl_exec($conn);
		$info = curl_getinfo($conn);
		curl_close($conn);

		if (in_array($method, ['HEAD', 'OPTIONS']))
			return [$info['http_code'], $body];
		if ($expect_json)
			$body = @json_decode($body, true);
		return [$info['http_code'], $body];
	}

	/**
	 * Check if a dict contains all necessary keys.
	 *
	 * @param array $array Dict to verify.
	 * @param array $keys List of keys to verify against.
	 * @param bool $trim Whether it should treat everything as string
	 *     and trim the values and drop keys of those with empty values.
	 * @return bool|array False on failure, filtered dict otherwise.
	 */
	final public static function check_dict(
		$array, $keys, $trim=false
	) {
		$checked = [];
		foreach ($keys as $key) {
			if (!isset($array[$key]))
				return false;
			$val = $array[$key];
			if ($trim) {
				if (!is_string($val))
					return false;
				$val = trim($val);
				if (!$val)
					return false;
			}
			$checked[$key] = $val;
		}
		return $checked;
	}

	/**
	 * Check if a dict contains all necessary keys with elements being
	 * immutables, i.e. numeric or string.
	 *
	 * @param array $array Dict to verify.
	 * @param array $keys List of keys to verify against.
	 * @param bool $trim Whether it should treat everything as string
	 *     and trim the values and drop keys of those with empty values.
	 * @return bool|array False on failure, filtered dict otherwise.
	 */
	final public static function check_idict(
		$array, $keys, $trim=false
	) {
		if (false === $array = self::check_dict($array, $keys, $trim))
			return false;
		foreach ($array as $val) {
			if (!is_numeric($val) && !is_string($val))
				return false;
		}
		return $array;
	}

	/**
	 * Initiate a kwargs array for safe extraction.
	 *
	 * This will remove keys not available in $init_array instead
	 * of filling in holes in input array.
	 *
	 * @param array $input_array Input array, typically first
	 *     parameter in a method.
	 * @param array $init_array Fallback array when input array
	 *     is not complete, of the form: `key => default value`.
	 * @return array A complete array ready to be extract()ed.
	 */
	final public static function extract_kwargs(
		$input_array, $init_array
	) {
		foreach (array_keys($init_array) as $key) {
			if (isset($input_array[$key]))
				$init_array[$key] = $input_array[$key];
		}
		return $init_array;
	}

}
