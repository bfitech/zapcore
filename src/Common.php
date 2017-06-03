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

		$pi = pathinfo($fname);
		if (isset($pi['extension'])) {
			# Because these things are magically ambiguous, we'll
			# resort to extension.
			switch (strtolower($pi['extension'])) {
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
		}

		# with builtin
		if (function_exists('mime_content_type')) {
			$mime = @mime_content_type($fname);
			if ($mime && $mime != 'application/octet-stream')
				return $mime;
		}

		# with `file`
		$cmd = '%s -bip %s';
		if ($path_to_file && is_executable($path_to_file)) {
			$bin = $path_to_file;
		} elseif (!($bin = self::exec("bash -c 'type -p file'")[0])) {
			// @codeCoverageIgnoreStart
			return 'application/octet-stream';
			// @codeCoverageIgnoreEnd
		}
		$mimes = self::exec($cmd, [$bin, $fname]);
		if ($mimes && preg_match('!^[a-z0-9\-]+/!i', $mimes[0]))
			return $mimes[0];

		# giving up
		// @codeCoverageIgnoreStart
		return 'application/octet-stream';
		// @codeCoverageIgnoreEnd
	}

	/**
	 * cURL-based HTTP client.
	 *
	 * @param string|array $url_or_kwargs If it's a string,
	 *     it's the URL. Otherwise it will be expanded as the whole
	 *     parameters and the rest are ignored. Use the kwargs format
	 *     to avoid many meaningless default values just to reach to a
	 *     desired parameter.
	 * @param string $method HTTP request method. Deprecated.
	 * @param array $headers Optional request headers. Use this
	 *     to set MIME, user-agent, etc. Deprecated.
	 * @param array $get Query string will be built off of this.
	 *     Do not use this if you already have query string in URL,
	 *     unless you have too. Deprecated.
	 * @param array $post POST, PUT, DELETE data dict. Deprecated.
	 * @param array $custom_opts Custom cURL options to add or
	 *     override defaults. Deprecated.
	 * @param bool $expect_json Automatically JSON-decode response
	 *     if this is set to true. This has nothing to do with
	 *     'Accept: application/json' request header. Deprecated.
	 * @param bool $is_raw If true, do not format request body as HTTP
	 *     query. Deprecated.
	 * @return array A list of the form [HTTP code, response body].
	 *     HTTP code is -1 for invalid method, 0 for failing request,
	 *     and any of standard code for successful request.
	 *
	 * @todo Only accept kwargs parameter in next minor release.
	 */
	public static function http_client(
		$url_or_kwargs, $method='GET', $headers=[], $get=[], $post=[],
		$custom_opts=[], $expect_json=false, $is_raw=false
	) {
		$url = $url_or_kwargs;
		if (is_array($url)) {
			extract(self::extract_kwargs($url, [
				'url' => null,
				'method' => 'GET',
				'headers'=> [],
				'get' => [],
				'post' => [],
				'custom_opts' => [],
				'expect_json' => false,
				'is_raw' => false,
			]));
		}

		if (!$url)
			throw new CommonError("URL not set.");

		$opts = [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => false,
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

		if (in_array($method, ['HEAD', 'OPTIONS'])) {
			curl_setopt($conn, CURLOPT_NOBODY, true);
			curl_setopt($conn, CURLOPT_HEADER, true);
		} elseif ($method == 'GET') {
			# noop
		} elseif (in_array($method, [
			'POST', 'PUT', 'DELETE', 'PATCH', 'TRACE',
		])) {
			curl_setopt($conn, CURLOPT_CUSTOMREQUEST, $method);
			if (!$is_raw && is_array($post))
				$post = http_build_query($post);
			curl_setopt($conn, CURLOPT_POSTFIELDS, $post);
		} else {
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
