<?php
/**
 * Διαχείριση των SQL triggers του plugin.
 *
 * Το trigger είναι στατικό και προκαθορισμένο: δεν αλλάζει ποτέ.
 * Η συμπεριφορά ελέγχεται μόνο από τα options που διαβάζει.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Όνομα του trigger για τον πίνακα χρηστών.
 *
 * Περιέχει το prefix ώστε να μην συγκρούεται με άλλη εγκατάσταση
 * WordPress που μοιράζεται την ίδια βάση με διαφορετικό prefix.
 *
 * @return string
 */
function nma_user_trigger_name() {
	global $wpdb;

	return $wpdb->base_prefix . 'nma_before_insert_user';
}

/**
 * Το SQL δημιουργίας του trigger για τον πίνακα χρηστών.
 *
 * Το trigger διαβάζει το option nma_prevent_users σε κάθε INSERT.
 * Αν το option λείπει, η σύγκριση με NULL αποτυγχάνει και το INSERT
 * επιτρέπεται (fail-open).
 *
 * @return string
 */
function nma_user_trigger_sql() {
	global $wpdb;

	$trigger_name  = nma_user_trigger_name();
	$options_table = $wpdb->base_prefix . 'options';

	return "CREATE TRIGGER `{$trigger_name}`
BEFORE INSERT ON `{$wpdb->users}`
FOR EACH ROW
IF (SELECT option_value FROM `{$options_table}`
    WHERE option_name = 'nma_prevent_users' LIMIT 1) = '1'
THEN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Unknown error';
END IF";
}

/**
 * Δημιουργεί (ή επιδιορθώνει) τα triggers του plugin.
 *
 * Κάνει πρώτα drop τυχόν υπάρχον trigger ώστε η λειτουργία
 * να είναι idempotent (εγκατάσταση = επιδιόρθωση).
 *
 * @return true|WP_Error
 */
function nma_create_triggers() {
	global $wpdb;

	$wpdb->query( 'DROP TRIGGER IF EXISTS `' . nma_user_trigger_name() . '`' );

	$result = $wpdb->query( nma_user_trigger_sql() );

	if ( false === $result ) {
		$error = $wpdb->last_error ? $wpdb->last_error : 'Άγνωστο σφάλμα βάσης δεδομένων.';

		return new WP_Error( 'nma_trigger_create_failed', $error );
	}

	return true;
}

/**
 * Αφαιρεί τα triggers του plugin από τη βάση.
 *
 * @return true|WP_Error
 */
function nma_drop_triggers() {
	global $wpdb;

	$result = $wpdb->query( 'DROP TRIGGER IF EXISTS `' . nma_user_trigger_name() . '`' );

	if ( false === $result ) {
		$error = $wpdb->last_error ? $wpdb->last_error : 'Άγνωστο σφάλμα βάσης δεδομένων.';

		return new WP_Error( 'nma_trigger_drop_failed', $error );
	}

	return true;
}

/**
 * Ελέγχει την κατάσταση του trigger του πίνακα χρηστών.
 *
 * Διαβάζει το information_schema και επαληθεύει ότι το trigger
 * υπάρχει, είναι BEFORE INSERT στον σωστό πίνακα και ότι το σώμα του
 * περιέχει τον αναμενόμενο έλεγχο.
 *
 * @return string 'ok' = εγκατεστημένο σωστά, 'missing' = δεν υπάρχει,
 *                'different' = υπάρχει αλλά διαφέρει από το αναμενόμενο.
 */
function nma_user_trigger_status() {
	global $wpdb;

	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT ACTION_STATEMENT FROM information_schema.TRIGGERS
			 WHERE TRIGGER_SCHEMA = DATABASE()
			   AND TRIGGER_NAME = %s
			   AND ACTION_TIMING = 'BEFORE'
			   AND EVENT_MANIPULATION = 'INSERT'
			   AND EVENT_OBJECT_TABLE = %s",
			nma_user_trigger_name(),
			$wpdb->users
		)
	);

	if ( ! $row ) {
		return 'missing';
	}

	if ( false === strpos( $row->ACTION_STATEMENT, "'nma_prevent_users'" )
		|| false === stripos( $row->ACTION_STATEMENT, 'SIGNAL' )
		|| false === strpos( $row->ACTION_STATEMENT, "'Unknown error'" ) ) {
		return 'different';
	}

	return 'ok';
}
