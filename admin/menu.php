<?php
/**
 * Δημιουργία admin menu και link ρυθμίσεων στη σελίδα των plugins.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once NMA_PLUGIN_DIR . 'admin/pages/settings.php';

/**
 * Προσθέτει τη σελίδα ρυθμίσεων στο μενού των Χρηστών.
 */
function nma_admin_menu() {
	$hook = add_users_page(
		'No more accounts',
		'No more accounts',
		'manage_options',
		'no-more-accounts',
		'nma_settings_page_render'
	);

	// Αποθήκευση της φόρμας πριν την εμφάνιση της σελίδας (pattern POST → redirect → GET).
	add_action( 'load-' . $hook, 'nma_settings_page_save' );
}
add_action( 'admin_menu', 'nma_admin_menu' );

/**
 * Προσθέτει link "Ρυθμίσεις" στη σελίδα των plugins.
 *
 * @param array $links Υπάρχοντα links του plugin.
 * @return array
 */
function nma_plugin_action_links( $links ) {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'users.php?page=no-more-accounts' ) ),
		'Ρυθμίσεις'
	);

	array_unshift( $links, $settings_link );

	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( NMA_PLUGIN_FILE ), 'nma_plugin_action_links' );
