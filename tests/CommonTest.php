<?php


use PHPUnit\Framework\TestCase;
use BFITech\ZapCore\Common;


/**
 * Common utilities tests.
 *
 * @requires OS Linux
 * @todo Support OSes other than Linux.
 */
class CoreTest extends TestCase {

	private function bail($msg) {
		echo "ERROR: $msg\n";
		exit(1);
	}

	public function test_mime() {
		$cmn = new Common;

		if (!function_exists('exec'))
			$this->bail("'exec' is disabled.");

		if (file_exists('/bin/bash')) {
			$this->assertEquals(
				$cmn::exec("echo hello")[0], "hello");
			$this->assertEquals(
				$cmn::exec("bash -c uwotm8 2>/dev/null")[0], "");
		} else {
			$this->bail("/bin/bash not available.");
		}

		$filebin = $cmn::exec("bash -c 'type -p file'")[0];

		foreach ([
			'xtest.htm'    => ['text/html; charset=utf-8'],
			'xtest.HTML'   => ['text/html; charset=utf-8'],
			'xtest.css'    => ['text/css'],
			'xtest.json'   => ['application/json'],
			'xtest.min.js' => ['application/javascript'],
			'xtest.pdf'    => [
				'application/pdf',
				pack('H*', "255044462d312e340a25"),
			],
			'xtest.dat'    => [
				'application/octet-stream',
				pack('H*', "F00F00F00F00F00F00F0")
			],
		] as $fbase => $fmime) {
			if (!is_dir('/tmp'))
				continue;
			$fname = "/tmp/zapcore-test-$fbase";
			$content = isset($fmime[1]) ? $fmime[1] : " ";
			file_put_contents($fname, $content);

			# auto
			$rmime = $cmn::get_mimetype($fname);
			$this->assertSame(strpos($rmime, $fmime[0]), 0);
			# with `file`
			if ($filebin) {
				$rmime = $cmn::get_mimetype($fname, $filebin);
				$this->assertSame(strpos($rmime, $fmime[0]), 0);
			}

			# last $fname is used by the next block
			if ($fbase != 'xtest.dat')
				unlink($fname);
		}

		if (file_exists($fname)) {
			# use bogus `file`, in this case, `nologin`
			$cmn::get_mimetype($fname, 'nologin');
			$this->assertSame(0,
				strpos(
					$cmn::get_mimetype($fname),
					'application/octet-stream')
				);
			unlink($fname);
		}

		$this->assertEquals(
			strpos($cmn::get_mimetype(__FILE__), 'text/x-php'), 0);
	}

	public function test_dict_filter() {
		$cmn = new Common;
		$this->assertEquals(
			$cmn::check_dict(['a' => 1], ['b']), false);

		$this->assertEquals(
			$cmn::check_dict(['a' => 1, 'b' => 2], ['a']),
			['a' => 1]
		);

		$this->assertEquals(
			$cmn::check_dict(['a' => 1, 'b' => '2 '], ['b'], true),
			['b' => 2]
		);
		$this->assertEquals(
			$cmn::check_dict(['a' => 1, 'b' => '2 '], ['a', 'b'], true),
			false
		);
		$this->assertEquals(
			$cmn::check_dict(['a' => 1, 'b' => ' '], ['b'], true),
			false
		);
		$rv = $cmn::check_dict(['a' => '1', 'b' => '2 '], ['a', 'b'],
			true
		);
		$rs = ['a' => 1, 'b' => 2];
		$this->assertEquals($rv, $rs);
		$this->assertNotSame($rv, $rs);
		$rv = array_map('intval', $rv);
		$this->assertSame($rv, $rs);
	}

	public function test_idict_filter() {
		$cmn = new Common;
		$this->assertEquals(
			$cmn::check_idict(['a' => '1', 'b' => 'x '], ['a', 'b'],
				true
			),
			['a' => 1, 'b' => 'x']
		);
		$this->assertEquals(
			$cmn::check_idict(['a' => 1, 'b' => []], ['b']),
			false
		);
		$this->assertEquals(
			$cmn::check_idict(['a' => 1, 'b' => false], ['b']),
			false
		);
		$this->assertEquals(
			$cmn::check_idict(['a' => 1, 'b' => null], ['b']),
			false
		);
		$this->assertEquals(
			$cmn::check_idict(['a' => 1, 'b' => 0], ['b']),
			['b' => 0]
		);
		$this->assertEquals(
			$cmn::check_idict(['a' => 1, 'b' => 'x'], ['b']),
			['b' => 'x']
		);
	}

	public function test_kwarg_extractor() {

		extract(Common::extract_kwargs([
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

}
