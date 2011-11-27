<?php defined('PATH_SYS') or die('不能直接访问.'); 
/**
 * 客户处理
 */
abstract class Request_Client {

 	protected $_cache;

	/**
	 * 创建一个Request_Client对象
	 * 可以传参数进行直接运行，调用该对象的方法 
	 */
	public function __construct(array $params = array())
	{
		foreach ($params as $key => $value)
		{
			if (method_exists($this, $key))
			{
				$this->$key($value);
			}
		}
	}

	/**
 	 * 处理请求信息，执行相应的控制器和其方法
	 * 1, 在调用方法之前，会调用 Controller::before 方法.
	 * 2, 然后调用控制器方法.
	 * 3, 最后调用 Controller::after 方法.
	 *
	 * 默认情况下

	 * 输出内容是捕获从控制器的方法调用的内容，没有发送头信息
	 *  
	 */
	public function execute(Request $request)
	{
		if ($this->_cache instanceof HTTP_Cache)
			return $this->_cache->execute($this, $request);

		return $this->execute_request($request);
	}

	/** 
	 * 通过URI信息处理一个REQUEST，并返回一个RESPONSE
	 */
	abstract public function execute_request(Request $request);

	/**
	 * 获取 或 设置内部缓存引擎
	 */
	public function cache(HTTP_Cache $cache = NULL)
	{
		if ($cache === NULL)
			return $this->_cache;

		$this->_cache = $cache;
		return $this;
	}
}