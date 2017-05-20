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
	private $is_active = true;

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
	 * Write lines as single line, with tab, CR and LF written
	 * symbolically.
	 */
	private function one_line($msg) {
		$msg = trim($msg);
		$msg = str_replace([
			"\t", "\n", "\r",
		], [
			" ", '\n', '\r',
		], $msg);
		return preg_replace('! +!', ' ', $msg);
	}

	/**
	 * Enable writing.
	 *
	 * Use this to re-enable logging after temporary deactivation.
	 */
	final public function activate() {
		$this->is_active = true;
	}

	/**
	 * Disable writing.
	 *
	 * Useful when it is necessary to temporarily disable all
	 * logging activities, e.g. to stop logger from writing
	 * sensitive information.
	 */
	final public function deactivate() {
		$this->is_active = false;
	}

	/**
	 * Write to handle.
	 */
	private function write($level, $msg) {
		if (!$this->is_active)
			return;
		$timestamp = gmdate(\DateTime::ATOM);
		try {
			$msg = $this->one_line($msg);
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

