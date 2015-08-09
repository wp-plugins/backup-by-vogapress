<?php
/**
 * Plugin Name: Backup by VOGA Press
 * Version: 0.3.3
 * Plugin URI: http://vogapress.com/
 * Description: Simplest way to manage your backups with VOGAPress cloud service. Added with file monitoring to let you know when your website has been compromised.
 * Author: VOGA Press
 * Author URI: http://vogapress.com/
 * Requires at least: 3.0.1
 * Tested up to: 4.2.4
 * Network: True
 *
 * Text Domain: backup-by-vogapress
 * Domain Path: /lang/
 *
 * PHP version 5.2
 *
 * @category WordPress,Restore,Backup
 * @package  Backup_By_Vogapress
 * @author   Raphael Tse <raphael@vogapress.com>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @link     http://vogapress.com
 * @since    0.3.0
 **/

namespace VPBackup ;

if ( ! defined( 'ABSPATH' ) ) {
		exit;
}

require(dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'timeout.php');

class VPBackup
{
	CONST VPURL         	= 'https://vogapress.com/';
	CONST ALLOWEDDOMAIN 	= 'vogapress.com';
	CONST OPTNAME		= 'byg-backup';
	CONST VERSION		= '0.3.3';

	/**
	 * The single instance of WordPress_Plugin_Template.
	 * @var     object
	 * @access  private
	 * @since     0.3.0
	 */
	private static $_instance = null;
	/**
	 * Settings class object
	 * @var     object
	 * @access  public
	 * @since   0.3.0
	 */
	public $settings = null;
	/**
	 * The version number.
	 * @var     string
	 * @access  public
	 * @since   0.3.0
	 */
	private $_version;
	/**
	 * The token.
	 * @var     string
	 * @access  public
	 * @since   0.3.0
	 */
	private $_token;
	/**
	 * Suffix for Javascripts.
	 * @var     string
	 * @access  public
	 * @since   0.3.0
	 */
	public $script_suffix;
	/**
	 * IPs that are allowed to create session
	 * @var     Array
	 * @access  public
	 * @since   0.3.0
	 */
	private $white_ips = array(
	'45.55.87.153',
	'45.55.237.104',
	'104.131.73.27',
	);

	/**
	 * Constructor function.
	 * @access  public
	 * @since   0.3.0
	 * @return  void
	 */
	public function __construct ( $version = VPBackup::VERSION )
	{
		Timeout::init();
		$this->_version = $version;
		$this->_token = 'backup-by-wordpress';
		// Load plugin environment variables
		$this->script_suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		register_activation_hook( $this->file, array( $this, 'install' ) );

		// Handle localisation
		$this->load_plugin_textdomain();
		add_action( 'init', array( $this, 'load_localisation' ), 0 );

		// add actions
		$this->add_actions();
	} // End __construct ()

	/**
	 * Load plugin localisation
	 * @access  public
	 * @since   0.3.0
	 * @return  void
	 */
	public function load_localisation ()
	{
		load_plugin_textdomain( 'backup-by-vogapress', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
	} // End load_localisation ()

	/**
	 * Load plugin textdomain
	 * @access  public
	 * @since   0.3.0
	 * @return  void
	 */
	public function load_plugin_textdomain ()
	{
		$domain = 'backup-by-vogapress';
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );
		load_textdomain( $domain, WP_LANG_DIR . DIRECTORY_SEPARATOR . $domain . DIRECTORY_SEPARATOR . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, false, dirname( plugin_basename( __FILE__ ) . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR ) );
	} // End load_plugin_textdomain ()

	/**
	 * Installation. Runs on activation.
	 * @access  public
	 * @since   0.3.0
	 * @return  void
	 */
	public function install ()
	{
		$this->_log_version_number();
	} // End install ()

	/**
	 * Log the plugin version number.
	 * @access  public
	 * @since   0.3.0
	 * @return  void
	 */
	private function _log_version_number ()
	{
		update_site_option( $this->_token . '-version', $this->_version );
	} // End _log_version_number ()

	/**
	 * Add settings page to admin menu
	 * @access  public
	 * @since   0.3.0
	 * @return void
	 */
	public function add_menu_item ()
	{
		$page = add_menu_page( __( 'Backup', 'backup-by-wordpress' ), __( 'Backup', 'backup-by-wordpress' ), 'export', $this->_token . '-settings',  array( $this, 'settings_page' ), null, 80 );
	}

