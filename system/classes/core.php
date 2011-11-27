<?php 
defined('PATH_SYS') or die('不能直接访问.');
/**
 * 系统框加文件，包含常用的系统方法
 *
 * @package   Core
 * author  whl@126.com
 * date 2011-11-14
 */
class Core {
 
	const VERSION  = '0.0.1'; 
	// 定义系统运行的环境，如：开发阶段会显示系统运行状态，关闭缓存等
	const PRODUCTION  = 10;
	const STAGING     = 20;
	const TESTING     = 30;
	const DEVELOPMENT = 40;
 

	//黓认为开发环境
	public static $environment = Core::DEVELOPMENT;

	//命令行执行方式
	public static $is_cli = FALSE;

	//WINDOWS操作系统
	public static $is_windows = FALSE;

	//是否要转义请求变量
	public static $magic_quotes = FALSE;

	//日志
	public static $log_errors = FALSE;

	//安全模式
	public static $safe_mode = FALSE;

	//网页类型
	public static $content_type = 'text/html';

	//字符集
	public static $charset = 'utf-8';

	//服务名称
	public static $server_name = '';

	//有效的主机列表
	public static $hostnames = array();

	//首页URL
	public static $base_url = '/';

	//入口文件
	public static $index_file = 'index.php';

	//缓存目录
	public static $cache_dir;

	//缓存时间
	public static $cache_life = 60;

	//缓存
	public static $caching = FALSE;

	//系统运行信息
	public static $profiling = TRUE;

	//是否记录错误信息
	public static $errors = TRUE;

	//程序中止时显示的错误信息
	public static $shutdown_errors = array(E_PARSE, E_ERROR, E_USER_ERROR);

	//日志对象
	public static $log;

	//配置对象
	public static $config;

	//初始化标志
	protected static $_init = FALSE;

	//加载的模块列表
	protected static $_modules = array();

	//系统路径
	protected static $_paths = array(PATH_APP, PATH_SYS);

	//缓存的文件路径
	protected static $_files = array(); 
	protected static $_files_changed = FALSE;

	/**
	 * 系统初始化方法 
	 * @param   array   配置数组 
	 */
	public static function init(array $settings = NULL)
	{
		if (Core::$_init)
		{	//不能两次执行
			return;
		} 
		Core::$_init = TRUE;

		if (isset($settings['profile']))
		{
			// 是否开启系统信息
			Core::$profiling = (bool) $settings['profile'];
		}

	 
		ob_start();

		if (isset($settings['errors']))
		{ 
			Core::$errors = (bool) $settings['errors'];
		}

		if (Core::$errors === TRUE)
		{
			// 开启异常信息处理
			set_exception_handler(array('Core_Exception', 'handler')); 
			set_error_handler(array('Core', 'error_handler'));
		}
 
		register_shutdown_function(array('Core', 'shutdown_handler'));

		if (ini_get('register_globals'))
		{
			// 过滤全局变量，防止被恶意覆盖
			Core::globals();
		} 
 
		Core::$is_cli = (PHP_SAPI === 'cli'); 
		Core::$is_windows = (DIRECTORY_SEPARATOR === '\\'); 
		Core::$safe_mode = (bool) ini_get('safe_mode');

		//初始化缓存目录
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
					throw new Core_Exception('不能创建缓存目录 :dir',
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
			throw new Core_Exception('缓存目录 :dir 不可写',
				array(':dir' => Debug::path(Core::$cache_dir)));
		}

		if (isset($settings['cache_life']))
		{ 
			Core::$cache_life = (int) $settings['cache_life'];
		}

		if (isset($settings['caching']))
		{
			// 是否启用内容缓存
			Core::$caching = (bool) $settings['caching'];
		}

		if (Core::$caching === TRUE)
		{
			//首先读取缓存的加载文件
			Core::$_files = Core::cache('Core::find_file()');
		}

		if (isset($settings['charset']))
		{
			// 系统的字符集设置
			Core::$charset = strtolower($settings['charset']);
		}

		if (function_exists('mb_internal_encoding'))
		{ 
			mb_internal_encoding(Core::$charset);
		}

		if (isset($settings['base_url']))
		{
			//设置根目录URL
			Core::$base_url = rtrim($settings['base_url'], '/').'/';
		}

		if (isset($settings['index_file']))
		{
			// 设置首页入口文件
			Core::$index_file = trim($settings['index_file'], '/');
		}
 
		Core::$magic_quotes = (bool) get_magic_quotes_gpc();

		//过滤输入变量
		$_GET    = Core::sanitize($_GET);
		$_POST   = Core::sanitize($_POST);
		$_COOKIE = Core::sanitize($_COOKIE);
 
		Core::$log = Log::instance();  //日志对象
		Core::$config = new Core_Config; //配置对象
	}

