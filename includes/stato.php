<?php
/**
 * Endpoint di sola lettura: GET /wp-json/assistentino/v1/stato
 * Chiave nell'intestazione "X-Assistentino-Key". Il sito conserva solo l'impronta sha256.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'assistentino/v1', '/stato', array(
		'methods'             => 'GET',
		'callback'            => 'assistentino_stato',
		'permission_callback' => 'assistentino_permesso',
		'show_in_index'       => false,   // non compare nell'elenco pubblico di /wp-json/
	) );
} );

// toglie anche il gruppo "assistentino/v1" dall'elenco dei namespace di /wp-json/
add_filter( 'rest_index', function ( $response ) {
	$data = $response->get_data();
	if ( isset( $data['namespaces'] ) && is_array( $data['namespaces'] ) ) {
		$data['namespaces'] = array_values( array_diff( $data['namespaces'], array( 'assistentino/v1' ) ) );
		$response->set_data( $data );
	}
	return $response;
} );

/**
 * Alcuni plugin di sicurezza (o «Disable REST API») chiudono tutte le REST API a chi non
 * ha fatto l'accesso, con il filtro rest_authentication_errors. Per la sola richiesta di
 * Colibri con la chiave giusta togliamo quel blocco; tutto il resto resta chiuso.
 */
add_filter( 'rest_authentication_errors', function ( $result ) {
	return assistentino_richiesta_di_colibri() ? true : $result;
}, PHP_INT_MAX );

function assistentino_richiesta_di_colibri() {
	$uri   = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$route = isset( $GLOBALS['wp']->query_vars['rest_route'] ) ? (string) $GLOBALS['wp']->query_vars['rest_route'] : '';
	if ( false === strpos( $route . ' ' . rawurldecode( $uri ), '/assistentino/v1/stato' ) ) {
		return false;
	}
	$hash = (string) get_option( 'assistentino_key_hash', '' );
	$key  = isset( $_SERVER['HTTP_X_ASSISTENTINO_KEY'] ) ? (string) wp_unslash( $_SERVER['HTTP_X_ASSISTENTINO_KEY'] ) : '';
	return '' !== $hash && '' !== $key && hash_equals( $hash, hash( 'sha256', $key ) );
}

/**
 * Chiave giusta → passa. Chiave mancante o sbagliata → risponde come se il percorso non esistesse.
 */
function assistentino_permesso( WP_REST_Request $request ) {
	$hash = (string) get_option( 'assistentino_key_hash', '' );
	$key  = (string) $request->get_header( 'X-Assistentino-Key' );

	if ( '' === $hash || '' === $key || ! hash_equals( $hash, hash( 'sha256', $key ) ) ) {
		return new WP_Error( 'rest_no_route', 'Nessun percorso corrisponde all\'URL e al metodo richiesti.', array( 'status' => 404 ) );
	}
	return true;
}

function assistentino_stato() {
	global $wp_version;

	require_once ABSPATH . 'wp-admin/includes/plugin.php';

	// plugin
	$upd_plugins = get_site_transient( 'update_plugins' );
	$auto        = (array) get_site_option( 'auto_update_plugins', array() );
	$plugins     = array();
	foreach ( get_plugins() as $file => $d ) {
		$new = null;
		if ( is_object( $upd_plugins ) && isset( $upd_plugins->response[ $file ]->new_version ) ) {
			$new = (string) $upd_plugins->response[ $file ]->new_version;
		}
		$dir       = dirname( $file );
		$plugins[] = array(
			'file'        => $file,
			'slug'        => '.' === $dir ? basename( $file, '.php' ) : $dir,
			'name'        => wp_strip_all_tags( $d['Name'] ),
			'version'     => (string) $d['Version'],
			'active'      => is_plugin_active( $file ),
			'network'     => is_multisite() && is_plugin_active_for_network( $file ),
			'update'      => $new,
			'auto_update' => in_array( $file, $auto, true ),
		);
	}

	$mu = array();
	foreach ( get_mu_plugins() as $file => $d ) {
		$mu[] = array(
			'file'    => $file,
			'name'    => wp_strip_all_tags( $d['Name'] ),
			'version' => (string) $d['Version'],
		);
	}

	// tema attivo (e genitore)
	$upd_themes = get_site_transient( 'update_themes' );
	$tema       = function ( $t ) use ( $upd_themes ) {
		$slug = $t->get_stylesheet();
		$new  = null;
		if ( is_object( $upd_themes ) && isset( $upd_themes->response[ $slug ]['new_version'] ) ) {
			$new = (string) $upd_themes->response[ $slug ]['new_version'];
		}
		return array(
			'slug'    => $slug,
			'name'    => wp_strip_all_tags( $t->get( 'Name' ) ),
			'version' => (string) $t->get( 'Version' ),
			'update'  => $new,
		);
	};
	$theme           = wp_get_theme();
	$theme_data      = $tema( $theme );
	$theme_data['parent'] = $theme->parent() ? $tema( $theme->parent() ) : null;

	// WordPress
	$core_new = null;
	$upd_core = get_site_transient( 'update_core' );
	if ( is_object( $upd_core ) && ! empty( $upd_core->updates ) ) {
		foreach ( $upd_core->updates as $offer ) {
			if ( isset( $offer->response ) && 'upgrade' === $offer->response ) {
				$core_new = (string) $offer->current;
				break;
			}
		}
	}

	$response = rest_ensure_response( array(
		'assistentino' => ASSISTENTINO_VERSION,
		'home'         => home_url( '/' ),
		'wordpress'    => array(
			'version' => $wp_version,
			'update'  => $core_new,
		),
		'php'          => PHP_VERSION,
		'multisite'    => is_multisite(),
		'theme'        => $theme_data,
		'plugins'      => $plugins,
		'mu_plugins'   => $mu,
		// quando WordPress ha controllato l'ultima volta gli aggiornamenti dei plugin
		'checked_at'   => ( is_object( $upd_plugins ) && ! empty( $upd_plugins->last_checked ) )
			? gmdate( 'c', (int) $upd_plugins->last_checked ) : null,
		'generated_at' => gmdate( 'c' ),
	) );
	$response->header( 'Cache-Control', 'no-store, private' );
	return $response;
}
