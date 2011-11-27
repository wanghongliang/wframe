<?php
require PATH_SYS.'classes/core.php';//�����ں�

//�Ƿ�����չ����
if (is_file(PATH_APP.'classes/app.php'))
{ 
	require PATH_APP.'classes/app.php';
}
else
{ 
	require PATH_SYS.'classes/app.php';
} 
// �趨ʱ��
if(function_exists('date_default_timezone_set')) {
	date_default_timezone_set('Etc/GMT-8');
} else {
	putenv('Etc/GMT-8');
}

//�Զ������෽��
spl_autoload_register(array('App', 'auto_load'));

//��ʼ�����
App::init(array(
	'base_url'   => '/',
));
Core::$log->attach(new Log_File(PATH_APP.'logs'));
Core::$config->attach(new Config_File);
//����·��
Route::set('default', '(<controller>(/<action>(/<id>)))')
	->defaults(array(
		'controller' => 'welcome',
		'action'     => 'index',
	));