<?php

namespace VPBackup ;

if ( ! defined( 'ABSPATH' ) ) { exit;
}

class VPBFiles
{
	/**
	 * The version number.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $_version;
	/**
	 * The token.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $_token;
	/**
	 * Deadline
	 * @var     int
	 * @access  public
	 * @since   1.0.0
	 */
	public $deadline;
	/**
	 * basePath
	 * @var     string
	 * @access  private
	 * @since   1.0.0
	 */
	private $basePath;
	/**
	 * File Mode Constants
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	const S_IFIFO  = 0010000  ;/* named pipe (fifo) */
	const S_IFCHR  = 0020000  ;/* character special */
	const S_IFDIR  = 0040000  ;/* directory */
	const S_IFBLK  = 0060000  ;/* block special */
	const S_IFREG  = 0100000  ;/* regular */
	const S_IFLNK  = 0120000  ;/* symbolic link */
	const S_IFSOCK = 0140000  ;/* socket */
	const S_IFWHT  = 0160000  ;/* whiteout */

	/**
	 * Constructor function.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function __construct ( $version = '1.0.0' )
	{
		$this->_version = $version;
		$this->_token = 'vbp-files';
		$this->deadline = ($_SERVER['REQUEST_TIME'] ? $_SERVER['REQUEST_TIME'] : time()) + ( ini_get( 'max_execution_time' ) == 0 ? 300 : ini_get( 'max_execution_time' ) ) - 1;
	} // End __construct ()

	private function get_absolute_path ($path)
	{
		$path = path_join( ABSPATH, $path );
		$path = str_replace( array( '/', '\\' ), DIRECTORY_SEPARATOR, $path );
		$parts = array_filter( explode( DIRECTORY_SEPARATOR, $path ), 'strlen' );
		$absolutes = array();
		foreach ( $parts as $part ) {
			if ( '.' == $part ) { continue;
			}
			if ( '..' == $part ) {
				array_pop( $absolutes );
			} else {
				$absolutes[] = $part;
			}
		}
			return DIRECTORY_SEPARATOR . implode( DIRECTORY_SEPARATOR, $absolutes );
	}

	private function mkdir ($dir)
	{
		if ( ! is_dir( $dir ) ) {
			for ( $parent = dirname( $dir );
			$parent != ABSPATH && ! is_dir( $parent );
			$parent = dirname( $parent ) ) {
			}

			if ( $stat = stat( $parent ) ) {
				$dir_perms = $stat['mode'] & 0007777;
			} else {
				$dir_perms = 0777;
			}
				mkdir( $dir, $dir_perms, true );
		}
	}

	public function get_remote_file( $src, $dst )
	{
		$resp = wp_remote_get(
			$src, array( 'filename' => $dst )
		);
		if ( is_wp_error( $resp ) || $resp->statusCode != 200 ) {
			return false;
		}
		return true;
	}

	public function upload ($stats)
	{
		$path = $this->get_absolute_path( $stats['path'] );
		$this->mkdir( dirname( $path ) );
		if ( ! empty($_POST['url']) ) {
			$resp = wp_remote_get( $_POST['url'], array( 'stream' => true, 'filename' => $path ) );
			if ( is_wp_error( $resp ) || 200 != $resp['response']['code'] ) {
				return false;
			}
			chmod( $path, $stats['mode'] & 0777 );
			touch( $path, $stats['mtime'] );
			return true;

		} else if ( ( $stats['mode'] & self::S_IFLNK ) == self::S_IFLNK ) {
			if ( ! file_exists( $path ) ) {
				symlink( $path, $this->get_absolute_path( $stats['link'] ) );
			}
			chmod( $path, $stats['mode'] & 0777 );
			touch( $path, $stats['mtime'] );
			return true;

		} else if ( ( $stats['mode'] & self::S_IFDIR ) == self::S_IFDIR ) {
			if ( ! file_exists( $path ) ) {
				mkdir( $path, $stats['mode'] & 0777, true );
				touch( $path, $stats['mtime'] );
			}
			return true;
		}
		return false;
	}

	public function download ($file)
	{
		$fileName = path_join( ABSPATH, $file );
		// validate file is within the wordpress directory
		$realPath = realpath( $fileName );
		if ( ! $realPath || ! preg_match( '#^'.ABSPATH.'#', $realPath ) || ! is_readable( $fileName ) ) {
			header( 'HTTP/1.0 404 Not Found' );
			return false ;
		}
		header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
		header( 'Content-Description: File Transfer' );
		header( 'Content-Disposition: attachment; filename='.basename( $file ) );
		header( 'Expires: 0' );
		header( 'Pragma: public' );
		readfile( $fileName );
		return true;
	}
	public function glob( $path )
	{
		$count = 0;
		$stack = [ $path ];
		$this->basePath = $path;
		clearstatcache();
		$resume = ( $_REQUEST['start'] ? false : get_transient( $_REQUEST['jobId'] ) );
		if ( ! $resume ) {
			echo '[';
		}
		while ( $p = array_shift( $stack ) ) {
			foreach ( scandir( $p ) as $file ) {
				$count++ ;
				$fullpath = path_join( $p, $file );
				if ( is_dir( $fullpath ) ) {
					if ( '.' == $file || '..' == $file ) {
						continue;
					} else {
						array_push( $stack, $fullpath );
					}
				}
				$this->_file_stat( $fullpath );
				if ( time() > $this->deadline ) {
					set_transient($_REQUEST['jobId'],
						array(
							'offset' => $count,
						), HOUR_IN_SECONDS
					);
					return false;
				}
			}
		}
		echo ']';
		return true;
	}
	private function _file_stat( $path, $echo = true )
	{
		$statKeys = array( 'ino', 'uid', 'mode', 'gid', 'size', 'mtime' );
		$stats = array_intersect_key( lstat( $path ), array_flip( $statKeys ) );
		$stats['path'] = preg_replace( '#^'.$this->basePath.'#', '', $path );
		$stats['level'] = count( explode( DIRECTORY_SEPARATOR, $stats['path'] ) );
		$stats['readable'] = is_readable( $path );
		if ( ($stats['mode'] & VPBFiles::S_IFLNK) == VPBFiles::S_IFLNK ) {
			$stats['link'] = readlink( $path );
		} else if ( ($stats['mode'] & VPBFiles::S_IFSOCK) == VPBFiles::S_IFSOCK ) {
			// nothing special
		} else if ( ($stats['mode'] & VPBFiles::S_IFWHT) == VPBFiles::S_IFWHT ) {
			// nothing special
		} else if ( $stats['mode'] & VPBFiles::S_IFREG && $stats['readable'] ) {
			$stats['md5'] = md5_file( $path );
		}
		if ( $echo ) { echo json_encode( $stats ), ',';
		}
		return $stats ;
	}
}
