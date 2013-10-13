<?php
/**
 * API for the IRC Database of the Countervandalism Network
 * Created on May 1st, 2010
 *
 * Alias: cvnDbAPI
 *
 * version 0.3.6 (2012-03-10)
 *
 * Copyright (C) 2010 Krinkle <krinklemail@gmail.com>
 *
 * This program is free software. It comes without any warranty, to
 * the extent permitted by applicable law. You can redistribute it
 * and/or modify it under the terms of the Do What The Fuck You Want
 * To Public License, Version 2, as published by Sam Hocevar. See
 * http://sam.zoy.org/wtfpl/COPYING for more details.
 */


/*
 * Variable settings
 */
$s = array();

// Is this for real ?
$s['real'] = $_GET['raw'] == '1' ? false : true;

// What format ?
// Backwards compatible: Anything random or if none given --> json
switch( strtolower(trim($_GET['format'])) ) {

	case 'php':
		$s['format'] = 'php';
		break;

	case 'text':
		$s['format'] = 'text';
		break;

	// case 'json':
	default:
		$s['format'] = 'json';
}

// Content-Type as javascript, causes file to be downloaded when requested in a browser (hence the 'raw=1'-mode as an option)
if ($s['real'] && $s['format'] == 'json') {
	header('Content-Type: text/javascript');
}

// No errror reporting (0), else (-1) for debugging
error_reporting(-1);

// Reset timezone
date_default_timezone_set('UTC');


/*
 * Functions
 */
// http://kramerc.com/2010/11/30/converting-datetime-ticks-into-a-unix-timestamp-in-php/
function ticks_to_time($ticks) {
	return floor(($ticks - 621355968000000000) / 10000000);
}

function fallback_fill() {
	global $userOuputArray, $pageOuputArray, $outputArray;

	// Default to blank, prevent null error
	if (empty($userOuputArray)) {
		$userOuputArray['Example']['usertype'] = 'oo';
		$userOuputArray['Example']['reason'] = 'example';
		$userOuputArray['Example']['expiry'] = 'the end of time';
	}
	if (empty($pageOuputArray)) {
		$pageOuputArray['Example']['adder'] = 'Example';
		$pageOuputArray['Example']['reason'] = 'example';
	}

	$outputArray['users'] = $userOuputArray;
	$outputArray['pages'] = $pageOuputArray;
}

function create_json() {
	global $userOuputArray, $pageOuputArray, $jsonspit;

	// JSON: Add the output to the JSON object
	$jsonspit .= "\t\"users\": ".json_encode($userOuputArray).",\n";
	$jsonspit .= "\t\"pages\": ".json_encode($pageOuputArray)."\n";
	$jsonspit .= "\n})\n";
}

function close_up_and_spit() {
	global $s, $jsonspit, $outputArray;

	// RAW MODE: Dump the output in ontouched PHP format
	if (!$s['real']) { echo "\n\n# Final result as PHP Array:\n"; print_r($outputArray);}

	// JSON: Spit it out
	if (!$s['real']) { echo "\n\n# Final result for JSON:\n"; }
	if (!$s['real'] || $s['format'] == 'json') echo $jsonspit;

	// PHP: Spit it out
	if (!$s['real']) { echo "\n\n# Final result for Serialised PHP:\n"; }
	if (!$s['real'] || $s['format'] == 'php') echo serialize($outputArray);

	// TEXT: Spit it out
	if (!$s['real']) { echo "\n\n# Final result for text dump:\n"; }
	if (!$s['real'] || $s['format'] == 'text') echo '<pre>' . print_r($outputArray, true);

	// RAW MODE: Close the <pre>
	if (!$s['real']) {	echo '</pre>'; }
}

function force_quit($shortcode = 'UNKNOWN') {
	global $s;

	if (!$s['real']) {
		echo "\n\n# '".$shortcode."' ERROR OCCURED - To avoid breaking scripts, let's output a sample and stop right here:\n";
	}

	fallback_fill();
	create_json();
	close_up_and_spit();
	die;
}


