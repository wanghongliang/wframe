<?php defined('PATH_SYS') or die('����ֱ�ӷ���.'); 
 
/**
 * Request ������Ҫ��װ
 * 1, ��ʼ��������Ϣ
 * 2, ����·�ɶ��󣬲�������ǰ�����ִ�п�����
 */
class Request  {
 
	public static $user_agent = '';   //�û������ַ��� 
	public static $client_ip = '0.0.0.0'; //IP��ַ 
	public static $trusted_proxies = array('127.0.0.1', 'localhost', 'localhost.localdomain'); //ֵ�������Ĵ��������IP
	public static $initial;  //�״γ�ʼ��ʵ�� 
	public static $current;  //��ǰRequestʵ��

	/**
	 * ������������ʼ��REQUST��������request��
	 */
	public static function factory($uri = TRUE, HTTP_Cache $cache = NULL, $injected_routes = array())
	{
		// �Ƿ��Ѿ���ʼ��
		if ( ! Request::$initial)
		{
			if (Core::$is_cli)
			{
				// ������ִ��Ĭ�ϵ�Э��Ϊ cli://
				$protocol = 'cli';

				//���ﲻִ�������е���Ϣ
				//....
			}
			else
			{  

				//��ʼ���������ز���
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
					$method = 'GET'; //Ĭ�ϵķ�����GET��ʽ
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
	 * �Զ����URI��Ϣ
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
 				throw new Core_Exception('��������URI��Ϣ�����»�������������: PATH_INFO, REQUEST_URI, PHP_SELF or REDIRECT_URL');
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
	 * ���ص�ǰREQUEST��Ϣ
	 */
	public static function current()
	{
		return Request::$current;
	}

	/**
	 * �ж��Ƿ��ʼ��REQUEST 
	 */
	public static function initial()
	{
		return Request::$initial;
	}

	/**
	 * �����û�������Ĵ�����Ϣ
	 * ������������Դ��������
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

		static $info; //��̬���飬�����ѯ���ı��� 
		if (isset($info[$value]))
		{
			//�ӻ�������з��������ֵ 
			return $info[$value];
		}

		if ($value === 'browser' OR $value == 'version')
		{
			// ��������������ļ�
			$browsers = Core::$config->load('user_agents')->browser; 
			foreach ($browsers as $search => $name)
			{
				if (stripos(Request::$user_agent, $search) !== FALSE)
				{
					// ����
					$info['browser'] = $name;

					if (preg_match('#'.preg_quote($search).'[^0-9.]*+([0-9.][0-9.a-z]*)#i', Request::$user_agent, $matches))
					{
						// �汾��
						$info['version'] = $matches[1];
					}
					else
					{
						// û�а汾����Ϣ
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
	 * ���ؽ��ܵ��������͡�
	 */
	public static function accept_type($type = NULL)
	{
		static $accepts;

		if ($accepts === NULL)
		{
			// ���� HTTP_ACCEPT ��Ϣ
			$accepts = Request::_parse_accept($_SERVER['HTTP_ACCEPT'], array('*/*' => 1.0));
		}

		if (isset($type))
		{
			// ���ط�������Ϣ
			return isset($accepts[$type]) ? $accepts[$type] : $accepts['*/*'];
		}

		return $accepts;
	}

	/**
	 * ���ؽ��ܵ�������Ϣ
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
			// ���������ַ�
			return isset($accepts[$lang]) ? $accepts[$lang] : FALSE;
		}

		return $accepts;
	}

	/**
	 * ���ر���
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
	 * �ж��ϴ����ļ���С�Ƿ����������������
	 */
	public static function post_max_size_exceeded()
	{
		// �ж��Ƿ�ΪPOST����
		if (Request::$initial->method() !== HTTP_Request::POST)
			return FALSE;

		// ���������������
		$max_bytes = Num::bytes(ini_get('post_max_size'));

		// �Ƿ񳬹�����
		return (Arr::get($_SERVER, 'CONTENT_LENGTH') > $max_bytes);
	}

	/**
	 * ���� URI 
	 *
	 * @param   string  $uri    URI�ַ���
	 * @param   array   $routes ·������
	 * @return  array
	 */
	public static function process_uri($uri, $routes = NULL)
	{
		//��������·����Ϣ
		$routes = (empty($routes)) ? Route::all() : $routes;
		$params = NULL;

		foreach ($routes as $name => $route)
		{
			// �ж��Ƿ�ƥ��·����Ϣ
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
	 * ���������������������������� 
	 * �磺	GB2312,utf-8;q=0.7,*;q=0.7
	 */
	protected static function _parse_accept( & $header, array $accepts = NULL)
	{
		if ( ! empty($header))
		{
			// �ָ������
			$types = explode(',', $header);

			foreach ($types as $type)
			{
				// ��;�ָ�ɶ������
				$parts = explode(';', $type);

				// ��ȷ��������Ϣ
				$type = trim(array_shift($parts));

				//Ĭ�ϵ�������1.0
				$quality = 1.0;

				foreach ($parts as $part)
				{
					//û�������Ļ��ͷ���
					if (strpos($part, '=') === FALSE)
						continue;

					//��������
					list ($key, $value) = explode('=', trim($part));
					if ($key === 'q')
					{
						$quality = (float) trim($value);
					}
				}

				// ��������
				$accepts[$type] = $quality;
			}
		}
 
		$accepts = (array) $accepts; 
		arsort($accepts); 
		return $accepts;
	}

	/**
	 * ajax ����ͷ��Ϣ
	 */
	protected $_requested_with;

	/**
	 * ����ʽ
	 */
	protected $_method = 'GET';

	/**
	 * ����Э��
	 */
	protected $_protocol;

	/**
	 * @var  boolean
	 */
	protected $_secure = FALSE;

	/**
	 * ���õ�ַ
	 */
	protected $_referrer;

	/**
	 * ���䱾�������·��
	 */
	protected $_route;

	/**
	 * ��ǰ·��
	 */
	protected $_routes;

	/**
	 * �ظ�����
	 */
	protected $_response;

	/**
	 * ͷ����Ϣ
	 */
	protected $_header;

	/**
	 * ������Ϣ
	 */
	protected $_body;

	/**
	 * ������Ŀ¼
	 */
	protected $_directory = '';

	/**
	 * ����������
	 */
	protected $_controller;

	/**
	 * ������
	 */
	protected $_action;

	/**
	 * ����URI�ַ���
	 */
	protected $_uri;

	/**
	 * �Ƿ����ⲿ������
	 */
	protected $_external = FALSE;

	/**
	 * ·�ɲ�����Ϣ
	 */
	protected $_params = array();

	/**
	 * GET �������
	 */
	protected $_get = array();

	/**
	 * POST�������
	 */
	protected $_post = array();

	/**
	 * cookie����
	 */
	protected $_cookies = array();

	/**
	 * client�������
	 */
	protected $_client;

	/**
	 * ͨ��URI����һ��REQUEST����
	 */
	public function __construct($uri, HTTP_Cache $cache = NULL, $injected_routes = array())
	{
		// ��ʼ��ͷ������
		$this->_header = new HTTP_Header(array());

		// ����ע��·��
		$this->_injected_routes = $injected_routes;

		// ����uri��Ϣ
		$split_uri = explode('?', $uri);
		$uri = array_shift($split_uri);

		// ��ʼ�������ʱ����Ӧ��GET��Ϣ
		if (Request::$initial !== NULL)
		{
			if ($split_uri)
			{
				parse_str($split_uri[0], $this->_get);
			}
		}

		// �Զ����Э�� ( �������)
		
		// ���û�г�ʼ��������Ĭ��Ϊ�ڲ�����
		//  �������Է�ֹ����
		// external pages.
		if (Request::$initial === NULL OR strpos($uri, '://') === FALSE)
		{
			//  ���Ƴ�URIβ����/
			$uri = trim($uri, '/');

			$processed_uri = Request::process_uri($uri, $this->_injected_routes);

			// ���û��ƥ���·�ɣ�ֱ�ӷ���URI
			if ($processed_uri === NULL)
			{
				$this->_uri = $uri;
				return;
			}

			// ����uri
			$this->_uri = $uri;

			// ����ƥ���·��
			$this->_route = $processed_uri['route'];
			$params = $processed_uri['params'];

			//�����Ƿ�Ϊ�ⲿ��
			$this->_external = $this->_route->is_external();

			if (isset($params['directory']))
			{
				// ������Ŀ¼
				$this->_directory = $params['directory'];
			}

			// ������
			$this->_controller = $params['controller'];

			if (isset($params['action']))
			{
				// ����
				$this->_action = $params['action'];
			}
			else
			{
				// Ĭ�ϵķ���
				$this->_action = Route::$default_action;
			}

			// ��Щ������Ϊ�����������Է���
			unset($params['controller'], $params['action'], $params['directory']);
			$this->_params = $params;

			// �ڲ�
			$this->_client = new Request_Client_Internal(array('cache' => $cache));
		}
		else
		{
			// ����һ��·�ɶ���
			$this->_route = new Route($uri); 
			$this->_uri = $uri;

			// ���Ϊhttps��Ϊ��ȫ����
			if (strpos($uri, 'https://') === 0)
			{
				$this->secure(TRUE);
			}

			// �ⲿ����
			$this->_external = TRUE;

			// �����ⲿ���ʴ������
			$this->_client = Request_Client_External::factory(array('cache' => $cache));
		}
	}

	/**
	 * ������Ӧ���ַ���
	 */
	public function __toString()
	{
		return $this->render();
	}

	/**
	 * ���ص�ǰ·�ɵ�URI�� 
	 */
	public function uri()
	{
		return empty($this->_uri) ? '/' : $this->_uri;
	}

	/**
	 * ����һ��URL
	 */
	public function url($protocol = NULL)
	{
 		return URL::site($this->uri(), $protocol);
	}

	/**
	 * ���ز�����Ϣ
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
	 * �ض���
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
	 * ���û��߻�ȡ request referrer
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
	 * ���û��ȡ route
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
	 * ���û��߻�ȡĿ¼
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
	 * ���û��ȡĿ¼
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
	 * ���û��ȡ����
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
	 * ���úͷ��� Request_Client
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
	 * ��ʼ����ִ�� request 
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
	 * �ж��Ƿ�Ϊ��ʼ������
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
	 * ����һ�� ETAG
	 */
	public function generate_etag()
	{
	    if ($this->_response === NULL)
		{
			throw new Request_Exception('No response yet associated with request - cannot auto generate resource ETag');
		}

		return '"'.sha1($this->_response).'"';
	}
 

	//���úͻ�ȡһ��response
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
	 * ��ȡ������HTTPͷ���������Ӧ�� 
	 */
	public function headers($key = NULL, $value = NULL)
	{
		if ($key instanceof HTTP_Header)
		{
			//���� HTTP_Header ��Ϣ
			$this->_header = $key;
			return $this;
		}
		

		//����һ��ͷ��Ϣ
		if (is_array($key))
		{
			// ����ͷ��Ϣ
			$this->_header->exchangeArray($key); 
			return $this;
		}

		if ($this->_header->count() === 0 AND $this->is_initial())
		{
			//���û������ͷ��Ϣ������������
			$this->_header = HTTP::request_headers();
		}

		if ($key === NULL)
		{
			// ����ͷ��Ϣ����
			return $this->_header;
		}
		elseif ($value === NULL)
		{
			//����ָ����KEY��Ϣ
			return ($this->_header->offsetExists($key)) ? $this->_header->offsetGet($key) : NULL;
		}

		//����
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

		// �������ݳ���
		$this->headers('content-length', (string) $this->content_length());

		// �����Ҫ���� user-agent , ��Ϣ�������� CoreFramework��Ϣ
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
 			// ����cookie��Ϣ
			$this->_header['cookie'] = implode('; ', $cookie_string);
		}

		$output = $this->method().' '.$this->uri().' '.$this->protocol()."\r\n";
		$output .= (string) $this->_header;
		$output .= $body;
		return $output;
	}

	/**
	 * ���� �� ȡ $_GET����
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
	 * ���ȡPOST����
	 */
	public function post($key = NULL, $value = NULL)
	{
		if (is_array($key))
		{
			// ����post
			$this->_post = $key;

			return $this;
		}

		if ($key === NULL)
		{
			// ����post
			return $this->_post;
		}
		elseif ($value === NULL)
		{
			// ȡpost
			return Arr::get($this->_post, $key);
		}

		//����ָ��KEY��POST��Ϣ
		$this->_post[$key] = $value;

		return $this;
	}

}  
