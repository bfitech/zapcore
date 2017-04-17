<?php


use PHPUnit\Framework\TestCase;
use BFITech\ZapCore\Common;


class CoreTest extends TestCase {

	public function test_mime() {
		if (file_exists('/bin/bash'))
			$this->assertEquals(
				Common::exec("echo hello")[0], "hello");

		foreach ([
			'xtest.htm' => ['text/html; charset=utf-8'],
			'xtest.HTML' => ['text/html; charset=utf-8'],
			'xtest.css' => ['text/css'],
			'xtest.json' => ['application/json'],
			'xtest.min.js' => ['application/javascript'],
			'xtest.pdf' => [
				'application/pdf',
				pack('H*', "255044462d312e340a25"),
			],
			'xtest.dat' => [
				'application/octet-stream',
				pack('H*', "F00F00F00F00F00F00F0")
			],
		] as $fbase => $fmime) {
			if (!is_dir('/tmp'))
				continue;
			$fname = "/tmp/zapcore-test-$fbase";
			$content = isset($fmime[1]) ? $fmime[1] : " ";
			file_put_contents($fname, $content);
			$rmime = Common::get_mimetype($fname);
			$this->assertSame(strpos($rmime, $fmime[0]), 0);
			# last $fname is used by the next block
			if ($fbase != 'xtest.dat')
				unlink($fname);
		}
		if (file_exists($fname)) {
			# use bogus `file`, in this case, PHP interpreter
			Common::get_mimetype($fname, PHP_BINARY);
			$this->assertEquals(
				Common::get_mimetype($fname),
				'application/octet-stream');
			unlink($fname);
		}

		$this->assertEquals(
			strpos(Common::get_mimetype(__FILE__), 'text/x-php'), 0);

	}

	public function test_dict_filter() {

		$this->assertEquals(
			Common::check_dict(['a' => 1], ['b']), false);

		$this->assertEquals(
			Common::check_dict(['a' => 1, 'b' => 2], ['a']),
			['a' => 1]
		);

		$this->assertEquals(
			Common::check_dict(['a' => 1, 'b' => '2 '], ['b'], true),
			['b' => 2]
		);
		$this->assertEquals(
			Common::check_dict(['a' => 1, 'b' => '2 '], ['a', 'b'], true),
			false
		);
		$this->assertEquals(
			Common::check_dict(['a' => 1, 'b' => ' '], ['b'], true),
			false
		);
		$rv = Common::check_dict(['a' => '1', 'b' => '2 '], ['a', 'b'], true);
		$rs = ['a' => 1, 'b' => 2];
		$this->assertEquals($rv, $rs);
		$this->assertNotSame($rv, $rs);
		$rv = array_map('intval', $rv);
		$this->assertSame($rv, $rs);
	}

	public function test_idict_filter() {

		$this->assertEquals(
			Common::check_idict(['a' => '1', 'b' => 'x '], ['a', 'b'], true),
			['a' => 1, 'b' => 'x']
		);

		$this->assertEquals(
			Common::check_idict(['a' => 1, 'b' => []], ['b']),
			false
		);
		$this->assertEquals(
			Common::check_idict(['a' => 1, 'b' => false], ['b']),
			false
		);
		$this->assertEquals(
			Common::check_idict(['a' => 1, 'b' => null], ['b']),
			false
		);
		$this->assertEquals(
			Common::check_idict(['a' => 1, 'b' => 0], ['b']),
			['b' => 0]
		);
		$this->assertEquals(
			Common::check_idict(['a' => 1, 'b' => 'x'], ['b']),
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