/*
 * Configuration
 */

// Filename of the database
$c['database'] = 'Lists.sqlite';

// Get last-modified date of the database
$c['database_lastmod'] = date ('Y-m-d H:i:s', filemtime($c['database']));

// JSON callback fallback
$_GET['jsoncallback'] = isset( $_GET['jsoncallback'] ) ? $_GET['jsoncallback'] : 'jsoncallback';

// Split by pipe and remove duplicates
$uids = explode( '|', isset( $_GET['uid'] ) ? $_GET['uid'] : "" );
$uids = array_unique( $uids );
//print_r($uids);die; // <- DEBUG
$pages = explode( '|', isset( $_GET['pages'] ) ? $_GET['pages'] : "" );
$pages = array_unique( $pages );
//print_r($pages);die; // <- DEBUG

// RAW MODE: Title and begin of <pre>
if (!$s['real']) {
	echo '<h1>CVNBot - Database dump '.$c['database_lastmod'].'</h1><p>cvndbAPI version 0.3.1 (May 22nd, 2010)</p><pre>//UserType { admin = 2, whitelisted = 0, blacklisted = 1, bot = 5, user = 4, anon = 3, greylisted = 6 }'."\n";
}

// JSON: Start of JSON Callback
$jsonspit = $_GET['jsoncallback']."({\n\t\"dumpdate\": \"".$c['database_lastmod']."\",\n\n";

// Check if there's any valid input
if ( empty($_GET['uid']) && empty($_GET['pages']) ) {
	force_quit('INPUT IS EMPTY');
}


/*
 * Prepare the input
 */

// Remove bad ones
foreach($uids as $i => $uid) {
	if ( $uid !== addslashes($uid) ) {
		unset($uids[$i]);
	} else {
		$uids[$i] = trim($uids[$i]);
	}
}

unset($uid);

foreach($pages as $i => $page) {
	if ( $page !== addslashes($page) ) {
		unset($pages[$i]);
	} else {
		$pages[$i] = trim($pages[$i]);
	}
}

unset($page);




