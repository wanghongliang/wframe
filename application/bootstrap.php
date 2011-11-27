<?php
require PATH_SYS.'classes/core.php';//加载内核

//是否有扩展基类
if (is_file(PATH_APP.'classes/app.php'))
{ 
	require PATH_APP.'classes/app.php';
}
else
{ 
	require PATH_SYS.'classes/app.php';
} 
// 设定时区
if(function_exists('date_default_timezone_set')) {
	date_default_timezone_set('Etc/GMT-8');
} else {
	putenv('Etc/GMT-8');
}

//自动加载类方法
spl_autoload_register(array('App', 'auto_load'));

//初始化框架
App::init(array(
	'base_url'   => '/',
));
Core::$log->attach(new Log_File(PATH_APP.'logs'));
Core::$config->attach(new Config_File);
//设置路由
Route::set('default', '(<controller>(/<action>(/<id>)))')
	->defaults(array(
		'controller' => 'welcome',
		'action'     => 'index',
	));