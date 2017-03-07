<?php


namespace BFITech\ZapCoreDev;

use BFITech\ZapCore as zc;

class CoreDevError extends \Exception {}


/**
 * CoreDev class.
 */
class CoreDev {


	/**
	 * Start a test server.
	 *
	 * Use this at class setup.
	 *
	 * @param string $starting_point Starting point, whether a
	 *     directory of a PHP script. Defaults to `pwd`.
	 * @param int $port Server port.
	 * @return int PID of server process.
	 */
	public static function server_up($starting_point=null, $port=9999) {

		if (!$starting_point) {
			$dir = getcwd();
		} else {
			if (is_file($starting_point)) {
				$file = realpath($starting_point);
				unset($dir);
			} elseif (is_dir($starting_point)) {
				$dir = realpath($starting_point);
			} else {
				throw new CoreDevError(sprintf(
					"Invalid starting point: '%s'.",
					$starting_point));
				return null;
			}
		}

		$port = (int)$port;
		if ($port < 0  || $port > 65535) {
			throw new CoreDevError(sprintf(
				"Invalid port number: '%s'.", $port));
			return null;
		}

		$srv = 'http://127.0.0.1:' . $port;

		# check if there's a server running
		if (zc\Common::http_client($srv)[0] != 0)
			throw new CoreDevError(
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
		$i = 10000;
		while (1) {
			if (zc\Common::http_client($srv)[0] == 200)
				return $pid;
			sleep(.1);
			$i--;
			if ($i <= 0)
				break;
		}

		# server can't start
		throw new CoreDevError("Cannot start test server.");
		return null;
	}

	/**
	 * Stop a test server.
	 *
	 * Use this at class tear down.
	 *
	 * @param int $pid PID of test server.
	 */
	public static function server_down($pid) {
		exec("kill " . $pid);
	}

}

