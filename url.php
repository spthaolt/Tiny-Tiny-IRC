<?php
	set_include_path(get_include_path() . PATH_SEPARATOR .
		dirname(__FILE__) ."/include");

	require_once "functions.php";
	require_once "sessions.php";
	require_once "sanity_check.php";
	require_once "version.php";
	require_once "config.php";

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

	$id = db_escape_string(shorten_key_to_id($_REQUEST['id']));

	$result = db_query($link, "SELECT url FROM ttirc_shorturls
		WHERE id = '$id'");

	if (db_num_rows($result) != 0) {
		$url = db_fetch_result($result, 0, "url");
		header("Location: $url");
	}

	db_query($link, "DELETE FROM ttirc_shorturls WHERE
		created < NOW() - INTERVAL '30 days'");
?>
