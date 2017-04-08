<?php


use PHPUnit\Framework\TestCase;
use BFITech\ZapCore\Header;


class HeaderPatched extends Header {
	public static function header($header_string, $replace=false) {
		echo "$header_string\n";
	}
	public static function halt($str=null) {
		if ($str)
			echo $str;
	}
}


class HeaderTest extends TestCase {

	public function test_start_header() {
		ob_start();
		HeaderPatched::start_header(200, 3600);
		$rv = ob_get_clean();
		$this->assertEquals(
			strpos($rv, 'HTTP/1.1 OK'), 0);
		$this->assertEquals(
			strpos($rv, 'Pragma'), false);

		ob_start();
		HeaderPatched::start_header(404, 0, [
			'X-Will-Work-For-Food: 1',
		]);
		$rv = ob_get_clean();
		$this->assertNotEquals(
			strpos($rv, 'Pragma'), false);
	}

	public function test_send_file() {
		ob_start();
		HeaderPatched::send_file(__FILE__, true);
		$rv = ob_get_clean();
		$this->assertNotEquals(
			strpos($rv, file_get_contents(__FILE__)), false);

		ob_start();
		HeaderPatched::send_file(__FILE__ . '.log', null,
			200, 0, [], [], function(){
			echo __FILE__;
		});
		$rv = ob_get_clean();
		$this->assertEquals(
			strpos($rv, 'HTTP/1.1 404'), 0);
		$this->assertNotEquals(
			strpos($rv, __FILE__), false);
	}

	public function test_send_header() {
		ob_start();
		HeaderPatched::send_header(false, 3600, false);
		$rv = ob_get_clean();
		$this->assertNotEquals(
			strpos($rv, 'Cache-Control'), false);

		ob_start();
		HeaderPatched::send_header(false, 0, false);
		$rv = ob_get_clean();
		$this->assertNotEquals(
			strpos($rv, 'Pragma'), false);

		ob_start();
		HeaderPatched::send_header(
			__FILE__, true, true, 302, 'test.php');
		$rv = ob_get_clean();
		$this->assertNotEquals(
			strpos($rv, 'Expire'), false);

		ob_start();
		HeaderPatched::send_header(
			__FILE__, true, true, 200, 'test.php');
		$rv = ob_get_clean();
		$this->assertNotEquals(
			strpos($rv, file_get_contents(__FILE__)), false);
	}

	public function test_print_json() {
		ob_start();
		HeaderPatched::print_json();
		$rv = ob_get_clean();
		$this->assertNotEquals(
			strpos($rv, '"errno":0'), false);
	}

	public function test_pj() {
		ob_start();
		HeaderPatched::pj([1, 403]);
		$rv = ob_get_clean();
		$this->assertNotEquals(
			strpos($rv, '"errno":1'), false);
	}

}

