<?php defined('PATH_SYS') or die('����ֱ�ӷ���.');

class Log {

	//ϵͳ��־�ļ���
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
	 * @var  string  ��־ʱ���ʽ
	 */
	public static $timestamp = 'Y-m-d H:i:s';

	/**
	 * @var  string  ʱ��
	 */
	public static $timezone;

	/**
	 * @var  boolean  ����д��־ʱ���
	 */
	public static $write_on_add = FALSE;

	/**
	 * @var  Log ����ģʽ
	 */
	protected static $_instance;

	/**
	 * ��ȡһ����־��������
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
	 * @var  array  ��Ϣ����
	 */
	protected $_messages = array();

	/**
	 * @var  array  ��־�������
	 */
	protected $_writers = array();

	/**
	 * ����һ����־��¼���󣬲��趨���յ���Ϣ����
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
	 * ȡ��Ϣһ����־��¼����
	 */
	public function detach(Log_Writer $writer)
	{
 
		unset($this->_writers["{$writer}"]);

		return $this;
	}

	/**
	 * �����Ϣ
	 */
	public function add($level, $message, array $values = NULL)
	{
		if ($values)
		{
			// ת����Ϣ����Ϊ��Ϣ�ı�
			$message = strtr($message, $values);
		}

		// ����ʱ��
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
	 * ����Ϣд�봢����Ϣ�Ķ����У��������Ϣ
	 */
	public function write()
	{
		if (empty($this->_messages))
		{ 
			return;
		}

		// ������Ҫ��¼����Ϣ����
		$messages = $this->_messages;

		// �����Ϣ
		$this->_messages = array();

		foreach ($this->_writers as $writer)
		{
			if (empty($writer['levels']))
			{
				//û��������Ϣ���ͣ���������Ϣд��
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
