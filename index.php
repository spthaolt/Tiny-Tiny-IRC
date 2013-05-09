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

	header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<head>
	<title>Tiny Tiny IRC</title>

	<?php stylesheet_tag("tt-irc.css") ?>
	<?php stylesheet_tag("lib/bootstrap/bootstrap.min.css") ?>

	<link id="favicon" rel="shortcut icon" type="image/png" href="images/favicon.png" />

	<link rel="icon" type="image/png" sizes="72x72"
		href="images/icon-hires.png" />

	<?php
	foreach (array("lib/prototype.js",
				"lib/scriptaculous/scriptaculous.js?load=effects,dragdrop,controls",
				"lib/knockout.js") as $jsfile) {

		javascript_tag($jsfile);
	} ?>

	<script type="text/javascript">
	<?php
		init_js_translations();

		print get_minified_js(array("tt-irc",
			"functions", "prefs", "users"));
	?>
	</script>

	<?php
	$user_theme = get_user_theme_path($link);
		if ($user_theme) {
			stylesheet_tag("$user_theme/theme.css");
		}
	?>

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

<div id="image-preview" onclick="Element.hide(this)" style="display : none"
	title="<?php echo __("Click to close") ?>"></div>

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

<div id="errorBox" class="modal" style="display : none">
	<div class="modal-header"><?php echo __('Fatal Exception') ?></div>
	<div id="errorBody" class="modal-body">&nbsp;</div>
	<div class="modal-footer">
		<button class="btn btn-primary" onclick="window.location.reload()">
			<?php echo __('Try again') ?></button>
	</div>
</div>

<div style="display : none" class="modal" id="infoBox">&nbsp;</div>

<div id="main">

<div id="header">
	<img id="spinner" style="display : none"
		alt="spinner" title="Loading..."
		src="<?php echo theme_image($link, 'images/indicator_tiny.gif') ?>"/>

	<img id="net-alert" style="display : none"
		title="<?php echo __("Communication problem with the server.") ?>"
		src="<?php echo theme_image($link, 'images/alert.png') ?>"/>

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

<div id="tabs">
	<div id="tabs-inner">
		<ul id="tabs-list" data-bind="foreach: connections">
			<li channel="---" tab_type="S" data-bind="attr: { id: 'tab-' + id(), connection_id: id() }, css: { selected: selected, attention: attention }" onclick="change_tab(this)">
				<span data-bind="attr: { id: 'cimg-' + id() }, css: { 'conn-img': true, connected: connected }"> </span>
				<div data-bind="text: title"></div>
			</li>
			<ul class="sub-tabs" data-bind="foreach: channels, attr: { id: 'tabs-' + id() }">
				<li onclick="change_tab(this)" data-bind="attr: { id: 'tab-' + title() + ':' + $parent.id(), connection_id: $parent.id(), channel: title(), tab_type: type() }, css: { selected: selected, highlight: highlight, attention: attention, offline: offline }">
					<span onclick="close_tab(this)" title="Close this tab" class="close-img"
						data-bind="attr: { tab_id: 'tab-' + title() + ':' + $parent.id() }">&times;</span>
					<div class="indented" data-bind="text: title"></div>
				</li>
			</ul>
		</ul>
	</div>
</div>

<div id="content">
	<div id="topic"><div class="wrapper">
		<div data-bind="html: activeTopicFormatted, attr: { title: activeTopic }, css: { 'uneditable-input': true, disabled: activeTopicDisabled }" id="topic-input" onclick="prepare_change_topic(this)"></div>

		<input onkeypress="change_topic_real(this, event)" onblur="hide_topic_input()" type="text"
			id="topic-input-real" style="display : none"/>

			<div class="autocomplete" id="topic-suggest" style="display:none"></div>
		</div>
	</div>
	<div id="log" data-bind="with: activeChannel" >
	<ul id="log-list" data-bind="foreach: lines">
		<li data-bind="html: format, css: { row: true, HL: is_hl }"> </li>
	</ul>
	</div>

	<div id="sidebar">

		<div id="sidebar-inner">

		<!-- fuck you very much, MSIE team -->
		<form action="javascript:void(null);" method="post" class="connect-form">
		<div id="connect"><button class="btn"
			id="connect-btn" data-bind="enable: toggleConnection, text: connectBtnLabel, click: toggleConnection"> </button>
		</div>
		</form>

		<div id="userlist">
			<div data-bind="with: activeChannel" id="userlist-inner">
				<ul id="userlist-list" data-bind="foreach: nicklist">
					<li>
						<span data-bind="css: { 'user-img': true, op: $root.nickIsOp($data), voice: $root.nickIsVoiced($data) }"> </span>
						<span onclick="query_user(this)"
							data-bind="text: $root.stripNickPrefix($data), attr: { nick: $root.stripNickPrefix($data),
								title: $root.getNickHost($root.activeChannel().connection_id(), $data) }"></span>
					</li>
				</ul>
			</div>
		</div>
	</div>

	</div>

	<div id="nick" onclick="change_nick()" data-bind="text: activeNick, css: { away: isAway }"></div>

	<div id="input"><div class="wrapper">
		<?php if (@$_REQUEST["ta"] != "1") { ?>
		<input type="text" data-bind="enable: activeStatus() == 2" rows="1" id="input-prompt"
			onkeypress="return send(this, event)"/>
		<?php } else { ?>
		<textarea data-bind="enable: activeStatus() == 2" disabled="true" rows="1" id="input-prompt"
			onkeypress="send(this, event)"/></textarea>
		<?php } ?>
		<div onclick="Element.toggle('emoticons')" class="emoticon_prompt" id="emoticon-prompt">:)</div>
		<div style="display : none" id="emoticons"><?php render_emoticons() ?></div>
		<div class="autocomplete" id="input-suggest" style="display:none"></div>
	</div></div>
</div>

</div>

</body>
</html>
