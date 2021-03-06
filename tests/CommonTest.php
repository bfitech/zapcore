<?php declare(strict_types=1);


use BFITech\ZapCore\Common;
use BFITech\ZapCoreDev\TestCase;


/**
 * Common utilities tests.
 *
 * @requires OS (Linux|Darwin)
 */
class CommonTest extends TestCase {

	public function test_exec() {
		extract(self::vars());
		$cmn = new Common;

		if (!function_exists('exec'))
			$this->markTestSkipped("'exec' is disabled.");

		if (!file_exists('/bin/bash')) {
			$this->markTestSkipped("/bin/bash not available.");
		}

		# escaping works, except for \b in some system
		$param = ":(\ta'\" k[\xc8\xb8aln \20";
		$out = $cmn::exec("echo %s", [$param], $retcode)[0];
		$eq($out, $param);
		$sm($retcode, 0);

		# invalid bash command
		$eq($cmn::exec(
			"bash -c %s 2>/dev/null", ['uwotm8'], $retcode),
			[]);
		$ns($retcode, 0);

		# invalid command
		$eq($cmn::exec(
			"uwotm8 2>/dev/null", [], $retcode),
			[]);
		$ns($retcode, 0);
	}

	public function test_mime() {
		extract(self::vars());
		$cmn = new Common;

		if (!is_dir('/tmp'))
			$this->markTestSkipped("/tmp directory not available.");

		$filebin = $cmn::exec("bash -c %s 2>/dev/null",
			["type -p file"])[0] ?? false;

		foreach ([
			# sample file names, mime and optional content
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
			# generate
			$fname = "/tmp/.zapcore-test-$fbase";
			$content = $fmime[1] ?? " ";
			file_put_contents($fname, $content);

			# auto
			$rmime = $cmn::get_mimetype($fname);
			$sm(strpos($rmime, $fmime[0]), 0);

			# with `file`, usually not available on CI
			if ($filebin) {
				$rmime = $cmn::get_mimetype($fname, $filebin);
				$sm(strpos($rmime, $fmime[0]), 0);
			}

			# last $fname is used by the next block
			if ($fbase != 'xtest.dat')
				unlink($fname);
		}

		# use bogus `file`, in this case, `nologin`
		$cmn::get_mimetype($fname, 'nologin');
		$sm(0,
			strpos(
				$cmn::get_mimetype($fname),
				'application/octet-stream')
			);
		unlink($fname);

		# check this file
		$eq(strpos($cmn::get_mimetype(__FILE__), 'text/x-php'), 0);
	}

	public function test_dict_filter() {
		extract(self::vars());

		$cmn = new Common;

		$eq($cmn::check_dict(['a' => 1], ['b']), false);
		$eq($cmn::check_dict(['a' => 1, 'b' => 2], ['a']), ['a' => 1]);

		$eq(
			$cmn::check_dict(['a' => 1, 'b' => '2 '], ['b'], true),
			['b' => 2]
		);
		$eq(
			$cmn::check_dict(['a' => 1, 'b' => '2 '], ['a', 'b'], true),
			false
		);
		$eq(
			$cmn::check_dict(['a' => 1, 'b' => ' '], ['b'], true),
			false
		);

		$rv = $cmn::check_dict(['a' => '1', 'b' => '2 '], ['a', 'b'],
			true
		);
		$rs = ['a' => 1, 'b' => 2];
		$eq($rv, $rs);
		$ns($rv, $rs);
		$rv = array_map('intval', $rv);
		$sm($rv, $rs);
	}

	public function test_idict_filter() {
		extract(self::vars());

		$cmn = new Common;
		$eq(
			$cmn::check_idict(['a' => '1', 'b' => 'x '], ['a', 'b'],
				true
			),
			['a' => 1, 'b' => 'x']
		);
		$eq(
			$cmn::check_idict(['a' => 1, 'b' => []], ['b']),
			false
		);
		$eq(
			$cmn::check_idict(['a' => 1, 'b' => false], ['b']),
			false
		);
		$eq(
			$cmn::check_idict(['a' => 1, 'b' => null], ['b']),
			false
		);
		$eq(
			$cmn::check_idict(['a' => 1, 'b' => 0], ['b']),
			['b' => 0]
		);
		$eq(
			$cmn::check_idict(['a' => 1, 'b' => 'x'], ['b']),
			['b' => 'x']
		);
	}

	public function test_kwarg_extractor() {
		extract(self::vars());

		extract(Common::extract_kwargs([
			'a' => 1,
			'b' => 'x',
			'd' => [],
		], [
			'a' => null,
			'b' => 'x',
			'c' => false,
		]));

		$eq(isset($c), true);
		$eq(isset($d), false);
		$eq($a, 1);
		$eq($b, 'x');
		$eq($c, false);
	}

}
