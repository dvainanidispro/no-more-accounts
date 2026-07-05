<?php
/**
 * Βοηθητικές συναρτήσεις του plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Επιστρέφει true αν το option είναι ενεργοποιημένο.
 *
 * Τα options αποθηκεύονται ως '1' / '0' ώστε αργότερα να μπορούν
 * να διαβαστούν εύκολα και από τα SQL triggers.
 *
 * @param string $option Όνομα option (π.χ. 'nma_prevent_users').
 * @return bool
 */
function nma_option_enabled( $option ) {
	return '1' === get_option( $option, '0' );
}

/**
 * Επιστρέφει true αν η αποτροπή δημιουργίας χρηστών είναι πραγματικά ενεργή:
 * το option ενεργοποιημένο ΚΑΙ το trigger σωστά εγκατεστημένο στη βάση.
 *
 * @return bool
 */
function nma_user_protection_active() {
	return nma_option_enabled( 'nma_prevent_users' ) && 'ok' === nma_user_trigger_status();
}

/**
 * Ελέγχει αν ο χρήστης της βάσης δεδομένων έχει δικαίωμα δημιουργίας triggers.
 *
 * Διαβάζει τα grants του τρέχοντος MySQL χρήστη και ψάχνει για
 * TRIGGER ή ALL PRIVILEGES είτε καθολικά (*.*) είτε στην τρέχουσα βάση.
 *
 * @return bool|null true = έχει δικαίωμα, false = δεν έχει, null = δεν ήταν δυνατός ο έλεγχος.
 */
function nma_db_can_create_triggers() {
	global $wpdb;

	$grants = $wpdb->get_col( 'SHOW GRANTS FOR CURRENT_USER()' );

	if ( empty( $grants ) ) {
		return null;
	}

	foreach ( $grants as $grant ) {
		if ( ! preg_match( '/^GRANT\s+(.+?)\s+ON\s+(.+?)\s+TO\s+/i', $grant, $matches ) ) {
			continue;
		}

		$privileges = strtoupper( $matches[1] );
		$scope      = trim( $matches[2] );

		if ( false === strpos( $privileges, 'ALL PRIVILEGES' ) && ! preg_match( '/\bTRIGGER\b/', $privileges ) ) {
			continue;
		}

		// Καθολικό δικαίωμα σε όλες τις βάσεις.
		if ( '*.*' === $scope ) {
			return true;
		}

		// Δικαίωμα σε συγκεκριμένη βάση, π.χ. `local`.* (πιθανόν με wildcards % και _).
		if ( preg_match( '/^`(.+)`\.\*$/', $scope, $scope_matches ) ) {
			$db_pattern = str_replace( array( '\\_', '\\%' ), array( '_', '%' ), $scope_matches[1] );
			$regex      = '/^' . str_replace( array( '%', '_' ), array( '.*', '.' ), preg_quote( $db_pattern, '/' ) ) . '$/i';

			if ( preg_match( $regex, DB_NAME ) ) {
				return true;
			}
		}
	}

	return false;
}
