<?php
namespace VPBackup ;

if ( ! defined( 'ABSPATH' ) ) { exit;
}

class CloudFlareProxy {

		static $ips = array (
				array( '1729491968', '-1024' ),
				array( '1729546240', '-1024' ),
				array( '1730085888', '-1024' ),
				array( '1745879040', '-1048576' ),
				array( '1822605312', '-16384' ),
				array( '-1922744320', '-16384' ),
				array( '-1566703616', '-131072' ),
				array( '-1405091840', '-524288' ),
				array( '-1376440320', '-4096' ),
				array( '-1133355008', '-4096' ),
				array( '-1101139968', '-4096' ),
				array( '-974458880', '-1024' ),
				array( '-970358784', '-32768' ),
				array( '-954499072', '-2048' ),
		);


		static function in_range_ip4 ($ip) {
				$long = ip2long( $ip );
			foreach ( self::$ips as $cfip ) {
				if ( ( $long & $cfip[1] ) == $cfip[0] ) {
						return true; }
			}
				return false;
		}
}