	/**
	 * Display settings page
	 * @access  public
	 * @since   0.3.0
	 * @return void
	 */
	public function settings_page ()
	{
		include dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'settings-page.php' ;
	}

	/**
	 * add action hooks
	 * @access  public
	 * @since   0.3.0
	 * @return  void
	 */
	public function add_actions ()
	{
		$actions = array( 'session', 'data_list', 'data_export', 'data_import', 'file_list', 'file_export', 'file_import', 'register' );
		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_vpb_'.$action, array( &$this, $action ) );
			add_action( 'wp_ajax_nopriv_vpb_'.$action, array( &$this, $action ) );
		}

		// Load API for generic admin functions
		if ( is_admin() ) {
			// Add settings page to menu
			if ( is_multisite() ) {
				add_action( 'network_admin_menu', array( &$this, 'add_menu_item' ) );
				add_action( 'network_admin_notices', array( &$this, 'notices' ) );
				add_action( 'admin_notices', array( &$this, 'notices' ) );
			} else {
				add_action( 'admin_menu', array( &$this, 'add_menu_item' ) );
				add_action( 'admin_notices', array( &$this, 'notices' ) );
			}
		}
	}

	/**
	 * admin notices
	 * @access  public
	 * @since   0.3.0
	 * @return  void
	 */
	public function notices ()
	{
		$byg_backup = get_site_option( self::OPTNAME, array() );
		if ( ! $byg_backup || ! strlen( $byg_backup['uuid'] ) ) {
			echo '<div class="update-nag"><a href="vogapress.com">Backup by VOGAPress</a> requires activation.  Activate <a href="' . esc_url( network_admin_url( 'admin.php?page=backup-by-wordpress-settings&registration=yes' ) ) . '">here</a>.</div>';
		}
		if ( '1' == $_REQUEST['bygmessage'] ) {
			echo "<div class='updated'><p>Backup by VOGAPress is activated.</p></div>";
		}
	}

	/**
	 * data list
	 * @access  public
	 * @since   0.3.0
	 * @return  void
	 */
	public function data_list ()
	{
		if ( $this->verify_request() ) {
			include dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'mysqldump.php' ;
			global $wpdb;
			$adapter = TypeAdapterFactory::create( 'mysql', $wpdb );

			$filename = 'php://output';
			if ( self::_is_curl_available() ) {
				$filename = Timeout::get_tmp_name( $_REQUEST['jobId'] );
			}
			$handle = fopen( $filename, 'wb' );

			fwrite( $handle,'[' );
			foreach ( $wpdb->get_results( $adapter->show_tables( DB_NAME ), ARRAY_N ) as $row ) {
				fwrite( $handle, json_encode( array( 'path' => current( $row ), 'subtype' => 'table' ) ) . ',' );
			}
			foreach ( $wpdb->get_results( $adapter->show_views( DB_NAME ), ARRAY_N ) as $row ) {
				fwrite( $handle, json_encode( array( 'path' => current( $row ), 'subtype' => 'view' ) ) . ',' );
			}
			foreach ( $wpdb->get_results( $adapter->show_triggers( DB_NAME ), ARRAY_N ) as $row ) {
				fwrite( $handle, json_encode( array( 'path' => current( $row ), 'subtype' => 'trigger' ) ) . ',' );
			}
			fwrite( $handle,']' );
			fclose( $handle );

			if ( self::_is_curl_available() ) {
				include dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'files.php' ;
				$file = new VPBFiles();
				if ( $file->download_curl( $filename ) ) {
					echo '1';
				} else {
					echo '-1';
				}
				unlink( $filename );
			}
			wp_die();
		}
	}

	/**
	 * data export
	 * @access  public
	 * @since   0.3.0
	 * @return  void
	 */
	public function data_export ()
	{
		if ( $this->verify_request() ) {
			include dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'mysqldump.php' ;
			include dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'files.php' ;
			$table   = filter_var( $_REQUEST['table'], FILTER_VALIDATE_REGEXP, array( 'options' => array( 'regexp' => '/[a-zA-Z0-9_\$]+/' ) ) );
			$view    = filter_var( $_REQUEST['view'], FILTER_VALIDATE_REGEXP, array( 'options' => array( 'regexp' => '/[a-zA-Z0-9_\$]+/' ) ) );
			$trigger = filter_var( $_REQUEST['trigger'], FILTER_VALIDATE_REGEXP, array( 'options' => array( 'regexp' => '/[a-zA-Z0-9_\$]+/' ) ) );
			if ( $table ) {
				$export = new Mysqldump( 'mysql', array( 'include-tables' => array( $table ) ) );
			} else if ( $view ) {
				$export = new Mysqldump( 'mysql', array( 'include-views' => array( $view ) ) );
			} else if ( $trigger ) {
				$export = new Mysqldump( 'mysql', array( 'include-triggers' => array( $trigger ) ) );
			}
			if ( $export ) {
				if ( self::_is_curl_available() ) {
					$filename = Timeout::get_tmp_name( $_REQUEST['jobId'] );
					if ( $export->start( $filename ) ) {
						$file = new VPBFiles();
						if ( $file->download_curl( $filename ) ) {
							echo '1';
						} else {
							echo '-1';
						}
						unlink( $filename );
					} else {
						echo '2';
					}
				} else {
					$export->start();
				}
			}
			wp_die();
		}
	}

	/**
	 * data import
	 * @access  public
	 * @since   0.3.0
	 * @return  void
	 */
	public function data_import ()
	{
		if ( $this->verify_request() && $this->verify_url( $_POST['url'] ) ) {
			$tmp_name = path_join( sys_get_temp_dir(), 'vpb-' . $_REQUEST['jobId'] );
			if ( ! $_REQUEST['start'] || $this->_get_remote_file( $_POST['url'], $tmp_name ) ) {

				$byg_backup = get_site_option( self::OPTNAME, array() );
				include dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'mysqlimport.php' ;
				$import = new Mysqlimport();
				try {
					if ( $import->start( $tmp_name, $_REQUEST['path'] ) ) {
						unlink( $tmp_name );
						echo '1';
					} else {
						echo '2'; // times up
					}
				} catch (Exception $e) {
					echo '-2';
				}
				$byg_backup['mtime'] = time();
				update_site_option( self::OPTNAME, $byg_backup );
				wp_die();
			} else {
				echo '-1';
				wp_die();
			}
		}
	}

	/**
	 * file list
	 * @access  public
	 * @since   0.3.0
	 * @return  void
	 */
	public function file_list ()
	{
		if ( $this->verify_request() ) {
			include dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'files.php' ;
			$export = new VPBFiles();
			if ( self::_is_curl_available() ) {
				$filename = Timeout::get_tmp_name( 'vpb-tmp-'.$_REQUEST['jobId'] );
				if ( ! $export->glob( ABSPATH, $filename ) ) {
					echo '2';
				} else {
					if ( $export->download_curl( $filename ) ) {
						unlink( $filename );
						echo '1';
					} else {
						unlink( $filename );
						echo '-1';
					}
				}
			} else {
				$export->glob( ABSPATH );
			}
			wp_die();
		}
	}

	/**
	 * file import
	 * @access  public
	 * @since   0.3.0
	 * @return  void
	 */
	public function file_import ()
	{
		if ( $this->verify_request() && (empty($_POST['url']) || $this->verify_url( $_POST['url'] )) ) {
			include dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'files.php' ;
			$import = new VPBFiles();
			if ( $import->upload( $_POST ) ) {
				echo '1';
			} else {
				echo '-1';
			}
			wp_die();
		}
	}

	/**
	 * file export
	 * @access  public
	 * @since   0.3.0
	 * @return  void
	 */
	public function file_export ()
	{
		if ( $this->verify_request() ) {
			include dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'files.php' ;
			$export = new VPBFiles();
			if ( self::_is_curl_available() ) {
				if ( $export->download_curl( $_REQUEST['file'] ) ) {
					echo '1';
				} else {
					echo '-1';
				}
				wp_die();
			} else {
				$export->download( $_REQUEST['file'] );
			}
			wp_die();
		}
	}

	/**
	 * Get session
	 * @access  public
	 * @since   0.3.0
	 * @return  void
	 */
	public function session()
	{
		// validate permission
		// validate against UUID
		$byg_backup = get_site_option( self::OPTNAME, array() );
		if ( ! strlen( $byg_backup['uuid'] ) ) { return ; }
		$signature = md5(
			$_REQUEST['sessionId'] . '|' .
			$_REQUEST['timestamp'] . '|' .
			$byg_backup['uuid'], false
		);

		if ( $signature == $_REQUEST['signature'] && abs( time() - intval( $_REQUEST['timestamp'] ) ) < 3600 && $this->verify_ip() ) {
			$post = array(
				'sessionSecret'	=> self::create_nonce( $_REQUEST['sessionId'] ),
				'sessionId'    	=> $_REQUEST['sessionId'],
				'version'	=> $this->_version,
				'paths'		=> $this->_get_paths(),
				'wpversion'	=> get_bloginfo( 'version' ),
				'curl'		=> self::_is_curl_available(),
			);
			$resp = wp_remote_post(
				esc_url( self::VPURL . 'jobs/session/' . $_REQUEST['sessionId'] ), array(
				'method'     => 'POST',
				'body'        => $post,
				)
			);
			if ( is_wp_error( $resp ) ) {
				echo '-2';
				wp_die();
			} else if ( 200 == $resp['response']['code'] ) {
				$data = json_decode( $resp['body'], true );
				$byg_backup['mdate'] = time();
				update_site_option( self::OPTNAME, $byg_backup );
				echo '1';
				wp_die();
			} else {
				echo '-1';
				wp_die();
			}
		}
	}

	/**
	 * Registration

	 * @access public
	 * @since  0.3.0
	 * @return void
	 */
	public function register()
	{
		// validate permission
		// validate against UUID
		$hosts = gethostbynamel( self::ALLOWEDDOMAIN );
		$request_ip = $this->_get_remote_ip();
		if ( (in_array( $request_ip, $hosts ) || in_array( $request_ip, $this->white_ips )) && $_POST['nonce'] == self::create_nonce( 'byg-token-register' ) ) {
			update_site_option(
				self::OPTNAME, array(
				'id'         => $_POST['id'],
				'uuid'        => $_POST['uuid'],
				)
			);
			echo '1';
			wp_die();
		}
	}

	/**
	 * Verify IP Address
	 * @access  private
	 * @since   0.3.0
	 * @return  boolean
	 */
	private function verify_ip() {
		// cloud flare proxy support
		if ( isset($_SERVER['HTTP_CF_CONNECTING_IP']) ) {
			$ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
			require_once(dirname( __FILE__ ).DIRECTORY_SEPARATOR.'cloudflareproxy.php');

			if ( ! CloudFlareProxy::in_range_ip4( $_SERVER['REMOTE_ADDR'] ) ) {
				return false;
			}
		} else {
			$ip = $this->_get_remote_ip();

		}
		return ( in_array( $ip, $this->white_ips ) || in_array( $ip, gethostbynamel( self::ALLOWEDDOMAIN ) ) );
	}

	/**
	 * Verify key
	 * @access  private
	 * @since   0.3.0
	 * @return  boolean
	 */
	private function verify_request()
	{
		$timestamp = filter_var( $_REQUEST['timestamp'], FILTER_VALIDATE_INT );
		$sessionId = filter_var( $_REQUEST['sessionId'], FILTER_VALIDATE_REGEXP, array( 'options' => array( 'regexp' => '/[a-zA-Z0-9]+/' ) ) );
		$signature = filter_var( $_REQUEST['signature'], FILTER_VALIDATE_REGEXP, array( 'options' => array( 'regexp' => '/[a-zA-Z0-9]+/' ) ) );
		$jobId = filter_var( $_REQUEST['jobId'], FILTER_VALIDATE_REGEXP, array( 'options' => array( 'regexp' => '/[a-zA-Z0-9]+/' ) ) );
		$byg_backup = get_site_option( self::OPTNAME, array() );

		if ( ! $timestamp || ! $sessionId || ! $signature || ! $jobId || ! strlen( $byg_backup['uuid'] ) ) {
			return false ;
		}

		$secret = self::create_nonce( $sessionId );
		$md = md5( $timestamp . '|' . $secret, false );

		return $md == $signature &&
			abs( intval( $timestamp ) - time() ) < 3600 && $this->verify_ip();
	}

	/**
	 * Verify url
	 * @access  private
	 * @since   0.3.0
	 * @return  boolean
	 */
	private function verify_url($url)
	{
		$urlcomp = parse_url( $url );
		return preg_match( '/vogapress\.com$/i', $urlcomp['host'] );
	}

	/**
	 * get remote ip
	 * @access  private
	 * @since   0.3.0
	 * @return  string
	 */
	private function _get_remote_ip()
	{
		if ( function_exists( 'apache_request_headers' ) ) {
			$headers = apache_request_headers();
		} else {
			$headers = $_SERVER;
		}
		if ( array_key_exists( 'X-Forwarded-For', $headers ) && filter_var( $headers['X-Forwarded-For'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$the_ip = $headers['X-Forwarded-For'];
		} elseif ( array_key_exists( 'HTTP_X_FORWARDED_FOR', $headers ) && filter_var( $headers['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$the_ip = $headers['HTTP_X_FORWARDED_FOR'];
		} else {
			$the_ip = filter_var( $_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 );
		}
		return $the_ip;
	}

	/**
	 * get remote file
	 * @access  private
	 * @since   0.3.0
	 * @return  string
	 */
	private function _get_remote_file( $src, $dst )
	{
		$resp = wp_remote_get(
			$src, array(
			'stream' => true,
			'filename' => $dst,
			)
		);
		if ( is_wp_error( $resp ) || 200 != $resp['response']['code'] ) {
			return false;
		}
		return true;
	}

	/**
	 * get system paths
	 * @access  private
	 * @since   0.3.0
	 * @return  array
	 */
	private function _get_paths( )
	{
		$paths = array(
			'plugin'	=> str_replace( ABSPATH,'',trailingslashit( dirname( plugin_dir_path( __FILE__ ) ) ) ),
			'theme'		=> str_replace( ABSPATH,'',trailingslashit( get_theme_root() ) ),
		);
		if ( ! is_multisite() ) {
			$upload_dir	= wp_upload_dir();
			$paths['upload'] = array( str_replace( ABSPATH, '', trailingslashit( $upload_dir['basedir'] ) ) );
		} else {
			if ( function_exists( 'wp_get_sites' ) ) {
				// beyond 10000 subsite, wp_is_large_network will trigger empty return
				$blog_list = wp_get_sites( array(
					'limit' => 10000,
				) );
			} else {
				$blog_list = get_blog_list( 0, 'all' );
			}
			$paths['upload'] = array();
			foreach ( $blog_list as $blog ) {
				switch_to_blog( $blog['blog_id'] );
				$upload_dir = wp_upload_dir();
				array_push( $paths['upload'],str_replace( ABSPATH,'',trailingslashit( $upload_dir['basedir'] ) ) );
				restore_current_blog();
			}
		}
		return $paths;
	}
	/**
	 * detect if curl is available
	 * @access  private
	 * @since   0.3.3
	 * @return  boolean
	 */
	private function _is_curl_available( )
	{
		return function_exists( 'curl_init' );
	}

	/**
	 * create nonce
	 * @access  public static
	 * @since   0.3.0
	 * @return  boolean
	 */
	public static function create_nonce($action)
	{
		$i = wp_nonce_tick();
		return substr( wp_hash( $i . '|' . $action, 'nonce' ), -12, 10 );
	}

	/**
	 * Registration

	 * @access public static
	 * @since  0.3.0
	 * @return string
	 */
	public static function get_status()
	{
		$byg_backup = get_site_option( self::OPTNAME, array() );
		$timestamp = time();
		$query_string = build_query(
			array(
			'signature' => md5( $timestamp . '|' . $byg_backup['uuid'] ),
			'timestamp' => $timestamp,
			)
		);
		$resp = wp_remote_get(
			esc_url( self::VPURL . 'backups/' . $byg_backup['id'] . '/status/' ) .'?'.$query_string, array(
			'method'    => 'GET',
			'body'        => $get,
			)
		);
		if ( is_wp_error( $resp ) ) {
			return 'unknown';
		} else if ( 200 == $resp['response']['code'] ) {
			$data = json_decode( $resp['body'], true );
			return $data['status'];
		}
		return 'unknown';
	}

	/**
	 * Escape UUID

	 * @access public static
	 * @since  0.3.0
	 * @return string
	 */
	public static function esc_uuid($x) {
		return preg_replace( '/[^A-Za-z0-9\-]/','',$x );
	}

}

new VPBackup();
