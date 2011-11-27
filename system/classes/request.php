<?php defined('PATH_SYS') or die('不能直接访问.'); 
 
/**
 * Request 对象，主要封装
 * 1, 初始化请求信息
 * 2, 加载路由对象，并分析当前请求的执行控制器
 */
class Request  {
 
	public static $user_agent = '';   //用户代理字符串 
	public static $client_ip = '0.0.0.0'; //IP地址 
	public static $trusted_proxies = array('127.0.0.1', 'localhost', 'localhost.localdomain'); //值得信赖的代理服务器IP
	public static $initial;  //首次初始化实例 
	public static $current;  //当前Request实例

	/**
	 * 工厂方法，初始化REQUST，并创建request类
	 */
	public static function factory($uri = TRUE, HTTP_Cache $cache = NULL, $injected_routes = array())
	{
		// 是否已经初始化
		if ( ! Request::$initial)
		{
			if (Core::$is_cli)
			{
				// 命令行执行默认的协议为 cli://
				$protocol = 'cli';

				//这里不执行命令行的信息
				//....
			}
			else
			{  

				//初始化请求的相关参数
				if (isset($_SERVER['SERVER_PROTOCOL']))
				{
					$protocol = $_SERVER['SERVER_PROTOCOL'];
				}
				else
				{
					$protocol = HTTP::$protocol;
				}

				if (isset($_SERVER['REQUEST_METHOD'])){    
					$method = $_SERVER['REQUEST_METHOD']; 
				}else{ 
					$method = 'GET'; //默认的方法是GET方式
				}

				if ( ! empty($_SERVER['HTTPS']) AND filter_var($_SERVER['HTTPS'], FILTER_VALIDATE_BOOLEAN))
				{ 
					$secure = TRUE;
				}

				if (isset($_SERVER['HTTP_REFERER']))
				{ 
					$referrer = $_SERVER['HTTP_REFERER'];
				}

				if (isset($_SERVER['HTTP_USER_AGENT']))
				{ 
					Request::$user_agent = $_SERVER['HTTP_USER_AGENT'];
				}

				if (isset($_SERVER['HTTP_X_REQUESTED_WITH']))
				{ 
					$requested_with = $_SERVER['HTTP_X_REQUESTED_WITH'];
				}

				if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])
				    AND isset($_SERVER['REMOTE_ADDR'])
				    AND in_array($_SERVER['REMOTE_ADDR'], Request::$trusted_proxies))
				{ 
					$client_ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
					
					Request::$client_ip = array_shift($client_ips);

					unset($client_ips);
				}
				elseif (isset($_SERVER['HTTP_CLIENT_IP'])
				        AND isset($_SERVER['REMOTE_ADDR'])
				        AND in_array($_SERVER['REMOTE_ADDR'], Request::$trusted_proxies))
				{ 
					$client_ips = explode(',', $_SERVER['HTTP_CLIENT_IP']); 
					Request::$client_ip = array_shift($client_ips);

					unset($client_ips);
				}
				elseif (isset($_SERVER['REMOTE_ADDR']))
				{ 
					Request::$client_ip = $_SERVER['REMOTE_ADDR'];
				}

				if ($method !== 'GET')
				{ 
					$body = file_get_contents('php://input');
				}

