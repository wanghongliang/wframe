<?php 
defined('PATH_SYS') or die('����ֱ�ӷ���.');
/**
 * ϵͳ����ļ����������õ�ϵͳ����
 *
 * @package   Core
 * author  whl@126.com
 * date 2011-11-14
 */
class Core {
 
	const VERSION  = '0.0.1'; 
	// ����ϵͳ���еĻ������磺�����׶λ���ʾϵͳ����״̬���رջ����
	const PRODUCTION  = 10;
	const STAGING     = 20;
	const TESTING     = 30;
	const DEVELOPMENT = 40;
 

	//�]��Ϊ��������
	public static $environment = Core::DEVELOPMENT;

	//������ִ�з�ʽ
	public static $is_cli = FALSE;

	//WINDOWS����ϵͳ
	public static $is_windows = FALSE;

	//�Ƿ�Ҫת���������
	public static $magic_quotes = FALSE;

	//��־
	public static $log_errors = FALSE;

	//��ȫģʽ
	public static $safe_mode = FALSE;

	//��ҳ����
	public static $content_type = 'text/html';

	//�ַ���
	public static $charset = 'utf-8';

	//��������
	public static $server_name = '';

	//��Ч�������б�
	public static $hostnames = array();

	//��ҳURL
	public static $base_url = '/';

	//����ļ�
	public static $index_file = 'index.php';

	//����Ŀ¼
	public static $cache_dir;

	//����ʱ��
	public static $cache_life = 60;

	//����
	public static $caching = FALSE;

	//ϵͳ������Ϣ
	public static $profiling = TRUE;

	//�Ƿ��¼������Ϣ
	public static $errors = TRUE;

	//������ֹʱ��ʾ�Ĵ�����Ϣ
	public static $shutdown_errors = array(E_PARSE, E_ERROR, E_USER_ERROR);

	//��־����
	public static $log;

	//���ö���
	public static $config;

	//��ʼ����־
	protected static $_init = FALSE;

	//���ص�ģ���б�
	protected static $_modules = array();

	//ϵͳ·��
	protected static $_paths = array(PATH_APP, PATH_SYS);

	//������ļ�·��
	protected static $_files = array(); 
	protected static $_files_changed = FALSE;

	/**
	 * ϵͳ��ʼ������ 
	 * @param   array   �������� 
	 */
	public static function init(array $settings = NULL)
	{
		if (Core::$_init)
		{	//��������ִ��
			return;
		} 
		Core::$_init = TRUE;

		if (isset($settings['profile']))
		{
			// �Ƿ���ϵͳ��Ϣ
			Core::$profiling = (bool) $settings['profile'];
		}

	 
		ob_start();

		if (isset($settings['errors']))
		{ 
			Core::$errors = (bool) $settings['errors'];
		}

		if (Core::$errors === TRUE)
		{
			// �����쳣��Ϣ����
			set_exception_handler(array('Core_Exception', 'handler')); 
			set_error_handler(array('Core', 'error_handler'));
		}
 
		register_shutdown_function(array('Core', 'shutdown_handler'));

		if (ini_get('register_globals'))
		{
			// ����ȫ�ֱ�������ֹ�����⸲��
			Core::globals();
		} 
 
		Core::$is_cli = (PHP_SAPI === 'cli'); 
		Core::$is_windows = (DIRECTORY_SEPARATOR === '\\'); 
		Core::$safe_mode = (bool) ini_get('safe_mode');

		//��ʼ������Ŀ¼
		if (isset($settings['cache_dir']))
		{
			if ( ! is_dir($settings['cache_dir']))
			{
				try
				{ 
					mkdir($settings['cache_dir'], 0755, TRUE);
 
					chmod($settings['cache_dir'], 0755);
				}
				catch (Exception $e)
				{
					throw new Core_Exception('���ܴ�������Ŀ¼ :dir',
						array(':dir' => Debug::path($settings['cache_dir'])));
				}
			}
 
			Core::$cache_dir = realpath($settings['cache_dir']);
		}
		else
		{ 
			Core::$cache_dir = PATH_APP.'cache';
		}

		if ( ! is_writable(Core::$cache_dir))
		{
			throw new Core_Exception('����Ŀ¼ :dir ����д',
				array(':dir' => Debug::path(Core::$cache_dir)));
		}

		if (isset($settings['cache_life']))
		{ 
			Core::$cache_life = (int) $settings['cache_life'];
		}

		if (isset($settings['caching']))
		{
			// �Ƿ��������ݻ���
			Core::$caching = (bool) $settings['caching'];
		}

		if (Core::$caching === TRUE)
		{
			//���ȶ�ȡ����ļ����ļ�
			Core::$_files = Core::cache('Core::find_file()');
		}

		if (isset($settings['charset']))
		{
			// ϵͳ���ַ�������
			Core::$charset = strtolower($settings['charset']);
		}

		if (function_exists('mb_internal_encoding'))
		{ 
			mb_internal_encoding(Core::$charset);
		}

		if (isset($settings['base_url']))
		{
			//���ø�Ŀ¼URL
			Core::$base_url = rtrim($settings['base_url'], '/').'/';
		}

		if (isset($settings['index_file']))
		{
			// ������ҳ����ļ�
			Core::$index_file = trim($settings['index_file'], '/');
		}
 
		Core::$magic_quotes = (bool) get_magic_quotes_gpc();

		//�����������
		$_GET    = Core::sanitize($_GET);
		$_POST   = Core::sanitize($_POST);
		$_COOKIE = Core::sanitize($_COOKIE);
 
		Core::$log = Log::instance();  //��־����
		Core::$config = new Core_Config; //���ö���
	}

