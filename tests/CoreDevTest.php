<?php


use PHPUnit\Framework\TestCase;
use BFITech\ZapCoreDev as zd;


class CoreDevTest extends TestCase {

	public static $server_coredev_pid;

	public function test_coredev_error_invalid_docroot() {
		# $this->expectException() on phpunit>=6
		$this->setExpectedException(zd\CoreDevError::class);
		zd\CoreDev::server_up(__DIR__ . '/xxx', 9997);
	}

	public function test_coredev_error_invalid_port() {
		$this->setExpectedException(zd\CoreDevError::class);
		zd\CoreDev::server_up(__DIR__, -1);
	}

	public function test_coredev_error_privileged_port() {
		$this->setExpectedException(zd\CoreDevError::class);
		zd\CoreDev::server_up(__DIR__, 81);
	}

	public function test_coredev_cwd() {
		$server_pid = zd\CoreDev::server_up(null, 9997);
		$this->assertTrue($server_pid > 0);
		zd\CoreDev::server_down($server_pid);
	}

	public function test_coredev_script() {
		$server_pid = zd\CoreDev::server_up(__FILE__, 9996);
		$this->assertTrue($server_pid > 0);
		zd\CoreDev::server_down($server_pid);
	}

	public function test_coredev_up() {
		self::$server_coredev_pid = zd\CoreDev::server_up(__DIR__, 9998);
		$this->assertTrue(self::$server_coredev_pid > 0);
	}

	/**
	 * @depends test_coredev_up
	 */
	public function test_coredev_error_server_running() {
		$this->setExpectedException(zd\CoreDevError::class);
		zd\CoreDev::server_up(__DIR__, 9998);
	}

	/**
	 * @depends test_coredev_error_server_running
	 */
	public function test_coredev_down() {
		$this->assertTrue(self::$server_coredev_pid > 0);
		zd\CoreDev::server_down(self::$server_coredev_pid);
	}

}

