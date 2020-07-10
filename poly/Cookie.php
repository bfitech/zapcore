<?php declare(strict_types=1);


namespace BFITech\ZapCore;


/**
 * Cookie polyfill class.
 */
class Cookie {

	private static function warn($msg, ...$args) {
		trigger_error(sprintf($msg, ...$args), E_USER_WARNING);
	}

	private static function check_vals($attr, $ichars, $ilits) {
		$msg = "Cookie %s cannot contain any of the following '%s'";
		foreach ($attr as $key => $val) {
			foreach (str_split($ichars) as $i) {
				if (strpos($val, $i) !== false) {
					self::warn($msg, $key, $ilits);
					return null;
				}
			}
		}
		return true;
	}

	/**
	 * Cookie response header builder.
	 *
	 * This builds specs-compliant cookie header string for PHP<7.3.
	 * Behavior is as close as possible to builtin PHP 7.4.8
	 * implementation. `__Secure-` or `__Host-` prefix does not affect
	 * other attributes.
	 *
	 * @param string $name Cookie name.
	 * @param string $value Cookie value.
	 * @param array $opts Cookie options.
	 * @return string Cookie response header on success, null otherwise.
	 * @see https://git.io/JJmDi
	 * @see https://archive.fo/S3Cnl
	 *
	 * @if TRUE
	 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
	 * @SuppressWarnings(PHPMD.NPathComplexity)
	 * @endif
	 */
	final public static function build(
		string $name, string $value=null, array $opts=[]
	) {
		if (!$name) {
			self::warn('Cookie name must not be empty');
			return null;
		}

		$expires = $maxage = 1;
		$path = $domain = $samesite = '';
		$secure = $httponly = false;

		# if expires doesn't exist, cookie is treated as session
		$has_expires = isset($opts['expires']);
		extract(Common::extract_kwargs($opts, [
			'expires' => 1,
			'path' => '',
			'domain' => '',
			'secure' => false,
			'httponly' => false,
			'samesite' => 'Lax',
		]));

		if ($has_expires) {
			$expires = intval($opts['expires']);
			$maxage = $expires - time();
			if ($expires < 1)
				$has_expires = false;
		}

		if (!self::check_vals([
			'names' => $name,
		], "=,; \t\r\n\013\014", '=,; \t\r\n\013\014'))
			return null;

		if (!$value) {
			$expires = 1;
			$maxage = 0;
			$value = 'deleted';
		}

		if (!self::check_vals([
			'values' => $value,
			'paths' => $path,
			'domains' => $domain,
		], ",; \t\r\n\013\014", ',; \t\r\n\013\014'))
			return null;

		if ($has_expires && $expires < time())
			$value = 'deleted';

		$hdr = sprintf("Set-Cookie: %s=%s",
			rawurlencode($name), rawurlencode($value));

		if ($has_expires) {
			$date = gmdate("D, d-M-Y H:i:s", $expires) . " GMT";
			$year = explode(" ", explode('-', $date)[2])[0];
			if (!$year || strlen($year) > 4) {
				self::warn('Expiry date cannot have a year ' .
					'greater than 9999');
				return null;
			}
			$hdr .= sprintf("; Expires=%s", $date);
			$hdr .= sprintf("; Max-Age=%d", $maxage);
		}

		if ($domain)
			$hdr .= sprintf("; Domain=%s", $domain);

		if ($path)
			$hdr .= sprintf("; Path=%s", $path);

		if ($secure)
			$hdr .= "; Secure";

		if ($httponly)
			$hdr .= "; HttpOnly";

		if (!in_array($samesite, ['None', 'Lax', 'Strict']))
			$samesite = 'Lax';
		$hdr .= sprintf("; SameSite=%s", $samesite);

		return $hdr;
	}

}
