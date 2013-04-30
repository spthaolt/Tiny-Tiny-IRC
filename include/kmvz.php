<?php


	function kmvz_shorten($url) {
		if (function_exists("curl_init")) {
			$query = "http://kmvz.us/api.php?op=shorten&key=" .
				urlencode(KMVZ_APIKEY) . "&url=" . urlencode($url);

			$ch = curl_init($query);

			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
			curl_setopt($ch, CURLOPT_TIMEOUT, 2);
			curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

			$contents = @curl_exec($ch);

			if ($contents) {
				$contents = json_decode($contents, true);
				if ($contents && $contents['shorturl']) {
					return $contents['shorturl'];
				}
			}
		}

		return $url;
	}
?>
