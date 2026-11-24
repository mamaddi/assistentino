<?php
/**
 * Pagina Strumenti → Assistentino: genera, mostra una sola volta e revoca la chiave.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_management_page( 'Assistentino', 'Assistentino', 'manage_options', 'assistentino', 'assistentino_pagina' );
} );

add_filter( 'plugin_action_links_' . plugin_basename( ASSISTENTINO_FILE ), function ( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'tools.php?page=assistentino' ) ) . '">Chiave</a>' );
	return $links;
} );

// genera una chiave nuova (sostituisce la precedente)
add_action( 'admin_post_assistentino_genera', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Permesso negato.' );
	}
	check_admin_referer( 'assistentino_genera' );

	$key = bin2hex( random_bytes( 24 ) );
	update_option( 'assistentino_key_hash', hash( 'sha256', $key ), false );
	update_option( 'assistentino_key_date', current_time( 'mysql' ), false );
	// la chiave in chiaro resta 10 minuti, solo per chi l'ha generata, e si vede una volta
	set_transient( 'assistentino_key_' . get_current_user_id(), $key, 10 * MINUTE_IN_SECONDS );

	wp_safe_redirect( admin_url( 'tools.php?page=assistentino' ) );
	exit;
} );

// revoca: nessuna chiave valida, l'endpoint non risponde più
add_action( 'admin_post_assistentino_revoca', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Permesso negato.' );
	}
	check_admin_referer( 'assistentino_revoca' );

	delete_option( 'assistentino_key_hash' );
	delete_option( 'assistentino_key_date' );
	wp_safe_redirect( admin_url( 'tools.php?page=assistentino&revocata=1' ) );
	exit;
} );

function assistentino_pagina() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$tkey  = 'assistentino_key_' . get_current_user_id();
	$nuova = get_transient( $tkey );
	if ( $nuova ) {
		delete_transient( $tkey );
	}
	$ha_chiave = '' !== (string) get_option( 'assistentino_key_hash', '' );
	$data      = (string) get_option( 'assistentino_key_date', '' );
	$endpoint  = rest_url( 'assistentino/v1/stato' );
	?>
	<div class="wrap">
		<h1 style="display:flex;align-items:center;gap:.5rem"><img src="<?php echo esc_url( plugins_url( 'assets/icon.svg', ASSISTENTINO_FILE ) ); ?>" alt="" width="40" height="40" style="border-radius:8px">Assistentino</h1>
		<p>Permette a Colibri di leggere lo stato di questo sito: versioni di WordPress e PHP, tema, plugin installati e aggiornamenti disponibili. Non può modificare niente.</p>

		<?php if ( isset( $_GET['revocata'] ) ) : ?>
			<div class="notice notice-success"><p>Chiave revocata: Colibri non può più leggere questo sito.</p></div>
		<?php endif; ?>

		<?php if ( $nuova ) : ?>
			<div class="notice notice-warning">
				<p><strong>Copia la chiave adesso e incollala in Colibri, nella pagina di questo sito.</strong> Per sicurezza non verrà più mostrata.</p>
				<p><input type="text" class="large-text code" readonly value="<?php echo esc_attr( $nuova ); ?>" onclick="this.select()"></p>
			</div>
		<?php endif; ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">Indirizzo per Colibri</th>
				<td><code><?php echo esc_html( $endpoint ); ?></code></td>
			</tr>
			<tr>
				<th scope="row">Chiave</th>
				<td>
					<?php if ( $ha_chiave ) : ?>
						Attiva<?php echo $data ? ', creata il ' . esc_html( mysql2date( 'd/m/Y H:i', $data ) ) : ''; ?>.
					<?php else : ?>
						Nessuna chiave: Colibri non può leggere questo sito.
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-inline-end:1rem">
			<input type="hidden" name="action" value="assistentino_genera">
			<?php wp_nonce_field( 'assistentino_genera' ); ?>
			<?php submit_button( $ha_chiave ? 'Genera una chiave nuova' : 'Genera la chiave', 'primary', 'submit', false, $ha_chiave ? array( 'onclick' => "return confirm('La chiave attuale smetterà di funzionare. Continuare?')" ) : array() ); ?>
		</form>

		<?php if ( $ha_chiave ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block">
				<input type="hidden" name="action" value="assistentino_revoca">
				<?php wp_nonce_field( 'assistentino_revoca' ); ?>
				<?php submit_button( 'Revoca la chiave', 'delete', 'submit', false, array( 'onclick' => "return confirm('Colibri non potrà più leggere questo sito. Continuare?')" ) ); ?>
			</form>
		<?php endif; ?>
	</div>
	<?php
}
