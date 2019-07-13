<?php


use BFITech\ZapCore\Header;
use BFITech\ZapCoreDev\TestCase;


class HeaderPatched extends Header {

	public static $code = 200;
	public static $head = [];

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

	public function setUp() {
		HeaderPatched::$code = 200;
		HeaderPatched::$head = [];
	}

	private function ele_starts_with($array, $str) {
		return count(array_filter($array, function($ele) use($str) {
			return strpos($ele, $str) === 0;
		})) > 0;
	}

	public function test_start_header() {
		extract(self::vars());

		$hdr = new HeaderPatched;
		$header_string = $hdr::get_header_string(-1);
		$eq($header_string['code'], 404);

		$hdr::start_header(200, 3600);
		$eq($hdr::$code, 200);
		$fl($this->ele_starts_with($hdr::$head, 'Pragma'));

		$hdr::start_header(404, 0, [
			'X-Will-Work-For-Food: 1',
		]);
		$eq($hdr::$code, 404);
		$tr($this->ele_starts_with($hdr::$head, 'Pragma'));
	}

	public function test_send_file() {
		extract(self::vars());

		$hdr = new HeaderPatched;
		$hdr::send_file(__FILE__, true,
			200, 0, ['X-Sendfile: ' . __FILE__], true);
		$eq($hdr::$code, 200);

		$hdr::$head = [];
		$hdr::send_file(__FILE__ . 'x', true,
			200, 0, ['X-Sendfile: ' . __FILE__], true);
		$eq($hdr::$code, 404);

		$hdr::$head = [];
		ob_start();
		$hdr::send_file(__FILE__ . '.log', null,
			200, 0, [], false, function(){
				echo __FILE__;
			});
		$rv = ob_get_clean();
		$eq($hdr::$code, 404);
		$eq($rv, __FILE__);

		$hdr::$head = [];
		ob_start();
		$hdr::send_file(__FILE__);
		$rv = ob_get_clean();
		$eq($hdr::$code, 200);
		$eq($rv, file_get_contents(__FILE__));
	}

	public function test_print_json() {
		extract(self::vars());

		ob_start();
		$hdr = new HeaderPatched;
		$hdr::print_json();
		extract(json_decode(ob_get_clean(), true));
		$tr($this->ele_starts_with(
			$hdr::$head, 'Content-Type: application/json'));
		$eq($hdr::$code, 200);
		$eq($errno, 0);
		$nil($data);
	}

	public function test_pj() {
		extract(self::vars());

		ob_start();
		$hdr = new HeaderPatched;
		$hdr::pj([1, null], 403);
		extract(json_decode(ob_get_clean(), true));
		$tr($this->ele_starts_with(
			$hdr::$head, 'Content-Type: application/json'));
		$eq($hdr::$code, 403);
		$eq($errno, 1);

		ob_start();
		# invalid first arg
		$hdr::pj(1, 403);
		extract(json_decode(ob_get_clean(), true));
		$tr($this->ele_starts_with(
			$hdr::$head, 'Content-Type: application/json'));
		# will send HTTP 500 regardless the forbidden code value
		$eq($hdr::$code, 500);
		$eq($errno, -1);
	}

}
