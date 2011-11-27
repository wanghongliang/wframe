<?php defined('PATH_SYS') or die('����ֱ�ӷ���.');
/**
 * ˵����·�������������������ͷ�����ÿ��·�ɻ�����һ��������ʽ������һ��URI��һ��·�ɶ�Ӧ��ÿ��·�ɶ���������ÿ������ͷ������в�����KEY
 *
 *     // ƥ��<id>Ϊ���ֵ�URI
 *     Route::set('user', 'user/<action>/<id>', array('id' => '\d+'));
 *
 *     //  <path> ƥ������URI
 *     Route::set('file', '<path>', array('path' => '.*'));
 *	
 *     ���ò��ֲ���
 *     // ��׼�ķ�ʽ����Ҫ�������
 *     Route::set('default', '(<controller>(/<action>(/<id>)))');
 *
 *     // ֻ��Ҫ <file> �� 
 *     Route::set('file', '(<path>/)<file>(.<format>)', array('path' => '.*', 'format' => '\w+'));
 * 
 */
class Route {

	// ����ƥ��KEY������ <segment>
	const REGEX_KEY     = '<([a-zA-Z0-9_]++)>';

	// <segment> ֵ
	const REGEX_SEGMENT = '[^/.,;?\n]++';

	// ���Ԫ�ַ�����
	const REGEX_ESCAPE  = '[.\\+*?[^\\]${}=!|]';

	/**
	 * Ĭ�ϵ�Э��
	 */
	public static $default_protocol = 'http://';

	/**
	 * ��Ч�ı���������Ŀ�б�
	 */
	public static $localhosts = array(FALSE, '', 'local', 'localhost');

	/**
	 * Ĭ�ϵķ���
	 */
	public static $default_action = 'index';

	/**
	 * ָʾ·���Ƿ�ᱻ����
	 */
	public static $cache = FALSE;

	/**
	 * ·�ɶ�������
	 */
	protected static $_routes = array();

	/**
	 * ���ӣ�
	 *
	 *     Route::set('default', '(<controller>(/<action>(/<id>)))')
	 *         ->defaults(array(
	 *             'controller' => 'welcome',
	 *         ));
	 *
	 * @param   string   ·�ɵ�����
	 * @param   string   URI����
	 * @param   array    ƥ������������ʽ
	 * @return  Route
	 */
	public static function set($name, $uri_callback = NULL, $regex = NULL)
	{
		return Route::$_routes[$name] = new Route($uri_callback, $regex);
	}

	/**
	 * �����Ʒ���һ��·�ɶ���
	 */
	public static function get($name)
	{
		if ( ! isset(Route::$_routes[$name]))
		{
			throw new Core_Exception('·�ɲ�����: :route',
				array(':route' => $name));
		}

		return Route::$_routes[$name];
	}

	/**
	 * ����·������
	 */
	public static function all()
	{
		return Route::$_routes;
	}

	/**
	 * ����·�ɵ�����
	 */
	public static function name(Route $route)
	{
		return array_search($route, Route::$_routes);
	}

	/**
	 * ����·����Ϣ
	 */
	public static function cache($save = FALSE)
	{
		if ($save === TRUE)
		{
			// ����
			Core::cache('Route::cache()', Route::$_routes);
		}
		else
		{
			if ($routes = Core::cache('Route::cache()'))
			{
				Route::$_routes = $routes;

				// �ѻ���
				return Route::$cache = TRUE;
			}
			else
			{
				// û�����ɻ���
				return Route::$cache = FALSE;
			}
		}
	}

	/**
	 * ����URL��Ϣ
	 *
	 *     echo URL::site(Route::get($name)->uri($params), $protocol);
	 * 
	 */
	public static function url($name, array $params = NULL, $protocol = NULL)
	{
		$route = Route::get($name);

		// �ⲿURL
		if ($route->is_external())
			return Route::get($name)->uri($params);
		else
			return URL::site(Route::get($name)->uri($params), $protocol);
	}

	/**
	 * ����һ��ת����URL������ʽ
	 */
	public static function compile($uri, array $regex = NULL)
	{
		if ( ! is_string($uri))
			return;

 
		// ת��Ԫ�ַ�
		$expression = preg_replace('#'.Route::REGEX_ESCAPE.'#', '\\\\$0', $uri);

		if (strpos($expression, '(') !== FALSE)
		{
			//��(��ת��Ϊ������ģʽ
			$expression = str_replace(array('(', ')'), array('(?:', ')?'), $expression);
		}

		//Ĭ�ϵ�KEY
		$expression = str_replace(array('<', '>'), array('(?P<', '>'.Route::REGEX_SEGMENT.')'), $expression);

		if ($regex)
		{
			$search = $replace = array();
			foreach ($regex as $key => $value)
			{
				$search[]  = "<$key>".Route::REGEX_SEGMENT;
				$replace[] = "<$key>$value";
			}

			// ת��Ϊ�����ַ���
			$expression = str_replace($search, $replace, $expression);
		}

		return '#^'.$expression.'$#uD';
	}

	/**
	 *  �ص�����
	 */
	protected $_callback; 
	protected $_uri = ''; 
	protected $_regex = array(); 
	protected $_defaults = array('action' => 'index', 'host' => FALSE); 
	protected $_route_regex;

	/**
	 * ���ӣ�
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
	 * @param   mixed    �ַ��������ߺ���
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

		//ƥ���������ʽ
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
	 * ���ڵ�ǰ��·������һ��URI��
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

		//���û������URI����
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

		//��ѡ����
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
		
		//�������
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
					throw new Core_Exception('��Ҫ�Ĳ���û������: :param', array(
						':param' => $param,
					));
				}
			}

			$uri = str_replace($key, $params[$param], $uri);
		}

		// ȥ��Э���е�//
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
