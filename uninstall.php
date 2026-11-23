<?php
// Eliminando il plugin si cancellano chiave e cache.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'assistentino_key_hash' );
delete_option( 'assistentino_key_date' );
delete_site_transient( 'assistentino_release' );