				if ($uri === TRUE)
				{ 
					$uri = Request::detect_uri();
				}
			}
 
			Request::$initial = $request = new Request($uri, $cache);
 
			$request->protocol($protocol)
				->query($_GET)
				->post($_POST);

			if (isset($secure))
			{ 
				$request->secure($secure);
			}

			if (isset($method))
			{ 
				$request->method($method);
			}

			if (isset($referrer))
			{
				$request->referrer($referrer);
			}

			if (isset($requested_with))
			{
				$request->requested_with($requested_with);
			}

			if (isset($body))
			{
				$request->body($body);
			}
		}
		else
		{
			$request = new Request($uri, $cache, $injected_routes);
		}

		return $request;
	}

	/**
	 * 自动检测URI信息
	 */
	public static function detect_uri()
	{
		if ( ! empty($_SERVER['PATH_INFO']))
		{
 			$uri = $_SERVER['PATH_INFO'];
		}
		else
		{ 
			if (isset($_SERVER['REQUEST_URI']))
			{
 				$uri = $_SERVER['REQUEST_URI'];

				if ($request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))
				{
 					$uri = $request_uri;
				}
 				$uri = rawurldecode($uri);
			}
			elseif (isset($_SERVER['PHP_SELF']))
			{
				$uri = $_SERVER['PHP_SELF'];
			}
			elseif (isset($_SERVER['REDIRECT_URL']))
			{ 
				$uri = $_SERVER['REDIRECT_URL'];
			}
			else
			{
 				throw new Core_Exception('不能生成URI信息，以下环境变量不存在: PATH_INFO, REQUEST_URI, PHP_SELF or REDIRECT_URL');
			} 
			$base_url = parse_url(Core::$base_url, PHP_URL_PATH);

			if (strpos($uri, $base_url) === 0)
			{
 				$uri = (string) substr($uri, strlen($base_url));
			}

			if (Core::$index_file AND strpos($uri, Core::$index_file) === 0)
			{
 				$uri = (string) substr($uri, strlen(Core::$index_file));
			}
		}

		return $uri;
	}

	/**
	 * 返回当前REQUEST信息
	 */
	public static function current()
	{
		return Request::$current;
	}

	/**
	 * 判断是否初始化REQUEST 
	 */
	public static function initial()
	{
		return Request::$initial;
	}

	/**
	 * 返回用户浏览器的代理信息
	 * 多个参数，可以传数组参数
	 */
	public static function user_agent($value)
	{
		if (is_array($value))
		{
			$agent = array();
			foreach ($value as $v)
			{
				$agent[$v] = Request::user_agent($v);
			} 
			return $agent;
		}

		static $info; //静态数组，缓存查询过的变量 
		if (isset($info[$value]))
		{
			//从缓存变量中返回所求的值 
			return $info[$value];
		}

		if ($value === 'browser' OR $value == 'version')
		{
			// 加载浏览器配置文件
			$browsers = Core::$config->load('user_agents')->browser; 
			foreach ($browsers as $search => $name)
			{
				if (stripos(Request::$user_agent, $search) !== FALSE)
				{
					// 名称
					$info['browser'] = $name;

					if (preg_match('#'.preg_quote($search).'[^0-9.]*+([0-9.][0-9.a-z]*)#i', Request::$user_agent, $matches))
					{
						// 版本号
						$info['version'] = $matches[1];
					}
					else
					{
						// 没有版本号信息
						$info['version'] = FALSE;
					}

					return $info[$value];
				}
			}
		}
		else
		{
			$group = Core::$config->load('user_agents')->$value;

			foreach ($group as $search => $name)
			{
				if (stripos(Request::$user_agent, $search) !== FALSE)
				{
					return $info[$value] = $name;
				}
			}
		}

		return $info[$value] = FALSE;
	}

	/**
	 * 返回接受的内容类型。
	 */
	public static function accept_type($type = NULL)
	{
		static $accepts;

		if ($accepts === NULL)
		{
			// 分析 HTTP_ACCEPT 信息
			$accepts = Request::_parse_accept($_SERVER['HTTP_ACCEPT'], array('*/*' => 1.0));
		}

		if (isset($type))
		{
			// 返回分析的信息
			return isset($accepts[$type]) ? $accepts[$type] : $accepts['*/*'];
		}

		return $accepts;
	}

	/**
	 * 返回接受的语言信息
	 */
	public static function accept_lang($lang = NULL)
	{
		static $accepts;

		if ($accepts === NULL)
		{ 
			$accepts = Request::_parse_accept($_SERVER['HTTP_ACCEPT_LANGUAGE']);
		}

		if (isset($lang))
		{
			// 返回语言字符
			return isset($accepts[$lang]) ? $accepts[$lang] : FALSE;
		}

		return $accepts;
	}

	/**
	 * 返回编码
	 */
	public static function accept_encoding($type = NULL)
	{
		static $accepts;

		if ($accepts === NULL)
		{ 
			$accepts = Request::_parse_accept($_SERVER['HTTP_ACCEPT_ENCODING']);
		}

		if (isset($type))
		{ 
			return isset($accepts[$type]) ? $accepts[$type] : FALSE;
		}

		return $accepts;
	}

	/**
	 * 判断上传的文件大小是否起过服务器的设置
	 */
	public static function post_max_size_exceeded()
	{
		// 判断是否为POST方法
		if (Request::$initial->method() !== HTTP_Request::POST)
			return FALSE;

		// 计算服务器的设置
		$max_bytes = Num::bytes(ini_get('post_max_size'));

		// 是否超过上限
		return (Arr::get($_SERVER, 'CONTENT_LENGTH') > $max_bytes);
	}

	/**
	 * 处理 URI 
	 *
	 * @param   string  $uri    URI字符串
	 * @param   array   $routes 路由数组
	 * @return  array
	 */
	public static function process_uri($uri, $routes = NULL)
	{
		//加载所有路由信息
		$routes = (empty($routes)) ? Route::all() : $routes;
		$params = NULL;

		foreach ($routes as $name => $route)
		{
			// 判断是否匹配路由信息
			if ($params = $route->matches($uri))
			{
				return array(
					'params' => $params,
					'route' => $route,
				);
			}
		}

		return NULL;
	}

	/**
	 * 分析服务器环境变量，返回数组 
	 * 如：	GB2312,utf-8;q=0.7,*;q=0.7
	 */
	protected static function _parse_accept( & $header, array $accepts = NULL)
	{
		if ( ! empty($header))
		{
			// 分割成数组
			$types = explode(',', $header);

			foreach ($types as $type)
			{
				// 按;分割成多个部分
				$parts = explode(';', $type);

				// 先确定类型信息
				$type = trim(array_shift($parts));

				//默认的质量是1.0
				$quality = 1.0;

				foreach ($parts as $part)
				{
					//没有质量的话就返回
					if (strpos($part, '=') === FALSE)
						continue;

					//解析质量
					list ($key, $value) = explode('=', trim($part));
					if ($key === 'q')
					{
						$quality = (float) trim($value);
					}
				}

				// 设置质量
				$accepts[$type] = $quality;
			}
		}
 
		$accepts = (array) $accepts; 
		arsort($accepts); 
		return $accepts;
	}

	/**
	 * ajax 请求头信息
	 */
	protected $_requested_with;

	/**
	 * 请求方式
	 */
	protected $_method = 'GET';

	/**
	 * 请求协议
	 */
	protected $_protocol;

	/**
	 * @var  boolean
	 */
	protected $_secure = FALSE;

	/**
	 * 引用地址
	 */
	protected $_referrer;

	/**
	 * 区配本次请求的路由
	 */
	protected $_route;

	/**
	 * 当前路由
	 */
	protected $_routes;

	/**
	 * 回复处理
	 */
	protected $_response;

	/**
	 * 头部信息
	 */
	protected $_header;

	/**
	 * 主题信息
	 */
	protected $_body;

	/**
	 * 控制器目录
	 */
	protected $_directory = '';

	/**
	 * 控制器名称
	 */
	protected $_controller;

	/**
	 * 控制器
	 */
	protected $_action;

	/**
	 * 请求URI字符串
	 */
	protected $_uri;

	/**
	 * 是否是外部的请求
	 */
	protected $_external = FALSE;

	/**
	 * 路由参数信息
	 */
	protected $_params = array();

	/**
	 * GET 数组参数
	 */
	protected $_get = array();

	/**
	 * POST数组参数
	 */
	protected $_post = array();

	/**
	 * cookie数组
	 */
	protected $_cookies = array();

	/**
	 * client处理对象
	 */
	protected $_client;

	/**
	 * 通过URI创建一个REQUEST对象
	 */
	public function __construct($uri, HTTP_Cache $cache = NULL, $injected_routes = array())
	{
		// 初始化头部对象
		$this->_header = new HTTP_Header(array());

		// 设置注入路由
		$this->_injected_routes = $injected_routes;

		// 分析uri信息
		$split_uri = explode('?', $uri);
		$uri = array_shift($split_uri);

		// 初始化请求的时候，已应用GET信息
		if (Request::$initial !== NULL)
		{
			if ($split_uri)
			{
				parse_str($split_uri[0], $this->_get);
			}
		}

		// 自动检测协议 ( 如果存在)
		
		// 如果没有初始化，总是默认为内部请求
		//  这样可以防止代理
		// external pages.
		if (Request::$initial === NULL OR strpos($uri, '://') === FALSE)
		{
			//  　移出URI尾部的/
			$uri = trim($uri, '/');

			$processed_uri = Request::process_uri($uri, $this->_injected_routes);

			// 如果没有匹配的路由，直接返回URI
			if ($processed_uri === NULL)
			{
				$this->_uri = $uri;
				return;
			}

			// 设置uri
			$this->_uri = $uri;

			// 设置匹配的路由
			$this->_route = $processed_uri['route'];
			$params = $processed_uri['params'];

			//设置是否为外部的
			$this->_external = $this->_route->is_external();

			if (isset($params['directory']))
			{
				// 控制器目录
				$this->_directory = $params['directory'];
			}

			// 控制器
			$this->_controller = $params['controller'];

			if (isset($params['action']))
			{
				// 方法
				$this->_action = $params['action'];
			}
			else
			{
				// 默认的方法
				$this->_action = Route::$default_action;
			}

			// 这些都是作为公共变量可以访问
			unset($params['controller'], $params['action'], $params['directory']);
			$this->_params = $params;

			// 内部
			$this->_client = new Request_Client_Internal(array('cache' => $cache));
		}
		else
		{
			// 创建一个路由对象
			$this->_route = new Route($uri); 
			$this->_uri = $uri;

			// 如果为https设为安全访问
			if (strpos($uri, 'https://') === 0)
			{
				$this->secure(TRUE);
			}

			// 外部访问
			$this->_external = TRUE;

			// 设置外部访问处理对象
			$this->_client = Request_Client_External::factory(array('cache' => $cache));
		}
	}

	/**
	 * 返回响应的字符串
	 */
	public function __toString()
	{
		return $this->render();
	}

	/**
	 * 返回当前路由的URI。 
	 */
	public function uri()
	{
		return empty($this->_uri) ? '/' : $this->_uri;
	}

	/**
	 * 创建一个URL
	 */
	public function url($protocol = NULL)
	{
 		return URL::site($this->uri(), $protocol);
	}

	/**
	 * 返回参数信息
	 */
	public function param($key = NULL, $default = NULL)
	{
		if ($key === NULL)
		{
 			return $this->_params;
		}

		return isset($this->_params[$key]) ? $this->_params[$key] : $default;
	}

	/**
	 * 重定向
	 */
	public function redirect($url = '', $code = 302)
	{
		$referrer = $this->uri();

		if (strpos($referrer, '://') === FALSE)
		{
			$referrer = URL::site($referrer, TRUE, Core::$index_file);
		}

		if (strpos($url, '://') === FALSE)
		{
 			$url = URL::site($url, TRUE, Core::$index_file);
		}

		if (($response = $this->response()) === NULL)
		{
			$response = $this->create_response();
		}

		echo $response->status($code)
			->headers('Location', $url)
			->headers('Referer', $referrer)
			->send_headers()
			->body();
 
		exit;
	}

	/**
	 * 设置或者获取 request referrer
	 */
	public function referrer($referrer = NULL)
	{
		if ($referrer === NULL)
		{
 			return $this->_referrer;
		}

 		$this->_referrer = (string) $referrer;

		return $this;
	}

	/**
	 * 设置或获取 route
	 */
	public function route(Route $route = NULL)
	{
		if ($route === NULL)
		{
 			return $this->_route;
		}

 		$this->_route = $route;

		return $this;
	}

	/**
	 * 设置或者获取目录
	 */
	public function directory($directory = NULL)
	{
		if ($directory === NULL)
		{
 			return $this->_directory;
		}

 		$this->_directory = (string) $directory;

		return $this;
	}

	/**
	 * 设置或获取目录
	 */
	public function controller($controller = NULL)
	{
		if ($controller === NULL)
		{
 			return $this->_controller;
		}

 		$this->_controller = (string) $controller;

		return $this;
	}

	/**
	 * 设置或获取方法
	 */
	public function action($action = NULL)
	{
		if ($action === NULL)
		{
 			return $this->_action;
		}

 		$this->_action = (string) $action;

		return $this;
	}

	/**
	 * 设置和返回 Request_Client
	 */
	public function client(Request_Client $client = NULL)
	{
		if ($client === NULL)
			return $this->_client;
		else
		{
			$this->_client = $client;
			return $this;
		}
	}

 	public function requested_with($requested_with = NULL)
	{
		if ($requested_with === NULL)
		{
 			return $this->_requested_with;
		}

 		$this->_requested_with = strtolower($requested_with);

		return $this;
	}

	/**
	 * 初始化后，执行 request 
	 */
	public function execute()
	{
		if ( ! $this->_route instanceof Route)
		{
			throw new HTTP_Exception_404('Unable to find a route to match the URI: :uri', array(
				':uri' => $this->_uri,
			));
		}

		if ( ! $this->_client instanceof Request_Client)
		{
			throw new Request_Exception('Unable to execute :uri without a Kohana_Request_Client', array(
				':uri' => $this->_uri,
			));
		}

		return $this->_client->execute($this);
	}

	/**
	 * 判断是否为初始化请求
	 */
	public function is_initial()
	{
		return ($this === Request::$initial);
	}
 
	public function is_external()
	{
		return $this->_external;
	}
 
	public function is_ajax()
	{
		return ($this->requested_with() === 'xmlhttprequest');
	}

	/**
	 * 生成一个 ETAG
	 */
	public function generate_etag()
	{
	    if ($this->_response === NULL)
		{
			throw new Request_Exception('No response yet associated with request - cannot auto generate resource ETag');
		}

		return '"'.sha1($this->_response).'"';
	}
 

	//设置和获取一个response
	public function response(Response $response = NULL)
	{
		if ($response === NULL)
		{
 			return $this->_response;
		}

 		$this->_response = $response;

		return $this;
	}

 
	public function create_response($bind = TRUE)
	{
		$response = new Response(array('_protocol' => $this->protocol()));

		if ($bind)
		{
 			$this->_response = $response;
		}

		return $response;
	}

 	public function method($method = NULL)
	{
		if ($method === NULL)
		{
 			return $this->_method;
		}

 		$this->_method = strtoupper($method);

		return $this;
	}

 	public function protocol($protocol = NULL)
	{
		if ($protocol === NULL)
		{
			if ($this->_protocol)
				return $this->_protocol;
			else
				return $this->_protocol = HTTP::$protocol;
		}

 		$this->_protocol = strtoupper($protocol);
		return $this;
	}

 	public function secure($secure = NULL)
	{
		if ($secure === NULL)
			return $this->_secure;

 		$this->_secure = (bool) $secure;
		return $this;
	}

	/**
	 * 获取或设置HTTP头的请求或响应。 
	 */
	public function headers($key = NULL, $value = NULL)
	{
		if ($key instanceof HTTP_Header)
		{
			//设置 HTTP_Header 信息
			$this->_header = $key;
			return $this;
		}
		

		//设置一个头信息
		if (is_array($key))
		{
			// 设置头信息
			$this->_header->exchangeArray($key); 
			return $this;
		}

		if ($this->_header->count() === 0 AND $this->is_initial())
		{
			//如果没有设置头信息，在这里设置
			$this->_header = HTTP::request_headers();
		}

		if ($key === NULL)
		{
			// 返回头信息对象
			return $this->_header;
		}
		elseif ($value === NULL)
		{
			//返回指定的KEY信息
			return ($this->_header->offsetExists($key)) ? $this->_header->offsetGet($key) : NULL;
		}

		//设置
		$this->_header[$key] = $value;

		return $this;
	}

 	public function cookie($key = NULL, $value = NULL)
	{
		if (is_array($key))
		{
 			$this->_cookies = $key;
		}

		if ($key === NULL)
		{
 			return $this->_cookies;
		}
		elseif ($value === NULL)
		{
 			return isset($this->_cookies[$key]) ? $this->_cookies[$key] : NULL;
		}

 		$this->_cookies[$key] = (string) $value;

		return $this;
	}

 	public function body($content = NULL)
	{
		if ($content === NULL)
		{
 			return $this->_body;
		}

 		$this->_body = $content;

		return $this;
	}

 
	public function content_length()
	{
		return strlen($this->body());
	}

	/**
	 * 
	 */
	public function render()
	{
		if ( ! $post = $this->post())
		{
			$body = $this->body();
		}
		else
		{
			$this->headers('content-type', 'application/x-www-form-urlencoded');
			$body = http_build_query($post, NULL, '&');
		}

		// 设置内容长度
		$this->headers('content-length', (string) $this->content_length());

		// 如果需要设置 user-agent , 信息，就设置 CoreFramework信息
		if (Core::$expose)
		{
			$this->headers('user-agent', 'Core Framework '.Core::VERSION.' ('.Core::CODENAME.')');
		}

		// COOKIE
		if ($this->_cookies)
		{
			$cookie_string = array();
  			foreach ($this->_cookies as $key => $value)
			{
				$cookie_string[] = $key.'='.$value;
			}
 			// 设置cookie信息
			$this->_header['cookie'] = implode('; ', $cookie_string);
		}

		$output = $this->method().' '.$this->uri().' '.$this->protocol()."\r\n";
		$output .= (string) $this->_header;
		$output .= $body;
		return $output;
	}

	/**
	 * 设置 或 取 $_GET变量
	 */
	public function query($key = NULL, $value = NULL)
	{
		if (is_array($key))
		{
 			$this->_get = $key;

			return $this;
		}

		if ($key === NULL)
		{
 			return $this->_get;
		}
		elseif ($value === NULL)
		{
 			return Arr::get($this->_get, $key);
		}

 		$this->_get[$key] = $value;

		return $this;
	}

	/**
	 * 设或取POST变量
	 */
	public function post($key = NULL, $value = NULL)
	{
		if (is_array($key))
		{
			// 设置post
			$this->_post = $key;

			return $this;
		}

		if ($key === NULL)
		{
			// 返回post
			return $this->_post;
		}
		elseif ($value === NULL)
		{
			// 取post
			return Arr::get($this->_post, $key);
		}

		//设置指定KEY的POST信息
		$this->_post[$key] = $value;

		return $this;
	}

}  
