<?php


namespace BFITech\ZapCommonDev;

use BFITech\ZapCore\Common;

class CommonDevError extends \Exception {
}


/**
 * CommonDev class.
 *
 * @todo Non-*nix support.
 */
class CommonDev {

	/**
	 * Start a test server.
	 *
	 * Use this at class setup.
	 *
	 * @param string $starting_point Starting point, whether a
	 *     directory of a PHP script. Defaults to `pwd`.
	 * @param int $port Server port.
	 * @return int PID of server process.
	 * @deprecated
	 *
	 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
	 * @SuppressWarnings(PHPMD.NPathComplexity)
	 */
	final public static function server_up(
		$starting_point=null, $port=9999
	) {

		if (!$starting_point) {
			$dir = getcwd();
		} else {
			if (is_file($starting_point)) {
				$file = realpath($starting_point);
				unset($dir);
			} elseif (is_dir($starting_point)) {
				$dir = realpath($starting_point);
			} else {
				throw new CommonDevError(sprintf(
					"Invalid starting point: '%s'.",
					$starting_point));
			}
		}

		$port = (int)$port;
		if ($port < 0 || $port > 65535)
			throw new CommonDevError(sprintf(
				"Invalid port number: '%s'.", $port));

		$srv = 'http://127.0.0.1:' . $port;

		# check if there's a server running
		# @note Client will obtain http_errno=0 if server is
		#     down and http_errno=-1 if it doesn't support
		#     current method. On the server side, root path of
		#     running server is not necessarily returning 200.
		if (Common::http_client(['url' => $srv])[0] > 0)
			throw new CommonDevError(
				"Server is running. Kill it with fire.");

		$cmd = PHP_BINARY;
		$cmd .= " -S 0.0.0.0:" . $port . " ";
		if (isset($dir))
			$cmd .= '-t ' . escapeshellarg($dir) . " ";
		else
			$cmd .= escapeshellarg($file) . " ";
		$cmd .= " >/dev/null 2>&1 & printf '%u' \$!";

		# bring it up
		exec($cmd, $out);
		$pid = $out[0];

		# wait till it's up
		$wait = 400;
		while (1) {
			if (Common::http_client(['url' => $srv])[0] > 0)
				return $pid;
			sleep(0.1);
			if (--$wait <= 0)
				break;
		}

		# server can't start
		throw new CommonDevError("Cannot start test server.");
	}

	/**
	 * Stop a test server.
	 *
	 * Use this at class tear down.
	 *
	 * @param int $pid PID of test server.
	 */
	final public static function server_down($pid) {
		exec("kill " . $pid);
	}

	/**
	 * Set test directory.
	 *
	 * This puts files generated by tests in single directory
	 * for easier cleanup and permission setup.
	 *
	 * @param string $fname Reference filename, typically a script
	 *     name provided by __FILE__.
	 * @codeCoverageIgnore
	 */
	final public static function testdir($fname) {
		if (defined('__TESTDIR__'))
			return __TESTDIR__;
		define('__TESTDIR__', dirname($fname) . '/testdata');
		if (!is_dir(__TESTDIR__))
			mkdir(__TESTDIR__, 0755);
		return __TESTDIR__;
	}

}
