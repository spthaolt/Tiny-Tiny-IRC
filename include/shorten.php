<?php
	function shorten_id_to_key($id) {
		return base_convert($id+10000, 10, 36);
	}

	function shorten_key_to_id($key) {
		return base_convert($key, 36, 10)-10000;
	}

	function shorten_url($link, $url, $prefix) {
		$url = db_escape_string(mb_substr($url,0,1024));
		$filename = basename($url);

		if (strpos($url, "://") === false)
			return $url;

		if (filter_var($url, FILTER_VALIDATE_URL) === FALSE)
			return $url;

		if ($filename && strrpos($url, ".") !== false) {
			$suffix = substr($url, strrpos($url, "."));
			if (array_search(strtolower($suffix),
				array(".jpg", ".png", ".jpeg", ".gif", ".bmp")) === false)
					$suffix = false;

		}

		$result = db_query($link,
			"SELECT id FROM ttirc_shorturls WHERE url = '$url'");

		if (db_num_rows($result) != 0) {
			$id = db_fetch_result($result, 0, "id");

			return $prefix . shorten_id_to_key($id) . $suffix;
		} else {
			$result = db_query($link,
				"INSERT INTO ttirc_shorturls (url) VALUES ('$url') RETURNING id");

			if (db_affected_rows($result) > 0) {
				$id = db_fetch_result($result, 0, "id");

				return $prefix . shorten_id_to_key($id) . $suffix;
			}
		}

		return $url;
	}
?>
