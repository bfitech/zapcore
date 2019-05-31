<?php


namespace BFITech\ZapCore;


/**
 * Configuration class.
 *
 */
class Config {

	private $config_file;
	private $config_list;

	/**
	 * Constructor.
	 *
	 * @param string $config_file Configuration file.
	 */
	public function __construct(string $config_file=null) {
		if (!file_exists($config_file))
			return;

		$config_list = json_decode(
			file_get_contents($config_file), true);
		if (!$config_list)
			return;

		$this->config_file = $config_file;
		$this->config_list = $config_list;
	}

	/**
	 * Get config.
	 *
	 * @param string $section Configuration section. If null, all
	 *     configuration list is returned. If it doesn't
	 *     match any section, null is returned.
	 * @param string $key Subsection key. If null, all config
	 *     list in the section is returned. If it doesn't
	 *     match any subsection, null is returned.
	 * @return mixed Configuration list or item.
	 */
	public final function get($section=null, $key=null) {
		if (!$section)
			return $this->config_list;
		if (!isset($this->config_list[$section]))
			return null;
		if (!$key)
			return $this->config_list[$section];
		$section_data = $this->config_list[$section];
		if (!isset($section_data[$key]))
			return null;
		return $section_data[$key];
	}

	/**
	 * Set config and save to file.
	 *
	 * @param string $section Configuration section.
	 * @param string|array $key Configuration key or data. If $key is an
	 *     array, $value is dropped and $key replaces it. See
	 *     configuration format above.
	 * @param string $value Configuration value.
	 * @note This doesn't have any sanitation. Use with extra care.
	 */
	public final function set($section, $key=null, $value=null) {
		$config_list =& $this->config_list;
		if (!isset($config_list[$section]))
			$config_list[$section] = [];
		if ($key && !is_array($key)) {
			$config_list[$section][$key] = $value;
		} else {
			$config_list[$section] = $key;
		}
		file_put_contents($this->config_file,
			json_encode($config_list,
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	}

}
