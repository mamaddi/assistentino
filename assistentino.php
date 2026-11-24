<?php
/**
 * Plugin Name:       Assistentino
 * Description:       Espone a Colibri, in sola lettura e con una chiave, lo stato del sito: versioni di WordPress e PHP, tema, plugin installati e aggiornamenti disponibili.
 * Version:           1.0.3
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Maddalena
 * Text Domain:       assistentino
 * Update URI:        https://github.com/mamaddi/assistentino
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ASSISTENTINO_VERSION', '1.0.3' );
define( 'ASSISTENTINO_FILE', __FILE__ );

// Repository GitHub da cui arrivano gli aggiornamenti ("utente/repository").
// Va cambiato anche "Update URI" qui sopra, con lo stesso valore.
define( 'ASSISTENTINO_GITHUB_REPO', 'mamaddi/assistentino' );

require_once __DIR__ . '/includes/stato.php';
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/updater.php';
