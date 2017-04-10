<?php


use PHPUnit\Framework\TestCase;
use BFITech\ZapCore\Header;


class HeaderPatched extends Header {

	public static $code = 200;
	public static $head = [];

	public static function header($header_string, $replace=false) {
		if (strpos($header_string, 'HTTP/1.') === 0)
			static::$code = (int)explode(' ', $header_string)[1];
		else
			static::$head[] = $header_string;
	}
	public static function halt($str=null) {
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
		HeaderPatched::start_header(200, 3600);
		$this->assertEquals(HeaderPatched::$code, 200);
		$this->assertFalse($this->ele_starts_with(
			HeaderPatched::$head, 'Pragma'));

		HeaderPatched::start_header(404, 0, [
			'X-Will-Work-For-Food: 1',
		]);
		$this->assertEquals(HeaderPatched::$code, 404);
		$this->assertTrue($this->ele_starts_with(
			HeaderPatched::$head, 'Pragma'));
	}

	public function test_send_file() {
		HeaderPatched::send_file(__FILE__, true,
			200, 0, [], 'X-Sendfile: ' . __FILE__);
		$this->assertEquals(HeaderPatched::$code, 200);

		ob_start();
		HeaderPatched::send_file(__FILE__ . '.log', null,
			200, 0, [], [], function(){
			echo __FILE__;
		});
		$rv = ob_get_clean();
		$this->assertEquals(HeaderPatched::$code, 404);
		$this->assertEquals($rv, __FILE__);

		ob_start();
		HeaderPatched::send_file(__FILE__);
		$rv = ob_get_clean();
		$this->assertEquals(HeaderPatched::$code, 200);
		$this->assertEquals($rv, file_get_contents(__FILE__));
	}

	/**
	 * @deprecated
	 */
	public function test_send_header() {
		HeaderPatched::send_header(false, 3600, false);
		$this->assertTrue($this->ele_starts_with(
			HeaderPatched::$head, 'Cache-Control'));

		HeaderPatched::send_header(false, 0, false);
		$this->assertTrue($this->ele_starts_with(
			HeaderPatched::$head, 'Pragma'));

		ob_start();
		HeaderPatched::send_header(
			__FILE__, true, true, 302, 'test.php');
		$rv = ob_get_clean();
		$this->assertTrue($this->ele_starts_with(
			HeaderPatched::$head, 'Expire'));

		ob_start();
		HeaderPatched::send_header(
			__FILE__, true, true, 200, 'test.php');
		$rv = ob_get_clean();
		$this->assertEquals(
			$rv, file_get_contents(__FILE__));
	}

	public function test_print_json() {
		ob_start();
		HeaderPatched::print_json();
		extract(json_decode(ob_get_clean(), true));
		$this->assertTrue($this->ele_starts_with(
			HeaderPatched::$head, 'Content-Type: application/json'));
		$this->assertEquals(HeaderPatched::$code, 200);
		$this->assertEquals($errno, 0);
		$this->assertSame($data, []);
	}

	public function test_pj() {
		ob_start();
		HeaderPatched::pj([1, null], 403);
		extract(json_decode(ob_get_clean(), true));
		$this->assertTrue($this->ele_starts_with(
			HeaderPatched::$head, 'Content-Type: application/json'));
		$this->assertEquals(HeaderPatched::$code, 403);
		$this->assertEquals($errno, 1);
	}

}

