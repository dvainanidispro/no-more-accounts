<?php
/**
 * Διαχείριση των SQL triggers του plugin.
 *
 * Τα triggers είναι στατικά και προκαθορισμένα: δεν αλλάζουν ποτέ.
 * Η συμπεριφορά ελέγχεται μόνο από τα options που διαβάζουν.
 *
 * - {prefix}nma_before_insert_user:     μπλοκάρει INSERT στον πίνακα χρηστών
 *                                       όταν το nma_prevent_users είναι '1'.
 * - {prefix}nma_before_insert_usermeta: μπλοκάρει INSERT γραμμής capabilities
 *                                       που περιέχει administrator όταν το
 *                                       nma_prevent_admins είναι '1'.
 * - {prefix}nma_before_update_usermeta: μπλοκάρει τη ΜΕΤΑΒΑΣΗ υπάρχουσας
 *                                       γραμμής capabilities σε administrator
 *                                       (privilege escalation) όταν το
 *                                       nma_prevent_admins είναι '1'.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Όνομα του trigger για τον πίνακα χρηστών.
 *
 * Τα ονόματα περιέχουν το prefix ώστε να μην συγκρούονται με άλλη
 * εγκατάσταση WordPress που μοιράζεται την ίδια βάση με άλλο prefix.
 *
 * @return string
 */
function nma_user_trigger_name() {
	global $wpdb;

	return $wpdb->base_prefix . 'nma_before_insert_user';
}

/**
 * Όνομα του INSERT trigger για τον πίνακα usermeta.
 *
 * @return string
 */
function nma_usermeta_insert_trigger_name() {
	global $wpdb;

	return $wpdb->base_prefix . 'nma_before_insert_usermeta';
}

/**
 * Όνομα του UPDATE trigger για τον πίνακα usermeta.
 *
 * @return string
 */
