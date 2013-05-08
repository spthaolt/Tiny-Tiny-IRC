<!DOCTYPE html>
<html>
<head>
	<title>Tiny Tiny IRC : Login</title>
	<link rel="shortcut icon" type="image/png" href="images/favicon.png">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<script type="text/javascript" src="lib/prototype.js"></script>
	<script type="text/javascript" src="lib/scriptaculous/scriptaculous.js"></script>
	<script type="text/javascript" src="js/functions.js"></script>
	<?php stylesheet_tag("lib/bootstrap/bootstrap.min.css") ?>
	<style type="text/css">
	body {
		padding : 2em;
	}

	div.header {
		border-width : 0px 0px 1px 0px;
		border-style : solid;
		border-color : #88b0f0;
		margin-bottom : 1em;
		padding-bottom : 5px;
	}

	div.footer {
		margin-top : 1em;
		padding-top : 5px;
		border-width : 1px 0px 0px 0px;
		border-style : solid;
		border-color : #88b0f0;
		text-align : center;
		color : gray;
		font-size : 12px;
	}

	div.footer a {
		color : gray;
	}

	div.footer a:hover {
		color : #88b0f0;
	}

	form {
		max-width : 450px;
		margin-left : auto;
		margin-right : auto;
	}

	</style>

</head>

<body>

<script type="text/javascript">
function init() {
	document.forms["loginForm"].login.focus();
}

function language_change(elem) {
	try {
		document.forms['loginForm']['click'].disabled = true;

		var lang = elem[elem.selectedIndex].value;
		set_cookie("ttirc_lang", lang, <?php print SESSION_COOKIE_LIFETIME ?>);
		window.location.reload();
	} catch (e) {
		exception_error("language_change", e);
	}
}

function gotoRegForm() {
	window.location.href = "register.php";
	return false;
}

function toggleEmoticons(elem) {
	try {
		var enabled = elem.checked;

		set_cookie("ttirc_emoticons", !elem.checked,
			<?php print SESSION_COOKIE_LIFETIME ?>);

	} catch (e) {
		exception_error("toggleEmoticons", e);
	}
}

function validateLoginForm(f) {
	try {

		if (f.login.value.length == 0) {
			new Effect.Highlight(f.login);
			return false;
		}

		if (f.password.value.length == 0) {
			new Effect.Highlight(f.password);
			return false;
		}

		document.forms['loginForm']['click'].disabled = true;

		return true;
	} catch (e) {
		exception_error("validateLoginForm", e);
		return true;
	}
}
</script>

<script type="text/javascript">
Event.observe(window, 'load', function() {
	init();
});
</script>

<div class='header'>
	<img src="images/logo_big.png">
</div>

<form class="form form-horizontal" action="" method="POST" id="loginForm" name="loginForm" onsubmit="return validateLoginForm(this)">
<input type="hidden" name="login_action" value="do_login">

		<?php if ($_SESSION["login_error_msg"]) { ?>
			<div class="pagination-centered">
			<div class="alert alert-error">
				<?php echo $_SESSION["login_error_msg"] ?>
			</div>
			<?php $_SESSION["login_error_msg"] = ""; ?>
			</div>
		<?php } ?>

	<div class="control-group">
		<label class="control-label"><?php echo __("Login:") ?></label>
		<div class="controls">
			<input name="login" type="text" required="1"
				value="<?php echo $_SESSION["fake_login"] ?>" />
		</div>
	</div>

	<div class="control-group">
		<label class="control-label"><?php echo __("Password:") ?></label>
		<div class="controls">
			<input type="password" name="password" required="1"
				value="<?php echo $_SESSION["fake_password"] ?>"/>
		</div>
	</div>

	<div class="control-group">
		<label class="control-label"><?php echo __("Language:") ?></label>
		<div class="controls">
			<?php
				print_select_hash("language", $_COOKIE["ttirc_lang"], get_translations(),
					"style='width : 220px' onchange='language_change(this)'");
			?>
		</div>
	</div>

	<div class="control-group">
		<div class="controls">
			<label class="checkbox">
				<input name="disable_emoticons" type="checkbox" onchange="toggleEmoticons(this)">
				<?php echo __("Disable emoticons") ?>
			</label>
			<button class="btn btn-primary" type="submit"><?php echo __('Log in') ?></button>
		</div>
	</div>

</form>

<div class='footer'>
	<a href="http://tt-rss.org/tt-irc/">Tiny Tiny IRC</a>
	<?php if (!defined('HIDE_VERSION')) { ?>
		 v<?php echo VERSION ?>
	<?php } ?>
	&copy; 2010&ndash;<?php echo date('Y') ?> <a href="http://fakecake.org/">Andrew Dolgov</a>
</div>

</form>

</body></html>
