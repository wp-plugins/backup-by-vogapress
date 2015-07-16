<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$byg_backup = get_site_option( 'byg-backup' );

if ( ! $byg_backup || 'yes' == $_REQUEST['registration'] ) {
	include( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'registration.php' );
} else {
	include( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'info.php' );
}

?>
