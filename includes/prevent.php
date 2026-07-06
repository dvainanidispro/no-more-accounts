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
 * Φιλτράρει τα REST endpoints χρηστών όταν το option nma_prevent_api_users
 * είναι ενεργό.
 *
 * - Μη συνδεδεμένοι: αφαιρούνται ΟΛΕΣ οι διαδρομές /wp/v2/users* (GET και
 *   POST). Το endpoint φαίνεται εντελώς ανύπαρκτο (αυθεντικό rest_no_route
 *   404, ούτε στο discovery) και κλείνει και το user enumeration μέσω του
 *   ανώνυμου GET (λίστα συντακτών δημοσιευμένων άρθρων).
 * - Συνδεδεμένοι: αφαιρείται μόνο το POST (δημιουργία), ώστε να συνεχίσουν
 *   να δουλεύουν όσα admin features διαβάζουν χρήστες μέσω REST
 *   (π.χ. το dropdown συντάκτη του Gutenberg).
 *
 * Προστασία σε επίπεδο WordPress (ανεξάρτητη από το SQL trigger).
 *
 * @param array $endpoints Τα καταχωρημένα REST endpoints.
 * @return array
 */
function nma_filter_rest_user_endpoints( $endpoints ) {
	if ( ! nma_option_enabled( 'nma_prevent_api_users' ) ) {
		return $endpoints;
	}

	if ( ! is_user_logged_in() ) {
		foreach ( array_keys( $endpoints ) as $route ) {
			if ( '/wp/v2/users' === $route || 0 === strpos( $route, '/wp/v2/users/' ) ) {
				unset( $endpoints[ $route ] );
			}
		}

		return $endpoints;
	}

	if ( empty( $endpoints['/wp/v2/users'] ) || ! is_array( $endpoints['/wp/v2/users'] ) ) {
		return $endpoints;
	}

	foreach ( $endpoints['/wp/v2/users'] as $index => $handler ) {
		if ( ! is_int( $index ) || ! isset( $handler['methods'] ) ) {
			continue;
		}

		// Το 'methods' μπορεί να είναι string ('POST' ή 'GET, POST') ή array.
		$methods = $handler['methods'];
		if ( is_string( $methods ) ) {
			$methods = array_map( 'trim', explode( ',', $methods ) );
		}

		if ( in_array( 'POST', (array) $methods, true ) ) {
			unset( $endpoints['/wp/v2/users'][ $index ] );
		}
	}

	return $endpoints;
}
add_filter( 'rest_endpoints', 'nma_filter_rest_user_endpoints' );

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
