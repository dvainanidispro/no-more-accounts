<?php
/**
 * Σελίδα ρυθμίσεων του plugin (Χρήστες → No more accounts).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Αποθηκεύει ειδοποίηση για εμφάνιση μετά το redirect.
 *
 * @param string $type    'success' ή 'error'.
 * @param string $message Το μήνυμα.
 */
function nma_set_settings_notice( $type, $message ) {
	set_transient( 'nma_settings_notice_' . get_current_user_id(), array(
		'type'    => $type,
		'message' => $message,
	), 60 );
}

/**
 * Χειρίζεται τις υποβολές της σελίδας (ρυθμίσεις και triggers).
 *
 * Τρέχει στο load-{page_hook}, δηλαδή πριν αρχίσει το output της σελίδας,
 * ώστε μετά την ενέργεια να γίνεται redirect (POST → redirect → GET).
 */
function nma_settings_page_save() {
	if ( empty( $_POST ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Δεν έχετε δικαίωμα πρόσβασης σε αυτή τη σελίδα.' );
	}

	$redirect = admin_url( 'users.php?page=no-more-accounts' );

	// Εγκατάσταση / επιδιόρθωση των triggers.
	if ( isset( $_POST['nma_install_triggers'] ) ) {
		check_admin_referer( 'nma_manage_triggers' );

		$result = nma_create_triggers();

		if ( is_wp_error( $result ) ) {
			nma_set_settings_notice( 'error', 'Η εγκατάσταση του trigger απέτυχε: ' . $result->get_error_message() );
		} else {
			nma_set_settings_notice( 'success', 'Το trigger εγκαταστάθηκε στη βάση δεδομένων.' );
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	// Αφαίρεση των triggers.
	if ( isset( $_POST['nma_remove_triggers'] ) ) {
		check_admin_referer( 'nma_manage_triggers' );

		$result = nma_drop_triggers();

		if ( is_wp_error( $result ) ) {
			nma_set_settings_notice( 'error', 'Η αφαίρεση του trigger απέτυχε: ' . $result->get_error_message() );
		} else {
			nma_set_settings_notice( 'success', 'Το trigger αφαιρέθηκε από τη βάση δεδομένων.' );
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	// Αποθήκευση ρυθμίσεων.
	if ( isset( $_POST['nma_settings_submit'] ) ) {
		check_admin_referer( 'nma_save_settings' );

		update_option( 'nma_prevent_api_users', isset( $_POST['nma_prevent_api_users'] ) ? '1' : '0' );

		// Το radio "nma_prevent_mode" της φόρμας μεταφράζεται στα δύο
		// υπάρχοντα options — ο υπόλοιπος κώδικας δεν αλλάζει.
		$mode = isset( $_POST['nma_prevent_mode'] ) ? sanitize_key( wp_unslash( $_POST['nma_prevent_mode'] ) ) : 'none';
		if ( ! in_array( $mode, array( 'none', 'users', 'admins' ), true ) ) {
			$mode = 'none';
		}

		update_option( 'nma_prevent_users', 'users' === $mode ? '1' : '0' );
		update_option( 'nma_prevent_admins', 'admins' === $mode ? '1' : '0' );

		nma_set_settings_notice( 'success', 'Οι ρυθμίσεις αποθηκεύτηκαν.' );

		wp_safe_redirect( $redirect );
		exit;
	}
}

/**
 * Εμφανίζει τη σελίδα ρυθμίσεων.
 */
function nma_settings_page_render() {
	// Δημιουργία των options με προεπιλογή "ανενεργό" αν λείπουν
	// (π.χ. αν το plugin δεν εγκαταστάθηκε μέσω activation hook).
	add_option( 'nma_prevent_api_users', '0' );
	add_option( 'nma_prevent_users', '0' );
	add_option( 'nma_prevent_admins', '0' );

	$prevent_api    = nma_option_enabled( 'nma_prevent_api_users' );
	$prevent_users  = nma_option_enabled( 'nma_prevent_users' );
	$prevent_admins = nma_option_enabled( 'nma_prevent_admins' );
	$can_triggers   = nma_db_can_create_triggers();
	$trigger_status = nma_user_trigger_status();

	// Η τρέχουσα επιλογή του radio, από τις τιμές των δύο options.
	$prevent_mode = 'none';
	if ( $prevent_users ) {
		$prevent_mode = 'users';
	} elseif ( $prevent_admins ) {
		$prevent_mode = 'admins';
	}

	$notice = get_transient( 'nma_settings_notice_' . get_current_user_id() );
	if ( $notice ) {
		delete_transient( 'nma_settings_notice_' . get_current_user_id() );
	}
	?>
	<div class="wrap">
		<h1>No more accounts</h1>

		<?php if ( $notice ) : ?>
			<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
				<p><?php echo esc_html( $notice['message'] ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( $prevent_users && 'ok' !== $trigger_status ) : ?>
			<div class="notice notice-warning">
				<p>
					<strong>Προσοχή:</strong> Η «Αποτροπή νέων χρηστών» είναι ενεργή, αλλά το trigger
					δεν είναι εγκατεστημένο σωστά στη βάση — η προστασία <strong>δεν</strong> ισχύει.
					Πατήστε «Εγκατάσταση / επιδιόρθωση trigger» παρακάτω.
				</p>
			</div>
		<?php endif; ?>

		<h2>Κατάσταση βάσης δεδομένων</h2>
		<table class="widefat striped" style="max-width: 700px;">
			<tbody>
				<tr>
					<td>Δικαίωμα δημιουργίας triggers (TRIGGER privilege)</td>
					<td>
						<?php if ( true === $can_triggers ) : ?>
							<span style="color: #00a32a; font-weight: 600;">✔ Ο χρήστης της βάσης έχει δικαίωμα δημιουργίας triggers.</span>
						<?php elseif ( false === $can_triggers ) : ?>
							<span style="color: #d63638; font-weight: 600;">✘ Ο χρήστης της βάσης ΔΕΝ έχει δικαίωμα δημιουργίας triggers.</span>
						<?php else : ?>
							<span style="color: #dba617; font-weight: 600;">– Δεν ήταν δυνατός ο έλεγχος δικαιωμάτων.</span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td>Trigger πίνακα χρηστών (<code><?php echo esc_html( nma_user_trigger_name() ); ?></code>)</td>
					<td>
						<?php if ( 'ok' === $trigger_status ) : ?>
							<span style="color: #00a32a; font-weight: 600;">✔ Εγκατεστημένο.</span>
						<?php elseif ( 'different' === $trigger_status ) : ?>
							<span style="color: #dba617; font-weight: 600;">! Υπάρχει, αλλά διαφέρει από το αναμενόμενο — προτείνεται επιδιόρθωση.</span>
						<?php else : ?>
							<span style="color: #d63638; font-weight: 600;">✘ Δεν είναι εγκατεστημένο.</span>
						<?php endif; ?>
						<form method="post" action="" style="margin-top: 8px;">
							<?php wp_nonce_field( 'nma_manage_triggers' ); ?>
							<input type="submit" name="nma_install_triggers" class="button button-primary button-small" value="Εγκατάσταση / επιδιόρθωση trigger" />
							<?php if ( 'missing' !== $trigger_status ) : ?>
								<input type="submit" name="nma_remove_triggers" class="button button-small" value="Αφαίρεση trigger"
									onclick="return confirm('Να αφαιρεθεί το trigger από τη βάση; Η αποτροπή δημιουργίας χρηστών θα πάψει να ισχύει.');" />
							<?php endif; ?>
						</form>
					</td>
				</tr>
				<tr>
					<td>Βάση δεδομένων</td>
					<td><code><?php echo esc_html( DB_NAME ); ?></code></td>
				</tr>
			</tbody>
		</table>

		<?php if ( false === $can_triggers ) : ?>
			<div class="notice notice-error inline" style="max-width: 660px; margin-top: 12px;">
				<p>
					Χωρίς το δικαίωμα <code>TRIGGER</code> το plugin δεν θα μπορέσει να εγκαταστήσει
					τα triggers στη βάση. Δώστε το δικαίωμα στον χρήστη της βάσης, π.χ.:
					<code>GRANT TRIGGER ON `<?php echo esc_html( DB_NAME ); ?>`.* TO '<?php echo esc_html( DB_USER ); ?>'@'...';</code>
				</p>
			</div>
		<?php endif; ?>

		<h2>Trigger βάσης δεδομένων</h2>
		<p>
			Το trigger μπλοκάρει νέες εγγραφές στον πίνακα
			χρηστών μόνο όταν η επιλογή «Αποτροπή νέων χρηστών» είναι ενεργή.
			Η εγκατάσταση είναι ασφαλής να επαναληφθεί (λειτουργεί και ως επιδιόρθωση).
		</p>
		<details style="max-width: 700px; margin-bottom: 12px;">
			<summary>Προβολή SQL του trigger που εγκαθιστά αυτό το πρόσθετο</summary>
			<pre style="background: #f6f7f7; padding: 12px; overflow-x: auto;"><?php echo esc_html( nma_user_trigger_sql() ); ?></pre>
		</details>

		<h2>Ρυθμίσεις</h2>
		<form method="post" action="">
			<?php wp_nonce_field( 'nma_save_settings' ); ?>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">Αποτροπή νέων χρηστών μέσω REST API</th>
						<td>
							<label for="nma_prevent_api_users">
								<input type="checkbox" name="nma_prevent_api_users" id="nma_prevent_api_users" value="1" <?php checked( $prevent_api ); ?> />
								Αποτροπή δημιουργίας νέων χρηστών μέσω του REST API του WordPress
								και πλήρης απόκρυψη του endpoint χρηστών για μη συνδεδεμένους χρήστες (προτείνεται).
							</label>
							<p class="description">
								Για μη συνδεδεμένους, όλο το endpoint χρηστών (<code>/wp/v2/users</code>) εμφανίζεται
								ανύπαρκτο — αποτρέπεται έτσι και το user enumeration. Για συνδεδεμένους αφαιρείται
								μόνο η δημιουργία (<code>POST</code>), ώστε να λειτουργεί κανονικά το wp-admin.
								Προστασία σε επίπεδο WordPress — δεν απαιτεί το trigger της βάσης.
								Σημείωση: το endpoint εμφανίζεται ανύπαρκτο και σε συνδεδεμένους χρήστες που το
								ανοίγουν απευθείας στον browser — ορατό παραμένει μόνο για εξουσιοδοτημένα αιτήματα
								του wp-admin (π.χ. Gutenberg) ή με Application Password.
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Αποτροπή δημιουργίας χρηστών σε επίπεδο βάσης δεδομένων (απαιτεί το trigger της βάσης)</th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><span>Αποτροπή δημιουργίας χρηστών σε επίπεδο βάσης δεδομένων</span></legend>
								<label>
									<input type="radio" name="nma_prevent_mode" value="none" <?php checked( 'none', $prevent_mode ); ?> />
									Επιτρέπεται η δημιουργία χρηστών
								</label><br />
								<label>
									<input type="radio" name="nma_prevent_mode" value="users" <?php checked( 'users', $prevent_mode ); ?> />
									Αποτροπή δημιουργίας νέων χρηστών (οποιουδήποτε ρόλου)
								</label><br />
								<label>
									<input type="radio" name="nma_prevent_mode" value="admins" <?php checked( 'admins', $prevent_mode ); ?> />
									Αποτροπή δημιουργίας νέων administrators
								</label>
								<p class="description">Η αποτροπή δημιουργίας νέων administrators δεν έχει υλοποιηθεί ακόμα.</p>
							</fieldset>
						</td>
					</tr>
				</tbody>
			</table>

			<p class="submit">
				<input type="submit" name="nma_settings_submit" class="button button-primary" value="Αποθήκευση ρυθμίσεων" />
			</p>
		</form>
	</div>
	<?php
}
