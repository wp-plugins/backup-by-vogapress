<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( isset( $_POST['byg-nonce'] ) && wp_verify_nonce( $_POST['byg-nonce'], __FILE__ ) ) {
	$upload_dir = wp_upload_dir();
	$response = wp_remote_post( 'https://vogapress.com/tokens/create' , array(
		'method' => 'POST',
		'body' => array(
			'urls[netAdmin]' => network_admin_url(),
			'urls[admin]' 	=> admin_url(),
			'urls[home]'	=> home_url(),
			'multisite'	=> is_multisite(),
			'type'		=> 'backup',
			'nonce'		=> \VPBackup\VPBackup::create_nonce( 'byg-token-register' ),
			'version'	=> \VPBackup\VPBackup::VERSION,
			'slash'		=> DIRECTORY_SEPARATOR,
		),
		));
	if ( is_wp_error( $response ) ) {
		$error = $response->get_error_message();
	} else if ( '200' != $response['response']['code'] ) {
		$messages = json_decode( $response['body'], true );
		if ( ! $messages ) {
			$error = $response['body'] ;
		} else {
			$error = $messages['message'];
		}
	} else {
		$messages = json_decode( $response['body'], true );
		if ( $messages['redirect'] ) {
			wp_redirect( $messages['redirect'] );
			die();
		}
	}
}
?>
<div class="wrap">
<h2>Backup By VOGAPress</h2>
<?php if ( $error ) : ?>
<div class="error below-h2">
	<p><?php echo $error; ?></p>
</div>
<?php endif; ?>
<form name="form" method="post" action="?page=backup-by-wordpress-settings&registration=yes&noheader=true">
	<p class="text-muted">Backup by VOGAPress requires registration.  Please click the button below and follow the instructions to register.</p>
	<?php wp_nonce_field( __FILE__, 'byg-nonce' ); ?>
	<?php submit_button( 'Register' ); ?>
</form>
</div>
