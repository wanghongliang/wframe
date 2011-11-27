<?php defined('PATH_SYS') or die('不能直接访问.');


class Config {
 
	protected $_sources = array();

	/**
	 * 添加一个配置文件
	 */
	public function attach( Config_Source $source, $first = TRUE)
	{
		if ($first === TRUE)
		{
			 
			array_unshift($this->_sources, $source);
		}
		else
		{ 
			$this->_sources[] = $source;
		}

		return $this;
	}

	/**
	 * 清除一个配置文件
	 */
	public function detach( Config_Source $source)
	{
		if (($key = array_search($source, $this->_sources)) !== FALSE)
		{
 
			unset($this->_sources[$key]);
		}

		return $this;
	}

	/**
	 * 加载配置信息数组
	 */
	public function load($group)
	{
		if( ! count($this->_sources))
		{
			throw new Core_Exception('没有指定配置文件');
		}

		if (empty($group))
		{
			throw new Core_Exception("需要指定一个配置组");
		}

		if ( ! is_string($group))
		{
			throw new Core_Exception("配置组必需是一个字符串");
		}

		if (strpos($group, '.') !== FALSE)
		{
			// 分割组
			list ($group, $path) = explode('.', $group, 2);
		}

		if(isset($this->_groups[$group]))
		{
			return $this->_groups[$group];
		}

		$config = array();

		$sources = array_reverse($this->_sources);

		foreach ($sources as $source)
		{
 
			if ($source_config = $source->load($group))
			{
				$config = array_merge($config, $source_config);
			}
		 
		}

		$this->_groups[$group] = new Config_Group($this, $group, $config);
 
		return $this->_groups[$group];
	}
 
	public function copy($group)
	{ 
		$config = $this->load($group);

		foreach($config->as_array() as $key => $value)
		{
			$this->_write_config($group, $key, $value);
		}

		return $this;
	}
 
	public function _write_config($group, $key, $value)
	{
		foreach($this->_sources as $source)
		{
			if ( ! ($source instanceof Config_Writer))
			{
				continue;
			} 
			$source->write($group, $key, $value);
		}

		return $this;
	}

} 
