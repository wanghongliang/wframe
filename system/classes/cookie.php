<?php defined('PATH_SYS') or die('不能直接访问.');
/**
 * Cookie封装类
 */
class Cookie {

	/**
	 * @var  string  密钥验证字符串
	 */
	public static $salt = NULL;

	/**
	 * @var  integer  cookie 过期秒数设置 
	 */
	public static $expiration = 0;

	/**
	 * @var  string  当前COOKIO所在的环境范围 
	 */
	public static $path = '/';

	/**
	 * @var  string  COOKIE所在域设置
	 */
	public static $domain = NULL;

	/**
	 * @var  boolean  仅通过安全连接传输的Cookie
	 */
	public static $secure = FALSE;

	/**
	 * @var  boolean  只通过http传输，禁止javascript访问
	 */
	public static $httponly = FALSE;

	/**
	 * 获取一个获得签名的COOKIE值，没有签名的Cookies不会被退回。如果cookie的签名无效，该cookie将被删除。
	 * 
	 *     $theme = Cookie::get('theme', 'blue');
	 *
	 * @param   string cookie 
	 * @param   mixed  默认值
	 * @return  string 返回一个字符串
	 */
	public static function get($key, $default = NULL)
	{

		//没有设定时，直接返回默认值 
		if ( ! isset($_COOKIE[$key]))
		{ 
			return $default;
		}

		//获取cookio的信息
		$cookie = $_COOKIE[$key];

		// Find the position of the split between salt and contents
		$split = strlen(Cookie::salt($key, NULL));

		if (isset($cookie[$split]) AND $cookie[$split] === '~')
		{
			// Separate the salt and the value
			list ($hash, $value) = explode('~', $cookie, 2);

			if (Cookie::salt($key, $value) === $hash)
			{
				// Cookie signature is valid
				return $value;
			}

			// The cookie signature is invalid, delete it
			Cookie::delete($key);
		}

		return $default;
	}

	/**
	 * 设置一个COOKIE信息，如果不是一个字符串，将自动系例化成为一个字符串
	 */
	public static function set($name, $value, $expiration = NULL)
	{
		if ($expiration === NULL)
		{
			//是否设置截止时间
			$expiration = Cookie::$expiration;
		}

		if ($expiration !== 0)
		{
			// 过期时间需要加上当前的时间
			$expiration += time();
		}

		// Add the salt to the cookie value
		$value = Cookie::salt($name, $value).'~'.$value; 
		return setcookie($name, $value, $expiration, Cookie::$path, Cookie::$domain, Cookie::$secure, Cookie::$httponly);
	}

	/**
	 * 清除一个COOKIE，并把时间设为过期
	 */
	public static function delete($name)
	{ 
		unset($_COOKIE[$name]); 
		//清除一个COOKIE，并把时间设为过期
		return setcookie($name, NULL, -86400, Cookie::$path, Cookie::$domain, Cookie::$secure, Cookie::$httponly);
	}

	/**
	 * 通过cookio的KEY和VALUE及salt来生成一个前缀salt 
	 */
	public static function salt($name, $value)
	{ 
		if ( ! Cookie::$salt)
		{
			throw new Core_Exception('A valid cookie salt is required. Please set Cookie::$salt.');
		} 
		$agent = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : 'unknown';

		return sha1($agent.$name.$value.Cookie::$salt);
	}

} 