function nma_usermeta_update_trigger_name() {
	global $wpdb;

	return $wpdb->base_prefix . 'nma_before_update_usermeta';
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
 * Το SQL δημιουργίας του INSERT trigger για τον πίνακα usermeta.
 *
 * Το pattern '%\"administrator\";b:1%' ταιριάζει ακριβώς το κλειδί του
 * ρόλου μέσα στο serialized array (τα εισαγωγικά αποκλείουν π.χ. custom
 * ρόλο 'administrator_assistant') και μόνο όταν είναι ενεργός (b:1).
 * Ο έλεγχος του option γίνεται σε φωλιασμένο IF ώστε να εκτελείται μόνο
 * για γραμμές capabilities με administrator — όλες οι άλλες εγγραφές του
 * usermeta πληρώνουν μόνο μία σύγκριση string.
 *
 * @return string
 */
function nma_usermeta_insert_trigger_sql() {
	global $wpdb;

	$trigger_name  = nma_usermeta_insert_trigger_name();
	$options_table = $wpdb->base_prefix . 'options';
	$caps_key      = $wpdb->base_prefix . 'capabilities';

	return "CREATE TRIGGER `{$trigger_name}`
BEFORE INSERT ON `{$wpdb->usermeta}`
FOR EACH ROW
IF NEW.meta_key = '{$caps_key}'
   AND NEW.meta_value LIKE '%\"administrator\";b:1%'
THEN
    IF (SELECT option_value FROM `{$options_table}`
        WHERE option_name = 'nma_prevent_admins' LIMIT 1) = '1'
    THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Unknown error';
    END IF;
END IF";
}

/**
 * Το SQL δημιουργίας του UPDATE trigger για τον πίνακα usermeta.
 *
 * Μπλοκάρει μόνο τη μετάβαση ΣΕ administrator: η παλιά τιμή δεν τον
 * περιείχε και η νέα τον περιέχει. Έτσι οι ενημερώσεις capabilities
 * υπαρχόντων administrators δεν επηρεάζονται.
 *
 * @return string
 */
function nma_usermeta_update_trigger_sql() {
	global $wpdb;

	$trigger_name  = nma_usermeta_update_trigger_name();
	$options_table = $wpdb->base_prefix . 'options';
	$caps_key      = $wpdb->base_prefix . 'capabilities';

	return "CREATE TRIGGER `{$trigger_name}`
BEFORE UPDATE ON `{$wpdb->usermeta}`
FOR EACH ROW
IF NEW.meta_key = '{$caps_key}'
   AND NEW.meta_value LIKE '%\"administrator\";b:1%'
   AND (OLD.meta_value IS NULL OR OLD.meta_value NOT LIKE '%\"administrator\";b:1%')
THEN
    IF (SELECT option_value FROM `{$options_table}`
        WHERE option_name = 'nma_prevent_admins' LIMIT 1) = '1'
    THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Unknown error';
    END IF;
END IF";
}

/**
 * Όλα τα triggers του plugin (όνομα + SQL δημιουργίας).
 *
 * @return array[]
 */
function nma_trigger_definitions() {
	return array(
		array(
			'name' => nma_user_trigger_name(),
			'sql'  => nma_user_trigger_sql(),
		),
		array(
			'name' => nma_usermeta_insert_trigger_name(),
			'sql'  => nma_usermeta_insert_trigger_sql(),
		),
		array(
			'name' => nma_usermeta_update_trigger_name(),
			'sql'  => nma_usermeta_update_trigger_sql(),
		),
	);
}

/**
 * Δημιουργεί (ή επιδιορθώνει) όλα τα triggers του plugin.
 *
 * Κάνει πρώτα drop τυχόν υπάρχοντα ώστε η λειτουργία να είναι
 * idempotent (εγκατάσταση = επιδιόρθωση).
 *
 * @return true|WP_Error
 */
function nma_create_triggers() {
	global $wpdb;

	foreach ( nma_trigger_definitions() as $trigger ) {
		$wpdb->query( 'DROP TRIGGER IF EXISTS `' . $trigger['name'] . '`' );

		$result = $wpdb->query( $trigger['sql'] );

		if ( false === $result ) {
			$error = $wpdb->last_error ? $wpdb->last_error : 'Άγνωστο σφάλμα βάσης δεδομένων.';

			return new WP_Error(
				'nma_trigger_create_failed',
				'(' . $trigger['name'] . ') ' . $error
			);
		}
	}

	return true;
}

/**
 * Αφαιρεί όλα τα triggers του plugin από τη βάση.
 *
 * @return true|WP_Error
 */
function nma_drop_triggers() {
	global $wpdb;

	foreach ( nma_trigger_definitions() as $trigger ) {
		$result = $wpdb->query( 'DROP TRIGGER IF EXISTS `' . $trigger['name'] . '`' );

		if ( false === $result ) {
			$error = $wpdb->last_error ? $wpdb->last_error : 'Άγνωστο σφάλμα βάσης δεδομένων.';

			return new WP_Error(
				'nma_trigger_drop_failed',
				'(' . $trigger['name'] . ') ' . $error
			);
		}
	}

	return true;
}

/**
 * Γενικός έλεγχος κατάστασης ενός trigger μέσω του information_schema.
 *
 * @param string   $trigger_name Όνομα του trigger.
 * @param string   $table        Πίνακας στον οποίο πρέπει να είναι δεμένο.
 * @param string   $timing       'BEFORE' ή 'AFTER'.
 * @param string   $event        'INSERT', 'UPDATE' ή 'DELETE'.
 * @param string[] $needles      Τμήματα που πρέπει να περιέχει το σώμα του.
 * @return string 'ok' = εγκατεστημένο σωστά, 'missing' = δεν υπάρχει,
 *                'different' = υπάρχει αλλά διαφέρει από το αναμενόμενο.
 */
function nma_trigger_status_check( $trigger_name, $table, $timing, $event, $needles ) {
	global $wpdb;

	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT ACTION_STATEMENT FROM information_schema.TRIGGERS
			 WHERE TRIGGER_SCHEMA = DATABASE()
			   AND TRIGGER_NAME = %s
			   AND ACTION_TIMING = %s
			   AND EVENT_MANIPULATION = %s
			   AND EVENT_OBJECT_TABLE = %s",
			$trigger_name,
			$timing,
			$event,
			$table
		)
	);

	if ( ! $row ) {
		return 'missing';
	}

	foreach ( $needles as $needle ) {
		if ( false === stripos( $row->ACTION_STATEMENT, $needle ) ) {
			return 'different';
		}
	}

	return 'ok';
}

/**
 * Κατάσταση του trigger του πίνακα χρηστών.
 *
 * @return string 'ok' | 'missing' | 'different'
 */
function nma_user_trigger_status() {
	global $wpdb;

	return nma_trigger_status_check(
		nma_user_trigger_name(),
		$wpdb->users,
		'BEFORE',
		'INSERT',
		array( "'nma_prevent_users'", 'SIGNAL', "'Unknown error'" )
	);
}

/**
 * Κατάσταση του INSERT trigger του πίνακα usermeta.
 *
 * @return string 'ok' | 'missing' | 'different'
 */
function nma_usermeta_insert_trigger_status() {
	global $wpdb;

	return nma_trigger_status_check(
		nma_usermeta_insert_trigger_name(),
		$wpdb->usermeta,
		'BEFORE',
		'INSERT',
		array( "'nma_prevent_admins'", '"administrator";b:1', 'SIGNAL', "'Unknown error'" )
	);
}

/**
 * Κατάσταση του UPDATE trigger του πίνακα usermeta.
 *
 * @return string 'ok' | 'missing' | 'different'
 */
function nma_usermeta_update_trigger_status() {
	global $wpdb;

	return nma_trigger_status_check(
		nma_usermeta_update_trigger_name(),
		$wpdb->usermeta,
		'BEFORE',
		'UPDATE',
		array( "'nma_prevent_admins'", '"administrator";b:1', 'OLD.meta_value', 'SIGNAL', "'Unknown error'" )
	);
}
