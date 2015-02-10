<?php
	set_include_path(get_include_path() . PATH_SEPARATOR .
		dirname(__FILE__) ."/include");

	/* remove ill effects of magic quotes */

	if (get_magic_quotes_gpc()) {
		function stripslashes_deep($value) {
			$value = is_array($value) ?
				array_map('stripslashes_deep', $value) : stripslashes($value);
				return $value;
		}

		$_POST = array_map('stripslashes_deep', $_POST);
		$_GET = array_map('stripslashes_deep', $_GET);
		$_COOKIE = array_map('stripslashes_deep', $_COOKIE);
		$_REQUEST = array_map('stripslashes_deep', $_REQUEST);
	}

	require_once "functions.php";
	require_once "sessions.php";
	require_once "db-prefs.php";
	require_once "sanity_check.php";
	require_once "version.php";
	require_once "config.php";
	require_once "prefs.php";
	require_once "users.php";

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

	$dt_add = get_script_dt_add();

	header('Content-Type: text/json; charset=utf-8');

	$op = $_REQUEST["op"];

	if (!$_SESSION["uid"] && $op != "fetch-profiles" && $op != "login") {
		print json_encode(array("error" => 6));
		return;
	} else if ($_SESSION["uid"]) {
		update_heartbeat($link);
	}

	if (!sanity_check($link)) { return; }

	switch ($op) {
	case "create-user":
		$login = strtolower(db_escape_string($_REQUEST["login"]));
		$rv = array();

		if ($_SESSION["access_level"] >= 10) {

			$result = db_query($link, "SELECT id FROM ttirc_users WHERE
				login = '$login'");

			if (db_num_rows($result) == 0) {
				$tmp_password = make_password();
				$salt = substr(bin2hex(get_random_bytes(125)), 0, 250);
				$pwd_hash = db_escape_string(encrypt_password($tmp_password, $salt, true));

				$rv[0] = T_sprintf("Created user %s with password <b>%s</b>.",
					$login, $tmp_password);

				db_query($link, "INSERT INTO ttirc_users
					(login, pwd_hash, email, nick, realname, salt)
					VALUES
					('$login', '$pwd_hash', '$login@localhost', '$login', '$login', '$salt')");
			} else {
				$rv[0] = T_sprintf("User %s already exists", $login);
			}

			$rv[1] = format_users($link);

			print json_encode($rv);
		}
		break;
	case "reset-password":
		$id = db_escape_string($_REQUEST["id"]);

		if ($_SESSION["access_level"] >= 10) {
			$tmp_password = make_password();

			$login = get_user_login($link, $id);

			$salt = substr(bin2hex(get_random_bytes(125)), 0, 250);
			$pwd_hash = db_escape_string(encrypt_password($tmp_password, $salt, true));

			db_query($link, "UPDATE ttirc_users SET pwd_hash = '$pwd_hash', salt = '$salt'
				WHERE id = '$id'");

			print json_encode(array("message" =>
				T_sprintf("Reset password of user %s to <b>%s</b>.", $login,
					$tmp_password)));
		}

		break;
	case "delete-user":
		$ids = db_escape_string($_REQUEST["ids"]);

		if ($_SESSION["access_level"] >= 10) {

			db_query($link, "DELETE FROM ttirc_users WHERE
				id in ($ids) AND id != " . $_SESSION["uid"]);

			print format_users($link);
		}
		break;
	case "users":
		if ($_SESSION["access_level"] >= 10) {
			show_users($link);
		}
		break;
	case "part-channel":
		$last_id = (int) db_escape_string($_REQUEST["last_id"]);
		$chan = db_escape_string($_REQUEST["chan"]);
		$connection_id = db_escape_string($_REQUEST["connection"]);

		if ($chan && valid_connection($link, $connection_id)) {
			handle_command($link, $connection_id, $chan, "/part");

			db_query($link, "DELETE FROM ttirc_channels WHERE channel = '$chan'
				AND connection_id = '$connection_id'");
		}

		$lines = get_new_lines($link, $last_id);
		$conn = get_conn_info($link);
		$chandata = get_chan_data($link, false);
		$params = get_misc_params($link);

		print json_encode(array($conn, $lines, $chandata, $params));
		break;
	case "query-user":
		$nick = db_escape_string(trim($_REQUEST["nick"]));
		$last_id = (int) db_escape_string($_REQUEST["last_id"]);
		$connection_id = db_escape_string($_REQUEST["connection"]);

		if ($nick && valid_connection($link, $connection_id)) {
			handle_command($link, $connection_id, $chan, "/query $nick");
		}

		$lines = get_new_lines($link, $last_id);
		$conn = get_conn_info($link);
		$chandata = get_chan_data($link, false);
		$params = get_misc_params($link);

		print json_encode(array($conn, $lines, $chandata, $params));
		break;

	case "send":
		$message = db_escape_string(trim($_REQUEST["message"]));
		$last_id = (int) db_escape_string($_REQUEST["last_id"]);
		$chan = db_escape_string($_REQUEST["chan"]);
		$connection_id = db_escape_string($_REQUEST["connection"]);
		$tab_type = db_escape_string($_REQUEST["tab_type"]);
		$send_only = $_REQUEST["send_only"] == "true";

		if ($message !== "" && valid_connection($link, $connection_id)) {
			if (strpos($message, "/") === 0) {
				handle_command($link, $connection_id, $chan, $message);
			} else {

				$message = shorten_urls($link, $message);

				$popcon_matches = array();
				preg_match_all("/(:[^ :]+:)/", $message, $popcon_matches);

				global $emoticons_map;

				if (count($popcon_matches[0]) > 0) {
					foreach ($popcon_matches[0] as $emoticon) {
						if (isset($emoticons_map[$emoticon])) {

							$emoticon = db_escape_string($emoticon);

							$result = db_query($link, "SELECT id, times_used FROM ttirc_emoticons_popcon
								WHERE emoticon = '$emoticon' AND owner_uid = " . $_SESSION["uid"]);

							if (db_num_rows($result) == 0) {
								db_query($link, "INSERT INTO ttirc_emoticons_popcon (emoticon, times_used, owner_uid)
									VALUES ('$emoticon', 1, ".$_SESSION["uid"].")");
							} else {
								$ref_id = db_fetch_result($result, 0, "id");
								$times_used = db_fetch_result($result, 0, "times_used");

#								if ($times_used < 1024) {
									db_query($link, "UPDATE ttirc_emoticons_popcon SET times_used = times_used + 1
										WHERE id = $ref_id");
#								}
							}
						}
					}
				}

				$lines = explode("\n", $message);

				if ($tab_type == "P") {
					foreach ($lines as $line)
						push_message($link, $connection_id, $chan, $line, false,
							MSGT_PRIVATE_PRIVMSG);
				} else {
					foreach ($lines as $line)
						push_message($link, $connection_id, $chan, $line);
				}

/*				$lines = explode("\n", wordwrap($message, 200, "\n"));

				foreach ($lines as $line) {
					push_message($link, $connection_id, $chan, $line);
				} */
			}
		}

		if (!$send_only) {
			$lines = get_new_lines($link, $last_id);
			$conn = get_conn_info($link);
			$chandata = get_chan_data($link, false);
			$params = get_misc_params($link);

			print json_encode(array($conn, $lines, $chandata, $params));
		}
		break;

	case "update":
		cleanup_session_cache();

		$last_id = (int) db_escape_string($_REQUEST["last_id"]);
		$init = db_escape_string($_REQUEST["init"]);
		@$uniqid = db_escape_string($_REQUEST["uniqid"]);

		if (!$init) {
			$sleep_start = time();
			while (time() - $sleep_start < UPDATE_DELAY_MAX &&
					!num_new_lines($link, $last_id)) {

				sleep(1);
			}
		}

		$lines = get_new_lines($link, $last_id);
		$conn = get_conn_info($link);
		$chandata = get_chan_data($link, false);
		$params = get_misc_params($link, $uniqid);
		$emoticons = render_emoticons($link);

		if ($uniqid) {
			if (serialize($conn) == $_SESSION["cache"][$uniqid]["conn"]) {
				$conn = array("duplicate" => true);
			} else {
				$_SESSION["cache"][$uniqid]["conn"] = serialize($conn);
			}

			if (serialize($chandata) == $_SESSION["cache"][$uniqid]["chandata"]) {
				$chandata = array("duplicate" => true);
			} else {
				$_SESSION["cache"][$uniqid]["chandata"] = serialize($chandata);
			}

			if (serialize($params) == $_SESSION["cache"][$uniqid]["params"]) {
				$params = array("duplicate" => true);
			} else {
				$_SESSION["cache"][$uniqid]["params"] = serialize($params);
			}

			if (serialize($emoticons) == $_SESSION["cache"][$uniqid]["emoticons"]) {
				$emoticons = array("duplicate" => true);
			} else {
				$_SESSION["cache"][$uniqid]["emoticons"] = serialize($emoticons);
			}

			$_SESSION["cache"][$uniqid]["last"] = time();
		}


		print json_encode(array($conn, $lines, $chandata, $params, $emoticons));
		break;

	case "set-topic":
		$last_id = (int) db_escape_string($_REQUEST["last_id"]);
		$topic = db_escape_string($_REQUEST["topic"]);
		$chan = db_escape_string($_REQUEST["chan"]);
		$connection_id = db_escape_string($_REQUEST["connection"]);

		if ($topic !== FALSE) {

			$topic = shorten_urls($link, $topic);

			handle_command($link, $connection_id, $chan, "/topic $topic");
		}

		$lines = get_new_lines($link, $last_id);
		$conn = get_conn_info($link);
		$chandata = get_chan_data($link, false);
		$params = get_misc_params($link);

		print json_encode(array($conn, $lines, $chandata, $params));

		break;

	case "login":
		$login = db_escape_string($_REQUEST["user"]);
		$password = db_escape_string($_REQUEST["password"]);

		$result = db_query($link, "SELECT id FROM ttirc_users WHERE login = '$login'");

		if (db_num_rows($result) != 0) {
			$uid = db_fetch_result($result, 0, "id");
		} else {
			$uid = 0;
		}

		if (!$uid) {
			print json_encode(array("error" => 6));
			return;
		}

		if (authenticate_user($link, $login, $password)) {
			print json_encode(array("sid" => session_id(), "version" => VERSION,
				"uniqid" => uniqid()));
		} else {
			print json_encode(array("error" => 6));
		}

		break;

	case "init":
		$result = db_query($link, "SELECT MAX(ttirc_messages.id) AS max_id
			FROM ttirc_messages, ttirc_connections
			WHERE connection_id = ttirc_connections.id AND owner_uid = " . $_SESSION["uid"]);

		$rv = array();

		if (db_num_rows($result) != 0) {
			$rv["max_id"] = db_fetch_result($result, 0, "max_id");
		} else {
			$rv["max_id"] = 0;
		}

		$rv["status"] = 1;

		$rv["theme"] = get_pref($link, "USER_THEME");
		$rv["update_delay_max"] = UPDATE_DELAY_MAX;
		$rv["uniqid"] = uniqid();

		global $emoticons_map;
		$rv["emoticons"] = $emoticons_map;

		print json_encode($rv);

		break;
	case "prefs":
		main_prefs($link);
		break;
	case "prefs-conn-save":
		$title = db_escape_string($_REQUEST["title"]);
		$autojoin = db_escape_string($_REQUEST["autojoin"]);
		$connect_cmd = db_escape_string($_REQUEST["connect_cmd"]);
		$encoding = db_escape_string($_REQUEST["encoding"]);
		$nick = db_escape_string($_REQUEST["nick"]);
		$auto_connect = bool_to_sql_bool(db_escape_string($_REQUEST["auto_connect"]));
		$permanent = bool_to_sql_bool(db_escape_string($_REQUEST["permanent"]));
		$connection_id = db_escape_string($_REQUEST["connection_id"]);
		$visible = bool_to_sql_bool(db_escape_string($_REQUEST["visible"]));
		$server_password = db_escape_string($_REQUEST["server_password"]);
		$use_ssl = bool_to_sql_bool(db_escape_string($_REQUEST["use_ssl"]));

		if (!$title) $title = __("[Untitled]");

		if (valid_connection($link, $connection_id)) {

			db_query($link, "UPDATE ttirc_connections SET title = '$title',
				autojoin = '$autojoin',
				connect_cmd = '$connect_cmd',
				auto_connect = $auto_connect,
				server_password = '$server_password',
				visible = $visible,
				use_ssl = $use_ssl,
				nick = '$nick',
				encoding = '$encoding',
				permanent = $permanent
				WHERE id = '$connection_id'");

			//print json_encode(array("error" => "Function not implemented."));
		}
		break;

	case "prefs-save":
		//print json_encode(array("error" => "Function not implemented."));

		$realname = db_escape_string($_REQUEST["realname"]);
		$quit_message = db_escape_string($_REQUEST["quit_message"]);
		$new_password = db_escape_string($_REQUEST["new_password"]);
		$confirm_password = db_escape_string($_REQUEST["confirm_password"]);
		$nick = db_escape_string($_REQUEST["nick"]);
		$email = db_escape_string($_REQUEST["email"]);
		$theme = db_escape_string($_REQUEST["theme"]);
		$highlight_on = db_escape_string($_REQUEST["highlight_on"]);
		$hide_join_part = bool_to_sql_bool(db_escape_string($_REQUEST["hide_join_part"]));
		$disable_image_preview = bool_to_sql_bool(db_escape_string($_REQUEST["disable_image_preview"]));

		$theme_changed = false;

		$_SESSION["prefs_cache"] = false;

		if (get_user_theme($link) != $theme) {
			set_pref($link, "USER_THEME", $theme);
			$theme_changed = true;
		}

		set_pref($link, "HIGHLIGHT_ON", $highlight_on);
		set_pref($link, "DISABLE_IMAGE_PREVIEW", $disable_image_preview);

		db_query($link, "UPDATE ttirc_users SET realname = '$realname',
			quit_message = '$quit_message',
			email = '$email',
			hide_join_part = $hide_join_part,
			nick = '$nick' WHERE id = " . $_SESSION["uid"]);

		if ($new_password != $confirm_password) {
			print json_encode(array("error" => "Passwords do not match."));
			return;
		}

		if ($confirm_password == $new_password && $new_password) {
			$salt = substr(bin2hex(get_random_bytes(125)), 0, 250);
			$pwd_hash =  encrypt_password($new_password, $salt, true);

			db_query($link, "UPDATE ttirc_users SET pwd_hash = '$pwd_hash', salt = '$salt'
				WHERE id = ". $_SESSION["uid"]);
		}

		if ($theme_changed) {
			print json_encode(array("message" => "THEME_CHANGED"));
			return;
		}

		break;
	case "prefs-edit-con":
		$connection_id = (int) db_escape_string($_REQUEST["id"]);
		connection_editor($link, $connection_id);
		break;
	case "prefs-customize-css":
		css_editor($link);
		break;
	case "prefs-save-css":
		$user_css = db_escape_string($_REQUEST["user_css"]);

		set_pref($link, "USER_STYLESHEET", $user_css);

		//print json_encode(array("error" => "Function not implemented."));
		break;
	case "create-server":
		$connection_id = (int) db_escape_string($_REQUEST["connection_id"]);
		list($server, $port) = explode(":", db_escape_string($_REQUEST["data"]));

		if (valid_connection($link, $connection_id)) {
			if ($server && $port) {
				db_query($link, "INSERT INTO ttirc_servers (server, port, connection_id)
					VALUES ('$server', '$port', '$connection_id')");

				print_servers($link, $connection_id);

			} else {

				$error = T_sprintf("Couldn't add server (%s:%d): Invalid syntax.",
					$server, $port);

				print json_encode(array("error" => $error));
			}
		}

		break;

	case "delete-server":
		$ids = db_escape_string($_REQUEST["ids"]);
		$connection_id = (int) db_escape_string($_REQUEST["connection_id"]);

		if (valid_connection($link, $connection_id)) {
			db_query($link, "DELETE FROM ttirc_servers WHERE
				id in ($ids) AND connection_id = '$connection_id'");

			print_servers($link, $connection_id);
		}
		break;

	case "delete-connection":
		$ids = db_escape_string($_REQUEST["ids"]);

		db_query($link, "DELETE FROM ttirc_connections WHERE
			id IN ($ids) AND status = 0 AND owner_uid = ".$_SESSION["uid"]);

		print_connections($link);

		break;
	case "create-connection":
		$title = db_escape_string(trim($_REQUEST["title"]));

		if ($title) {
			db_query($link, "INSERT INTO ttirc_connections (enabled, title, owner_uid)
				VALUES ('false', '$title', '".$_SESSION["uid"]."')");
		}

		print_connections($link);
		break;

		case "fetch-profiles":
			$login = db_escape_string($_REQUEST["login"]);
			$password = db_escape_string($_REQUEST["password"]);

			if (authenticate_user($link, $login, $password)) {
				$result = db_query($link, "SELECT * FROM ttirc_settings_profiles
					WHERE owner_uid = " . $_SESSION["uid"] . " ORDER BY title");

				print "<select style='width: 100%' name='profile'>";

				print "<option value='0'>" . __("Default profile") . "</option>";

				while ($line = db_fetch_assoc($result)) {
					$id = $line["id"];
					$title = $line["title"];

					print "<option value='$id'>$title</option>";
				}

				print "</select>";

				$_SESSION = array();
			}
		break;

	case "toggle-connection":
		$connection_id = (int) db_escape_string($_REQUEST["connection_id"]);

		$status = bool_to_sql_bool(db_escape_string($_REQUEST["set_enabled"]));

		db_query($link, "UPDATE ttirc_connections SET enabled = $status
			WHERE id = '$connection_id' AND owner_uid = " . $_SESSION["uid"]);

		print json_encode(array("status" => $status));

		break;

	case "prefs-edit-notify":
		notification_editor($link);

		break;

	case "prefs-save-notify":
		$notify_events = json_encode($_REQUEST["notify_event"]);

		set_pref($link, "NOTIFY_ON", $notify_events);

		break;

	case "preview":
		$url = htmlspecialchars($_REQUEST["url"]);

		header("Location: $url");

		break;

	case "emoticons":
		global $emoticons_map;
		$tmp = array();

		$result = db_query($link, "SELECT emoticon FROM ttirc_emoticons_popcon
					WHERE owner_uid = " . $_SESSION["uid"] . " ORDER BY times_used DESC LIMIT 30");

		while ($line = db_fetch_assoc($result)) {
			$k = $line["emoticon"];


			$tmp[$k] = $emoticons_map[$k];

		}

		if ($tmp) {
			print json_encode($tmp);
		} else {
			print json_encode(false);
		}
		break;

	case "emoticons_list":
		header('Content-Type: text/html; charset=utf-8');

		render_emoticons_full();
		break;

	case "logout":
		logout_user();
		header("Location: index.php");
		break;
	}
?>
