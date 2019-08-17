<?php

$main = c(PQNAME);

$php_files = find_files($main->modelsPath, "php");
if(!empty($php_files[0]))
	foreach($php_files as $php)
		require_once($php);