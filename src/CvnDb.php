<?php
/**
 * @author Timo Tijhof, 2010–2015
 * @license http://krinkle.mit-license.org/
 * @package https://github.com/countervandalism/cvn-api
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
	public function __construct( Array $params ) {
		$this->file = $params['file'];
	}

	protected function open() {
		if ( !$this->conn ) {
			$this->conn = new PDO( 'sqlite:' . $this->file );
			$this->conn->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		}
	}

	protected function query( $sql, $values ) {
		try {
			$this->open();

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
			error_log( $e->__toString() );
			$this->warnings[] = get_class( $e ) . ': ' . $e->getMessage();
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
		$mtime = @filemtime( $this->file );
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
