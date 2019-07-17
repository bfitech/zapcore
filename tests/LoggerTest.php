<?php


use BFITech\ZapCore\Logger;
use BFITech\ZapCoreDev\RouterDev;
use BFITech\ZapCoreDev\TestCase;


class LoggerTest extends TestCase {

	public static $flogs = [];
	public static $testdir;

	public static function setUpBeforeClass() {
		self::$testdir = self::tdir(__FILE__);
	}

	public static function tearDownAfterClass() {
		foreach (self::$flogs as $fl) {
			if (file_exists($fl))
				unlink($fl);
		}
	}

	private function str_in_file($path, $str) {
		if (strpos(file_get_contents($path), $str) !== false)
			return true;
		return false;
	}

	public function test_testdir() {
		extract(self::vars());

		$valid_test_basefile = true;
		try {
			self::tdir('/z/z/z');
		} catch(\Exception $err) {
			$valid_test_basefile = false;
		}
		$fl($valid_test_basefile);

		$valid_testdir_basename = true;
		try {
			self::tdir(__FILE__, '');
		} catch(\Exception $err) {
			$valid_testdir_basename = false;
		}
		$fl($valid_testdir_basename);

		$create_testdir_ok = true;
		try {
			self::tdir(__FILE__, '/z/z/z');
		} catch(\Exception $err) {
			$create_testdir_ok = false;
		}
		$fl($create_testdir_ok);
	}

	public function test_constructor() {
		extract(self::vars());

		$fname = self::$testdir . '/zapcore-logger-test-00.log';
		self::$flogs[] = $fname;

		try {
			$logger = new Logger('a', $fname);
		} catch(\TypeError $err) {
		}

		$logger = new Logger(null, $fname);

		$logger->warning("some warning");
		$fl($this->str_in_file($fname, 'some warning'));
		$fl($this->str_in_file($fname, 'WRN'));

		$logger->error("some error");
		$tr($this->str_in_file($fname, 'some error'));
		$tr($this->str_in_file($fname, 'ERR'));

		$res = $logger->get_logger_resource();
		$eq($res[0], Logger::ERROR);
		$eq($res[1], $fname);
	}

	public function test_logger_write() {
		extract(self::vars());

		$fname = self::$testdir . '/zapcore-logger-test-01.log';
		self::$flogs[] = $fname;

		$logger = new Logger(Logger::INFO, $fname);
		$tr(file_exists($fname));

		# write to logfile w.r.t log level

		$logger->info("Some info.");
		$tr($this->str_in_file($fname, 'INF'));

		$logger->warning("Some warning.");
		$tr($this->str_in_file($fname, 'WRN'));

		$res = $logger->get_logger_resource();
		$eq($res[0], Logger::INFO);
		$eq($res[1], $fname);

		$logger->error("Some error.");
		$tr($this->str_in_file($fname, 'ERR'));

		$logger->debug("Some debug.");
		$fl($this->str_in_file($fname, 'DEB'));
	}

	public function test_logger_io() {
		extract(self::vars());

		$pfx = self::$testdir . '/zapcore-logger-test-';

		$fl2 = $pfx . '02.log';
		self::$flogs[] = $fl2;
		touch($fl2);

		$fl3 = $pfx . '03.log';
		self::$flogs[] = $fl3;
		touch($fl3);

		# file handle argument overrides file path
		$logger = new Logger(Logger::DEBUG, $fl2, fopen($fl3, 'ab'));
		$logger->debug("Some debug.");
		# path becomes null
		$nil($logger->get_logger_resource()[1]);
		# nothing was written to initial files
		$fl($this->str_in_file($fl2, 'DEB'));
		# log is written to file handle instead
		$tr($this->str_in_file($fl3, 'DEB'));

		# automatically write to STDERR if file is read-only
		chmod($fl3, 0400);
		$logger = new Logger(Logger::DEBUG, $fl3);
		# this will show up on screen, use 2>/dev/null to silent
		$logger->info("XREDIR 01");
		$eq($logger->get_logger_resource()[1], 'php://stderr');
		$fl($this->str_in_file($fl3, 'XREDIR'));

		# if chmod-ing happens after opening handle, handle is
		# still writable
		file_put_contents($fl2, "XSTART\n");
		$logger = new Logger(Logger::DEBUG, $fl2);
		chmod($fl2, 0400);
		$logger->info("XNOWRITE");
		$tr($this->str_in_file($fl2, 'XSTART'));
		$tr($this->str_in_file($fl2, 'XNOWRITE'));

		### simulate unwritable directory
		chmod(self::$testdir, 0500);
		$logger = new Logger(Logger::DEBUG, $fl3);
		list($level, $path, $_) = $logger->get_logger_resource();
		# level is preserved
		$eq($level, Logger::DEBUG);
		# path becomes /dev/stderr
		$eq($path, 'php://stderr');
		# this will show up on screen, use 2>/dev/null to silent
		$logger->debug("XREDIR 02");
		### restore permission back
		chmod(self::$testdir, 0755);
	}

}
