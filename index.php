<?php
	require_once('global.php');
	$_VARS["PAGE"]['url_request'] = $_REQUEST["PATH"];
	
	$_VARS["PAGE"]['status_code'] = array('id' => 200);
	// Pull page data
	if(isset($_VARS["PAGE"]['url_request']) && !empty($_VARS["PAGE"]['url_request'])) {
		$page = Log::$status[$DBI->execute('SELECT page.*, page_type.description, page_type.noindex, user.username, user.first_name, user.last_name FROM page LEFT JOIN user ON page.author_user_id = user.id LEFT JOIN page_type ON page.page_type_id = page_type.id WHERE page.url = "' . $_VARS["PAGE"]['url_request'] . '";', MYSQL_ASSOC)]['data']['result'][0];
		
		// Check page aliases
		if(!isset($page) || empty($page)) {
			$page = Log::$status[$DBI->execute('SELECT page.*, page_type.description, page_type.noindex, user.username, user.first_name, user.last_name FROM page LEFT JOIN user ON page.author_user_id = user.id LEFT JOIN page_type ON page.page_type_id = page_type.id LEFT JOIN page_alias ON page.id = page_alias.page_id WHERE page_alias.alias_url = "' . $_VARS["PAGE"]['url_request'] . '";', MYSQL_ASSOC)]['data']['result'][0];
			
			if(isset($page) && !empty($page)) {
				$_VARS["PAGE"]['status_code'] = array('id' => 301, 'url' => BASE_DIR . '/' . $page['url']);
				header("HTTP/1.1 301 Moved Permanently");
				header("Location: " . $_VARS["PAGE"]['status_code']['url']);
			}
		}
		
		// Check extendible pages (and page aliases)
		if(!isset($page) || empty($page)) {
			$chain = explode("/", $_VARS["PAGE"]['url_request']);
			for($i = 0; $i < count($chain); $i++) {
				$url = implode("/", array_slice($chain, 0, $i + 1));
				$pageExtended = Log::$status[$DBI->execute('SELECT page.*, page_type.description, page_type.noindex, user.username, user.first_name, user.last_name FROM page LEFT JOIN user ON page.author_user_id = user.id LEFT JOIN page_type ON page.page_type_id = page_type.id LEFT JOIN page_alias ON page.id = page_alias.page_id WHERE page.extendible = 1 AND (page.url = "' . $url . '" OR page_alias.alias_url ="' . $url . '");', MYSQL_ASSOC)]['data']['result'][0];
				
				if(isset($pageExtended) && !empty($pageExtended)) {
					$page = $pageExtended;
					$_VARS["PAGE"]['status_code'] = array('id' => 200, 'url' => BASE_DIR . '/' . $page['url'], 'extension' => implode("/", array_slice($chain, $i + 1)));
				}
				unset($pageExtended);
				unset($pageExtended);
			}
			unset($chain);
		}
		
		// Return 404
		if(!isset($page) || empty($page) || $_VARS["PAGE"]['status_code']['id'] == 404) {
			$page = Page::get404();
		}
	}
	// Return homepage
	else $page = Log::$status[$DBI->execute('SELECT page.*, page_type.description, page_type.noindex, user.username, user.first_name, user.last_name FROM page JOIN page_type ON page.page_type_id = page_type.id LEFT JOIN user ON page.author_user_id = user.id WHERE page.id = ' . $_VARS["WEBSITE"]['homepage_id'] . ';', MYSQL_ASSOC)]['data']['result'][0];
	
	$_VARS["PAGE"]['permissions'] = Page::permissions($page['id']);
	$_VARS["PAGE"]['allow'] = 1;
	if($_VARS["PAGE"]['permissions'] != null) {
		if($_VARS["WEBSITE"]['account']['success'] == 1) {
			foreach($_VARS["PAGE"]["permissions"] as $i=>$page_permission) {
				$allow = false;
				foreach($_VARS["WEBSITE"]['account']['permissions'] as $user_permission) {
					if($page_permission == $user_permission) $allow = true;
				}
				if(!$allow) {
					$_VARS["PAGE"]['allow'] = 0;
					$page = Page::get404();
					unset($allow);
					break;
				}
				unset($allow);
			}
		}
		else {
			$_VARS["PAGE"]['allow'] = 1;
			$page = Page::get404();
		}
	}
	$_VARS["PAGE"] = array_merge((array)$_VARS["PAGE"], (array)$page);
	unset($page);
	
	// Log access into database
	Log::$status[$DBI->execute('INSERT INTO log_access (code, url, page_id, ip_address, user_id) VALUES(' . $_VARS["PAGE"]['status_code']['id'] . ', "' . $_VARS["PAGE"]['url_request'] . '", ' . (isset($_VARS["PAGE"]['id']) ? $_VARS["PAGE"]['id'] : 'NULL') . ', "' . $_SERVER["REMOTE_ADDR"] . '", ' . (isset($_VARS["WEBSITE"]['account']['data']['id']) ? $_VARS["WEBSITE"]['account']['data']['id'] : 'NULL') . ');')];
	if(isset($_VARS["PAGE"]['header'])) eval('?>' . $_VARS["PAGE"]['header']);
	
	Page::template($_VARS);
	$DBI->disconnect();
	//htmlentities(print_r($_VARS));
	//htmlentities(print_r(Log::$status));
?>
