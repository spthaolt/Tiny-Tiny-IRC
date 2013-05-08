<html>
<head>
	<title>Tiny Tiny IRC : Login</title>
	<link rel="shortcut icon" type="image/png" href="images/favicon.png">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<?php stylesheet_tag("lib/bootstrap/bootstrap.min.css") ?>
	<script type="text/javascript" src="lib/prototype.js"></script>
	<script type="text/javascript" src="lib/scriptaculous/scriptaculous.js"></script>
	<script type="text/javascript" src="js/functions.js"></script>
	<style type="text/css">
	body {
		padding : 2em;
	}

	fieldset {
		margin-left : auto;
		margin-right : auto;
		display : block;
		width : 400px;
		border-width : 0px;
	}

	/* input.input {
		font-family : sans-serif;
		font-size : medium;
		border-spacing : 2px;
		border : 1px solid #b5bcc7;
		padding : 2px;
	} */

	label {
		width : 120px;
		margin-right : 20px;
		display : inline-block;
		text-align : right;
		color : gray;
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

	div.row {
		padding : 0px 0px 5px 0px;
	}

	div.row-error {
		color : red;
		text-align : center;
		padding : 0px 0px 5px 0px;
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

<form action="" method="POST" id="loginForm" name="loginForm" onsubmit="return validateLoginForm(this)">
<input type="hidden" name="login_action" value="do_login">

<div>

	<fieldset>
		<?php if ($_SESSION["login_error_msg"]) { ?>
		<div class="row-error">
			<?php echo $_SESSION["login_error_msg"] ?>
		</div>
			<?php $_SESSION["login_error_msg"] = ""; ?>
		<?php } ?>
		<div class="row">
			<label><?php echo __("Login:") ?></label>
			<input name="login" type="text"
				style="width : 220px"
				required="1"
				value="<?php echo $_SESSION["fake_login"] ?>" />
		</div>

		<div class="row">
			<label><?php echo __("Password:") ?></label>
			<input type="password" name="password" required="1"
					style="width : 220px"
					value="<?php echo $_SESSION["fake_password"] ?>"/>
		</div>

		<div class="row">
			<label><?php echo __("Language:") ?></label>
			<?php
				print_select_hash("language", $_COOKIE["ttirc_lang"], get_translations(),
					"style='width : 220px' onchange='language_change(this)'");
			?>
		</div>

		<div class="row">
			<label>&nbsp;</label>
			<input name="disable_emoticons" id="disable_emoticons" type="checkbox"
				onchange="toggleEmoticons(this)">
			<label style='display : inline' for="disable_emoticons">
				<?php echo __("Disable emoticons") ?></label>
		</div>

		<div class="row" style='text-align : right'>
			<button class="btn btn-primary" type="submit"><?php echo __('Log in') ?></button>
			<!-- <?php if (defined('ENABLE_REGISTRATION') && ENABLE_REGISTRATION) { ?>
				<button onclick="return gotoRegForm()" dojoType="dijit.form.Button">
					<?php echo __("Create new account") ?></button>
			<?php } ?> -->
		</div>

	</fieldset>
</div>

<div class='footer'>
	<a href="http://tt-rss.org/tt-irc/">Tiny Tiny IRC</a>
	<?php if (!defined('HIDE_VERSION')) { ?>
		 v<?php echo VERSION ?>
	<?php } ?>
	&copy; 2010&ndash;<?php echo date('Y') ?> <a href="http://fakecake.org/">Andrew Dolgov</a>
</div>

</form>

</body></html>
