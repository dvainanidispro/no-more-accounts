<?php
/**
 * Plugin Name:       No more accounts
 * Description:       Αποτρέπει τη δημιουργία νέων χρηστών σε επίπεδο βάσης δεδομένων.
 * Version:           0.4.2
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Computer Studio
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       no-more-accounts
 */

// Αποτροπή άμεσης πρόσβασης στο αρχείο.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Βασικές σταθερές του plugin.
define( 'NMA_VERSION', '0.4.2' );
define( 'NMA_PLUGIN_FILE', __FILE__ );
define( 'NMA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NMA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Includes.
require_once NMA_PLUGIN_DIR . 'includes/install.php';
require_once NMA_PLUGIN_DIR . 'includes/helpers.php';
require_once NMA_PLUGIN_DIR . 'includes/triggers.php';
require_once NMA_PLUGIN_DIR . 'includes/prevent.php';

// Admin.
if ( is_admin() ) {
	require_once NMA_PLUGIN_DIR . 'admin/menu.php';
	require_once NMA_PLUGIN_DIR . 'admin/notices.php';
}

// Activation / deactivation hooks.
register_activation_hook( __FILE__, 'nma_install' );
register_deactivation_hook( __FILE__, 'nma_deactivate' );
