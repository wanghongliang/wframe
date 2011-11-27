<?php

//应用目录，彩票网站的所有核心框架类都在这个目录
$application = 'application';

//模块，核心功能扩展目录
$modules = 'modules';

//框架目录
$system = 'system';
 

/**
 * 开发环境设为：  E_ALL | E_STRICT
 *
 * 产品发布设为: E_ALL ^ E_NOTICE
 *
 * PHP >= 5.3: E_ALL & ~E_DEPRECATED
 */
error_reporting(E_ALL | E_STRICT);

define('PATH_ROOT', realpath(dirname(__FILE__)).DIRECTORY_SEPARATOR);  //根目录
define('PATH_APP', PATH_ROOT.$application.DIRECTORY_SEPARATOR); //应用目录
define('PATH_MOD', PATH_ROOT.$modules.DIRECTORY_SEPARATOR); //模块目录
define('PATH_SYS', PATH_ROOT.$system.DIRECTORY_SEPARATOR);  //系统目录

//调试备用
define('START_TIME', microtime(TRUE));
define('START_MEMORY', memory_get_usage());


//加载初始化框架文件
require PATH_APP.'bootstrap.php'; 

//执行
echo Request::factory()
	->execute()
	->send_headers()
	->body();
