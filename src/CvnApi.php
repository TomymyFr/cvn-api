<?php
/**
 * @author Timo Tijhof, 2010–2015
 * @license http://krinkle.mit-license.org/
 * @package https://github.com/countervandalism/cvn-api
 */

class CvnApi {
	/** @var CvnDb */
	protected $db;

	/** @var Array|null */
	protected $users = null;

	/** @var Array|null */
	protected $pages = null;

	/** @var string|null */
	protected $callback = null;

	/** @var bool */
	protected $isJsonP = false;

	/** @var Array */
	protected $warnings = array();

	/**
	 * @param CvnDb db
	 * @param Array params
	 *  - (string) users [optional] List of users separated by pipe.
	 *  - (string) pages [optional] List of users separated by pipe.
	 *  - (string) callback [optional] JavaScript identifier for JSON-P callback.
	 */
	public function __construct( CvnDb $db, Array $params ) {
		$this->db = $db;

		if ( isset( $params['users'] ) && $params['users'] !== '' ) {
			$this->users = explode( '|', $params['users'] );
		}

		if ( isset( $params['pages'] ) && $params['pages'] !== '' ) {
			$this->pages = explode( '|', $params['pages'] );
		}

		if ( isset( $params['callback'] ) ) {
			$this->callback = $params['callback'];
			$this->isJsonP = true;
		}
	}

	/**
	 * @return Array
	 */
	protected function getResponseData() {
		$data = array();

		// We want our data to serialise as objects in JSON. Non-empty associative arrays
		// serialise as objects in JSON, but empty arrays do not, so we cast it here to keep
		// this consistent.

		if ( $this->users !== null ) {
			$data['users'] = (object)$this->db->queryUsers( $this->users );
		}

		if ( $this->pages !== null ) {
			$data['pages'] = (object)$this->db->queryPages( $this->pages );
		}

		$data['lastUpdate'] = $this->db->getMTime();

		$warnings = array_merge( $this->warnings, $this->db->getWarnings() );
		if ( count( $warnings ) ) {
			$data['warnings'] = $warnings;
		}

		return $data;
	}

	protected function outputJson( $data ) {
		Response::setHeader( 'Content-Type', 'application/json; charset=utf-8' );
		echo json_encode( $data );
	}

	protected function outputJsonP( $data, $callback ) {
		Response::setHeader( 'Content-Type', 'text/javascript; charset=utf-8' );
		echo $callback . '(' . json_encode( $data ) .')';
	}

	protected function error( $errorCode ) {
		// HTTP 400 Bad Request
		http_response_code( 400 );
		$this->outputJson( array( 'error' => $errorCode ) );
	}

	public function execute() {
		if ( $this->callback !== null ) {
			// Validate callback
			if ( preg_match( "/[^a-zA-Z0-9_\.\]\[\'\"]/", $this->callback ) ) {
				$this->error( 'invalid-callback' );
				return;
			}
		}

		// Validate query
		if ( $this->users === null && $this->pages === null ) {
			$this->error( 'missing-query' );
			return;
		}

		$data = $this->getResponseData();

		// Allow CORS
		// Avoid having to use JSON-P, which has a cache-busting callback.
		Response::setHeader( 'Access-Control-Allow-Origin', '*' );

		if ( isset( $data['warnings'] ) ) {
			// Do not cache responses with warnings
			Response::setHeader( 'Cache-Control', 'no-cache' );
		} elseif ( $data['lastUpdate'] ) {
			$maxAge = 5 * 60; // 5 minutes
			Response::setHeader( 'Last-Modified', gmdate( 'D, d M Y H:i:s', $data['lastUpdate'] ) . ' GMT' );
			Response::setHeader( 'Cache-Control', 'public, max-age=' . intval( $maxAge ) );
			Response::setHeader( 'Expires', gmdate( 'D, d M Y H:i:s', time() + $maxAge ) . ' GMT' );

			if ( Request::tryLastModified( $data['lastUpdate'] ) ) {
				exit;
			}
		}

		if ( $this->isJsonP ) {
			$this->outputJsonP( $data, $this->callback );
		} else {
			$this->outputJson( $data );
		}
	}
}
