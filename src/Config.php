<?php declare(strict_types=1);


namespace BFITech\ZapCore;


/**
 * Configuration error class.
 */
class ConfigError extends \Exception {

	/** Configuration file not found. */
	const CNF_FILE_NOT_FOUND = 0x0101;
	/** Key separator is invalid. */
	const CNF_SEPARATOR_INVALID = 0x0102;
	/** Configuration file is broken. */
	const CNF_FILE_INVALID = 0x0103;

	/** Key to retrive not found. */
	const GET_KEY_NOT_FOUND = 0x0201;

	/** Setting a key that has a subkey. */
	const SET_HAS_SUBKEY = 0x0201;
	/** Wrongly setting array to scalar value. */
	const SET_VALUE_NOT_SCALAR = 0x0202;
	/** Wrongly setting scalar to array value. */
	const SET_VALUE_NOT_ARRAY = 0x0203;

	/** New key for addition is too short. */
	const ADD_KEY_TOO_SHORT = 0x0301;
	/** New key for addition already exists. */
	const ADD_KEY_FOUND = 0x0302;
	/** New value for addition is an associative array */
	const ADD_VALUE_IS_DICT = 0x0303;

	/** Key for deletion doesn't exist. */
	const DEL_KEY_NOT_FOUND = 0x0401;

	/**
	 * Constructor.
	 *
	 * @param int $errno Error number.
	 * @param string $errmsg Error message.
	 */
	public function __construct(int $errno, string $errmsg) {
		$this->code = $errno;
		$this->message = $errmsg;
	}

}


/**
 * Configuration class.
 *
 * This is a generic class to set and get configuration values.
 * Default backend is JSON, using `json_encode` and `json_decode`.
 *
 * You can implement your own backend with YAML, TOML, XML,
 * or any other format as long as it supports JSON-like arbitrary data
 * structure. You only need to patch Config::read() and Config::write()
 * to do this.
 *
 * ### example
 * @code
 * <?php
 *
 * file_put_contents('config.json', '[]');
 *
 * $cnf = Config('config.json');
 *
 * $cnf->add('tom.yam.goong', 2);
 * print_f($cnf->get('tom.yam');
 * # prints ['goong' => 2]
 *
 * $cnf = Config('config.json', ':');
 *
 * $cnf->set('tom:yam', -1);
 * # throws ConfigError
 *
 * $cnf->set('tom:yam', [null, false]);
 * # throws ConfigError
 *
 * $cnf->set('tom:yam:goong', -1);
 * print_f($cnf->get('tom:yam:goong');
 * # prints -1
 * @endcode
 */
class Config {

	private $cfile;
	private $clist;
	private $sep;

	/**
	 * Valid separators.
	 */
	const SEPARATORS = '.,:;/-_';

	/**
	 * Constructor.
	 *
	 * @param string $cfile Configuration file.
	 * @param string $sep Key-subkey separator.
	 */
	public function __construct(
		string $cfile, string $sep='.'
	) {
		if (!file_exists($cfile))
			$this->throw(
				ConfigError::CNF_FILE_NOT_FOUND,
				"Config file '$cfile' not found.");
		$this->cfile = $cfile;

		$seps = self::SEPARATORS;
		if (strlen($sep) != 1 || strpos($seps, $sep) === false)
			$this->throw(
				ConfigError::CNF_SEPARATOR_INVALID,
				"Allowed section separator: '$seps'.");
		$this->sep = $sep;

		$this->read();
	}

	/**
	 * Standard JSON decoder.
	 *
	 * Only used in this class if you're using default $this->read().
	 *
	 * @note This method relies on `json_decode` under the hood. Numeric
	 *     value in an array is always decoded as float, whether it's
	 *     integer or actually float. However, if the value is not in an
	 *     array, decoding works as expected, and even passes naive
	 *     float comparison.
	 *
	 * @param string $json_str JSON string.
	 * @param bool $throw Throw exception on decoding error.
	 * @return array|null Decoded JSON on success, null on failure.
	 * @throws Exception on decoding error if $throw is set to true.
	 */
	public static function djson(string $json_str, bool $throw=false) {
		$data = json_decode($json_str, true);
		if ($data === null && $throw)
			throw new \Exception("Invalid JSON file.");
		return $data;
	}

	/**
	 * Read from configuration JSON file.
	 *
	 * Patch this if you want to implement your own storage backend.
	 */
	protected function read() {
		$cfile = $this->cfile;
		try {
			$clist = self::djson(file_get_contents($cfile), true);
		} catch(\Exception $err) {
			$this->throw(
				ConfigError::CNF_FILE_INVALID,
				"Config file '$cfile' broken or invalid.");
		}
		$this->clist = $clist;
	}

