<?php
/**
 * Aggiornamenti da GitHub (release pubbliche).
 *
 * Grazie all'intestazione "Update URI: https://github.com/…" WordPress non cerca il plugin
 * su wordpress.org e chiede a noi, con il filtro update_plugins_github.com (WP 5.8+).
 * Ogni release deve avere allegato "assistentino.zip" (lo prepara l'azione GitHub).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ultima release, tenuta in cache 6 ore (1 ora se GitHub non risponde).
 * Restituisce array( version, package, url, notes ) oppure null.
 */
function assistentino_release() {
	$cached = get_site_transient( 'assistentino_release' );
	if ( false !== $cached ) {
		return $cached ? $cached : null;
	}

	$res = wp_remote_get(
		'https://api.github.com/repos/' . ASSISTENTINO_GITHUB_REPO . '/releases/latest',
		array(
			'timeout' => 10,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'Assistentino/' . ASSISTENTINO_VERSION,
			),
		)
	);

	$release = null;
	if ( ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res ) ) {
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( is_array( $body ) && ! empty( $body['tag_name'] ) ) {
			$package = '';
			foreach ( (array) ( $body['assets'] ?? array() ) as $asset ) {
				if ( isset( $asset['name'], $asset['browser_download_url'] ) && 'assistentino.zip' === $asset['name'] ) {
					$package = $asset['browser_download_url'];
					break;
				}
			}
			if ( $package ) {
				$release = array(
					'version' => ltrim( (string) $body['tag_name'], 'vV' ),
					'package' => $package,
					'url'     => (string) ( $body['html_url'] ?? '' ),
					'notes'   => (string) ( $body['body'] ?? '' ),
				);
			}
		}
	}

	// '' in cache = "nessuna release valida": non richiedere a ogni caricamento
	set_site_transient( 'assistentino_release', $release ? $release : '', $release ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
	return $release;
}

add_filter( 'update_plugins_github.com', function ( $update, $plugin_data, $plugin_file ) {
	if ( plugin_basename( ASSISTENTINO_FILE ) !== $plugin_file ) {
		return $update;
	}
	$r = assistentino_release();
	if ( ! $r ) {
		return $update;
	}
	return array(
		'id'      => $plugin_data['UpdateURI'],
		'slug'    => 'assistentino',
		'version' => $r['version'],
		'url'     => $r['url'],
		'package' => $r['package'],
	);
}, 10, 3 );

// finestra «Visualizza dettagli» nella pagina Plugin
add_filter( 'plugins_api', function ( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || empty( $args->slug ) || 'assistentino' !== $args->slug ) {
		return $result;
	}
	$r = assistentino_release();
	return (object) array(
		'name'          => 'Assistentino',
		'slug'          => 'assistentino',
		'version'       => $r ? $r['version'] : ASSISTENTINO_VERSION,
		'author'        => 'Maddalena',
		'homepage'      => 'https://github.com/' . ASSISTENTINO_GITHUB_REPO,
		'download_link' => $r ? $r['package'] : '',
		'sections'      => array(
			'description' => 'Espone a Colibri, in sola lettura e con una chiave, lo stato del sito.',
			'changelog'   => $r && $r['notes'] ? wpautop( esc_html( $r['notes'] ) ) : 'Nessuna nota.',
		),
	);
}, 10, 3 );

// dopo un aggiornamento, rileggi subito la release
add_action( 'upgrader_process_complete', function () {
	delete_site_transient( 'assistentino_release' );
} );
