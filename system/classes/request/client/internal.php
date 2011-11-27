<?php defined('PATH_SYS') or die('不能直接访问.'); 
/**
 * 执行一个请求
 */
class Request_Client_Internal extends Request_Client {

	/**
	 * @var    array
	 */
	protected $_previous_environment;

	/**
	 * 根据request执行控制器的方法
	 */
	public function execute_request(Request $request)
	{
		// 创建控制器的前缀
		$prefix = 'controller_';

		// 控制器目录
		$directory = $request->directory();

		//控制器对象
		$controller = $request->controller();

		if ($directory)
		{
			// 把目录转成以_分割，作为控制器的前缀
			$prefix .= str_replace(array('\\', '/'), '_', trim($directory, '/')).'_';
		}

		//是否调试
		if (Core::$profiling)
		{
			//设置调试信息标签
			$benchmark = '"'.$request->uri().'"';

			if ($request !== Request::$initial AND Request::$current)
			{
				// 添加 parent request uri
				$benchmark .= ' « "'.Request::$current->uri().'"';
			}

			// 开始记录
			$benchmark = Profiler::start('Requests', $benchmark);
		}

		// 保存当前的URI
		$previous = Request::$current;

		// 改变当前的request
		Request::$current = $request;

		//判断当前request是否为初始化request
		$initial_request = ($request === Request::$initial);

		try
		{
			if ( ! class_exists($prefix.$controller))
			{
				throw new HTTP_Exception_404('The requested URL :uri was not found on this server.',
													array(':uri' => $request->uri()));
			}

			// 用反射机制加载一个控制器类
			$class = new ReflectionClass($prefix.$controller);

			if ($class->isAbstract())
			{
				throw new Core_Exception('Cannot create instances of abstract :controller',
					array(':controller' => $prefix.$controller));
			}

			// 创建一个控制器对象
			$controller = $class->newInstance($request, $request->response() ? $request->response() : $request->create_response());

			//调用 before 方法
			$class->getMethod('before')->invoke($controller);

			// 当前请求的方法
			$action = $request->action();

			$params = $request->param();

			// 如果这个方法并不存在，将会报出一个404错误
			if ( ! $class->hasMethod('action_'.$action))
			{
				throw new HTTP_Exception_404('The requested URL :uri was not found on this server.',
													array(':uri' => $request->uri()));
			}

			$method = $class->getMethod('action_'.$action);
			$method->invoke($controller);

			// 执行after方法
			$class->getMethod('after')->invoke($controller);
		}
		catch (Exception $e)
		{
			// 恢复request对象
			if ($previous instanceof Request)
			{
				Request::$current = $previous;
			}

			if (isset($benchmark))
			{
				// 删除benchmark，它没有执行
				Profiler::delete($benchmark);
			}

			//再抛出一个异常
			throw $e;
		}

		//执行完后，恢复request
		Request::$current = $previous;


		//并关闭 benchmark
		if (isset($benchmark))
		{
 			Profiler::stop($benchmark);
		}

		// 返回response信息
		return $request->response();
	}
}