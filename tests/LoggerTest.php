<?php


use PHPUnit\Framework\TestCase;
use BFITech\ZapCore\Logger;


class LoggerTest extends TestCase {

	public function test_logger() {
		$logfile = getcwd() . '/zapcore-logger-test.log';
		if (file_exists($logfile))
			unlink($logfile);
		$logger = new Logger(Logger::INFO, $logfile);
		$this->assertTrue(file_exists($logfile));

		# write to logfile
		$logger->info("Some info.");
		$this->assertNotEquals(
			strpos(file_get_contents($logfile), 'INF'), false);
		$logger->warning("Some warning.");
		$this->assertNotEquals(
			strpos(file_get_contents($logfile), 'WRN'), false);
		$logger->error("Some error.");
		$this->assertNotEquals(
			strpos(file_get_contents($logfile), 'ERR'), false);
		$logger->debug("Some debug.");
		$this->assertEquals(
			strpos(file_get_contents($logfile), 'DEB'), false);

		# file handle argument overrides file path
		$logfile_02 = $logfile . '.log';
		$logger = new Logger(Logger::DEBUG, $logfile,
			fopen($logfile_02, 'ab'));
		$logger->debug("Some debug.");
		$this->assertEquals(
			strpos(file_get_contents($logfile), 'DEB'), false);

		# automatically write to STDERR if file is read-only
		chmod($logfile, 0400);
		$logger = new Logger(Logger::DEBUG, $logfile);
		# to not clutter terminal, use 2>/dev/null
		$logger->info("Auto-redirect logging to STDERR.");
		$this->assertEquals(
			strpos(file_get_contents($logfile), 'STDERR'), false);

		# if chmod-ing happens after opening handle, handle is
		# still writable
		$logfile_03 = $logfile_02 . '.log';
		if (file_exists($logfile_03))
			unlink($logfile_03);
		file_put_contents($logfile_03, "START\n");
		$logger = new Logger(Logger::DEBUG, $logfile_03);
		chmod($logfile_03, 0400);
		$logger->info("Some info.");
		$content = file_get_contents($logfile_03);
		$this->assertEquals(substr($content, 0, 5), "START");
		$this->assertNotEquals(strpos($content, "INF"), false);

		foreach ([$logfile, $logfile_02, $logfile_03] as $fl)
			unlink($fl);
	}

}
