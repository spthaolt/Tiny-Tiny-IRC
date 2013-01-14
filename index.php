<?php
	set_include_path(get_include_path() . PATH_SEPARATOR .
		dirname(__FILE__) ."/include");

	require_once "functions.php";
	require_once "sessions.php";
	require_once "sanity_check.php";
	require_once "version.php";
	require_once "config.php";

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

	login_sequence($link);

	$dt_add = get_script_dt_add();

	no_cache_incantation();

	header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
	<title>Tiny Tiny IRC</title>

	<link rel="stylesheet" type="text/css" href="tt-irc.css?<?php echo $dt_add ?>"/>

	<link id="favicon" rel="shortcut icon" type="image/png" href="images/favicon.png" />

	<link rel="icon" type="image/png" sizes="72x72"
		href="images/icon-hires.png" />

	<script type="text/javascript" charset="utf-8" src="localized_js.php?<?php echo $dt_add ?>"></script>
	<script type="text/javascript" src="lib/prototype.js"></script>
	<script type="text/javascript" src="lib/scriptaculous/scriptaculous.js?load=effects,dragdrop,controls"></script>
		<script type="text/javascript" charset="utf-8" src="js/tt-irc.js?<?php echo $dt_add ?>"></script>
		<script type="text/javascript" charset="utf-8" src="js/prefs.js?<?php echo $dt_add ?>"></script>
	<script type="text/javascript" charset="utf-8" src="js/users.js?<?php echo $dt_add ?>"></script>
	<script type="text/javascript" charset="utf-8" src="js/functions.js?<?php echo $dt_add ?>"></script>

	<?php	$user_theme = get_user_theme_path($link);
		if ($user_theme) { ?>
			<link rel="stylesheet" type="text/css" href="<?php echo $user_theme ?>/theme.css?<?php echo $dt_add ?>">
	<?php } ?>

	<?php print_user_css($link); ?>

	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>

	<script type="text/javascript">
		Event.observe(window, 'load', function() {
			init();
		});

		Event.observe(window, 'focus', function() {
			set_window_active(true);
		});

		Event.observe(window, 'blur', function() {
			set_window_active(false);
		});

	</script>
</head>
<body class="main">

<div id="image-tooltip" onclick="Element.hide(this)"
	title="<?php echo __("Click to close") ?>" style="display : none"></div>

<div id="preview-shadow" style="display : none"  onclick="Element.hide(this)">
<div id="image-preview"
	title="<?php echo __("Click to close") ?>"></div></div>

<div id="overlay" style="display : block">
	<div id="overlay_inner">
		<?php echo __("Loading, please wait...") ?>

		<div id="l_progress_o">
			<div id="l_progress_i"></div>
		</div>

	<noscript>
		<p><?php print_error(__("Your browser doesn't support Javascript, which is required
		for this application to function properly. Please check your
		browser settings.")) ?></p>
	</noscript>
	</div>
</div>

<div id="dialog_overlay" style="display : none"> </div>

<ul id="debug_output" style='display : none'><li>&nbsp;</li></ul>

<div id="infoBoxShadow" style="display : none"><div id="infoBox">&nbsp;</div></div>

<div id="errorBoxShadow" style="display : none">
	<div id="errorBox">
		<div id="xebTitle"><?php echo __('Fatal Exception') ?></div>
		<div id="xebContent">&nbsp;</div>
		<div id='xebBtn'>
			<button onclick="window.location.reload()">
				<?php echo __('Try again') ?></button>
		</div>
	</div>
</div>

<div id="header">
	<div class="topLinks" id="topLinks">

	<img id="spinner" style="display : none"
		alt="spinner" title="Loading..."
		src="<?php echo theme_image($link, 'images/indicator_tiny.gif') ?>"/>

	<?php if (!SINGLE_USER_MODE) { ?>
			<span class="hello"><?php echo __('Hello,') ?> <b><?php echo $_SESSION["name"] ?></b></span> |
	<?php } ?>
	<a href="#" onclick="show_prefs()"><?php echo __('Preferences') ?></a>

	<?php if ($_SESSION["access_level"] >= 10) { ?>
	| <a href="#" onclick="show_users()"><?php echo __('Users') ?></a>
	<?php } ?>

	| <a href="#" onclick="join_channel()"><?php echo __('Join channel') ?></a>

	<?php if (!SINGLE_USER_MODE) { ?>
			| <a href="backend.php?op=logout"><?php echo __('Logout') ?></a>
	<?php } ?>

	</div>
</div>

<div id="tabs">
	<div id="tabs-inner"><ul id="tabs-list"></ul></div>
</div>

<div id="content">
	<div id="topic"><div class="wrapper">
		<div id="topic-input" onclick="prepare_change_topic(this)"></div>

		<input onkeypress="change_topic_real(this, event)" onblur="hide_topic_input()"
			id="topic-input-real" value="" style="display : none">
		</div>
	</div>
	<div id="log"><ul id="log-list"></ul></div>

	<div id="sidebar">

		<div onclick="toggle_sidebar()" id="sidebar-grip"
			title="<?php echo __("Toggle sidebar") ?>"></div>

		<div id="sidebar-inner">

		<!-- fuck you very much, MSIE team -->
		<form action="javascript:void(null);" method="post">
		<div id="connect"><button onclick="toggle_connection(this)"
			id="connect-btn">Connect</button></div>
		</form>

		<div id="userlist">
			<div id="userlist-inner"><ul id="userlist-list"></ul></div>
		</div>

		</div>

	</div>

	<div id="nick" onclick="change_nick()"></div>

	<div id="input"><div class="wrapper">
		<?php if (@$_REQUEST["ta"] != "1") { ?>
		<input disabled="true" rows="1" id="input-prompt"
			onkeypress="return send(this, event)"/>
		<?php } else { ?>
		<textarea disabled="true" rows="1" id="input-prompt"
			onkeypress="send(this, event)"/></textarea>
		<?php } ?>
		<div class="autocomplete" id="input-suggest" style="display:none"></div>
		<div onclick="Element.toggle('emoticons')" class="emoticon_prompt" id="emoticon-prompt">:)</div>
		<div style="display : none" id="emoticons"><?php render_emoticons() ?></div>
	</div></div>
</div>

<?php db_close($link); ?>

</body>
</html>
