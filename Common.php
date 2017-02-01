<?php


namespace BFITech\ZapCore;

class CommonError extends \Exception {}

class Common {

	/**
	 * Execute arbitrary shell commands. Use with care.
	 *
	 * @param string $cmd Command with '%s' as parameter placeholders.
	 * @param array $args List of parameters to replace the
	 *     placeholders in command.
	 * @return bool|array False on failure, stdout lines otherwise.
	 */
	public static function exec($cmd, $args=[]) {
		foreach ($args as $k => $v)
			$args[$k] = escapeshellarg($arg);
		$cmd = vsprintf($cmd, $args);
		exec($cmd, $output, $retcode);
		if ($retcode !== 0)
			return false;
		return $output;
	}

	/**
	 * Find a mime type, fall back to using `file`.
	 *
	 * @param string $fname The file name.
	 * @return string The MIME type or application/octet-stream.
	 */
	public static function get_mimetype($fname) {

		$pi = pathinfo($fname);
		if (isset($pi['extension'])) {
			# Because these things are magically ambiguous, we'll
			# resort to extension.
			$ext = strtolower($pi['extension']);
			switch ($ext) {
				case 'css':
					return 'text/css';
				case 'js':
					return 'application/x-javascript';
				case 'json':
					return 'application/x-json';
				case 'htm':
				case 'html':
					# always assume UTF-8
					return 'text/html; charset=utf-8';
			}
		}
		# with builtin
		if (function_exists('mime_content_type')) {
			// using mime_content_type() if exists
			$mime = @mime_content_type($fname);
			if ($mime)
				return $mime;
		}
		# with `file`, assuming it's in PATH
		$mimes = self::exec("file -bip %s", [$fname]);
		if ($mimes)
			return $mimes[0];
		# giving up
		return 'application/octet-stream';
	}


	/**
	 * cURL-based HTTP client.
	 *
	 * @param string|array $url_or_kwargs If it's a string,
	 *     it's the URL. Otherwise it will be expanded as the whole
	 *     parameters and the rest are ignored. Use the kwargs format
	 *     to avoid many meaningless default values just to reach to a
	 *     desired parameter.
	 * @param string $method HTTP request method.
	 * @param array $headers Optional request headers. Use this
	 *     to set MIME, user-agent, etc.
	 * @param array $get Query string will be built off of this.
	 *     Do not use this if you already have query string in URL,
	 *     unless you have too.
	 * @param array $post POST, PUT, DELETE data dict.
	 * @param array $custom_opts Custom cURL options to add or
	 *     override defaults.
	 * @param bool $expect_json Automatically JSON-decode response
	 *     if this is set to true.
	 * @return array A list of the form [HTTP code, response body].
	 *     HTTP code is -1 for invalid method, 0 for failing request,
	 *     and any of standard code for successful request.
	 */
	public static function http_client(
		$url_or_kwargs, $method='GET', $headers=[], $get=[], $post=[],
		$custom_opts=[], $expect_json=false
	) {

		if (!function_exists('curl_setopt'))
			throw new CommonError(
				"cURL extension not installed.");

		if (is_array($url_or_kwargs)) {
			$kwargs = $url_or_kwargs;
			if (!isset($kwargs['method']))
				$kwargs['method'] = 'GET';
			if (!isset($kwargs['headers']))
				$kwargs['headers'] = [];
			if (!isset($kwargs['get']))
				$kwargs['get'] = [];
			if (!isset($kwargs['post']))
				$kwargs['post'] = [];
			if (!isset($kwargs['custom_opts']))
				$kwargs['custom_opts'] = [];
			if (!isset($kwargs['expect_json']))
				$kwargs['expect_json'] = false;
			extract($kwargs);
			unset($kwargs);
		} else {
			$url = $url_or_kwargs;
		}

		if (!isset($url) || !$url)
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
		foreach ($custom_opts as $k => $v)
			$opts[$k] = $v;

		$conn = curl_init();
		foreach ($opts as $k => $v)
			curl_setopt($conn, $k, $v);

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
		} elseif (in_array($method, ['POST', 'PUT', 'DELETE'])) {
			curl_setopt($conn, CURLOPT_CUSTOMREQUEST, $method);
			curl_setopt($conn, CURLOPT_POSTFIELDS,
				http_build_query($post));
		} else {
			# TRACE, PATCH, etc. are not supported ... yet?
			return [-1, null];
		}

		$body = curl_exec($conn);
		$info = curl_getinfo($conn);
		if (in_array($method, ['HEAD', 'OPTIONS'])) {
			return [$info['http_code'], $body];
		}
		curl_close($conn);
		if ($expect_json)
			$body = @json_decode($body, true);
		return [$info['http_code'], $body];
	}

	/**
	 * Check if a dict contains all necessary keys.
	 *
	 * @param array $array Dict to verify.
	 * @param array $keys List of keys to verify aganst.
	 * @param bool $trim Whether it should treat everything as string
	 *     and trim the values and drop keys of those with empty values.
	 * @param bool|array False on failure, filtered dict otherwise.
	 */
	public static function check_dict($array, $keys, $trim=false) {
		$checked = [];
		foreach ($keys as $key) {
			if (!isset($array[$key]))
				return false;
			$val = $array[$key];
			if ($trim) {
				$val = trim((string)$val);
				if (!$val)
					return false;
			}
			$checked[$key] = $val;
		}
		return $checked;
	}

}

