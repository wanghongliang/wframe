<?php defined('PATH_SYS') or die('不能直接访问.');

class Log {

	//系统日志的级别
	const EMERGENCY = LOG_EMERG;    // 0
	const ALERT     = LOG_ALERT;    // 1
	const CRITICAL  = LOG_CRIT;     // 2
	const ERROR     = LOG_ERR;      // 3
	const WARNING   = LOG_WARNING;  // 4
	const NOTICE    = LOG_NOTICE;   // 5
	const INFO      = LOG_INFO;     // 6
	const DEBUG     = LOG_DEBUG;    // 7
	const STRACE    = 8;

	/**
	 * @var  string  日志时间格式
	 */
	public static $timestamp = 'Y-m-d H:i:s';

	/**
	 * @var  string  时区
	 */
	public static $timezone;

	/**
	 * @var  boolean  立即写日志时添加
	 */
	public static $write_on_add = FALSE;

	/**
	 * @var  Log 单例模式
	 */
	protected static $_instance;

	/**
	 * 获取一个日志单例对象
	 */
	public static function instance()
	{
		if (Log::$_instance === NULL)
		{ 
			Log::$_instance = new Log;
 
			register_shutdown_function(array(Log::$_instance, 'write'));
		}

		return Log::$_instance;
	}

	/**
	 * @var  array  消息队列
	 */
	protected $_messages = array();

	/**
	 * @var  array  日志储存对象
	 */
	protected $_writers = array();

	/**
	 * 设置一个日志记录对象，并设定接收的消息级别
	 */
	public function attach(Log_Writer $writer, $levels = array(), $min_level = 0)
	{
		if ( ! is_array($levels))
		{
			$levels = range($min_level, $levels);
		}
		
		$this->_writers["{$writer}"] = array
		(
			'object' => $writer,
			'levels' => $levels
		);

		return $this;
	}

	/**
	 * 取消息一个日志记录对象
	 */
	public function detach(Log_Writer $writer)
	{
 
		unset($this->_writers["{$writer}"]);

		return $this;
	}

	/**
	 * 添加消息
	 */
	public function add($level, $message, array $values = NULL)
	{
		if ($values)
		{
			// 转义消息变量为消息文本
			$message = strtr($message, $values);
		}

		// 设置时间
		$this->_messages[] = array
		(
			'time'  => Date::formatted_time('now', Log::$timestamp, Log::$timezone),
			'level' => $level,
			'body'  => $message,
		);

		if (Log::$write_on_add)
		{ 
			$this->write();
		}

		return $this;
	}

	/**
	 * 所消息写入储存消息的对象中，并清除消息
	 */
	public function write()
	{
		if (empty($this->_messages))
		{ 
			return;
		}

		// 导入需要记录的消息队列
		$messages = $this->_messages;

		// 清空消息
		$this->_messages = array();

		foreach ($this->_writers as $writer)
		{
			if (empty($writer['levels']))
			{
				//没有设置消息类型，把所有消息写入
				$writer['object']->write($messages);
			}
			else
			{ 
				$filtered = array();

				foreach ($messages as $message)
				{
					if (in_array($message['level'], $writer['levels']))
					{ 
						$filtered[] = $message;
					}
				} 
				$writer['object']->write($filtered);
			}
		}
	}

}  
