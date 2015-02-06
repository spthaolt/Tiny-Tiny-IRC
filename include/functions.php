<?php
	if (defined('E_DEPRECATED')) {
		error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
	} else {
		error_reporting(E_ALL & ~E_NOTICE);
	}

	mb_internal_encoding("UTF-8");

	require_once "config.php";
	require_once "version.php";
	require_once "message_types.php";
	require_once "db-prefs.php";
	require_once "shorten.php";

	require_once "lib/ShortUrl.php";

	require_once "emoticons/map.php";

	if (file_exists("emoticons/map_local.php"))
	  	require_once "emoticons/map_local.php";

	define('SINGLE_USER_MODE', false);

	if (DB_TYPE == 'mysql') {
		define('DB_KEY_FIELD', 'param');
	} else {
		define('DB_KEY_FIELD', 'key');
	}

	if (DB_TYPE == "pgsql") {
		define('SUBSTRING_FOR_DATE', 'SUBSTRING_FOR_DATE');
	} else {
		define('SUBSTRING_FOR_DATE', 'SUBSTRING');
	}

	function get_translations() {
		$tr = array(
					"auto"  => "Detect automatically",
					"en_US" => "English",
					"es_MX" => "Spanish (Mexican)",
					"fr_FR" => "Français",
					"xx_XX" => "Bork bork bork!");

		return $tr;
	}

	if (ENABLE_TRANSLATIONS == true) { // If translations are enabled.
		require_once "lib/accept-to-gettext.php";
		require_once "lib/gettext/gettext.inc";

		function startup_gettext() {

			# Get locale from Accept-Language header
			$lang = al2gt(array_keys(get_translations()), "text/html");

			if (defined('_TRANSLATION_OVERRIDE_DEFAULT')) {
				$lang = _TRANSLATION_OVERRIDE_DEFAULT;
			}

			if ($_COOKIE["ttirc_lang"] && $_COOKIE["ttirc_lang"] != "auto") {
				$lang = $_COOKIE["ttirc_lang"];
			}

			/* In login action of mobile version */
			if ($_POST["language"] && defined('MOBILE_VERSION')) {
				$lang = $_POST["language"];
				$_COOKIE["ttirc_lang"] = $lang;
			}

			if ($lang) {
				if (defined('LC_MESSAGES')) {
					_setlocale(LC_MESSAGES, $lang);
				} else if (defined('LC_ALL')) {
					_setlocale(LC_ALL, $lang);
				} else {
					die("can't setlocale(): please set ENABLE_TRANSLATIONS to false in config.php");
				}

				if (defined('MOBILE_VERSION')) {
					_bindtextdomain("messages", "../locale");
				} else {
					_bindtextdomain("messages", "locale");
				}

				_textdomain("messages");
				_bind_textdomain_codeset("messages", "UTF-8");
			}
		}

		startup_gettext();

	} else { // If translations are enabled.
		function __($msg) {
			return $msg;
		}
		function startup_gettext() {
			// no-op
			return true;
		}
	} // If translations are enabled.

	require_once "errors.php";

	function init_connection($link) {

		if (!$link) {
			if (DB_TYPE == "mysql") {
			print mysql_error();
		}
		// PG seems to display its own errors just fine by default.
			die("Connection failed.");
		}

		if (DB_TYPE == "pgsql") {
			pg_query($link, "set client_encoding = 'UTF-8'");
			pg_set_client_encoding("UNICODE");
			pg_query($link, "set datestyle = 'ISO, european'");
		} else {
			if (defined('MYSQL_CHARSET') && MYSQL_CHARSET) {
				db_query($link, "SET NAMES " . MYSQL_CHARSET);
	//			db_query($link, "SET CHARACTER SET " . MYSQL_CHARSET);
			}
		}
	}

	function login_sequence($link, $mobile = false) {

		$_SESSION["prefs_cache"] = array();

		if (!SINGLE_USER_MODE) {

			$login_action = $_POST["login_action"];

			# try to authenticate user if called from login form
			if ($login_action == "do_login") {
				$login = $_POST["login"];
				$password = $_POST["password"];
				$remember_me = $_POST["remember_me"];

				if (authenticate_user($link, $login, $password)) {
					$_POST["password"] = "";

					$_SESSION["language"] = $_POST["language"];
					$_SESSION["ref_schema_version"] = get_schema_version($link, true);
					$_SESSION["bw_limit"] = !!$_POST["bw_limit"];

					header("Location: " . $_SERVER["REQUEST_URI"]);
					exit;

					return;
				} else {
					$_SESSION["login_error_msg"] = __("Incorrect username or password");
				}
			}

			if (!$_SESSION["uid"] || !validate_session($link)) {
				header('Cache-Control: public');
				render_login_form($link, $mobile);
				//header("Location: login.php");
				exit;
			} else {
				/* bump login timestamp */

				if (get_schema_version($link) >= 3) {
					db_query($link, "UPDATE ttirc_users SET last_login = NOW() WHERE id = " .
						$_SESSION["uid"]);
				}

				if ($_SESSION["language"] && SESSION_COOKIE_LIFETIME > 0) {
					setcookie("ttirc_lang", $_SESSION["language"],
						time() + SESSION_COOKIE_LIFETIME);
				}

				/* Enable automatic connections */

				db_query($link, "UPDATE ttirc_connections SET enabled = true
					WHERE auto_connect = true AND owner_uid = " . $_SESSION["uid"]);

				initialize_user_prefs($link, $_SESSION["uid"]);

/*				$tmp_result = db_query($link, "SELECT id FROM ttirc_connections
					WHERE status != ".CS_DISCONNECTED." AND owner_uid = " .
					$_SESSION["uid"]);

				while ($conn = db_fetch_assoc($tmp_result)) {
					push_message($link, $conn['id'], "---",
						"Accepted connection from " . $_SERVER["REMOTE_ADDR"],
						true);
				} */
			}

		} else {
			return authenticate_user($link, "admin", null);
		}
	}

	function render_login_form($link, $mobile = 0) {
		switch ($mobile) {
		case 0:
			require_once "login_form.php";
			break;
		case 1:
			require_once "mobile/login_form.php";
			break;
		case 2:
			require_once "mobile/classic/login_form.php";
		}
	}

	function print_select($id, $default, $values, $attributes = "") {
		print "<select name=\"$id\" id=\"$id\" $attributes>";
		foreach ($values as $v) {
			if ($v == $default)
				$sel = " selected";
			 else
			 	$sel = "";

			print "<option$sel>$v</option>";
		}
		print "</select>";
	}

	function print_select_hash($id, $default, $values, $attributes = "") {
		print "<select name=\"$id\" id='$id' $attributes>";
		foreach (array_keys($values) as $v) {
			if ($v == $default)
				$sel = 'selected="selected"';
			 else
			 	$sel = "";

			print "<option $sel value=\"$v\">".$values[$v]."</option>";
		}

		print "</select>";
	}

	function encrypt_password($pass, $salt = '', $mode2 = false) {
		if ($salt && $mode2) {
			return "MODE2:" . hash('sha256', $salt . $pass);
		} else if ($salt) {
			return "SHA1X:" . sha1("$salt:$pass");
		} else {
			return "SHA1:" . sha1($pass);
		}
	} // function encrypt_password

	function authenticate_user($link, $login, $password, $force_auth = false) {

		if (!SINGLE_USER_MODE) {

			$pwd_hash1 = encrypt_password($password);
			$pwd_hash2 = encrypt_password($password, $login);
			$login = db_escape_string($login);

			if (defined('ALLOW_REMOTE_USER_AUTH') && ALLOW_REMOTE_USER_AUTH
					&& $_SERVER["REMOTE_USER"] && $login != "admin") {

				$login = db_escape_string($_SERVER["REMOTE_USER"]);

				$query = "SELECT id,login,access_level,pwd_hash
	            FROM ttirc_users WHERE
					login = '$login'";

			} else if (get_schema_version($link) > 6) {
				$result = db_query($link, "SELECT salt FROM ttirc_users WHERE
					login = '$login'");

				$salt = db_fetch_result($result, 0, "salt");

				if ($salt == "") {

					$query = "SELECT id,login,access_level,pwd_hash
		            FROM ttirc_users WHERE
						login = '$login' AND (pwd_hash = '$pwd_hash1' OR
						pwd_hash = '$pwd_hash2')";

					// verify and upgrade password to new salt base

					$result = db_query($link, $query);

					if (db_num_rows($result) == 1) {
						// upgrade password to MODE2

						$salt = substr(bin2hex(get_random_bytes(125)), 0, 250);
						$pwd_hash = encrypt_password($password, $salt, true);

						db_query($link, "UPDATE ttirc_users SET
							pwd_hash = '$pwd_hash', salt = '$salt' WHERE login = '$login'");

						$query = "SELECT id,login,access_level,pwd_hash
			            FROM ttirc_users WHERE
							login = '$login' AND pwd_hash = '$pwd_hash'";

					} else {
						return false;
					}

				} else {

					$pwd_hash = encrypt_password($password, $salt, true);

					$query = "SELECT id,login,access_level,pwd_hash
			         FROM ttirc_users WHERE
						login = '$login' AND pwd_hash = '$pwd_hash'";

				}

			} else {
				$query = "SELECT id,login,access_level,pwd_hash
	            FROM ttirc_users WHERE
					login = '$login' AND (pwd_hash = '$pwd_hash1' OR
						pwd_hash = '$pwd_hash2')";
			}

			$result = db_query($link, $query);

			if (db_num_rows($result) == 1) {
				$_SESSION["uid"] = db_fetch_result($result, 0, "id");
				$_SESSION["name"] = db_fetch_result($result, 0, "login");
				$_SESSION["access_level"] = db_fetch_result($result, 0, "access_level");

				db_query($link, "UPDATE ttirc_users SET last_login = NOW() WHERE id = " .
					$_SESSION["uid"]);

				$_SESSION["ip_address"] = $_SERVER["REMOTE_ADDR"];
				$_SESSION["pwd_hash"] = db_fetch_result($result, 0, "pwd_hash");

				initialize_user_prefs($link, $_SESSION["uid"]);

				return true;
			}

			return false;

		} else {

			$_SESSION["uid"] = 1;
			$_SESSION["name"] = "admin";

			$_SESSION["ip_address"] = $_SERVER["REMOTE_ADDR"];

			initialize_user_prefs($link, $_SESSION["uid"]);

			return true;
		}
	}

	function get_schema_version($link, $nocache = false) {
		if (!$_SESSION["schema_version"] || $nocache) {
			$result = db_query($link, "SELECT schema_version FROM ttirc_version");
			$version = db_fetch_result($result, 0, "schema_version");
			$_SESSION["schema_version"] = $version;
			return $version;
		} else {
			return $_SESSION["schema_version"];
		}
	}

	function validate_session($link) {
		if (SINGLE_USER_MODE) {
			return true;
		}

		if (SESSION_CHECK_ADDRESS && $_SESSION["uid"]) {
			if ($_SESSION["ip_address"]) {
				if ($_SESSION["ip_address"] != $_SERVER["REMOTE_ADDR"]) {
					$_SESSION["login_error_msg"] = __("Session failed to validate (incorrect IP)");
					return false;
				}
			}
		}

		if ($_SESSION["ref_schema_version"] != get_schema_version($link, true)) {
			return false;
		}

		if ($_SESSION["uid"]) {

			$result = db_query($link,
				"SELECT pwd_hash FROM ttirc_users WHERE id = '".$_SESSION["uid"]."'");

			$pwd_hash = db_fetch_result($result, 0, "pwd_hash");

			if ($pwd_hash != $_SESSION["pwd_hash"]) {
				return false;
			}
		}

		return true;
	}

	function get_script_dt_add() {
		return time();
	}

	function theme_image($link, $filename) {
		if ($link) {
			$theme_path = get_user_theme_path($link);

			if ($theme_path && is_file($theme_path.$filename)) {
				return $theme_path.$filename;
			} else {
				return $filename;
			}
		} else {
			return $filename;
		}
	}

	function get_user_theme($link) {
		$theme_name = get_pref($link, "USER_THEME");
		if (is_dir("themes/$theme_name")) {
			return $theme_name;
		} else {
			return '';
		}
	}

	function get_user_theme_path($link) {
		$theme_name = get_pref($link, "USER_THEME");

		if ($theme_name && is_dir("themes/$theme_name")) {
			$theme_path = "themes/$theme_name/";
		} else {
			$theme_name = '';
		}

		return $theme_path;

	}

	function get_all_themes() {
		$themes = glob("themes/*");

		asort($themes);

		$rv = array();

		foreach ($themes as $t) {
			if (is_file("$t/theme.ini")) {
				$ini = parse_ini_file("$t/theme.ini", true);
				if ($ini['theme']['version'] && !$ini['theme']['disabled']) {
					$entry = array();
					$entry["path"] = $t;
					$entry["base"] = basename($t);
					$entry["name"] = $ini['theme']['name'];
					$entry["version"] = $ini['theme']['version'];
					$entry["author"] = $ini['theme']['author'];
					$entry["options"] = $ini['theme']['options'];
					array_push($rv, $entry);
				}
			}
		}

		return $rv;
	}

	function logout_user() {
		session_destroy();
		if (isset($_COOKIE[session_name()])) {
		   setcookie(session_name(), '', time()-42000, '/');
		}
	}

	function format_warning($msg, $id = "") {
		global $link;
		return "<div class=\"warning\" id=\"$id\">
			<img src=\"".theme_image($link, "images/sign_excl.png")."\">$msg</div>";
	}

	function format_notice($msg) {
		global $link;
		return "<div class=\"notice\" id=\"$id\">
			<img src=\"".theme_image($link, "images/sign_info.png")."\">$msg</div>";
	}

	function format_error($msg) {
		global $link;
		return "<div class=\"error\" id=\"$id\">
			<img src=\"".theme_image($link, "images/sign_excl.png")."\">$msg</div>";
	}

	function print_notice($msg) {
		return print format_notice($msg);
	}

	function print_warning($msg) {
		return print format_warning($msg);
	}

	function print_error($msg) {
		return print format_error($msg);
	}


	function T_sprintf() {
		$args = func_get_args();
		return vsprintf(__(array_shift($args)), $args);
	}

	function _debug($msg) {
		$ts = strftime("%H:%M:%S", time());
		if (function_exists('posix_getpid')) {
			$ts = "$ts/" . posix_getpid();
		}
		print "[$ts] $msg\n";
	} // function _debug

	# TODO return actual nick, not hardcoded one
	function get_nick($link, $connection_id) {
		$result = db_query($link, "SELECT active_nick FROM ttirc_connections
			WHERE id ='$connection_id'");

		if (db_num_rows($result) == 1) {
			return db_fetch_result($result, 0, "active_nick");
		} else {
			return "?UNKNOWN?";
		}
	}

	function handle_command($link, $connection_id, $channel, $message) {

		$keywords = array();

		preg_match("/^\/([^ ]+) ?(.*)$/", $message, $keywords);

		$command = trim(strtolower($keywords[1]));
		$arguments = trim($keywords[2]);

		if ($command == "j") $command = "join";

		if ($command == "me") {
			$command = "action";
			push_message($link, $connection_id, $channel,
				"$arguments", true, MSGT_ACTION);
		}

		if ($command == "notice") {
			list ($nick, $message) = explode(" ", $arguments, 2);
			push_message($link, $connection_id, $channel,
				"$message", false, MSGT_NOTICE, $nick);
		}

		switch ($command) {
		case "query":

			db_query($link, "BEGIN");

			$result = db_query($link, "SELECT id FROM ttirc_channels WHERE
				channel = '$arguments' AND connection_id = '$connection_id'");

			if (db_num_rows($result) == 0) {
				db_query($link, "INSERT INTO ttirc_channels
					(channel, connection_id, chan_type) VALUES
					('$arguments', '$connection_id', '".CT_PRIVATE."')");
			}

			db_query($link, "COMMIT");

			break;
		case "part":

			if (!$arguments) $arguments = $channel;

			db_query($link, "BEGIN");

			$result = db_query($link, "SELECT chan_type FROM ttirc_channels WHERE
				channel = '$arguments' AND connection_id = '$connection_id'");

			if (db_num_rows($result) != 0) {
				$chan_type = db_fetch_result($result, 0, "chan_type");

				if ($chan_type == CT_PRIVATE) {
					db_query($link, "DELETE FROM ttirc_channels WHERE
						channel = '$arguments' AND connection_id = '$connection_id'");
				} else {
					push_message($link, $connection_id, $channel,
						"$command:$arguments", false, MSGT_COMMAND);
				}
			}

			db_query($link, "COMMIT");

			break;
		default:
			push_message($link, $connection_id, $channel,
				"$command:$arguments", false, MSGT_COMMAND);
			break;
		}

		return $last_id;
	}


	function push_message($link, $connection_id, $channel, $message,
		$incoming = false, $message_type = MSGT_PRIVMSG, $from_nick = false) {

		if ($message === "") return false;

		$incoming = bool_to_sql_bool($incoming);

		if ($channel != "---") {
			$my_nick = get_nick($link, $connection_id);
		} else {
			$my_nick = "---";
		}

		if ($from_nick) $my_nick = $from_nick;

		//$message = db_escape_string($message);

		$result = db_query($link, "INSERT INTO ttirc_messages
			(incoming, connection_id, channel, sender, message, message_type, ts) VALUES
			($incoming, $connection_id, '$channel', '$my_nick', '$message',
			'$message_type', NOW())");
	}

	function get_initial_last_id($link) {
		$result = db_query($link, "SELECT ttirc_messages.id
			FROM ttirc_messages, ttirc_connections
			WHERE
				owner_uid = ".$_SESSION["uid"]." AND
				connection_id = ttirc_connections.id
			ORDER BY id DESC LIMIT 1 OFFSET 50");

		if (db_num_rows($result) != 0) {
			return db_fetch_result($result, 0, "id");
		} else {
			return 0;
		}
	}

	function num_new_lines($link, $last_id) {
		if (!$last_id) $last_id = get_initial_last_id($link);

		$result = db_query($link, "SELECT COUNT(*) AS cl
			FROM ttirc_messages, ttirc_connections WHERE
			connection_id = ttirc_connections.id AND
			message_type != ".MSGT_COMMAND." AND
			ttirc_messages.id > '$last_id' AND
			owner_uid = ".$_SESSION["uid"]." LIMIT 50");

		return db_fetch_result($result, 0, "cl");
	}

	function get_new_lines($link, $last_id) {

		if (!$last_id) $last_id = get_initial_last_id($link);

		$result = db_query($link, "SELECT ttirc_messages.id,
			message_type, sender, channel, connection_id, incoming,
			message, ".SUBSTRING_FOR_DATE."(ts,1,19) AS ts
			FROM ttirc_messages, ttirc_connections WHERE
			connection_id = ttirc_connections.id AND
			message_type != ".MSGT_COMMAND." AND
			ttirc_messages.id > '$last_id' AND
			owner_uid = ".$_SESSION["uid"]." ORDER BY id LIMIT 50");

		$lines = array();

		while ($line = db_fetch_assoc($result)) {
			$line["sender_color"] = color_of($line["sender"]);
			$line["incoming"] = sql_bool_to_bool($line["incoming"]);
			array_push($lines, $line);
		}

		return $lines;
	}

	function get_chan_data($link, $active_chan = false) {

		if ($active_chan && $active_chan != "---") {
			$active_chan_qpart = "channel = '$active_chan' AND";
		} else {
			$active_chan_qpart = "";
		}

		$result = db_query($link, "SELECT nicklist,channel,connection_id,
			chan_type,topic,
			topic_owner,".SUBSTRING_FOR_DATE."(topic_set,1,16) AS topic_set
			FROM ttirc_channels, ttirc_connections
			WHERE connection_id = ttirc_connections.id AND
			$active_chan_qpart
			owner_uid = ".$_SESSION["uid"]);

		$rv = array();

		while ($line = db_fetch_assoc($result)) {
			$chan = $line["channel"];

			$re = array();

			$re["chan_type"] = $line["chan_type"];
			$re["users"] = sort_nicklist(json_decode($line["nicklist"]));
			$re["topic"] = array(
				$line["topic"], $line["topic_owner"], $line["topic_set"]);

			$rv[$line["connection_id"]][$chan] = $re;
		}

		return $rv;
	}

	function get_conn_info($link) {

		$result = db_query($link, "SELECT id,active_server,active_nick,status,
			title,userhosts
			FROM ttirc_connections
			WHERE visible = true AND owner_uid = ".$_SESSION["uid"] . "
			ORDER BY id");

		$conn = array();

		while ($line = db_fetch_assoc($result)) {
			if ($line['userhosts']) $line['userhosts'] = json_decode($line['userhosts']);
			array_push($conn, $line);
		}

		return $conn;

	}

	function sql_bool_to_string($s) {
		if ($s == "t" || $s == "1") {
			return "true";
		} else {
			return "false";
		}
	}

	function sql_bool_to_bool($s) {
		if ($s == "t" || $s == "1") {
			return true;
		} else {
			return false;
		}
	}

	function bool_to_sql_bool($s) {
		if ($s) {
			return "true";
		} else {
			return "false";
		}
	}

	function sanity_check($link) {

		global $ERRORS;

		$error_code = 0;
		$schema_version = get_schema_version($link);

		if ($schema_version != SCHEMA_VERSION) {
			$error_code = 5;
		}

		if (DB_TYPE == "mysql") {
			$result = db_query($link, "SELECT true", false);
			if (db_num_rows($result) != 1) {
				$error_code = 10;
			}
		}

		if (db_escape_string("testTEST") != "testTEST") {
			$error_code = 12;
		}

		$result = db_query($link, "SELECT value FROM ttirc_system WHERE
			".DB_KEY_FIELD." = 'MASTER_RUNNING'");

		$master_running = db_fetch_result($result, 0, "value") == "true";

		if (!$master_running) {
			$error_code = 13;
		}

		if ($error_code != 0) {
			print json_encode(array("error" => $error_code,
				"errormsg" => $ERRORS[$error_code]));
			return false;
		} else {
			return true;
		}
	}

	function get_random_server($link, $connection_id) {
		$result = db_query($link, "SELECT * FROM ttirc_servers WHERE
			connection_id = '$connection_id' ORDER BY RANDOM() LIMIT 1");

		if (db_num_rows($result) != 0) {
			return db_fetch_assoc($result);
		} else {
			return false;
		}

	}

	// shamelessly stolen from xchat source code

	function color_of($name) {

		$rcolors = array( 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12,
		  13, 14, 15 );

		$i = 0;
		$sum = 0;

		for ($i = 0; $i < strlen($name); $i++) {
			$sum += ord($name{$i});
		}

		$sum %= count($rcolors);

		return $rcolors[$sum];
	}

	function purge_old_lines($link) {
		db_query($link, "DELETE FROM ttirc_messages WHERE
			ts < NOW() - INTERVAL '3 hours'");
	}

	function update_heartbeat($link) {

		if (time() - $_SESSION["heartbeat_last"] > 120) {
			$result = db_query($link, "UPDATE ttirc_users SET heartbeat = NOW()
				WHERE id = " . $_SESSION["uid"]);
			$_SESSION["heartbeat_last"] = time();
		}
	}

	function initialize_user_prefs($link, $uid, $profile = false) {

		$uid = db_escape_string($uid);

		if (!$profile) {
			$profile = "NULL";
			$profile_qpart = "AND profile IS NULL";
		} else {
			$profile_qpart = "AND profile = '$profile'";
		}

		db_query($link, "BEGIN");

		$result = db_query($link, "SELECT pref_name,def_value FROM ttirc_prefs");

		$u_result = db_query($link, "SELECT pref_name
			FROM ttirc_user_prefs WHERE owner_uid = '$uid' $profile_qpart");

		$active_prefs = array();

		while ($line = db_fetch_assoc($u_result)) {
			array_push($active_prefs, $line["pref_name"]);
		}

		while ($line = db_fetch_assoc($result)) {
			if (array_search($line["pref_name"], $active_prefs) === FALSE) {
//				print "adding " . $line["pref_name"] . "<br>";

				if (get_schema_version($link) < 63) {
					db_query($link, "INSERT INTO ttirc_user_prefs
						(owner_uid,pref_name,value) VALUES
						('$uid', '".$line["pref_name"]."','".$line["def_value"]."')");

				} else {
					db_query($link, "INSERT INTO ttirc_user_prefs
						(owner_uid,pref_name,value, profile) VALUES
						('$uid', '".$line["pref_name"]."','".$line["def_value"]."', $profile)");
				}

			}
		}

		db_query($link, "COMMIT");

	}

	function valid_connection($link, $id) {
		$result = db_query($link, "SELECT id FROM ttirc_connections
			WHERE id = '$id' AND owner_uid = "  . $_SESSION["uid"]);
		return db_num_rows($result) == 1;
	}

	function make_password($length = 8) {

		$password = "";
		$possible = "0123456789abcdfghjkmnpqrstvwxyzABCDFGHJKMNPQRSTVWXYZ";

   	$i = 0;

		while ($i < $length) {
			$char = substr($possible, mt_rand(0, strlen($possible)-1), 1);

			if (!strstr($password, $char)) {
				$password .= $char;
				$i++;
			}
		}
		return $password;
	}

	function get_user_login($link, $id) {
		$result = db_query($link, "SELECT login FROM ttirc_users WHERE id = '$id'");

		if (db_num_rows($result) == 1) {
			return db_fetch_result($result, 0, "login");
		} else {
			return false;
		}

	}

	function get_iconv_encodings() {
		return explode("\n", file_get_contents("lib/iconv.txt"));
	}

	function print_theme_select($link) {
		$user_theme = get_pref($link, "USER_THEME");
		$themes = get_all_themes();

		print "<select name=\"theme\">";
		print "<option value=''>".__('Default')."</option>";
		print "<option disabled>--------</option>";

		foreach ($themes as $t) {
			$base = $t['base'];
			$name = $t['name'];

			if ($base == $user_theme) {
				$selected = "selected=\"1\"";
			} else {
				$selected = "";
			}

			print "<option $selected value='$base'>$name</option>";

		}

		print "</select>";
	}

	function print_user_css($link) {
		$user_css = get_pref($link, "USER_STYLESHEET");

		if ($user_css) {
			print "<style type=\"text/css\">$user_css</style>";
		}
	}

	function get_misc_params($link, $uniqid = false) {
		if (!$uniqid) $uniqid = uniqid();

		$notify_on = json_decode(get_pref($link, "NOTIFY_ON"));

		if (!is_array($notify_on)) $notify_on = array();

		$notify_events = array();

		foreach ($notify_on as $no) {
			$notify_events[$no] = true;
		}

		$result = db_query($link, "SELECT hide_join_part FROM ttirc_users
			WHERE id = " . $_SESSION["uid"]);

		$hide_join_part = sql_bool_to_bool(db_fetch_result($result, 0, "hide_join_part"));

		$rv = array(
			"hide_join_part" => $hide_join_part,
			"uniqid" => $uniqid,
			"disable_image_preview" => get_pref($link, "DISABLE_IMAGE_PREVIEW"),
			"highlight_on" => explode(",", get_pref($link, "HIGHLIGHT_ON")),
			"notify_events" => $notify_events);

		return $rv;
	}

	function get_self_url_prefix() {
		$url_path = "";

		if ($_SERVER['HTTPS'] != "on") {
			$url_path = "http://";
		} else {
			$url_path = "https://";
		}

		$url_path .= $_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']);

		return $url_path;

	}

	function create_snippet($link, $text) {
		$text = db_escape_string($text);

		$key = db_escape_string(sha1(uniqid(rand(), true)));

		$result = db_query($link, "INSERT INTO ttirc_snippets (snippet, owner_uid, created, ".DB_KEY_FIELD.")
			VALUES ('$text', '".$_SESSION['uid']."', NOW(), '$key')");

		return $key;

	}

	function truncate_string($str, $max_len) {
		if (mb_strlen($str, "utf-8") > $max_len - 3) {
			return trim(mb_substr($str, 0, $max_len, "utf-8")) . "...";
		} else {
			return $str;
		}
	}

	function get_random_bytes($length) {
		if (function_exists('openssl_random_pseudo_bytes')) {
			return openssl_random_pseudo_bytes($length);
		} else {
			$output = "";

			for ($i = 0; $i < $length; $i++)
				$output .= chr(mt_rand(0, 255));

			return $output;
		}
	}

	function cleanup_session_cache() {
		if (is_array($_SESSION["cache"])) {
			foreach (array_keys($_SESSION["cache"]) as $uniqid) {
				if (time() - $_SESSION["cache"][$uniqid]["last"] > 120) {
					unset($_SESSION["cache"][$uniqid]);
				}
			}
		}
	}

	function render_emoticons_full() {
		global $emoticons_map;

		?>
		<html>
		<head>
			<title>Tiny Tiny IRC</title>
			<?php echo stylesheet_tag("tt-irc.css") ?>
		</head>
		<body id="emoticons_list">
		<div id="emoticons_long">
		<?php

		foreach ($emoticons_map as $k => $e) {
			print "<div class=\"tile\"><div class=\"wrapper\">";
			print "<img onclick=\"window.opener.inject_text('$k')\" title=\"$k\" src=\"emoticons/$e[0]\">";
			print "</div>$k";
			print "</div>";
		}

		/* foreach ($emoticons_map as $k => $e) {
			if ($e[2] != 1) {
				print "<img onclick=\"window.opener.inject_text('$k')\" title=\"$k\" src=\"emoticons/$e[0]\">";
			}
		} */

		?>
		</div>
		</body>
		</html>
		<?php
	}

	function render_emoticons($link) {
		global $emoticons_map;

		$tmp = "";

		$result = db_query($link, "SELECT emoticon FROM ttirc_emoticons_popcon
					WHERE owner_uid = " . $_SESSION["uid"] . " ORDER BY times_used DESC LIMIT 30");

		$num_favs = 0;
		while ($line = db_fetch_assoc($result)) {
			++$num_favs;

			$k = $line["emoticon"];
			$e = $emoticons_map[$k][0];

			$tmp .= "<div class=\"wrapper\"><img onclick=\"inject_text('$k')\" title=\"$k\" src=\"emoticons/$e\"></div>";

		}

		$num_more = count($emoticons_map) - $num_favs;

		if ($num_more > 0) {
			$tmp .= "<br clear='both'><p><a href=\"#\" onclick=\"return emoticons_popup()\">$num_more more...</a></p>";
		}

		return $tmp;
	}

	function shorten_urls($link, $line, $prefix = false) {

		if (!(defined('SHORTURL_ENABLE') && SHORTURL_ENABLE))
			return $line;

		$prefix = (defined('SHORTURL_PREFIX') ? SHORTURL_PREFIX :
				get_self_url_prefix()) . "?id=";
		$urls = null;

		if (preg_match_all("/(([a-z]+):\/\/[^ ]+)/i", $line, $urls) > 0) {
			foreach ($urls as $url) {
				if (mb_strlen($url[0]) > 150 || mb_strlen($line) >= 200) {
					$shorturl = shorten_url($link, $url[0], $prefix);

					if ($shorturl != $url[0] && $shorturl) {
						$line = str_replace($url[0], $shorturl, $line);
					}
				}
			}
		}
		return $line;
	}

	function stylesheet_tag($filename) {
		$timestamp = filemtime($filename);

		echo "<link media=\"screen\" rel=\"stylesheet\" type=\"text/css\" href=\"$filename?$timestamp\"/>\n";
	}

	function javascript_tag($filename) {
		$query = "";

		if (!(strpos($filename, "?") === FALSE)) {
			$query = substr($filename, strpos($filename, "?")+1);
			$filename = substr($filename, 0, strpos($filename, "?"));
		}

		$timestamp = filemtime($filename);

		if ($query) $timestamp .= "&$query";

		echo "<script type=\"text/javascript\" charset=\"utf-8\" src=\"$filename?$timestamp\"></script>\n";
	}

	function get_minified_js($files) {
		require_once 'lib/jshrink/Minifier.php';

		$rv = '';

		foreach ($files as $js) {
			if (!isset($_GET['debug'])) {
				$cached_file = "cache/js/$js.js";

				if (file_exists($cached_file) &&
						is_readable($cached_file) &&
						filemtime($cached_file) >= filemtime("js/$js.js")) {

					$rv .= file_get_contents($cached_file);

				} else {
					$minified = JShrink\Minifier::minify(file_get_contents("js/$js.js"));
					file_put_contents($cached_file, $minified);
					$rv .= $minified;
				}
			} else {
				$rv .= file_get_contents("js/$js.js");
			}
		}

		return $rv;
	}

	function init_js_translations() {

	print 'var T_messages = new Object();

		function __(msg) {
			if (T_messages[msg]) {
				return T_messages[msg];
			} else {
				return msg;
			}
		}

		function ngettext(msg1, msg2, n) {
			return (parseInt(n) > 1) ? msg2 : msg1;
		}';

		if (ENABLE_TRANSLATIONS == true) { // If translations are enabled.
			$l10n = _get_reader();

			for ($i = 0; $i < $l10n->total; $i++) {
				$orig = $l10n->get_original_string($i);
				$translation = __($orig);

				print T_js_decl($orig, $translation);
			}
		}
	}

	function strip_prefix($nick) {
		return preg_replace("/^[\+\@]/", "", $nick);
	}

	function nicklist_sort_callback($o1, $o2) {
		$c1 = $o1[0];
		$c2 = $o2[0];

		if ($c1 == '@' && $c2 != '@') {
			return -1;
		}

		if ($c1 != '@' && $c2 == '@') {
			return 1;
		}

		if ($c1 == '+' && $c2 != '+') {
			return -1;
		}

		if ($c1 != '+' && $c2 == '+') {
			return 1;
		}

		return strip_prefix($o1) < strip_prefix($o2) ? -1 : 1;
	}

	function sort_nicklist($nicklist) {
		usort($nicklist, "nicklist_sort_callback");
		return $nicklist;
	}

?>
