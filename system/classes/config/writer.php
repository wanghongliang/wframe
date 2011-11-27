<?php defined('SYSPATH') or die('No direct script access.'); 
interface Config_Writer 
{
	public function write($group, $key, $config);
}
