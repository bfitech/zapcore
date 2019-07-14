<?php declare(strict_types=1);


namespace BFITech\ZapCore;


/**
 * Logging class.
 */
class Logger {

	/** Debug log level. */
	const DEBUG = 0x10;
	/** Info log level. */
	const INFO = 0x20;
	/** Warning log level. */
	const WARNING = 0x30;
	/** Error log level. */
	const ERROR = 0x40;

	private $level = self::ERROR;
	private $path = null;
	private $handle = null;

	/**
	 * Constructor.
	 *
	 * @param int $level Log level.
	 * @param string $path Path to log file. If null, stderr is used.
	 * @param resource $handle Log file handle. $path is ignored if this
	 *     is not null. Write to stderr if it's not writable.
	 * @todo Write to syslog.
	 */
	public function __construct(
		int $level=null, string $path=null, $handle=null
	) {
		$this->level = $level;
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
	 * @param string $levelstr String representation of current log
	 *     level, e.g. `DEB` for debug.
	 * @param string $msg Error message.
	 * @return string Formatted line.
	 */
	protected function format(
		string $timestamp, string $levelstr, string $msg
	) {
		$fmt = "[%s] %s: %s\n";
		return sprintf($fmt, $timestamp, $levelstr, $msg);
	}

	/**
	 * Write lines as single line, with tab, CR and LF written
	 * symbolically.
	 */
	private function one_line(string $msg) {
		$msg = trim($msg);
		$msg = str_replace([
			"\t", "\n", "\r",
		], [
			'\t', '\n', '\r',
		], $msg);
		return preg_replace('! +!', ' ', $msg);
	}

	/**
	 * Write to handle.
	 */
	private function write(string $levelstr, string $msg) {
		$timestamp = gmdate(\DateTime::ATOM);
			// @codeCoverageIgnoreStart
		try {
			// @codeCoverageIgnoreEnd
			$msg = $this->one_line($msg);
			$line = $this->format($timestamp, $levelstr, $msg);
			fwrite($this->handle, $line);
			// @codeCoverageIgnoreStart
		} catch(\Exception $e) {
		}
			// @codeCoverageIgnoreEnd
	}

	/**
	 * Debug.
	 *
	 * @param string $msg Error message.
	 */
	public function debug(string $msg) {
		if ($this->level > self::DEBUG)
			return;
		$this->write('DEB', $msg);
	}

	/**
	 * Info.
	 *
	 * @param string $msg Error message.
	 */
	public function info(string $msg) {
		if ($this->level > self::INFO)
			return;
		$this->write('INF', $msg);
	}

	/**
	 * Warning.
	 *
	 * @param string $msg Error message.
	 */
	public function warning(string $msg) {
		if ($this->level > self::WARNING)
			return;
		$this->write('WRN', $msg);
	}

	/**
	 * Error.
	 *
	 * @param string $msg Error message.
	 */
	public function error(string $msg) {
		$this->write('ERR', $msg);
	}

}
