<?php
	require_once "functions.php";

	define('SCHEMA_VERSION', 11);
	define('EXPECTED_CONFIG_VERSION', 2);

	$err_msg = "";

	if (!file_exists("config.php")) {
		print "<b>Fatal Error</b>: You forgot to copy
		<b>config.php-dist</b> to <b>config.php</b> and edit it.\n";
		exit;
	}

	require_once "config.php";

	if (CONFIG_VERSION != EXPECTED_CONFIG_VERSION) {
		$err_msg = "config: your config file version is incorrect. See config.php-dist.\n";
	}

	if ($err_msg) {
		print "<b>Fatal Error</b>: $err_msg\n";
		exit;
	}

?>
