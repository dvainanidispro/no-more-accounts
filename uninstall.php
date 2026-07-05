<?php
/**
 * Εκτελείται κατά την απεγκατάσταση (διαγραφή) του plugin.
 *
 * Αφαιρεί τα triggers από τη βάση και διαγράφει τα options.
 */

// Αποτροπή άμεσης πρόσβασης - το αρχείο τρέχει μόνο από το WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/triggers.php';

nma_drop_triggers();

delete_option( 'nma_prevent_users' );
delete_option( 'nma_prevent_admins' );
