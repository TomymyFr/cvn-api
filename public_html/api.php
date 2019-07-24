<?php
/**
 * Web API for the database of the Countervandalism Network.
 *
 * @author Timo Tijhof
 * @license https://krinkle.mit-license.org/2010-2019/
 * @package https://github.com/countervandalism/cvn-api
 */

/**
 * Prepare environment
 */

// Reset timezone
date_default_timezone_set( 'UTC' );

// Clean start (no default handling like cookies and "x-powered-by")
header_remove();

// Fallback for PHP 5.3
if (!function_exists('http_response_code')) {
	function http_response_code($code = null) {
		if ($code !== null) {
			switch ($code) {
				case 200: $text = 'OK'; break;
				case 304: $text = 'Not Modified'; break;
				case 400: $text = 'Bad Request'; break;
				case 401: $text = 'Unauthorized'; break;
				case 402: $text = 'Payment Required'; break;
				case 403: $text = 'Forbidden'; break;
				case 404: $text = 'Not Found'; break;
				case 500: $text = 'Internal Server Error'; break;
				default:
					$code = 500;
					$text = 'Unknown-Http-Status-Code';
				break;
			}

			header("HTTP/1.x $code $text");

			$GLOBALS['http_response_code'] = $code;

		} else {
			$code = (isset($GLOBALS['http_response_code']) ? $GLOBALS['http_response_code'] : 200);
		}

		return $code;
	}
}

/**
 * Configuration
 */

$projectDir = dirname( __DIR__ );
$conf = array(
	'dbFile' => "$projectDir/data/Lists.sqlite",
);

/**
 * Handle request
 */
require "$projectDir/src/Request.php";
require "$projectDir/src/Response.php";
require "$projectDir/src/CvnDb.php";
require "$projectDir/src/CvnApi.php";

$db = new CvnDb( array(
	'file' => $conf['dbFile'],
) );

$api = new CvnApi( $db, $_GET + $_POST );

$api->execute();
