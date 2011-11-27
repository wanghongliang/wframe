<?php defined('PATH_SYS') or die('不能直接访问.');
 
abstract class Log_Writer {
 
	protected $_log_levels = array(
		LOG_EMERG   => 'EMERGENCY',
		LOG_ALERT   => 'ALERT',
		LOG_CRIT    => 'CRITICAL',
		LOG_ERR     => 'ERROR',
		LOG_WARNING => 'WARNING',
		LOG_NOTICE  => 'NOTICE',
		LOG_INFO    => 'INFO',
		LOG_DEBUG   => 'DEBUG',
		8           => 'STRACE',
	);
 
	abstract public function write(array $messages); 
	final public function __toString()
	{
		return spl_object_hash($this);
	}

} 