	/**
	 * Write to configuration JSON file.
	 *
	 * Patch this if you want to implement your own storage backend.
	 */
	protected function write() {
		file_put_contents(
			$this->cfile,
			json_encode($this->clist,
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	}

	private function is_dict(array $arr) {
		return array_keys($arr) !== range(0, count($arr) - 1);
	}

	private function throw(int $errno, string $errmsg) {
		throw new ConfigError($errno, $errmsg);
	}

	/**
	 * Get a value.
	 *
	 * @param string $key Keys joined by separator.
	 * @return mixed Configuration value. If key is null, all
	 *     configuration is returned.
	 * @throws ConfigError when key is invalid.
	 */
	public final function get(string $key=null) {
		if ($key === null)
			return $this->clist;
		$sdata = $this->clist;
		$sep = $this->sep;
		$skey = [];
		$akey = explode($sep, $key);
		while ($akey) {
			$ckey = $akey[0];
			$skey[] = $ckey;
			if (!array_key_exists($ckey, $sdata))
				$this->throw(
					ConfigError::GET_KEY_NOT_FOUND,
					sprintf("Invalid key '%s'.", implode($sep, $skey))
				);
			$sdata = $sdata[$ckey];
			array_shift($akey);
		}
		return $sdata;
	}

	/**
	 * Set a value.
	 *
	 * @param string $key Keys joined by separator.
	 * @param mixed $value Configuration value. Only scalar and
	 *     non-associative array are accepted.
	 * @return void
	 * @throws ConfigError when key or value is invalid.
	 */
	public final function set(string $key, $value) {
		$sdata = $this->get($key);
		if (is_array($sdata) && $this->is_dict($sdata)) {
			$this->throw(
				ConfigError::SET_HAS_SUBKEY,
				"Key '$key' has subkey."
			);
		}
		if (is_array($value) && !is_array($sdata)) {
			$this->throw(
				ConfigError::SET_VALUE_NOT_SCALAR,
				"Key '$key' is an array."
			);
		}
		if (!is_array($value) && is_array($sdata)) {
			$this->throw(
				ConfigError::SET_VALUE_NOT_ARRAY,
				"Key '$key' is not an array."
			);
		}

		$nval =& $this->clist;
		$akey = explode($this->sep, $key);
		while ($akey) {
			$nval =& $nval[$akey[0]];
			array_shift($akey);
		}
		$nval = $value;

		$this->write();
	}

	/**
	 * Add a key.
	 *
	 * @param string $key Keys joined by separator.
	 * @param mixed $value Configuration value. Only scalar and
	 *     non-associative array are accepted.
	 * @return void
	 * @throws ConfigError when key or value is invalid, or key already
	 *     exists.
	 */
	public final function add(string $key, $value) {
		if (strlen($key) < 1)
			$this->throw(
				ConfigError::ADD_KEY_TOO_SHORT,
				"Key name is too short.");

		if (is_array($value) && $this->is_dict($value))
			$this->throw(
				ConfigError::ADD_VALUE_IS_DICT,
				"Value must not be associative array.");

		$sdata = null;
		try {
			$sdata = $this->get($key);
		} catch (ConfigError $err) {
		}
		if ($sdata)
			$this->throw(
				ConfigError::ADD_KEY_FOUND,
				"Key '$key' already exists.");

		$nval =& $this->clist;
		$akey = explode($this->sep, $key);
		while ($akey) {
			$nval =& $nval[$akey[0]];
			array_shift($akey);
		}
		$nval = $value;

		$this->write();
	}

	/**
	 * Remove a key.
	 *
	 * This removes the key even if it has subkeys under it.
	 *
	 * @param string $key Keys joined by separator.
	 * @return void
	 * @throws ConfigError when key is not found.
	 *
	 * @if TRUE
	 * @SuppressWarnings(PHPMD.EvalExpression)
	 * @endif
	 */
	public final function del(string $key) {
		try {
			$this->get($key);
		} catch(ConfigError $err) {
			$this->throw(
				ConfigError::DEL_KEY_NOT_FOUND,
				"Key '$key' not found.");
		}

		$skey = '';
		$akey = explode($this->sep, $key);
		while ($akey) {
			$skey .= sprintf("['%s']", $akey[0]);
			array_shift($akey);
		}
		eval('unset($this->clist' . $skey . ');');

		$this->write();
	}

}
