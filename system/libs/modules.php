<?php

$main = c(PQNAME);

function find_files($path, $ext){
	return glob("$path/*.$ext");
}

function pre($arg){
	return print(print_r($arg, true));
}

$php_files = find_files($main->modulesPath, "php");
if(!empty($php_files[0]))
	foreach($php_files as $php)
		require_once($php);