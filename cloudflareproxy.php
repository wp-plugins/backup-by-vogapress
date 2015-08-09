<?php
namespace VPBackup ;

if ( ! defined( 'ABSPATH' ) ) { exit;
}

class CloudFlareProxy
{

		static $ips = array( array( 0x6715f400, '-1024' ), array( 0x6716c800, '-1024' ), array( 0x671f0400, '-1024' ), array( 0x68100000, '-1048576' ), array( 0x6ca2c000, '-16384' ), array( 0x8d654000, '-16384' ), array( 0xa29e0000, '-131072' ), array( 0xac400000, '-524288' ), array( 0xadf53000, '-4096' ), array( 0xbc726000, '-4096' ), array( 0xbe5df000, '-4096' ), array( 0xc5eaf000, '-1024' ), array( 0xc6298000, '-32768' ), array( 0xc71b8000, '-2048' ), );

		static function in_range_ip4( $ip ) {
				$long = ip2long( $ip );
		foreach ( self::$ips as $cfip ) {
			if ( ( $long & $cfip[1] ) == $cfip[0] ) {
				return true; }
		}
				return false;
		}

}
