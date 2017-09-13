<?php


use PHPUnit\Framework\TestCase;
use BFITech\ZapCore\Common;
use BFITech\ZapCore\CommonError;
use BFITech\ZapCoreDev\CoreDev;


/**
 * Run core tests via HTTP.
 *
 * @requires OS Linux
 * @todo Support OSes other than Linux.
 */
class RouterHTTPTest extends TestCase {

	public static $server_pid;
	public static $server_addr = 'http://127.0.0.1:9999';
	public static $logfile = null;

	public static function setUpBeforeClass() {
		self::$logfile = __DIR__ . '/zapcore-test.log';
		if (file_exists(self::$logfile))
			unlink(self::$logfile);
		self::$server_pid = CoreDev::server_up(__DIR__);
		if (!self::$server_pid)
			die();
	}

	public static function tearDownAfterClass() {
		CoreDev::server_down(self::$server_pid);
	}

	public static function client(
		$url, $method='GET', $headers=[], $get=[], $post=[],
		$custom_opts=[], $expect_json=false, $is_raw=false
	) {
		return Common::http_client([
			'url' => $url,
			'method' => $method,
			'headers' => $headers,
			'get' => $get,
			'post' => $post,
			'custom_opts' => $custom_opts,
			'expect_json' => $expect_json,
			'is_raw' => $is_raw
		]);
	}

	public static function request($kwargs) {
		$kwargs['url'] = self::$server_addr . $kwargs['url'];
		return Common::http_client($kwargs);
	}

	public function test_environment() {
		try {
			Common::http_client([]);
		} catch(CommonError $e) {
			# URL not set.
		}

		$this->assertEquals(
			Common::http_client([
				'url' => self::$server_addr,
				'method' => 'HEAD'
			])[0], 200);
		$this->assertEquals(
			self::client(self::$server_addr)[0], 200);

		$ret = self::client(self::$server_addr . '/');
		$this->assertEquals($ret[0], 200);
		$this->assertEquals($ret[1], 'Hello Friend');

		$ret = self::client(self::$server_addr . '/', 'POST',
			['Authorization: Basic noop'], [], [], [], true);
		$this->assertEquals($ret[0], 200);
		$data = $ret[1]['data'];
		$this->assertEquals($data['home'], '/');
		$srv = trim(self::$server_addr, '/') . '/';
		$this->assertEquals($data['host'], $srv);

		$data = json_encode(['x' => 1, 'y' => 2]);
		$ret = self::client(self::$server_addr . '/raw', 'POST',
			[], [], $data, [], true, true);
		$this->assertEquals($ret[0], 200);
		$this->assertEquals($ret[1]['data'], $data);

		# curl wrapper doesn't support CONNECT
		$ret = self::request([
			'url' => '/xpatch',
			'method' => 'CONNECT',
		]);
		$this->assertEquals($ret[0], -1);
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

		# trace is not supported
		$ret = self::request([
			'url' => '/xtrace',
			'method' => 'TRACE',
		]);
		$this->assertEquals($ret[0], 405);
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