	/**
	 * 全局变量过滤
	 */
	public static function globals()
	{
		if (isset($_REQUEST['GLOBALS']) OR isset($_FILES['GLOBALS']))
		{ 
			echo "全局变量恶意覆盖保护，访问禁止.\n"; 
			exit(1);
		}
		
		//所有系统全局变量KEY
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
	 * 安全转义变量
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
	 * 自动加载类名
	 *
	 * @param   string   类名称 
	 * @return  boolean
	 */
	public static function auto_load($class)
	{
		try
		{
			// 转换类文件路径
			$file = str_replace('_', '/', strtolower($class));

			if ($path = Core::find_file('classes', $file))
			{
				// 加载类文件
				require $path;

				// 找到类文件
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
	 * 系统初始化的模块
	 */
	public static function modules(array $modules = NULL)
	{
		if ($modules === NULL)
		{ 
			return Core::$_modules;
		}
 

		//首先加载应用程序目录
		$paths = array(PATH_APP);

		foreach ($modules as $name => $path)
		{
			if (is_dir($path))
			{ 
				$paths[] = $modules[$name] = realpath($path).DIRECTORY_SEPARATOR;
			}
			else
			{ 
				throw new Core_Exception('无效的模块目录 \':module\' 路径： \':path\'', array(
					':module' => $name,
					':path'   => Debug::path($path),
				));
			}
		}
		

		//然后加载核心目录
		$paths[] = PATH_SYS;

		// 设置新的位置
		Core::$_paths = $paths;

		//设置模块列表
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
	 * 返回包含文件的目录数组，如 PATH_APP,PATH_SYS, 模块目录等
	 *
	 * @return  array
	 */
	public static function include_paths()
	{
		return Core::$_paths;
	}

	/**
	 * 搜索文件
	 */
	public static function find_file($dir, $file, $ext = NULL, $array = FALSE)
	{
		//默认的后缀名称
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
			// 没有后缀名
			$ext = '';
		}

		// 指定要查找的文件路径
		$path = $dir.DIRECTORY_SEPARATOR.$file.$ext;

		if (Core::$caching === TRUE AND isset(Core::$_files[$path.($array ? '_array' : '_path')]))
		{
			// 缓存查找的文件路径
			return Core::$_files[$path.($array ? '_array' : '_path')];
		}

		if (Core::$profiling === TRUE AND class_exists('Profiler', FALSE))
		{
			//启动了一个新的标记
			$benchmark = Profiler::start('Core', __FUNCTION__);
		}


		//支持多个文件被加载
		if ($array OR $dir === 'config'  OR $dir === 'messages')
		{
			// 包含路径必须在反向搜索
			$paths = array_reverse(Core::$_paths);

			// 找到的位置
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
			//单个文件搜索
			$found = FALSE;

			foreach (Core::$_paths as $dir)
			{
				if (is_file($dir.$path))
				{
					// 找到文件
					$found = $dir.$path;

					// 停止搜索
					break;
				}
			}
		}

		if (Core::$caching === TRUE)
		{
			// 缓存文件
			Core::$_files[$path.($array ? '_array' : '_path')] = $found;

			//加载文件被改变
			Core::$_files_changed = TRUE;
		}

		if (isset($benchmark))
		{
			// 停止记录系统信息
			Profiler::stop($benchmark);
		}

		return $found;
	}

 
	/**
	 * 包含一个文件
	 */
	public static function load($file)
	{
		return include $file;
	}

	/**
	 * 缓存 
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
	 * 把所有的消息定义在文件中，然后用KEY来转义成消息
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
	 * 系统错误处理
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
	 * 捕获不是由错误处理程序捕获的错误 如： E_PARSE.
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
			// 清除系统缓存的信息
			ob_get_level() and ob_clean();

			// 异常调试信息
			Core_Exception::handler(new ErrorException($error['message'], $error['type'], 0, $error['file'], $error['line']));
 
			exit(1);
		}
	}

}
