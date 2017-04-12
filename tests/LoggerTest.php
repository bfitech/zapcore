<?php


use PHPUnit\Framework\TestCase;
use BFITech\ZapCore\Logger;


class LoggerTest extends TestCase {

	public static $flogs = [];

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

	public function test_logger_write() {
		$fl = getcwd() . '/zapcore-logger-test-01.log';
		self::$flogs[] = $fl;

		$logger = new Logger(Logger::INFO, $fl);
		$this->assertTrue(file_exists($fl));

		# write to logfile w.r.t log level

		$logger->info("Some info.");
		$this->assertTrue($this->str_in_file($fl, 'INF'));

		$logger->warning("Some warning.");
		$this->assertTrue($this->str_in_file($fl, 'WRN'));

		$logger->error("Some error.");
		$this->assertTrue($this->str_in_file($fl, 'ERR'));

		$logger->debug("Some debug.");
		$this->assertFalse($this->str_in_file($fl, 'DEB'));
	}

	public function test_logger_io() {
		$_fl = getcwd() . '/zapcore-logger-test-';

		$fl2 = $_fl . '02.log';
		self::$flogs[] = $fl2;
		touch($fl2);

		$fl3 = $_fl . '03.log';
		self::$flogs[] = $fl3;
		touch($fl3);

		# file handle argument overrides file path
		$logger = new Logger(Logger::DEBUG, $fl2,
			fopen($fl3, 'ab'));
		$logger->debug("Some debug.");
		$this->assertFalse($this->str_in_file($fl2, 'DEB'));
		$this->assertTrue($this->str_in_file($fl3, 'DEB'));

		# automatically write to STDERR if file is read-only
		chmod($fl3, 0400);
		$logger = new Logger(Logger::DEBUG, $fl3);
		# to not clutter terminal, use 2>/dev/null when
		# running test
		$logger->info("XREDIR");
		$this->assertFalse($this->str_in_file($fl3, 'XREDIR'));

		# if chmod-ing happens after opening handle, handle is
		# still writable
		file_put_contents($fl2, "XSTART\n");
		$logger = new Logger(Logger::DEBUG, $fl2);
		chmod($fl2, 0400);
		$logger->info("XNOWRITE");
		$this->assertTrue($this->str_in_file($fl2, 'XSTART'));
		$this->assertTrue($this->str_in_file($fl2, 'XNOWRITE'));
	}

	public function test_logger_activation() {
		$fl = getcwd() . '/zapcore-logger-test-04.log';
		self::$flogs[] = $fl;

		$logger = new Logger(Logger::DEBUG, $fl);
		$logger->info("X01");
		$this->assertTrue($this->str_in_file($fl, 'X01'));

		# temporarily deactivate
		$logger->deactivate();
		$logger->info("X02");
		$this->assertFalse($this->str_in_file($fl, 'X02'));

		# re-activate
		$logger->activate();
		$logger->info("X03");
		$this->assertTrue($this->str_in_file($fl, 'X03'));
	}

}
