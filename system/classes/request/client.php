<?php defined('PATH_SYS') or die('����ֱ�ӷ���.'); 
/**
 * �ͻ�����
 */
abstract class Request_Client {

 	protected $_cache;

	/**
	 * ����һ��Request_Client����
	 * ���Դ���������ֱ�����У����øö���ķ��� 
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
 	 * ����������Ϣ��ִ����Ӧ�Ŀ��������䷽��
	 * 1, �ڵ��÷���֮ǰ������� Controller::before ����.
	 * 2, Ȼ����ÿ���������.
	 * 3, ������ Controller::after ����.
	 *
	 * Ĭ�������

	 * ��������ǲ���ӿ������ķ������õ����ݣ�û�з���ͷ��Ϣ
	 *  
	 */
	public function execute(Request $request)
	{
		if ($this->_cache instanceof HTTP_Cache)
			return $this->_cache->execute($this, $request);

		return $this->execute_request($request);
	}

	/** 
	 * ͨ��URI��Ϣ����һ��REQUEST��������һ��RESPONSE
	 */
	abstract public function execute_request(Request $request);

	/**
	 * ��ȡ �� �����ڲ���������
	 */
	public function cache(HTTP_Cache $cache = NULL)
	{
		if ($cache === NULL)
			return $this->_cache;

		$this->_cache = $cache;
		return $this;
	}
}