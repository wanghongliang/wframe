<?php defined('PATH_SYS') or die('不能直接访问.');
 
class Config_Group extends ArrayObject {

 
	protected $_parent_instance = NULL;
 
	protected $_group_name = '';
 
	public function __construct( Config $instance, $group, array $config = array())
	{
		$this->_parent_instance = $instance;
		$this->_group_name      = $group; 
		parent::__construct($config, ArrayObject::ARRAY_AS_PROPS);
	}
 
	public function __toString()
	{
		return serialize($this->getArrayCopy());
	}
 
	public function as_array()
	{
		return $this->getArrayCopy();
	} 
	public function group_name()
	{
		return $this->_group_name;
	} 
	public function get($key, $default = NULL)
	{
		return $this->offsetExists($key) ? $this->offsetGet($key) : $default;
	} 
	public function set($key, $value)
	{
		$this->offsetSet($key, $value);

		return $this;
	} 
	public function offsetSet($key, $value)
	{
		$this->_parent_instance->_write_config($this->_group_name, $key, $value);

		return parent::offsetSet($key, $value);
	}
}