	/**
	 * ȫ�ֱ�������
	 */
	public static function globals()
	{
		if (isset($_REQUEST['GLOBALS']) OR isset($_FILES['GLOBALS']))
		{ 
			echo "ȫ�ֱ������⸲�Ǳ��������ʽ�ֹ.\n"; 
			exit(1);
		}
		
		//����ϵͳȫ�ֱ���KEY
		$global_variables = array_keys($GLOBALS);
 
		$global_variables = array_diff($global_variables, array(
			'_COOKIE',
			'_ENV',
			'_GET',
			'_FILES',
			'_POST',
			'_REQUEST',
			'_SERVER',
			'_SESSION',
			'GLOBALS',
		));

		foreach ($global_variables as $name)
		{ 
			unset($GLOBALS[$name]);
		}
	}

	/**
	 * ��ȫת�����
	 */
	public static function sanitize($value)
	{
		if (is_array($value) OR is_object($value))
		{
			foreach ($value as $key => $val)
			{ 
				$value[$key] = Core::sanitize($val);
			}
		}
		elseif (is_string($value))
		{
			if (Core::$magic_quotes === TRUE)
			{ 
				$value = stripslashes($value);
			}

			if (strpos($value, "\r") !== FALSE)
			{ 
				$value = str_replace(array("\r\n", "\r"), "\n", $value);
			}
		}

		return $value;
	}

	/**
	 * �Զ���������
	 *
	 * @param   string   ������ 
	 * @return  boolean
	 */
	public static function auto_load($class)
	{
		try
		{
			// ת�����ļ�·��
			$file = str_replace('_', '/', strtolower($class));

			if ($path = Core::find_file('classes', $file))
			{
				// �������ļ�
				require $path;

				// �ҵ����ļ�
				return TRUE;
			}

			// Class is not in the filesystem
			return FALSE;
		}
		catch (Exception $e)
		{
			Core_Exception::handler($e);
			die;
		}
	}

	/**
	 * ϵͳ��ʼ����ģ��
	 */
	public static function modules(array $modules = NULL)
	{
		if ($modules === NULL)
		{ 
			return Core::$_modules;
		}
 

		//���ȼ���Ӧ�ó���Ŀ¼
		$paths = array(PATH_APP);

		foreach ($modules as $name => $path)
		{
			if (is_dir($path))
			{ 
				$paths[] = $modules[$name] = realpath($path).DIRECTORY_SEPARATOR;
			}
			else
			{ 
				throw new Core_Exception('��Ч��ģ��Ŀ¼ \':module\' ·���� \':path\'', array(
					':module' => $name,
					':path'   => Debug::path($path),
				));
			}
		}
		

		//Ȼ����غ���Ŀ¼
		$paths[] = PATH_SYS;

		// �����µ�λ��
		Core::$_paths = $paths;

		//����ģ���б�
		Core::$_modules = $modules;

		foreach (Core::$_modules as $path)
		{
			$init = $path.'init.php';

			if (is_file($init))
			{ 
				require_once $init;
			}
		}

		return Core::$_modules;
	}

	/**
	 * ���ذ����ļ���Ŀ¼���飬�� PATH_APP,PATH_SYS, ģ��Ŀ¼��
	 *
	 * @return  array
	 */
	public static function include_paths()
	{
		return Core::$_paths;
	}

