<?php defined('PATH_SYS') or die('不能直接访问.');
/**
 * 说明：路由类用来决定控制器和方法，每个路由会生成一个正则表达式来区配一个URI和一个路由对应。每个路由对象可以设置控制器和方法还有参数的KEY
 *
 *     // 匹配<id>为数字的URI
 *     Route::set('user', 'user/<action>/<id>', array('id' => '\d+'));
 *
 *     //  <path> 匹配任意URI
 *     Route::set('file', '<path>', array('path' => '.*'));
 *	
 *     设置部分参数
 *     // 标准的方式，需要任意参数
 *     Route::set('default', '(<controller>(/<action>(/<id>)))');
 *
 *     // 只需要 <file> 键 
 *     Route::set('file', '(<path>/)<file>(.<format>)', array('path' => '.*', 'format' => '\w+'));
 * 
 */
class Route {

	// 定义匹配KEY的正则 <segment>
	const REGEX_KEY     = '<([a-zA-Z0-9_]++)>';

	// <segment> 值
	const REGEX_SEGMENT = '[^/.,;?\n]++';

	// 清除元字符正则
	const REGEX_ESCAPE  = '[.\\+*?[^\\]${}=!|]';

	/**
	 * 默认的协议
	 */
	public static $default_protocol = 'http://';

	/**
	 * 有效的本地主机条目列表
	 */
	public static $localhosts = array(FALSE, '', 'local', 'localhost');

	/**
	 * 默认的方法
	 */
	public static $default_action = 'index';

	/**
	 * 指示路线是否会被缓存
	 */
	public static $cache = FALSE;

	/**
	 * 路由对象数组
	 */
	protected static $_routes = array();

	/**
	 * 例子：
	 *
	 *     Route::set('default', '(<controller>(/<action>(/<id>)))')
	 *         ->defaults(array(
	 *             'controller' => 'welcome',
	 *         ));
	 *
	 * @param   string   路由的名称
	 * @param   string   URI规则
	 * @param   array    匹配规则的正则表达式
	 * @return  Route
	 */
	public static function set($name, $uri_callback = NULL, $regex = NULL)
	{
		return Route::$_routes[$name] = new Route($uri_callback, $regex);
	}

	/**
	 * 按名称返回一个路由对象
	 */
	public static function get($name)
	{
		if ( ! isset(Route::$_routes[$name]))
		{
			throw new Core_Exception('路由不存在: :route',
				array(':route' => $name));
		}

		return Route::$_routes[$name];
	}

	/**
	 * 返回路由数组
	 */
	public static function all()
	{
		return Route::$_routes;
	}

	/**
	 * 返回路由的名称
	 */
	public static function name(Route $route)
	{
		return array_search($route, Route::$_routes);
	}

	/**
	 * 缓存路由信息
	 */
	public static function cache($save = FALSE)
	{
		if ($save === TRUE)
		{
			// 缓存
			Core::cache('Route::cache()', Route::$_routes);
		}
		else
		{
			if ($routes = Core::cache('Route::cache()'))
			{
				Route::$_routes = $routes;

				// 已缓存
				return Route::$cache = TRUE;
			}
			else
			{
				// 没有生成缓存
				return Route::$cache = FALSE;
			}
		}
	}

	/**
	 * 创建URL信息
	 *
	 *     echo URL::site(Route::get($name)->uri($params), $protocol);
	 * 
	 */
	public static function url($name, array $params = NULL, $protocol = NULL)
	{
		$route = Route::get($name);

		// 外部URL
		if ($route->is_external())
			return Route::get($name)->uri($params);
		else
			return URL::site(Route::get($name)->uri($params), $protocol);
	}

	/**
	 * 返回一下转换的URL正则表达式
	 */
	public static function compile($uri, array $regex = NULL)
	{
		if ( ! is_string($uri))
			return;

 
		// 转义元字符
		$expression = preg_replace('#'.Route::REGEX_ESCAPE.'#', '\\\\$0', $uri);

		if (strpos($expression, '(') !== FALSE)
		{
			//把(号转义为不包含模式
			$expression = str_replace(array('(', ')'), array('(?:', ')?'), $expression);
		}

		//默认的KEY
		$expression = str_replace(array('<', '>'), array('(?P<', '>'.Route::REGEX_SEGMENT.')'), $expression);

		if ($regex)
		{
			$search = $replace = array();
			foreach ($regex as $key => $value)
			{
				$search[]  = "<$key>".Route::REGEX_SEGMENT;
				$replace[] = "<$key>$value";
			}

			// 转换为正则字符串
			$expression = str_replace($search, $replace, $expression);
		}

		return '#^'.$expression.'$#uD';
	}

