<?php


use BFITech\ZapCore\Config;
use BFITech\ZapCore\ConfigError;
use BFITech\ZapCoreDev\RouterDev;
use BFITech\ZapCoreDev\TestCase;


class ConfigTest extends TestCase {

	private static $cfile;

	public function setUp() {
		$this->data = [
			's0' => [
				'k0' => 'v0',
				'k1' => null,
				'k2' => 65535,
				'k3' => [1e2, 1e2 + 1],
				'k4' => M_PI,
			],
			's1' => [
				'k0' => ['a', 'b', 1e3],
			],
		];
		self::$cfile = self::tdir(__FILE__) . '/zapconfig.json';
		file_put_contents(
			self::$cfile, json_encode($this->data,
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	}

	public function tearDown() {
		unlink(self::$cfile);
	}

	public function test_constructor() {
		extract($this->vars());

		# file doesn't exist
		$code = 0;
		try {
			new Config('/z/z/z');
		} catch (ConfigError $err) {
			$code = $err->getCode();
		}
		$eq($code, ConfigError::CNF_FILE_NOT_FOUND);

		### create dummy file with non-standard key names
		$cfile = self::tdir(__FILE__) . '/zapconfig-broken.json';
		$cdata = [
			's0.x' => [
				'w:t' => 'xoxo',
			],
		];
		file_put_contents($cfile, json_encode($cdata));

		# separator invalid
		$code = 0;
		try {
			new Config($cfile, 'a');
		} catch (ConfigError $err) {
			$code = $err->getCode();
		}
		$eq($code, ConfigError::CNF_SEPARATOR_INVALID);

		# success
		$cnf = new Config($cfile, '/');
		$eq($cnf->get('s0.x/w:t'), 'xoxo');
		$sm($cnf->get(), $cdata);

		### break the file
		file_put_contents($cfile, 'breakit', FILE_APPEND);

		# decoding is broken
		$broken = false;
		try {
			Config::djson($cfile, true);
		} catch (\Exception $err) {
			$broken = true;
		}
		$tr($broken);

		# file is broken
		$code = 0;
		try {
			new Config($cfile);
		} catch (ConfigError $err) {
			$code = $err->getCode();
		}
		$sm($code, ConfigError::CNF_FILE_INVALID);

		unlink($cfile);
	}

	public function test_get() {
		extract($this->vars());

		$cnf = new Config(self::$cfile);

		# null key
		$sm($cnf->get()['s0']['k4'], M_PI);

		# these arrays are equal
		$eq($cnf->get('s1.k0'), ['a', 'b', 1e3]);
		# these arrays are not the same because the last element was
		# decoded as float.
		$ns($cnf->get('s1.k0'), ['a', 'b', 1e3]);

		# invalid key
		$code = 0;
		try {
			$cnf->get('s3');
		} catch (ConfigError $err) {
			$code = $err->getCode();
		}
		$eq(ConfigError::GET_KEY_NOT_FOUND, $code);
		$code = 0;
		try {
			$cnf->get('s1.k1');
		} catch (ConfigError $err) {
			$code = $err->getCode();
		}
		$eq(ConfigError::GET_KEY_NOT_FOUND, $code);

		# string
		$eq($cnf->get('s0.k0'), 'v0');

		# null
		$nil($cnf->get('s0.k1'));

		# integer
		$eq($cnf->get('s0.k2'), 2 ** 16 - 1);

		# number inside array, always decoded to float by json_decode
		$eq($cnf->get('s0.k3')[1], 1e2 + 1);
		$ns($cnf->get('s0.k3')[1], 1e2 + 1);

		# number
		$sm($cnf->get('s0.k4'), M_PI);
		$eq($cnf->get('s0.k4'), M_PI);
	}

	public function test_set() {
		extract($this->vars());

		$cnf = new Config(self::$cfile);

		# setting key that has subkey
		$code = 0;
		try {
			$cnf->set('s0', 2);
		} catch (ConfigError $err) {
			$code = $err->getCode();
		}
		$eq(ConfigError::SET_HAS_SUBKEY, $code);

		# invalid array value
		$code = 0;
		try {
			$cnf->set('s1.k0', 2);
		} catch (ConfigError $err) {
			$code = $err->getCode();
		}
		$eq(ConfigError::SET_VALUE_NOT_ARRAY, $code);

		# invalid scalar value
		$code = 0;
		try {
			$cnf->set('s0.k0', [2]);
		} catch (ConfigError $err) {
			$code = $err->getCode();
		}
		$eq(ConfigError::SET_VALUE_NOT_SCALAR, $code);

		# valid scalar value
		$cnf->set('s0.k0', 'v0x');
		### comparison with the same config instance
		$sm($cnf->get('s0.k0'), 'v0x');

		# valid array value
		$cnf->set('s0.k3', ['x', 'y']);

		# valid, by re-reading from config file
		$ncnf = new Config(self::$cfile);
		$sm($ncnf->get('s0.k3'), ['x', 'y']);
	}

	public function test_add() {
		extract($this->vars());

		$cnf = new Config(self::$cfile);

		# invalid key
		$code = 0;
		try {
			$cnf->add('', 2);
		} catch (ConfigError $err) {
			$code = $err->getCode();
		}
		$eq(ConfigError::ADD_KEY_TOO_SHORT, $code);

		# value is assoc
		$code = 0;
		try {
			$cnf->add('s0.k7', ['a' => null]);
		} catch (ConfigError $err) {
			$code = $err->getCode();
		}
		$eq(ConfigError::ADD_VALUE_IS_DICT, $code);

		# key exists
		$code = 0;
		try {
			$cnf->add('s0.k4', 2);
		} catch (ConfigError $err) {
			$code = $err->getCode();
		}
		$eq(ConfigError::ADD_KEY_FOUND, $code);

		# success
		$cnf->add('s2.k3.l3', false);
		$fl($cnf->get('s2.k3.l3'));

		# success, with re-reading config file
		$ncnf = new Config(self::$cfile);
		$sm($ncnf->get('s2.k3.l3'), false);
	}

	public function test_del() {
		extract($this->vars());

		$cnf = new Config(self::$cfile);

		# invalid key
		$code = 0;
		try {
			$cnf->del('s3');
		} catch (ConfigError $err) {
			$code = $err->getCode();
		}
		$eq(ConfigError::DEL_KEY_NOT_FOUND, $code);

		# success
		$cnf->del('s0.k2');
		$code = 0;
		try {
			$cnf->get('s0.k2');
		} catch(ConfigError $err) {
			$code = $err->getCode();
		}
		$eq(ConfigError::GET_KEY_NOT_FOUND, $code);

		# success, with re-rading config file
		$cnf->del('s0');
		$code = 0;
		$ncnf = new Config(self::$cfile);
		try {
			$ncnf->get('s0');
		} catch(ConfigError $err) {
			$code = $err->getCode();
		}
		$eq(ConfigError::GET_KEY_NOT_FOUND, $code);
	}

}
