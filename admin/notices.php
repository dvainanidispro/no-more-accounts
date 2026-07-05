<?php
/**
 * Admin notices του plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ενημερώνει τον διαχειριστή στη σελίδα "Προσθήκη χρήστη" ότι η δημιουργία
 * χρηστών είναι μπλοκαρισμένη, όταν η προστασία είναι πραγματικά ενεργή.
 *
 * Σκόπιμα δεν αναφέρεται το plugin. Αν η φόρμα υποβληθεί παρόλα αυτά,
 * ο χρήστης θα δει το γενικό σφάλμα του core ("Not enough data to create
 * this user.") από τον handler στο includes/prevent.php.
 */
function nma_user_new_admin_notice() {
	$screen = get_current_screen();

	if ( ! $screen || 'user' !== $screen->id ) {
		return;
	}

	if ( ! nma_user_protection_active() ) {
		return;
	}

	echo '<div class="notice notice-warning"><p><strong>Η δημιουργία χρηστών είναι μπλοκαρισμένη.</strong></p></div>';
}
add_action( 'admin_notices', 'nma_user_new_admin_notice' );