	/**
	 * �����ļ�
	 */
	public static function find_file($dir, $file, $ext = NULL, $array = FALSE)
	{
		//Ĭ�ϵĺ�׺����
		if ($ext === NULL)
		{ 
			$ext = '.php';
		}
		elseif ($ext)
		{ 
			$ext = ".{$ext}";
		}
		else
		{
			// û�к�׺��
			$ext = '';
		}

		// ָ��Ҫ���ҵ��ļ�·��
		$path = $dir.DIRECTORY_SEPARATOR.$file.$ext;

		if (Core::$caching === TRUE AND isset(Core::$_files[$path.($array ? '_array' : '_path')]))
		{
			// ������ҵ��ļ�·��
			return Core::$_files[$path.($array ? '_array' : '_path')];
		}

		if (Core::$profiling === TRUE AND class_exists('Profiler', FALSE))
		{
			//������һ���µı��
			$benchmark = Profiler::start('Core', __FUNCTION__);
		}


		//֧�ֶ���ļ�������
		if ($array OR $dir === 'config'  OR $dir === 'messages')
		{
			// ����·�������ڷ�������
			$paths = array_reverse(Core::$_paths);

			// �ҵ���λ��
			$found = array();

			foreach ($paths as $dir)
			{
				if (is_file($dir.$path))
				{ 
					$found[] = $dir.$path;
				}
			}
		}
		else
		{
			//�����ļ�����
			$found = FALSE;

			foreach (Core::$_paths as $dir)
			{
				if (is_file($dir.$path))
				{
					// �ҵ��ļ�
					$found = $dir.$path;

					// ֹͣ����
					break;
				}
			}
		}

		if (Core::$caching === TRUE)
		{
			// �����ļ�
			Core::$_files[$path.($array ? '_array' : '_path')] = $found;

			//�����ļ����ı�
			Core::$_files_changed = TRUE;
		}

		if (isset($benchmark))
		{
			// ֹͣ��¼ϵͳ��Ϣ
			Profiler::stop($benchmark);
		}

		return $found;
	}

 
	/**
	 * ����һ���ļ�
	 */
	public static function load($file)
	{
		return include $file;
	}

	/**
	 * ���� 
	 */
	public static function cache($name, $data = NULL, $lifetime = NULL)
	{
 
		$file = sha1($name).'.txt'; 
		$dir = Core::$cache_dir.DIRECTORY_SEPARATOR.$file[0].$file[1].DIRECTORY_SEPARATOR;

		if ($lifetime === NULL)
		{ 
			$lifetime = Core::$cache_life;
		}

		if ($data === NULL)
		{
			if (is_file($dir.$file))
			{
				if ((time() - filemtime($dir.$file)) < $lifetime)
				{ 
					try
					{
						return unserialize(file_get_contents($dir.$file));
					}
					catch (Exception $e)
					{
						//  
					}
				}
				else
				{
					try
					{ 
						unlink($dir.$file);
					}
					catch (Exception $e)
					{
						// 
					}
				}
			}
 
			return NULL;
		}

		if ( ! is_dir($dir))
		{
			mkdir($dir, 0777, TRUE); 
			chmod($dir, 0777);
		}
 
		$data = serialize($data);

		try
		{ 
			return (bool) file_put_contents($dir.$file, $data, LOCK_EX);
		}
		catch (Exception $e)
		{ 
			return FALSE;
		}
	}

	/**
	 * �����е���Ϣ�������ļ��У�Ȼ����KEY��ת�����Ϣ
	 */
	public static function message($file, $path = NULL, $default = NULL)
	{
		static $messages;

		if ( ! isset($messages[$file]))
		{ 
			$messages[$file] = array();

			if ($files = Core::find_file('messages', $file))
			{
				foreach ($files as $f)
				{ 
					$messages[$file] = array_merge($messages[$file], Core::load($f));
				}
			}
		}

		if ($path === NULL)
		{ 
			return $messages[$file];
		}
		else
		{ 
			return isset($messages[$file][$path])?$messages[$file][$path]:$default;// 
		}
	}
 
	/**
	 * ϵͳ������
	 */
	public static function error_handler($code, $error, $file = NULL, $line = NULL)
	{
		if (error_reporting() & $code)
		{ 
			throw new ErrorException($error, $code, 0, $file, $line);
		}
 
		return TRUE;
	}

	/**
	 * �������ɴ�������򲶻�Ĵ��� �磺 E_PARSE.
	 * 
	 */
	public static function shutdown_handler()
	{
		if ( ! Core::$_init)
		{ 
			return;
		}

		try
		{
			if (Core::$caching === TRUE AND Core::$_files_changed === TRUE)
			{ 
				Core::cache('Core::find_file()', Core::$_files);
			}
		}
		catch (Exception $e)
		{ 
			Core_Exception::handler($e);
		}

		if (Core::$errors AND $error = error_get_last() AND in_array($error['type'], Core::$shutdown_errors))
		{
			// ���ϵͳ�������Ϣ
			ob_get_level() and ob_clean();

			// �쳣������Ϣ
			Core_Exception::handler(new ErrorException($error['message'], $error['type'], 0, $error['file'], $error['line']));
 
			exit(1);
		}
	}

}
