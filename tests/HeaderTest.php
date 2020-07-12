<?php


use BFITech\ZapCore\Header;
use BFITech\ZapCoreDev\TestCase;


class HeaderPatched extends Header {

	public static $code = 200;
	public static $head = [];

	public function __construct() {
		static::$code = 200;
		static::$head = [];
	}

	public static function header(
		string $header_string, bool $replace=false
	) {
		if (strpos($header_string, 'HTTP/1.') === 0)
			static::$code = (int)explode(' ', $header_string)[1];
		else
			static::$head[] = $header_string;
	}

	public static function halt(string $str=null) {
		if ($str)
			echo $str;
	}

}


class HeaderTest extends TestCase {

	private static function get_header(HeaderPatched $hdr, $key) {
		return array_values(array_filter($hdr::$head,
			function($ele) use($key) {
				return strpos($ele, "$key:") !== false;
			}
		));
	}

	public function test_start_header() {
		extract(self::vars());

		$hdr = new HeaderPatched;
		$header_string = $hdr::get_header_string(-1);
		$eq($header_string['code'], 404);

		$hdr = new HeaderPatched;
		$hdr::start_header(200, 3600);
		$eq($hdr::$code, 200);
		$pragmas = self::get_header($hdr, 'Pragma');
		$eq(count($pragmas), 0);

		$hdr = new HeaderPatched;
		$hdr::start_header(404, 0, [
			'X-Will-Work-For-Food: 1',
		]);
		$pragmas = self::get_header($hdr, 'Pragma');
		$eq(count($pragmas), 1);
		$eq($hdr::$code, 404);
	}

	public function test_send_file() {
		extract(self::vars());

		# default
		$hdr = new HeaderPatched;
		ob_start();
		$hdr::send_file(__FILE__);
		ob_get_clean();
		$eq($hdr::$code, 200);

		# not found
		$hdr = new HeaderPatched;
		$hdr::send_file(__FILE__ . 'x');
		$eq($hdr::$code, 404);

		# not found with custom abort
		$hdr = new HeaderPatched;
		ob_start();
		$hdr::send_file(__FILE__ . '.log', 0, null, [], [], null,
			function(){
				echo 'missing';
			}
		);
		$eq($hdr::$code, 404);
		$eq(ob_get_clean(), 'missing');

		# disposition string
		$hdr = new HeaderPatched;
		ob_start();
		$hdr::send_file(__FILE__, 0, 'my file.log');
		$eq($hdr::$code, 200);
		$disp = self::get_header($hdr, 'Content-Disposition')[0];
		$tr(strpos($disp, 'my%20file.log') !== false);
		$eq(ob_get_clean(), file_get_contents(__FILE__));

		# disposition true
		$hdr = new HeaderPatched;
		ob_start();
		$hdr::send_file(__FILE__, 0, true);
		$eq(ob_get_clean(), file_get_contents(__FILE__));
		$eq($hdr::$code, 200);
		$disp = self::get_header($hdr, 'Content-Disposition')[0];
		$tr(strpos($disp, basename(__FILE__)) !== false);

		# etag matches, 304 is sent without body
		$hdr = new HeaderPatched;
		ob_start();
		$hdr::send_file(__FILE__, 0, null, [], [
			'if_none_match' => $hdr::gen_etag(__FILE__),
		]);
		$ne(ob_get_clean(), file_get_contents(__FILE__));
		$eq($hdr::$code, 304);

		# etag doesn't match
		ob_start();
		$hdr::send_file(__FILE__, 0, null, [], [
			'if_none_match' => 'x' . $hdr::gen_etag(__FILE__),
		]);
		$eq(ob_get_clean(), file_get_contents(__FILE__));
		$eq($hdr::$code, 200);
	}

	public function test_print_json() {
		extract(self::vars());

		$hdr = new HeaderPatched;
		ob_start();
		$hdr::print_json();
		extract(json_decode(ob_get_clean(), true));
		$mime = self::get_header($hdr, 'Content-Type')[0];
		$tr(strpos($mime, 'application/json') !== false);
		$eq($hdr::$code, 200);
		$eq($errno, 0);
		$nil($data);
	}

	public function test_pj() {
		extract(self::vars());

		# valid
		$hdr = new HeaderPatched;
		ob_start();
		$hdr::pj([1, null], 403);
		extract(json_decode(ob_get_clean(), true));
		$mime = self::get_header($hdr, 'Content-Type')[0];
		$tr(strpos($mime, 'application/json') !== false);
		$eq($hdr::$code, 403);
		$eq($errno, 1);
		$nil($data);

		# invalid first arg
		$hdr = new HeaderPatched;
		ob_start();
		$hdr::pj(1, 403);
		extract(json_decode(ob_get_clean(), true));
		$mime = self::get_header($hdr, 'Content-Type')[0];
		$tr(strpos($mime, 'application/json') !== false);
		# will send HTTP 500 regardless the forbidden code value
		$eq($hdr::$code, 500);
		$eq($errno, -1);
	}

}
