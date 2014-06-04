<?php
	/* Loads all servers declared in servers.json */
	function loadServers($fileName = 'servers.json') {
		$serversJson = file_get_contents(__DIR__.'/'.$fileName);
		$servers = json_decode($serversJson, true);
		return $servers;
	}

	/* Return full list of servers */
	function getServers($servers) {
		return $servers['servers'];
	}

	/* Return default server */
	function getDefaultServer($servers) {
		return $servers['servers'][$servers['default']];
	}

	/* Return the default format */
	function getDefaultFormat($servers) {
		$server = getDefaultServer($servers);
		return $server['format'];
	}

	/* Return list of declared formats */
	function getFormats($servers) {
		$allServers = getServers($servers);
		$formats = [];
		foreach ($allServers as $key => $conf) {
			$formats[] = $conf['format'];
		}
		return $formats;
	}

	/* Get list of servers that match to a format */
	function filterServersOnFormat($servers, $format) {
		$allServers = getServers($servers);
		$filteredServers = [];
		foreach ($allServers as $key => $conf) {
			if ($conf['format'] == $format) {
				$filteredServers[$key] = $conf;
			}
		}
		return $filteredServers; 
	}

	/* Read Shoutcast data */
	function readShoutcastData($host, $port) {
		$fp=@fsockopen($host, $port);
		if ($fp) {
			fputs($fp,"GET /7 HTTP/1.1\nContent-type: text/html; charset: iso-8859-1\nUser-Agent:Mozilla\n\n");
			for ($i = 0; $i < 1; $i++) {
				if (feof($fp)) break;
				$fp_data = fread($fp, 31337);
				usleep(500000);
			}
			$fp_data = iconv('iso-8859-1', 'utf-8', $fp_data);
			preg_match("/<body>(.*)<\/body>/", $fp_data, $fp_data);
			return $fp_data[1];
		} else return "";
	}

	/* Return number of listeners on server */
	function getListenersFrom($host, $port) {
		$data = readShoutcastData($host, $port);
		list($current, $status, $peak, $max, $reported, $bit, $song) = explode(',', $data, 7);
		return [$current, $max];
	}

	/* Return the address of the server at which listener will be redirected */
	function getServerAffected($serversObj, $format) {
		$servers = filterServersOnFormat($serversObj, $format);
		$defaultServer = getDefaultServer($serversObj);
		$defaultServerUrl = 'http://'.$defaultServer['host'].':'.$defaultServer['port'].'/;';
		$listeners = [];
		foreach ($servers as $key => $server) {
			list($current, $max) = getListenersFrom($server['host'], $server['port']);

			if ($current - 1 < $max) {
				$listeners['http://'.$server['host'].':'.$server['port'].'/;'] = $current;
			}
		}
		$serversAffected = array_keys($listeners, min($listeners));
		$serverAffected = isset($serversAffected[0]) ? $serversAffected[0] : $defaultServerUrl;
		return $serverAffected;
	}

	function app($debug = false) {
		$servers = loadServers();
		$formats = getFormats($servers);
		$format = (isset($_GET['format']) && array_search($_GET['format'], $formats)) ? $_GET['format'] : getDefaultFormat($servers);
		$redirect = getServerAffected($servers, $format)[0];

		if ($debug) {
			var_dump($redirect);
		} else {
			header('Location: '.$redirect);
		}
	}

	app(true);
?>
