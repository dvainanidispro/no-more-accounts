<?php
/**
 * Καθολικός handler αποτροπής δημιουργίας χρηστών σε επίπεδο WordPress.
 *
 * Όλοι οι δρόμοι δημιουργίας χρήστη (admin, REST API, wp_create_user,
 * εγγραφή, WooCommerce κ.λπ.) περνούν από την wp_insert_user(). Το filter
 * wp_pre_insert_user_data τρέχει εκεί, λίγο πριν το INSERT: αν επιστρέψουμε
 * κενά δεδομένα, ο core σταματά μόνος του με το δικό του γενικό σφάλμα
 * WP_Error('empty_data', 'Not enough data to create this user.') —
 * κοινό, αόριστο μήνυμα που δεν αποκαλύπτει τίποτα για το plugin.
 *
 * Το SQL trigger παραμένει η τελική γραμμή άμυνας για ό,τι δεν περνάει
 * καθόλου από το WordPress (π.χ. απευθείας SQL).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Αδειάζει τα δεδομένα εισαγωγής νέου χρήστη όταν η προστασία είναι ενεργή.
 *
 * @param array $data   Τα δεδομένα προς εισαγωγή στον πίνακα χρηστών.
 * @param bool  $update true αν πρόκειται για ενημέρωση υπάρχοντος χρήστη.
 * @return array
 */
function nma_block_user_insert_data( $data, $update ) {
	// Οι ενημερώσεις υπαρχόντων χρηστών επιτρέπονται πάντα.
	if ( $update ) {
		return $data;
	}

	if ( nma_user_protection_active() ) {
		return array();
	}

	return $data;
}
// Μεγάλο priority ώστε να τρέχει τελευταίο και να μην μπορεί
// άλλο filter να ξαναγεμίσει τα δεδομένα μετά από εμάς.
add_filter( 'wp_pre_insert_user_data', 'nma_block_user_insert_data', 999, 2 );

/**
 * Αποτρέπει την αποστολή των emails ειδοποίησης νέου χρήστη όταν ο χρήστης
 * δεν δημιουργήθηκε.
 *
 * Ο core (edit_user) πυροδοτεί το action ειδοποιήσεων ακόμα και όταν η
 * wp_insert_user() επιστρέψει WP_Error, οπότε η wp_new_user_notification()
 * καταλήγει με $user = false και παράγει PHP warnings. Τα δύο αυτά filters
 * τρέχουν πριν από τα προβληματικά σημεία και ακυρώνουν την αποστολή.
 *
 * @param bool  $send Αν θα σταλεί το email.
 * @param mixed $user Το αντικείμενο χρήστη (false αν δεν υπάρχει χρήστης).
 * @return bool
 */
function nma_skip_notifications_for_missing_user( $send, $user ) {
	if ( ! $user instanceof WP_User || empty( $user->ID ) ) {
		return false;
	}

	return $send;
}
add_filter( 'wp_send_new_user_notification_to_admin', 'nma_skip_notifications_for_missing_user', 10, 2 );
add_filter( 'wp_send_new_user_notification_to_user', 'nma_skip_notifications_for_missing_user', 10, 2 );
