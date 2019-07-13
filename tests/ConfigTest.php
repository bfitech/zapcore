<?php


use PHPUnit\Framework\TestCase;
use BFITech\ZapCore\Config;
use BFITech\ZapCoreDev\RouterDev;


class ConfigTest extends TestCase {

	private $file;

	public function setUp() {
		$this->data = [
			'section_1' => [
				'key_a' => 'value_a',
				'key_b' => 'value_b'
			]
		];
		$this->file = RouterDev::testdir(__FILE__) . '/zapcore.json';
		file_put_contents(
			$this->file, json_encode($this->data,
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	}

	public function tearDown() {
		unlink($this->file);
	}

	public function test_constructor() {
		$file = RouterDev::testdir(__FILE__) . '/invalid-zapcore.json';

		# invalid file
		$config = new Config($file);
		$this->assertNull($config->get());

		# invalid config
		file_put_contents($file, 'config data');
		$config = new Config($file);
		$this->assertNull($config->get());

		# valid config
		$config = new Config($this->file);
		$this->assertEquals($this->data, $config->get());

		unlink($file);
	}

	public function test_set() {
		$config = new Config($this->file);

		# non-exist section
		$config->set('section_2');
		$this->assertNull($config->get('section_2'));

		# key is string
		$config->set('section_2', 'key_c', 'value_c');
		$this->assertEquals(
			'value_c', $config->get('section_2', 'key_c'));
	}

	public function test_get() {
		$config = new Config($this->file);

		# invalid section
		$this->assertNull($config->get('section_3'));

		# valid section
		$this->assertEquals(
			$this->data['section_1'], $config->get('section_1'));

		# invalid key
		$this->assertNull($config->get('section_1', 'key_c'));

		# valid key
		$this->assertEquals(
			$this->data['section_1']['key_a'], $config->get(
				'section_1', 'key_a'));
	}

}
