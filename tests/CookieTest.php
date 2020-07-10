<?php declare(strict_types=1);


require __DIR__ . '/../poly/Cookie.php';


use BFITech\ZapCore\Cookie;
use BFITech\ZapCoreDev\TestCase;


/**
 * Cookie polyfill tests.
 */
class CookieTest extends TestCase {

	public function test_cookie_with_opts() {
		extract(self::vars());

		$isin = function($needle, $haystack): bool {
			return strpos($haystack, $needle) !== false;
		};

		$core = new Cookie;

		# empty name
		$hdr = @$core::build("", "b", ['path' => 'x ']);
		$nil($hdr);

		# invalid name 
		$hdr = @$core::build("a ", "b");
		$nil($hdr);

		# invalid path
		$hdr = @$core::build("a", "b", ['domain' => 'x ']);
		$nil($hdr);

		# invalid expires
		$hdr = @$core::build("a", "b", ['expires' => 9e11]);
		$nil($hdr);

		# deletion by value
		$hdr = $core::build("a", null);
		$tr($isin('a=deleted', $hdr));

		# deletion by expires
		$hdr = $core::build("a", "b", ['expires' => 100]);
		$tr($isin('a=deleted', $hdr));

		# <1 expires beecomes session
		$hdr = $core::build("a", "b", ['expires' => 0]);
		$fl($isin('Max-Age', $hdr));

		# with domain
		$hdr = $core::build("a", "b", ['domain' => 'bfi.io']);
		$tr($isin('; Domain=bfi.io', $hdr));

		# with path
		$hdr = $core::build("a", "b", ['path' => '/x/']);
		$tr($isin('; Path=/x/', $hdr));

		# secure
		$hdr = $core::build("a", "b", ['secure' => true]);
		$tr($isin('; Secure', $hdr));

		# httponly
		$hdr = $core::build("a", "b", ['httponly' => true]);
		$tr($isin('; HttpOnly', $hdr));

		# samesite invalid
		$hdr = $core::build("a", "b", ['samesite' => 'wot']);
		$tr($isin('; SameSite=Lax', $hdr));

		# samesite ok
		$hdr = $core::build("a", "b", ['samesite' => 'Strict']);
		$tr($isin('; SameSite=Strict', $hdr));
	}

}
