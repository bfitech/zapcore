<?php


namespace BFITech\ZapCore;


/**
 * Logging class.
 */
class Logger {

	/** Debug log level constant. */
	const DEBUG   = 0x10;
	/** Info log level constant. */
	const INFO    = 0x20;
	/** Warning log level constant. */
	const WARNING = 0x30;
	/** Error log level constant. */
	const ERROR   = 0x40;

	private $level = self::ERROR;
	private $path = null;
	private $handle = null;

	/**
	 * Constructor.
	 *
	 * @param int $level Log level.
	 * @param string $path Path to log file. If null, stderr is used.
	 * @param resource $handle Log file handle. $path is ignored if this
	 *     is not null.
	 */
	public function __construct($level=null, $path=null, $handle=null) {
		if ($level)
			$this->level = @(int)$level;
		if (!$this->level)
			$this->level = self::ERROR;
		if ($handle) {
			$this->handle = $handle;
			return;
		}
		$this->path = $path ? $path : 'php://stderr';
		try {
			$this->handle = fopen($this->path, 'ab');
		} catch(\Exception $e) {
			$this->handle = STDERR;
		}
	}

	/**
	 * Log line formatter.
	 *
	 * Patch this to customize line format or add additional
	 * information.
	 *
	 * @param string $timestamp Timestamp, always in UTC ISO-8601.
	 * @param int $level Log level of current log event.
	 * @param string $msg Error message.
	 * @return string Formatted line.
	 */
	protected function format($timestamp, $level, $msg) {
		$fmt = "[%s] %s: %s\n";
		return sprintf($fmt, $timestamp, $level, $msg);
	}

	/**
	 * Write to handle.
	 */
	private function write($level, $msg) {
		$timestamp = gmdate(\DateTime::ATOM);
		try {
			$line = $this->format($timestamp, $level, $msg);
			fwrite($this->handle, $line);
		} catch(\Exception $e) {}
	}

	/**
	 * Debug.
	 *
	 * @param string $msg Error message.
	 */
	public function debug($msg) {
		if ($this->level > self::DEBUG)
			return;
		$this->write('DEB', $msg);
	}

	/**
	 * Info.
	 *
	 * @param string $msg Error message.
	 */
	public function info($msg) {
		if ($this->level > self::INFO)
			return;
		$this->write('INF', $msg);
	}

	/**
	 * Warning.
	 *
	 * @param string $msg Error message.
	 */
	public function warning($msg) {
		if ($this->level > self::WARNING)
			return;
		$this->write('WRN', $msg);
	}

	/**
	 * Error.
	 *
	 * @param string $msg Error message.
	 */
	public function error($msg) {
		if ($this->level > self::ERROR)
			return;
		$this->write('ERR', $msg);
	}
}