/*
 * Database connection
 */
	// RAW MODE: Log
	if (!$s['real']) {
		echo "\n\n# Checking the users table:\n";
	}

	// Try connection
	try {

		// Open the SQLite database
		$dbConnect = new PDO('sqlite:'.dirname(__FILE__) . '/' . $c['database']);

		/*
		 * USERS QUERY
		 */

		// Convert the array for the IN ('a', 'b', 'c') clause
		$uids_inClause = implode("','" , $uids);
		// Select the uids from the users table
		$sql = "SELECT * FROM users WHERE name IN ('".$uids_inClause."');";

		// RAW MODE: Log query
		if (!$s['real']) {
			echo "# SQL: \n\t".$sql."\n";
		}

		$dbQuery = $dbConnect->query($sql);
		if (!$dbQuery) {
			force_quit('dbConnect->query');
		}

		$dump = $dbQuery->execute();
		if (!$dump) {
			force_quit('dbQuery->execute');
		}

		$users = $dbQuery->fetchAll();
		// Do a more strict comparision here since an empty array is acceptable here
		if ($users === false) {
			force_quit('dbQuery->fetchAll');
		}

		// RAW MODE: Log the result
		if (!$s['real']) {
			var_dump($users);
		}

		// Loop through the results of each user.
		// Bot-status and admin-status are per-wiki, blacklisting is global.
		// Usually the next loop runs only once for each user, if user is on more lists it'll run for each
		foreach($users as $item) {

			// Check reason
			if (!empty($item['reason'])) {
				if (!$s['real']) {
					echo "\n# ".$item['name']." has a list reason";
				}
				$userOuputArray[$item['name']]['reason'] = $item['reason'];
			} else {
				if (!$s['real']) {
					echo "\n# ".$item['name']." has no list reason"; }
				$userOuputArray[$item['name']]['reason'] = 'No reason given';
			}

			// Check adder
			if (!empty($item['adder'])) {
				if (!$s['real']) {
					"\n# ".$item['name']." has a known adder";
				}
				$userOuputArray[$item['name']]['adder'] = $item['adder'];
			} else {
				if (!$s['real']) {
					"\n# ".$item['name']." has no known adder";
				}
				$userOuputArray[$item['name']]['adder'] = 'Unknown';
			}

			// Check expiry
			if (!empty($item['expiry'])) {
				if (!$s['real']) {
					"\n# ".$item['name']." has a known expiry";
				}
				$userOuputArray[$item['name']]['expiry'] = date('l, j F Y H:i', ticks_to_time($item['expiry']) ) . ' (UTC)';
			} else {
				if (!$s['real']) {
					"\n# ".$item['name']." has no known expiry"; }
				$userOuputArray[$item['name']]['expiry'] = 'the end of time';
			}

			// Check type
			// admin = 2, whitelisted = 0, blacklisted = 1, bot = 5, user = 4, anon = 3, greylisted = 6

			if ($item['type']=='1') {
				$userOuputArray[$item['name']]['usertype'] = 'bl';

			// 2 = adminlist (local), 0 = whitelist
			} elseif ($item['type']=='2' || $item['type']=='0') {
				$userOuputArray[$item['name']]['usertype'] = 'oo';

			// 3 = anon, 4 = user, 5 = botlist (local), 6 = greylist or not listed at all
			} else {
				$userOuputArray[$item['name']]['usertype'] = 'unknown';
			}

			if (!$s['real']) {
				"\n\n";
			}

		}

		/*
		 * PAGES QUERY
		 */

		// Convert the array for the IN ('a', 'b', 'c') clause
		$pages_inClause = implode("','" , $pages);
		// Select the uids from the users table
		$sql = "SELECT * FROM watchlist WHERE project='' AND article IN ('".$pages_inClause."');";

		// RAW MODE: Log query
		if (!$s['real']) {
			"# SQL: \n\t".$sql."\n";
		}

		$dbQuery = $dbConnect->query($sql);
		if (!$dbQuery) { force_quit('dbConnect->query'); }

		$dump = $dbQuery->execute();
		if (!$dump) { force_quit('dbQuery->execute'); }

		$pages = $dbQuery->fetchAll();
		// Do a more strict comparision here since an empty array is acceptable here
		if ($pages === false) { force_quit('dbQuery->fetchAll'); }

		// RAW MODE: Log the result
		if (!$s['real']) { var_dump($pages); }

		// Loop through the results of each article.
		foreach($pages as $item) {

			// Check reason
			if (!empty($item['reason'])) {
				if (!$s['real']) { echo "\n# ".$item['article']." has a watchlist reason"; }
				$pageOuputArray[$item['article']]['reason'] = htmlentities($item['reason'], ENT_NOQUOTES, "UTF-8");
			} else {
				if (!$s['real']) { echo "\n# ".$item['article']." has no watchlist reason"; }
				$pageOuputArray[$item['article']]['reason'] = 'No reason given';
			}

			// Check adder
			if (!empty($item['adder'])) {
				if (!$s['real']) { echo "\n# ".$item['article']." has a known adder"; }
				$pageOuputArray[$item['article']]['adder'] = htmlentities($item['adder'], ENT_NOQUOTES, "UTF-8");
			} else {
				if (!$s['real']) { echo "\n# ".$item['article']." has no known adder"; }
				$pageOuputArray[$item['article']]['adder'] = 'Unknown';
			}

			// Check project
				// For now it only checks global (WHERE projects=''),
				// but in the future we could, with a third parameter in the url (1:uid; 2:pages, 3:project) also include local items
				// however this isn't ideal right now since this SQLite-database is only of a few wikis
				// If we would ever switch to one central MySQL database with everything in it,
				// then this is a great possibility aswell ! --Krinkle 20100914

			if (!$s['real']) { echo "\n\n";}

		}

	} catch(PDOException $e) {
		if (!$s['real']) { echo $e->getMessage(); }
	}

/*
 * Close up and spit it out
 */

fallback_fill();

create_json();

close_up_and_spit();
