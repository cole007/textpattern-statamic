<?php
	
	/*
		Textpattern to Statamic migration tool
		URL: https://github.com/cole007/textpattern-statamic
		Author: Cole Henley
		Twitter: @cole007
		Version: 0.1
		
		1. Create 'Statamic' folder at your site root, ensure has write permissions enabled (usually 775)
		2. Place output.php in Statamic folder, be sure to update/customise config accordingly 
		3. Access the URL via your web browser, eg http://www.domain.com/statamic/output.php
		
		// to do:	custom fields?	
	
	*/
	
	## CONFIG BELOW HERE ##
	
	# Location of your textpattern config file
	include_once('../textpattern/config.php');
	
	# what is your domain?
	# this is used to set up .htaccess redirection, defaults to the currently hosted domain
	
	# $domain = 'http://www.domain.com/';
	$domain = (isset($_SERVER['HTTPS'])) ? 'https://'.$_SERVER['HTTP_HOST'] : 'http://'.$_SERVER['HTTP_HOST'];
	
	# what format are your textpattern files saved in?
	
	$format = '.textile'; // textile
	# $format = '.html'; // HTML
	# $format = '.txt'; // plain text
	
	# How do you want your Statamic entries to be saved?
	# see http://statamic.com/docs/how-statamic-works#entry-types
	
	$statamic = 'index'; // eg 2012-07-04-forth-of-july.md
	# $statmic = 'plain'; // eg how-to-eat-soup-with-a-fork.md
	# $statmic = 'date'; // eg 85.handlebar-mustache.md
	
	# How do you want your Statamic URL to work? 
	# see http://statamic.com/docs/how-statamic-works#urls
	
	$statamicURLS = 'plain'; // eg http://example.com/blog/mustache-grooming
	# $statamicURLS = 'date'; // eg http://example.com/blog/2012-07-04-mustache-grooming
	
	# do you want to generate a htaccess to redirect files from old URLs to new equivalents?
	$doHtaccess = 1;
	# $doHtaccess = null;
	
	# do you want to import the Textpattern categories?
	$importCategories = 1;
	# $importCategories = null;
	
	# specify author?
	
	# choose your template?
	# see http://statamic.com/docs/how-statamic-works#markup-parsing

	# $template = 'post';
	$template = null;
	
	## CONFIG ABOVE HERE ##
	
	
	$db = mysql_connect($txpcfg['host'], $txpcfg['user'], $txpcfg['pass']);
	mysql_select_db($txpcfg['db'],$db);
	
	function getUser($AuthorId,$table_prefix) {
		$permlinkRes = mysql_query('SELECT `RealName` FROM `'.$table_prefix.'txp_users` WHERE `name`="'.$AuthorId.'" LIMIT 1'); 
		$permlinkData = mysql_fetch_array($permlinkRes);
		return $permlinkData['RealName'];
	}
	function URLstructure($section,$thisid,$url_title,$posted,$table_prefix) {
		// get permalink link preference
		$permlinkRes = mysql_query('SELECT `val` FROM `'.$table_prefix.'txp_prefs` WHERE `name`="permlink_mode" LIMIT 1'); 
		$permlinkData = mysql_fetch_array($permlinkRes);
		switch($permlinkData['val']) {
			case 'section_id_title':
				return "$section/$thisid/$url_title";
			case 'year_month_day_title':
				list($y,$m,$d) = explode("-",date("Y-m-d",$posted));
				return "$y/$m/$d/$url_title";
			case 'id_title':
				return "$thisid/$url_title";
			case 'section_title':
				return "$section/$url_title";
			case 'title_only':
				return "$url_title";
		}
		// permalink_title_format
	}	
	
	$res = mysql_query('SELECT * FROM  `'.$txpcfg['table_prefix'].'txp_section`'); 
	$htaccess = '';
	$created = 0;
	$modified = 0;
	while ($data = mysql_fetch_array($res)) { 
	    $section = $data['name'];
	    $resContent = mysql_query('SELECT * FROM `'.$txpcfg['table_prefix'].'textpattern` WHERE `Section` = "'.$section.'" && (`Status`="4" OR `Status`="5") ORDER BY `Posted` DESC'); 
		$numContent = mysql_num_rows($resContent);
		if ($numContent > 0) {		
			if (!file_exists($section)) {
				mkdir($section);
			}
			while ($dataContent = mysql_fetch_array($resContent)) { 
				
				// Redirect 301 /blog/136/responsiveish-viewport-hack http://cole007.net/blog/responsiveish-viewport-hack
				switch($statamic) {
					case 'index':
						$file = $dataContent['Section'].'/'.$dataContent['ID'].'.'.$dataContent['url_title'].$format;
					case 'plain':
						$file = $dataContent['Section'].'/'.$dataContent['url_title'].$format;
					case 'date':
						$file = $dataContent['Section'].'/'.date('Y-m-d',strtotime($dataContent['Posted'])).'-'.$dataContent['url_title'].$format;
				}
				
				if (isset($doHtaccess)) {
					switch($statamicURLS) {
						case 'date':  $htaccess .= 'Redirect 301 '.URLstructure($dataContent['Section'],$dataContent['ID'],$dataContent['url_title'],$dataContent['Posted'],$txpcfg['table_prefix']).' '.$domain.'/'.$dataContent['Section'].'/'.$dataContent['url_title']."\r\n";
						case 'plain':  $htaccess .= 'Redirect 301 '.URLstructure($dataContent['Section'],$dataContent['ID'],$dataContent['url_title'],$dataContent['Posted'],$txpcfg['table_prefix']).' '.$domain.'/'.$dataContent['Section'].'/'.date('Y-m-d',strtotime($dataContent['Posted'])).'-'.$dataContent['url_title']."\r\n";
					}
					
				}
				if (!file_exists($file)) { 
					$created++;
				} 
				$handle = fopen($file, "wb");
				fwrite($handle, '---'."\n");
				fwrite($handle, 'title: '.$dataContent['Title']."\n");
				$author = getUser($dataContent['AuthorID'],$txpcfg['table_prefix']);
				if ($author != '') fwrite($handle, 'author: '.$author."\n");
				fwrite($handle, 'category: '."\n");
				if(isset($importCategories) && $dataContent['Category1'] != '') fwrite($handle, ' - '.$dataContent['Category1']."\n");
				if(isset($importCategories) && $dataContent['Category2'] != '') fwrite($handle, ' - '.$dataContent['Category2']."\n");
				if(isset($template)) fwrite($handle, '_template: '.$template."\n");
				fwrite($handle, '---'."\n");
				fwrite($handle, $dataContent['Body']);
				$modified++;
				fclose($handle);
			}	
		}
    } 
    
    if(isset($doHtaccess)) {
    	$handle = fopen('.htaccess', "wb");
		fwrite($handle, $htaccess);
		fclose($handle);
	}
	echo '<p>'.$created.' files created and '.$modified.' files modified</p>';
?>