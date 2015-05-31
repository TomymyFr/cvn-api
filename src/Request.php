<?php
/**
 * @author Timo Tijhof, 2015
 * @license http://krinkle.mit-license.org/
 * @package https://github.com/countervandalism/cvn-api
 */

class Request {

	/**
	 * Initialise the header list
	 */
	private static function initHeaders() {
		$headers = array();
		$apacheHeaders = function_exists( 'apache_request_headers' ) ? apache_request_headers() : false;
		if ( $apacheHeaders ) {
			foreach ( $apacheHeaders as $key => $val ) {
				$headers[strtoupper( $key )] = $val;
			}
		} else {
			foreach ( $_SERVER as $name => $value ) {
				if ( substr( $name, 0, 5 ) === 'HTTP_' ) {
					$name = str_replace( '_', '-', substr( $name, 5 ) );
					$headers[$name] = $value;
				} elseif ( $name === 'CONTENT_LENGTH' ) {
					$headers['CONTENT-LENGTH'] = $value;
				}
			}
		}

		return $headers;
	}

	/**
	 * Get all request headers
	 *
	 * @return array Headers keyed by name (normalised to upper case)
	 */
	public static function getAllHeaders() {
		static $headers = null;
		if ( $headers === null ) {
			$headers = self::initHeaders();
		}
		return $headers;
	}

	/**
	 * Get a request header, or false if it isn't set
	 *
	 * @param string $name Case-insensitive header name
	 * @return string|bool False on failure
	 */
	public static function getHeader( $name ) {
		$name = strtoupper( $name );
		$headers = self::getAllHeaders();
		if ( !isset( $headers[$name] ) ) {
			return false;
		}
		return $headers[$name];
	}

	/**
	 * Respond with 304 Last Modified if appropiate
	 *
	 * @param int $modifiedTime UNIX timestamp
	 * @return bool True if 304 header was sent
	 */
	public static function tryLastModified( $modifiedTime ) {
		$clientCache = self::getHeader( 'If-Modified-Since' );
		if ( $clientCache !== false ) {
			# IE sends sizes after the date like "Wed, 20 Aug 2003 06:51:19 GMT; length=5202"
			# which would break strtotime() parsing.
			$clientCache = preg_replace( '/;.*$/', '', $clientCache );
			$clientCacheTime = @strtotime( $clientCache );
			if ( $modifiedTime <= $clientCacheTime ) {
				// HTTP 304 Not Modified
				http_response_code( 304 );
				return true;
			}
		}
		return false;
	}
}
