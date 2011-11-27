<?php defined('PATH_SYS') or die('不能直接访问.');
/** 
 */
class Config_File 
{
	protected $_directory = '';
 
	public function __construct($directory = 'config')
	{
 
		$this->_directory = trim($directory, '/');
	}
 
	public function load($group)
	{
		$config = array();

		if ($files = Core::find_file($this->_directory, $group, NULL, TRUE))
		{
			foreach ($files as $file)
			{
 
				$config = array_merge($config, Core::load($file));
			}
		}

		return $config;
	}
}
