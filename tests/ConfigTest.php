<?php


use BFITech\ZapCore\Config;
use BFITech\ZapCoreDev\RouterDev;
use BFITech\ZapCoreDev\TestCase;


class ConfigTest extends TestCase {

	private $file;

	public function setUp() {
		$this->data = [
			'section_1' => [
				'key_a' => 'value_a',
				'key_b' => 'value_b'
			]
		];
		$this->file = self::tdir(__FILE__) . '/zapcore.json';
		file_put_contents(
			$this->file, json_encode($this->data,
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	}

	public function tearDown() {
		unlink($this->file);
	}

	public function test_constructor() {
		extract($this->vars());

		$file = self::tdir(__FILE__) . '/invalid-zapcore.json';

		# invalid file
		$config = new Config($file);
		$nil($config->get());

		# invalid config
		file_put_contents($file, 'config data');
		$config = new Config($file);
		$nil($config->get());

		# valid config
		$config = new Config($this->file);
		$eq($this->data, $config->get());

		unlink($file);
	}

	public function test_set() {
		extract($this->vars());

		$config = new Config($this->file);

		# non-exist section
		$config->set('section_2');
		$nil($config->get('section_2'));

		# key is string
		$config->set('section_2', 'key_c', 'value_c');
		$this->eq()(
			'value_c', $config->get('section_2', 'key_c'));
	}

	public function test_get() {
		extract($this->vars());

		$config = new Config($this->file);

		# invalid section
		$nil($config->get('section_3'));

		# valid section
		$eq($this->data['section_1'], $config->get('section_1'));

		# invalid key
		$nil($config->get('section_1', 'key_c'));

		# valid key
		$eq(
			$this->data['section_1']['key_a'],
			$config->get('section_1', 'key_a')
		);
	}

}
