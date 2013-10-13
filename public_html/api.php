<?php
/**
 * API for the database of the Countervandalism Network
 * https://github.com/countervandalism/cvn-api
 *
 * @author Timo Tijhof, 2010–2013
 * @license http://krinkle.mit-license.org/
 * @package cvn-api
 */

class CvnDb {
	/** @var string */
	protected $file;

	/** @var PDO */
	protected $conn;

	/** @var Array */
	protected $warnings = array();

	/**
	 * @param Array params
	 */
	public function __construct( $params ) {
		$this->file = $params['file'];
	}

	protected function open() {
		if ( !$this->conn ) {
			$this->conn = new PDO( 'sqlite:' . $this->file );
		}
	}

	protected function query( $sql, $values ) {
		try {
			$sth = $this->conn->prepare( $sql );
			if ( !$sth ) {
				$this->warnings[] = 'PDO::prepare failed.';
				return array();
			}

			if ( !$sth->execute( $values ) ) {
				$this->warnings[] = 'PDOStatement::execute failed.';
				return array();
			}

			$rows = $sth->fetchAll();
			if ( $rows === false ) {
				$this->warnings[] = 'PDOStatement::fetchAll failed.';
				return array();
			}

			return $rows;

		} catch ( PDOException $e ) {
			$this->warnings[] = 'PDOException code ' . $e->getCode();
			return array();
		}
	}

	// http://kramerc.com/2010/11/30/converting-datetime-ticks-into-a-unix-timestamp-in-php/
	protected static function ticksToTime( $ticks ) {
		return floor(($ticks - 621355968000000000) / 10000000);
	}

	/** @return Array */
	public function queryUsers( $users ) {
		// Map of users.type to names
		static $userTypeNames = array(
			'0' => 'whitelist',
			'1' => 'blacklist',
			'2' => 'admin',
			'3' => 'anon',
			'4' => 'user',
			'5' => 'bot',
			'6' => 'greylist',
		);

		// Simplify list type for the purpose of this API
		static $userTypeMap = array(
			'admin' => 'whitelist',
			'bot' => 'whitelist',
		);

		static $userTypeFilter = array( 'whitelist', 'blacklist', 'greylist' );

		$this->open();

		$users = array_values( array_unique( $users ) );

		$marks = implode( ',', array_fill( 0, count( $users ), '?' ) );
		$sql = "SELECT * FROM users WHERE name IN ($marks);";
		$rows = $this->query( $sql, $users );

		$userData = array();

		foreach ( $rows as $row ) {
			// If one of these fields is missing, the row is corrupt
			if ( $row['name'] === '' || $row['type'] === '' || $row['adder'] === '' ) {
				$this->warnings[] = 'Skipped a corrupt user row';
				continue;
			}

			if ( !isset( $userTypeNames[ $row['type'] ] ) ) {
				$this->warnings[] = 'Skipped row with unknown type';
				continue;
			}

			$type = $userTypeNames[ $row['type'] ];

			if ( isset( $userTypeMap[ $type ] ) ) {
				$type = $userTypeMap[ $type ];
			}

			if ( !in_array( $type, $userTypeFilter ) ) {
				// Skip types we're interested in.
				$this->warnings[] = 'Skipped row with excluded type';
				continue;
			}

			$userData[ $row['name'] ] = array(
				'type' => $type,
				'comment' => !empty( $row['reason'] ) ? $row['reason'] : false,
				'expiry' => !empty( $row['expiry'] ) ? self::ticksToTime( $row['expiry'] ) : false,
				'adder' => $row['adder'],
			);
		}

		return $userData;
	}

	/** @return Array */
	public function queryPages( $pages ) {
		$this->open();

		$pages = array_values( array_unique( $pages ) );

		$marks = implode( ',', array_fill( 0, count( $pages ), '?' ) );
		$sql = "SELECT * FROM watchlist WHERE project='' AND article IN ($marks);";
		$rows = $this->query( $sql, $pages );

		$pageData = array();

		foreach ( $rows as $row ) {

			// If one of these fields is missing, the row is corrupt
			if ( $row['article'] === '' || $row['adder'] === '' ) {
				$this->warnings[] = 'Skipped a corrupt page row';
				continue;
			}

			$pageData[ $row['article'] ] = array(
				'comment' => !empty( $row['reason'] ) ? $row['reason'] : false,
				'expiry' => !empty( $row['expiry'] ) ? self::ticksToTime( $row['expiry'] ) : false,
				'adder' => $row['adder'],
			);
		}

		return $pageData;
	}

	/** @return int */
	public function getMTime() {
		$mtime = filemtime( $this->file );
		if ( $mtime === false ) {
			$this->warnings[] = 'Could not determine last modified timestamp.';
			return 0;
		}
		return $mtime;
	}

	/** @return Array|false */
	public function getWarnings() {
		return $this->warnings;
	}
}

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
	public function __construct( $db, $params ) {
		$this->db = $db;

		if ( isset( $params['users'] ) ) {
			$this->users = explode( '|', $params['users'] );
		}

		if ( isset( $params['pages'] ) ) {
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
		if ( ( $this->users === null || !count( $this->users ) ) &&
			( $this->pages === null || !count( $this->pages ) )
		) {
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

/**
 * Set up environment
 */

// No errror reporting (0), else (-1) for debugging
error_reporting( -1 );

// Reset timezone
date_default_timezone_set( 'UTC' );

/**
 * Configuration
 */

$conf = array(
	'dbFile' => dirname( __DIR__ ) . '/data/Lists.sqlite',
);

/**
 * Handle request
 */

$db = new CvnDb( array(
	'file' => $conf['dbFile'],
) );

$api = new CvnApi( $db, $_GET );

$api->execute();

