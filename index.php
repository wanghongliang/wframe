<?php

//Ӧ��Ŀ¼����Ʊ��վ�����к��Ŀ���඼�����Ŀ¼
$application = 'application';

//ģ�飬���Ĺ�����չĿ¼
$modules = 'modules';

//���Ŀ¼
$system = 'system';
 

/**
 * ����������Ϊ��  E_ALL | E_STRICT
 *
 * ��Ʒ������Ϊ: E_ALL ^ E_NOTICE
 *
 * PHP >= 5.3: E_ALL & ~E_DEPRECATED
 */
error_reporting(E_ALL | E_STRICT);

define('PATH_ROOT', realpath(dirname(__FILE__)).DIRECTORY_SEPARATOR);  //��Ŀ¼
define('PATH_APP', PATH_ROOT.$application.DIRECTORY_SEPARATOR); //Ӧ��Ŀ¼
define('PATH_MOD', PATH_ROOT.$modules.DIRECTORY_SEPARATOR); //ģ��Ŀ¼
define('PATH_SYS', PATH_ROOT.$system.DIRECTORY_SEPARATOR);  //ϵͳĿ¼

//���Ա���
define('START_TIME', microtime(TRUE));
define('START_MEMORY', memory_get_usage());


//���س�ʼ������ļ�
require PATH_APP.'bootstrap.php'; 

//ִ��
echo Request::factory()
	->execute()
	->send_headers()
	->body();
