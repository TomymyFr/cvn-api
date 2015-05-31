<?php
/**
 * @author Timo Tijhof, 2015
 * @license http://krinkle.mit-license.org/
 * @package https://github.com/countervandalism/cvn-api
 */

class Response {

	/**
	 * Output a HTTP header, wrapper for PHP's header()
	 * @param string $key Header name
	 * @param string $value Header value
	 * @param bool $replace Replace current similar header
	 */
	public static function setHeader( $key, $value, $replace = true ) {
		header( strtolower( $key ) . ": $value", $replace );
	}
}
