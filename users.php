<?php
	set_include_path(get_include_path() . PATH_SEPARATOR .
		dirname(__FILE__) ."/include");

	require_once "functions.php";

	function format_users($link) {
		$result = db_query($link, "SELECT * FROM ttirc_users ORDER BY login");

		$lnum = 1;

		$tmp = "";

		while ($line = db_fetch_assoc($result)) {

			$row_class = "row";

			$id = $line['id'];

			$tmp .= "<li id='U-$id' class='$row_class' user_id='$id'>";
			$tmp .= "<input type='checkbox' onchange='select_row(this)'
				row_id='U-$id'>";
			$tmp .= "&nbsp;<a href=\"#\" title=\"".__('Click to edit user')."\"
				onclick=\"edit_user($id)\">".
				$line['login']."</a>";
			$tmp .= "</li>";

			++$lnum;
		}

		return $tmp;

	}

	function show_users($link) {

	?>
	<div class="modal-header">
		<button type="button" onclick="close_infobox()" class="close">&times;</button>
		<h3><?php echo __("Edit Users") ?></h3></div>
	<div class="modal-body">
		<div id="mini-notice" class="alert" style='display : none'>&nbsp;</div>

		<ul class="unstyled scrollable" id="users-list">
			<?php echo format_users($link); ?>
		</ul>
	</div>

	<div class="modal-footer">
		<div style='float : left'>
			<button class="btn" onclick="create_user()">
				<?php echo __('Add user') ?></button>
			<button class="btn" onclick="reset_user()">
				<?php echo __('Reset password') ?></button>
			<button class="btn btn-danger" onclick="delete_user()">
				<?php echo __('Delete') ?></button>
		</div>
		<button class="btn btn-primary" type="submit" onclick="close_infobox()">
			<?php echo __('Close') ?></button></div>
	</div>
	<?php

	}
?>
