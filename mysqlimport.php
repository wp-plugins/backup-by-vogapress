<?php

namespace VPBackup ;

if ( ! defined( 'ABSPATH' ) ) { exit;
}

use Exception;

class Mysqlimport
{

	// Same as mysqldump
	const MAXLINESIZE = 1000000;

	// Available compression methods as constants
	const GZIP = 'Gzip';
	const BZIP2 = 'Bzip2';
	const NONE = 'None';

	// Available connection strings
	const UTF8 = 'utf8';
	const UTF8MB4 = 'utf8mb4';

	// This can be set both on constructor or manually
	private $host;
	private $db;
	private $fileName;

	private $fileHandler = null;
	private $deadline;

	public function __construct($db = DB_NAME, $host = DB_HOST, $type = 'mysql')
	{
		$this->db = $db;
		$this->host = $host;
		$this->dbType = strtolower( $type );
		$this->deadline = time() + ( ini_get( 'max_execution_time' ) == 0 ? 300 : ini_get( 'max_execution_time' ) ) - 1;
	}

	public function start($filename = '')
	{
		// Output file can be redefined here
		if ( ! empty($filename) ) {
			$this->fileName = $filename;
		}
		// We must set a name to continue
		if ( empty($this->fileName) ) {
			throw new Exception( 'Input file name is not set' );
		}
		$this->fileHandler = fopen( $filename, 'rb' );
		if ( false === $this->fileHandler ) {
			throw new Exception( 'Input file is not readable' );
		}
		return $this->process();
	}

	private function process()
	{
		global $wpdb;
		$query = '';
		$offset = 0;
		$resume = ( $_REQUEST['start'] ? false : get_transient( $_REQUEST['jobId'] ) );
		if ( $resume ) {
			$fpos = $resume['offset'];
			fseek( $this->fileHandler, $offset );
		}

		while ( $this->deadline > time() && ($line = fgets( $this->fileHandler )) ) {
			if ( substr( $line, 0, 2 ) == '--' || trim( $line ) == '' ) {
				continue;
			}

			$query .= $line;
			if ( substr( trim( $query ), -1 ) == ';' ) {
				$updates = $wpdb->query( $query );
				if ( false === $updates ) {
					throw new Exception( 'Error performing query \'<strong>' . $query . '\': ' . mysql_error() );
				}
				$query = '';
				$offset = ftell( $this->fileHandler );
			}
		}
		if ( ! $line ) {
			// complete
			return true;
		}
		set_transient($_REQUEST['jobId'],
			array(
				'offset' => $offset,
			), HOUR_IN_SECONDS
		);
		return false;
	}
}