	/**
	 *  回调方法
	 */
	protected $_callback; 
	protected $_uri = ''; 
	protected $_regex = array(); 
	protected $_defaults = array('action' => 'index', 'host' => FALSE); 
	protected $_route_regex;

	/**
	 * 例子：
	 *
	 *     $route = new Route(function($uri)
	 *     {
	 *     	if (list($controller, $action, $param) = explode('/', $uri) AND $controller == 'foo' AND $action == 'bar')
	 *     	{
	 *     		return array(
	 *     			'controller' => 'foobar',
	 *     			'action' => $action,
	 *     			'id' => $param,
	 *     		);
	 *     	},
	 *     	'foo/bar/<id>'
	 *     });
	 *
	 * @param   mixed    字符串，或者函数
	 * @param   array    key patterns
	 * @return  void
	 * @uses    Route::_compile
	 */
	public function __construct($uri = NULL, $regex = NULL)
	{
		if ($uri === NULL)
		{ 
			return;
		}

		if ( ! is_string($uri) AND is_callable($uri))
		{
			$this->_callback = $uri;
			$this->_uri = $regex;
			$regex = NULL;
		}
		elseif ( ! empty($uri))
		{
			$this->_uri = $uri;
		}

		if ( ! empty($regex))
		{
			$this->_regex = $regex;
		}

		//匹配的正则表达式
		$this->_route_regex = Route::compile($uri, $regex);
	}
 
	public function defaults(array $defaults = NULL)
	{
		if ($defaults === NULL)
		{
			return $this->_defaults;
		}

		$this->_defaults = $defaults;

		return $this;
	}
 
	public function matches($uri)
	{
		if ($this->_callback)
		{
			$closure = $this->_callback;
			$params = call_user_func($closure, $uri);

			if ( ! is_array($params))
				return FALSE;
		}
		else
		{
			if ( ! preg_match($this->_route_regex, $uri, $matches))
				return FALSE;

			$params = array();
			foreach ($matches as $key => $value)
			{
				if (is_int($key))
				{
					continue;
				}

				$params[$key] = $value;
			}
		}

		foreach ($this->_defaults as $key => $value)
		{
			if ( ! isset($params[$key]) OR $params[$key] === '')
			{
				// Set default values for any key that was not matched
				$params[$key] = $value;
			}
		}

		return $params;
	}
 
	public function is_external()
	{
		return ! in_array(Arr::get($this->_defaults, 'host', FALSE), Route::$localhosts);
	}

	/**
	 * 基于当前的路由生成一个URI：
	 *
	 *     // Using the "default" route: "users/profile/10"
	 *     $route->uri(array(
	 *         'controller' => 'users',
	 *         'action'     => 'profile',
	 *         'id'         => '10'
	 *     ));
	 * 
	 */
	public function uri(array $params = NULL)
	{ 
		$uri = $this->_uri;

		//如果没有设置URI变量
		if (strpos($uri, '<') === FALSE AND strpos($uri, '(') === FALSE)
		{
 			if ( ! $this->is_external())
				return $uri;

 			if (strpos($this->_defaults['host'], '://') === FALSE)
			{
 				$params['host'] = Route::$default_protocol.$this->_defaults['host'];
			}
			else
			{
 				$params['host'] = $this->_defaults['host'];
			}

			return rtrim($params['host'], '/').'/'.$uri;
		}

		//可选参数
		while (preg_match('#\([^()]++\)#', $uri, $match))
		{
			$search = $match[0];

			$replace = substr($match[0], 1, -1);

			while (preg_match('#'.Route::REGEX_KEY.'#', $replace, $match))
			{
				list($key, $param) = $match;

				if (isset($params[$param]))
				{
					$replace = str_replace($key, $params[$param], $replace);
				}
				else
				{
					$replace = '';
					break;
				}
			}

			$uri = str_replace($search, $replace, $uri);
		}
		
		//必填参数
		while (preg_match('#'.Route::REGEX_KEY.'#', $uri, $match))
		{
			list($key, $param) = $match;

			if ( ! isset($params[$param]))
			{
				if (isset($this->_defaults[$param]))
				{
					$params[$param] = $this->_defaults[$param];
				}
				else
				{
					throw new Core_Exception('需要的参数没有设置: :param', array(
						':param' => $param,
					));
				}
			}

			$uri = str_replace($key, $params[$param], $uri);
		}

		// 去掉协议中的//
		$uri = preg_replace('#//+#', '/', rtrim($uri, '/'));

		if ($this->is_external())
		{ 
			$host = $this->_defaults['host'];

			if (strpos($host, '://') === FALSE)
			{ 
				$host = Route::$default_protocol.$host;
			} 
			$uri = rtrim($host, '/').'/'.$uri;
		}

		return $uri;
	}

}  
