<?php declare(strict_types=1);


namespace BFITech\ZapCore;


/**
 * Parser error.
 */
class ParserError extends \Exception {

	/** Generic invalid route path. */
	const PATH_INVALID = 0x0101;
	/** Invalid character in route path. */
	const CHAR_INVALID = 0x0102;
	/** Dynamic route path not wellformed. */
	const DYNAMIC_PATH_INVALID = 0x0103;
	/** Dynamic key invalid. */
	const PARAM_KEY_INVALID = 0x0104;
	/** Dynamic key reused. */
	const PARAM_KEY_REUSED = 0x0105;

	/**
	 * Constructor.
	 *
	 * @param int $errno Error number.
	 * @param string $errmsg Error message.
	 */
	public function __construct(int $errno, string $errmsg) {
		$this->code = $errno;
		$this->message = $errmsg;
	}

}


/**
 * Path parser class.
 */
class Parser {

	/**
	 * Match route path.
	 *
	 * This parses route path and returns arrays that will parse
	 * request path.
	 *
	 * @param string $path Route path with special enclosing
	 *     characters:
	 *     - `< >` for dynamic URL parameter without `/`
	 *     - `{ }` for dynamic URL parameter with `/`
	 * @return array A duple with values:
	 *     - a regular expression to match against request path
	 *     - an array containing keys that will be used to create
	 *       dynamic variables with whatever matches the previous
	 *       regex
	 */
	final public static function match_route(string $path) {
		# path must start with slash
		if ($path[0] != '/')
			throw new ParserError(
				ParserError::PATH_INVALID,
				"Router: path invalid in '$path'.");

		# ignore trailing slash
		if ($path != '/')
			$path = rtrim($path, '/');

		# allowed characters in path
		$validchars = 'a-zA-Z0-9\_\.\-@%:';
		# param left delimiter
		$elf = '<{';
		# param right delimiter
		$erg = '>}';
		# param delimiter
		$delims = "\/$elf$erg";
		# non-delimiter
		$nondelims = "[^${delims}]";
		# valid param key
		$validkey = "[a-z][a-z0-9\_]*[a-z0-9]?$";

		if (!preg_match("!^[$validchars$delims]+$!", $path))
			throw new ParserError(
				ParserError::CHAR_INVALID,
				"Router: invalid characters in path: '$path'.");

		$validdynpath = sprintf('!(%s[%s]|[%s]%s)!',
			$nondelims, $elf, $erg, $nondelims);
		if (preg_match($validdynpath, $path))
			throw new ParserError(
				ParserError::DYNAMIC_PATH_INVALID,
				"Router: dynamic path not well-formed: '$path'.");

		# capture dynamic path tokens
		preg_match_all("!/([$elf][^$erg]+[$erg])!", $path, $tokens,
			PREG_OFFSET_CAPTURE);

		$keys = $symbols = [];
		foreach ($tokens[1] as $token) {
			list($tname, $tpos) = $token;

			# capture and verify param key
			$key = str_replace(['{','}','<','>'], '', $tname);
			if (!preg_match("!^$validkey\$!i", $key))
				throw new ParserError(
					ParserError::PARAM_KEY_INVALID,
					"Router: invalid param key: '$path'.");
			$keys[] = $key;

			# capture symbols for transforming route path to regex
			$replacement = $validchars;
			if (strpos($tname, '{') !== false)
				# long dynamic var allows slash
				$replacement .= '/';
			$replacement = '([' . $replacement . ']+)';
			$symbols[] = [$replacement, $tpos, strlen($tname)];
		}

		if (count($keys) > count(array_unique($keys)))
			throw new ParserError(
				ParserError::PARAM_KEY_REUSED,
				"Router: param key reused: '$path'.");

		return [self::transform($symbols, $path), $keys];
	}

	/** Transform route path to regex. */
	private static function transform($symbols, $path) {
		$idx = 0;
		$pattern = '';
		while ($idx < strlen($path)) {
			$matched = false;
			foreach ($symbols as $symbol) {
				list($replacement, $tpos, $ltname) = $symbol;
				if ($idx < $tpos)
					continue;
				if ($idx == $tpos) {
					$matched = true;
					$pattern .= $replacement;
					$idx++;
					$idx += $ltname - 1;
				}
			}
			if (!$matched) {
				$pattern .= $path[$idx];
				$idx++;
			}
		}
		return $pattern;
	}

	/**
	 * Match route and request paths.
	 *
	 * @param string $route_path Route path.
	 * @param string $request_path Request path.
	 * @return array|bool If false, route doesn't match request.
	 *     Otherwise, params that is asignable to callback
	 *     $args['params']. If it's empty, the route path is not
	 *     dynamic.
	 * @see Router::route() for usage.
	 */
	final public static function match(
		string $route_path, string $request_path
	) {
		# route path and request path is identical
		if ($route_path == $request_path)
			return [];

		# no dynamic route path found
		list($pattern, $keys) = self::match_route($route_path);
		if (!$keys)
			return false;

		# request path doesn't match
		$matched = preg_match_all(
			"!^$pattern\$!", $request_path, $result, PREG_SET_ORDER);
		if (!$matched)
			return false;

		unset($result[0][0]);
		return array_combine($keys, $result[0]);
	}

}
