<?php


use PHPUnit\Framework\TestCase;
use BFITech\ZapCore as zc;
use BFITech\ZapCoreDev as zd;


class RouterTest extends TestCase {


	public static $server_pid;
	public static $server_addr = 'http://127.0.0.1:9999';

	public static function setUpBeforeClass() {
		self::$server_pid = zd\CoreDev::server_up(__DIR__);
		if (!self::$server_pid)
			die();
	}

	public static function tearDownAfterClass() {
		zd\CoreDev::server_down(self::$server_pid);
	}

	public static function client(
		$url_or_kwargs, $method='GET', $header=[], $get=[], $post=[],
		$curl_opts=[], $expect_json=false
	) {
		return zc\Common::http_client(
			$url_or_kwargs, $method, $header, $get, $post,
			$curl_opts, $expect_json);
	}

	public static function request($kwargs) {
		$kwargs['url'] = self::$server_addr . $kwargs['url'];
		return self::client($kwargs);
	}

	public function test_common() {
		$this->assertEquals(
			zc\Common::exec("echo hello")[0], "hello");

		$this->assertEquals(
			zc\Common::get_mimetype(__FILE__), 'text/x-php');

		$this->assertEquals(
			zc\Common::http_client(self::$server_addr, 'HEAD')[0], 200);
		$this->assertEquals(
			self::client(self::$server_addr)[0], 200);

		$this->assertEquals(
			zc\Common::check_dict(['a' => 1], ['b']), false);

		$this->assertEquals(
			zc\Common::check_dict(['a' => 1, 'b' => 2], ['a']),
			['a' => 1]
		);

		$this->assertEquals(
			zc\Common::check_dict(['a' => 1, 'b' => '2 '], ['b'], true),
			['b' => 2]
		);
		$this->assertEquals(
			zc\Common::check_dict(['a' => 1, 'b' => ' '], ['b'], true),
			false
		);

		extract(zc\Common::extract_kwargs([
			'a' => 1,
			'b' => 'x',
			'd' => [],
		], [
			'a' => null,
			'b' => 'x',
			'c' => false,
		]));
		$this->assertEquals(isset($c), true);
		$this->assertEquals(isset($d), false);
		$this->assertEquals($a, 1);
		$this->assertEquals($b, 'x');
		$this->assertEquals($c, false);
	}

	public function test_header() {
		$ret = self::client(self::$server_addr . '/json', 'GET',
			[], [], [], [], true);
		$this->assertEquals($ret[0], 200);
		$this->assertEquals($ret[1]['errno'], 0);
		$this->assertEquals($ret[1]['data'], 1);

		$ret = self::client(self::$server_addr . '/json', 'POST',
			[], [], [], [CURLOPT_HEADER => true]);
		$this->assertEquals($ret[0], 403);
		$headers = explode("\n", $ret[1]);
		$headers = array_filter($headers, function($h) {
			$hline = explode(":", $h);
			if ($hline[0] != 'Content-Type')
				return false;
			return true;
		});
		$headers = array_map(function($h) {
			return array_map('trim', explode(":", $h));
		}, $headers);
		foreach ($headers as $header) {
			$this->assertEquals($header[0], 'Content-Type');
			$this->assertEquals($header[1], 'application/json');
		}
	}

	public function test_request_components() {
		$ret = self::request([
			'url' => '/X/2/thing',
			'expect_json' => true]);
		$this->assertEquals($ret[0], 404);

		$ret = self::request([
			'url' => '/1/2/thing',
			'expect_json' => true]);
		$this->assertEquals($ret[0], 200);
		$comp = [1, 2, 'thing'];
		$this->assertEquals($ret[1]['data'], $comp);
	}

	public function test_request_method() {
		$data = ['a' => 1, 'b' => 'x'];
		$ret = self::request([
			'url' => '/put/it/down',
			'method' => 'PUT',
			'post' => $data,
			'expect_json' => true,
		]);
		$this->assertEquals($ret[0], 200);
		$this->assertEquals($ret[1]['errno'], 0);
		$this->assertEquals($ret[1]['data'][0], 'PUT');
		parse_str($ret[1]['data'][1], $recv);
		$this->assertEquals($data, $recv);
	}

	public function test_path_variables() {
		$ret = self::request([
			'url' => '/some/3/other/6/thing',
			'expect_json' => true]);
		$this->assertEquals($ret[0], 200);
		extract($ret[1]['data']['params']);
		$this->assertEquals($var1, 3);
		$this->assertEquals($var2, 6);
	}

	public function test_path_long_variables() {
		/**
		 * @caveat
		 *   When using PHP builtin webserver, do not use path
		 *   with dot somewhere, e.g. `/ver/1.20/dl` or `/dl/thing.jpg`.
		 *   PHP will find it in file system instead of using index.php.
		 */
		$ret = self::request([
			'url' => '/some/body/has/2x/that/ends/with/thing',
			'expect_json' => true]);
		$this->assertEquals($ret[0], 200);
		extract($ret[1]['data']['params']);
		$this->assertEquals($dir, 'body/has/2x');
		$this->assertEquals($file, 'thing');
	}

	public function test_redirect() {
		$ret = self::request([
			'url' => '/some/thing',
			'get' => [
				'var1' => 3,
				'var2' => 6,
			],
			'expect_json' => true]);
		$this->assertEquals($ret[0], 200);
		extract($ret[1]['data']['params']);
		$this->assertEquals($var1, 3);
		$this->assertEquals($var2, 6);
	}

	public function test_unimplemented() {
		$ret = self::request([
			'url' => '/put/it/down',
			'method' => 'DELETE'
		]);
		$this->assertEquals($ret[0], 501);
	}
}

