<?php
	function update_self_step($link, $step, $params, $force = false) {
		// __FILE__ is in include/ so we need to go one level up
		$work_dir = dirname(dirname(__FILE__));
		$parent_dir = dirname($work_dir);

		if (!chdir($work_dir)) {
			array_push($log, "Unable to change to work directory: $work_dir");
			$stop = true; break;
		}

		$stop = false;
		$log = array();
		if (!is_array($params)) $params = array();

		switch ($step) {
		case 0:
			array_push($log, "Work directory: $work_dir");

			if (!is_writable($work_dir) && !is_writable("$parent_dir")) {
				$user = posix_getpwuid(posix_geteuid());
				$user = $user["name"];
				array_push($log, "Both tt-rss and parent directories should be writable as current user ($user).");
				$stop = true; break;
			}

			if (!file_exists("$work_dir/config.php") || !file_exists("$work_dir/include/sanity_check.php")) {
				array_push($log, "Work directory $work_dir doesn't look like tt-rss installation.");
				$stop = true; break;
			}

			if (!is_writable(sys_get_temp_dir())) {
				array_push($log, "System temporary directory should be writable as current user.");
				$stop = true; break;
			}

			array_push($log, "Checking for tar...");

			$system_rc = 0;
			system("tar --version >/dev/null", $system_rc);

			if ($system_rc != 0) {
				array_push($log, "Could not run tar executable (RC=$system_rc).");
				$stop = true; break;
			}

			array_push($log, "Checking for latest version...");

			$version_info = json_decode(fetch_file_contents("http://tt-rss.org/version.php"),
				true);

			if (!is_array($version_info)) {
				array_push($log, "Unable to fetch version information.");
				$stop = true; break;
			}

			$target_version = $version_info["version"];
			$target_dir = "$parent_dir/tt-rss-$target_version";

			array_push($log, "Target version: $target_version");
			$params["target_version"] = $target_version;

			if (version_compare(VERSION, $target_version) != -1 && !$force) {
				array_push($log, "Your Tiny Tiny RSS installation is up to date.");
				$stop = true; break;
			}

			if (file_exists($target_dir)) {
				array_push($log, "Target directory $target_dir already exists.");
				$stop = true; break;
			}

			break;
		case 1:
			$target_version = $params["target_version"];

			array_push($log, "Downloading checksums...");
			$md5sum_data = fetch_file_contents("http://tt-rss.org/download/md5sum.txt");

			if (!$md5sum_data) {
				array_push($log, "Could not download checksums.");
				$stop = true; break;
			}

			$md5sum_data = explode("\n", $md5sum_data);

			foreach ($md5sum_data as $line) {
				$pair = explode("  ", $line);

				if ($pair[1] == "tt-rss-$target_version.tar.gz") {
					$target_md5sum = $pair[0];
					break;
				}
			}

			if (!$target_md5sum) {
				array_push($log, "Unable to locate checksum for target version.");
				$stop = true; break;
			}

			$params["target_md5sum"] = $target_md5sum;

			break;
		case 2:
			$target_version = $params["target_version"];
			$target_md5sum = $params["target_md5sum"];

			array_push($log, "Downloading distribution tarball...");

			$tarball_url = "http://tt-rss.org/download/tt-rss-$target_version.tar.gz";
			$data = fetch_file_contents($tarball_url);

			if (!$data) {
				array_push($log, "Could not download distribution tarball ($tarball_url).");
				$stop = true; break;
			}

			array_push($log, "Verifying tarball checksum...");

			$test_md5sum = md5($data);

			if ($test_md5sum != $target_md5sum) {
				array_push($log, "Downloaded checksum doesn't match (got $test_md5sum, expected $target_md5sum).");
				$stop = true; break;
			}

			$tmp_file = tempnam(sys_get_temp_dir(), 'tt-rss');
			array_push($log, "Saving download to $tmp_file");

			if (!file_put_contents($tmp_file, $data)) {
				array_push($log, "Unable to save download.");
				$stop = true; break;
			}

			$params["tmp_file"] = $tmp_file;

			break;
		case 3:
			$tmp_file = $params["tmp_file"];
			$target_version = $params["target_version"];

			if (!chdir($parent_dir)) {
				array_push($log, "Unable to change into parent directory.");
				$stop = true; break;
			}

			$old_dir = tmpdirname($parent_dir, "tt-rss-old");

			array_push($log, "Renaming tt-rss directory to ".basename($old_dir));
			if (!rename($work_dir, $old_dir)) {
				array_push($log, "Unable to rename tt-rss directory.");
				$stop = true; break;
			}

			array_push($log, "Extracting tarball...");
			system("tar zxf $tmp_file", $system_rc);

			if ($system_rc != 0) {
				array_push($log, "Error while extracting tarball (RC=$system_rc).");
				$stop = true; break;
			}

			$target_dir = "$parent_dir/tt-rss-$target_version";

			array_push($log, "Renaming target directory...");
			if (!rename($target_dir, $work_dir)) {
				array_push($log, "Unable to rename target directory.");
				$stop = true; break;
			}

			if (!chdir($work_dir)) {
				array_push($log, "Unable to change to work directory: $work_dir");
				$stop = true; break;
			}

			array_push($log, "Copying config.php...");
			if (!copy("$old_dir/config.php", "$work_dir/config.php")) {
				array_push($log, "Unable to copy config.php to $work_dir.");
				$stop = true; break;
			}

			array_push($log, "Cleaning up...");
			unlink($tmp_file);

			array_push($log, "Fixing permissions...");

			$directories = array(
				CACHE_DIR,
				CACHE_DIR . "/htmlpurifier",
				CACHE_DIR . "/export",
				CACHE_DIR . "/images",
				CACHE_DIR . "/magpie",
				CACHE_DIR . "/simplepie",
				ICONS_DIR,
				LOCK_DIRECTORY);

			foreach ($directories as $dir) {
				array_push($log, "-> $dir");
				chmod($dir, 0777);
			}

			array_push($log, "Upgrade completed.");
			array_push($log, "Your old tt-rss directory is saved at $old_dir. ".
				"Please migrate locally modified files (if any) and remove it.");
			array_push($log, "You might need to re-enter current directory in shell to see new files.");

			$stop = true;
			break;
		default:
			$stop = true;
		}

		return array("step" => $step, "stop" => $stop, "params" => $params, "log" => $log);
	}


	function update_self($link, $force = false) {
		$step = 0;
		$stop = false;
		$params = array();

		while (!$stop) {
			$rc = update_self_step($link, $step, $params, $force);

			$params = $rc['params'];
			$stop = $rc['stop'];

			foreach ($rc['log'] as $line) {
				_debug($line);
			}
			++$step;
		}
	}
?>
