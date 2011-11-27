<?php defined('PATH_SYS') or die('����ֱ�ӷ���.');
/**
 * Cookie��װ��
 */
class Cookie {

	/**
	 * @var  string  ��Կ��֤�ַ���
	 */
	public static $salt = NULL;

	/**
	 * @var  integer  cookie ������������ 
	 */
	public static $expiration = 0;

	/**
	 * @var  string  ��ǰCOOKIO���ڵĻ�����Χ 
	 */
	public static $path = '/';

	/**
	 * @var  string  COOKIE����������
	 */
	public static $domain = NULL;

	/**
	 * @var  boolean  ��ͨ����ȫ���Ӵ����Cookie
	 */
	public static $secure = FALSE;

	/**
	 * @var  boolean  ֻͨ��http���䣬��ֹjavascript����
	 */
	public static $httponly = FALSE;

	/**
	 * ��ȡһ�����ǩ����COOKIEֵ��û��ǩ����Cookies���ᱻ�˻ء����cookie��ǩ����Ч����cookie����ɾ����
	 * 
	 *     $theme = Cookie::get('theme', 'blue');
	 *
	 * @param   string cookie 
	 * @param   mixed  Ĭ��ֵ
	 * @return  string ����һ���ַ���
	 */
	public static function get($key, $default = NULL)
	{

		//û���趨ʱ��ֱ�ӷ���Ĭ��ֵ 
		if ( ! isset($_COOKIE[$key]))
		{ 
			return $default;
		}

		//��ȡcookio����Ϣ
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
	 * ����һ��COOKIE��Ϣ���������һ���ַ��������Զ�ϵ������Ϊһ���ַ���
	 */
	public static function set($name, $value, $expiration = NULL)
	{
		if ($expiration === NULL)
		{
			//�Ƿ����ý�ֹʱ��
			$expiration = Cookie::$expiration;
		}

		if ($expiration !== 0)
		{
			// ����ʱ����Ҫ���ϵ�ǰ��ʱ��
			$expiration += time();
		}

		// Add the salt to the cookie value
		$value = Cookie::salt($name, $value).'~'.$value; 
		return setcookie($name, $value, $expiration, Cookie::$path, Cookie::$domain, Cookie::$secure, Cookie::$httponly);
	}

	/**
	 * ���һ��COOKIE������ʱ����Ϊ����
	 */
	public static function delete($name)
	{ 
		unset($_COOKIE[$name]); 
		//���һ��COOKIE������ʱ����Ϊ����
		return setcookie($name, NULL, -86400, Cookie::$path, Cookie::$domain, Cookie::$secure, Cookie::$httponly);
	}

	/**
	 * ͨ��cookio��KEY��VALUE��salt������һ��ǰ׺salt 
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
