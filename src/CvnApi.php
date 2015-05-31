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
	 */
	public function __construct( CvnDb $db, Array $params ) {
		$this->db = $db;

		if ( isset( $params['users'] ) && strlen( $params['users'] ) ) {
			$this->users = explode( '|', $params['users'] );
		}

		if ( isset( $params['pages'] ) && strlen( $params['pages'] ) ) {
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
		// serialise as objects in JSON, but empty arrays do not, so we case it here to keep
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
		header( 'content-type: application/json; charset=utf-8', /* replace = */ true );
		echo json_encode( $data );
	}

	protected function outputJsonP( $data, $callback ) {
		header( 'content-type: text/javascript; charset=utf-8', /* replace = */ true );
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

		if ( $this->isJsonP ) {
			$this->outputJsonP( $data, $this->callback );
		} else {
			$this->outputJson( $data );
		}
	}
}
