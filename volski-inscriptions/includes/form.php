<?php
/**
 * Formulaire public : shortcode [volski_inscription].
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'volski_inscription', 'volski_form_shortcode' );
add_action( 'template_redirect', 'volski_handle_submit' );
add_action( 'wp_enqueue_scripts', 'volski_register_assets' );

function volski_register_assets() {
	wp_register_style( 'volski', VOLSKI_URL . 'assets/volski.css', array(), VOLSKI_VERSION );
	wp_register_script( 'volski', VOLSKI_URL . 'assets/volski.js', array(), VOLSKI_VERSION, true );
}

/** Erreurs et valeurs du dernier envoi raté (affichées sans rechargement). */
$GLOBALS['volski_form_state'] = null;

function volski_handle_submit() {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['volski_submit'] ) ) {
		return;
	}
	$s = volski_settings();
	$state = array( 'values' => wp_unslash( $_POST ), 'errors' => array() );

	// Anti-robots : champ piège invisible rempli = robot.
	if ( ! empty( $_POST['volski_site_web'] ) ) {
		wp_safe_redirect( add_query_arg( 'volski', 'merci', wp_get_referer() ?: home_url( '/' ) ) . '#volski' );
		exit;
	}
	if ( ! isset( $_POST['volski_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['volski_nonce'] ), 'volski_inscription' ) ) {
		$state['errors']['_form'] = 'Le formulaire a expiré. Vérifie tes informations et renvoie-le.';
		$GLOBALS['volski_form_state'] = $state;
		return;
	}
	if ( empty( $s['ouvert'] ) ) {
		$state['errors']['_form'] = 'Les inscriptions sont fermées.';
		$GLOBALS['volski_form_state'] = $state;
		return;
	}

	list( $data, $errors ) = volski_validate( $_POST, true );
	if ( ! $errors ) {
		global $wpdb;
		$dupe = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . volski_table() . ' WHERE email = %s AND LOWER(nom) = LOWER(%s) AND LOWER(prenom) = LOWER(%s) LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$data['email'],
				$data['nom'],
				$data['prenom']
			)
		);
		if ( $dupe ) {
			$errors['_form'] = 'Tu es déjà inscrit(e) avec cette adresse e-mail. Pour modifier ton inscription, contacte l\'organisateur.';
		}
	}
	if ( $errors ) {
		$state['errors']              = $errors;
		$GLOBALS['volski_form_state'] = $state;
		return;
	}

	global $wpdb;
	$row = array_merge(
		$data,
		array(
			'created_at' => current_time( 'mysql' ),
			'paiement'   => 'a_payer',
			'note_admin' => '',
		)
	);
	$ok = $wpdb->insert( volski_table(), $row );
	if ( ! $ok ) {
		$state['errors']['_form']     = 'L\'inscription n\'a pas pu être enregistrée. Réessaie dans un instant.';
		$GLOBALS['volski_form_state'] = $state;
		return;
	}
	$row['id'] = $wpdb->insert_id;
	volski_send_emails( $row );

	$back = remove_query_arg( 'volski', wp_get_referer() ?: get_permalink() );
	wp_safe_redirect( add_query_arg( 'volski', 'merci', $back ) . '#volski' );
	exit;
}

function volski_form_shortcode() {
	wp_enqueue_style( 'volski' );
	wp_enqueue_script( 'volski' );
	$s = volski_settings();

	ob_start();
	echo '<div class="volski" id="volski">';

	if ( isset( $_GET['volski'] ) && 'merci' === $_GET['volski'] ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="volski-notice volski-ok" role="status"><strong>Inscription enregistrée.</strong> ';
		echo 'Un e-mail de confirmation vient de t\'être envoyé. Nous te recontacterons pour finaliser le paiement.</div>';
		echo '</div>';
		return ob_get_clean();
	}
	if ( empty( $s['ouvert'] ) ) {
		echo '<div class="volski-notice">Les inscriptions sont fermées pour le moment.</div></div>';
		return ob_get_clean();
	}

	$st     = $GLOBALS['volski_form_state'];
	$v      = $st ? $st['values'] : array();
	$e      = $st ? $st['errors'] : array();
	$val    = function ( $k ) use ( $v ) {
		return isset( $v[ $k ] ) && is_string( $v[ $k ] ) ? $v[ $k ] : '';
	};
	$err    = function ( $k ) use ( $e ) {
		return isset( $e[ $k ] ) ? '<p class="volski-err" id="volski-err-' . esc_attr( $k ) . '">' . esc_html( $e[ $k ] ) . '</p>' : '';
	};
	$aria   = function ( $k ) use ( $e ) {
		return isset( $e[ $k ] ) ? ' aria-invalid="true" aria-describedby="volski-err-' . esc_attr( $k ) . '"' : '';
	};
	$cats_v = isset( $v['categories'] ) && is_array( $v['categories'] ) ? $v['categories'] : array();

	if ( $e ) {
		echo '<div class="volski-notice volski-bad" role="alert">';
		echo isset( $e['_form'] ) ? esc_html( $e['_form'] ) : 'Il manque quelques informations : vérifie les champs en rouge.';
		echo '</div>';
	}
	?>
	<form method="post" class="volski-form" id="volski-form" novalidate>
		<?php wp_nonce_field( 'volski_inscription', 'volski_nonce' ); ?>
		<input type="hidden" name="volski_submit" value="1">
		<div class="volski-hp" aria-hidden="true"><label>Ne pas remplir <input type="text" name="volski_site_web" tabindex="-1" autocomplete="off"></label></div>

		<fieldset>
			<legend>Toi</legend>
			<div class="volski-row">
				<p class="volski-field"><label for="volski-prenom">Prénom *</label><input id="volski-prenom" name="prenom" autocomplete="given-name" required value="<?php echo esc_attr( $val( 'prenom' ) ); ?>"<?php echo $aria( 'prenom' ); // phpcs:ignore ?>><?php echo $err( 'prenom' ); // phpcs:ignore ?></p>
				<p class="volski-field"><label for="volski-nom">Nom *</label><input id="volski-nom" name="nom" autocomplete="family-name" required value="<?php echo esc_attr( $val( 'nom' ) ); ?>"<?php echo $aria( 'nom' ); // phpcs:ignore ?>><?php echo $err( 'nom' ); // phpcs:ignore ?></p>
			</div>
			<div class="volski-row">
				<p class="volski-field"><label for="volski-email">E-mail *</label><input id="volski-email" type="email" name="email" autocomplete="email" required value="<?php echo esc_attr( $val( 'email' ) ); ?>"<?php echo $aria( 'email' ); // phpcs:ignore ?>><?php echo $err( 'email' ); // phpcs:ignore ?></p>
				<p class="volski-field"><label for="volski-tel">Téléphone *</label><input id="volski-tel" type="tel" name="telephone" autocomplete="tel" required value="<?php echo esc_attr( $val( 'telephone' ) ); ?>"<?php echo $aria( 'telephone' ); // phpcs:ignore ?>><?php echo $err( 'telephone' ); // phpcs:ignore ?></p>
			</div>
			<div class="volski-field" role="radiogroup" aria-labelledby="volski-sexe-l">
				<span class="volski-label" id="volski-sexe-l">Sexe *</span>
				<div class="volski-choices">
				<?php foreach ( volski_sexes() as $k => $label ) : ?>
					<label class="volski-choice"><input type="radio" name="sexe" value="<?php echo esc_attr( $k ); ?>" <?php checked( $val( 'sexe' ), $k ); ?>> <?php echo esc_html( $label ); ?></label>
				<?php endforeach; ?>
				</div>
				<?php echo $err( 'sexe' ); // phpcs:ignore ?>
			</div>
		</fieldset>

		<fieldset>
			<legend>Catégorie *</legend>
			<p class="volski-help">Une seule catégorie par pilote.</p>
			<div class="volski-choices" role="radiogroup" aria-label="Catégorie">
			<?php foreach ( volski_categories() as $k => $label ) : ?>
				<?php $px = volski_prix_categorie( $k, $s ); ?>
				<label class="volski-choice"><input type="radio" name="categories[]" value="<?php echo esc_attr( $k ); ?>" <?php checked( isset( $cats_v[0] ) && $cats_v[0] === $k ); ?> data-volski-cat="<?php echo esc_attr( $k ); ?>" data-passager="<?php echo volski_cat_passager( $k ) ? '1' : '0'; ?>" data-prix="<?php echo esc_attr( (string) (float) $px ); ?>"> <?php echo esc_html( $label ); ?><?php if ( null !== $px ) : ?> <span class="volski-price"><?php echo esc_html( volski_money( $px ) ); ?></span><?php endif; ?></label>
			<?php endforeach; ?>
			</div>
			<?php echo $err( 'categories' ); // phpcs:ignore ?>
			<p class="volski-field" data-volski-passager><label for="volski-passager">Nom du passager (si tu le connais)</label><input id="volski-passager" name="passager" value="<?php echo esc_attr( $val( 'passager' ) ); ?>"></p>
		</fieldset>

		<fieldset>
			<legend>Contact d'urgence</legend>
			<div class="volski-row">
				<p class="volski-field"><label for="volski-urg-nom">Nom de la personne *</label><input id="volski-urg-nom" name="urgence_nom" required value="<?php echo esc_attr( $val( 'urgence_nom' ) ); ?>"<?php echo $aria( 'urgence_nom' ); // phpcs:ignore ?>><?php echo $err( 'urgence_nom' ); // phpcs:ignore ?></p>
				<p class="volski-field"><label for="volski-urg-tel">Son téléphone *</label><input id="volski-urg-tel" type="tel" name="urgence_tel" required value="<?php echo esc_attr( $val( 'urgence_tel' ) ); ?>"<?php echo $aria( 'urgence_tel' ); // phpcs:ignore ?>><?php echo $err( 'urgence_tel' ); // phpcs:ignore ?></p>
			</div>
		</fieldset>

		<?php if ( empty( $GLOBALS['volski_cards_outside'] ) && volski_options( $s ) ) : ?>
		<fieldset>
			<legend>Tarifs</legend>
			<?php echo volski_tarif_cards( $s, isset( $v['opt'] ) && is_array( $v['opt'] ) ? $v['opt'] : array(), true ); // phpcs:ignore ?>
		</fieldset>
		<?php endif; ?>

		<?php if ( volski_has_prices( $s ) ) : ?>
		<div class="volski-total">
			<span>Total à régler</span>
			<strong data-volski-total data-currency="<?php echo esc_attr( volski_currency() ); ?>">0 <?php echo esc_html( volski_currency() ); ?></strong>
			<small>Pas de paiement en ligne : l'organisation te recontacte pour finaliser le règlement.</small>
		</div>
		<?php endif; ?>

		<p class="volski-field"><label for="volski-com">Commentaire</label><textarea id="volski-com" name="commentaire" rows="3"><?php echo esc_textarea( $val( 'commentaire' ) ); ?></textarea></p>

		<div class="volski-consent">
			<?php if ( ! empty( $s['liste_publique'] ) ) : ?>
			<label class="volski-check"><input type="checkbox" name="afficher_public" value="1" <?php checked( ! empty( $v['afficher_public'] ) ); ?>> J'accepte que mon nom et ma catégorie apparaissent dans la liste publique des inscrits.</label>
			<?php endif; ?>
			<label class="volski-check"><input type="checkbox" name="rgpd" value="1" <?php checked( ! empty( $v['rgpd'] ) ); ?>> J'accepte que ces informations soient utilisées par l'organisation pour gérer la compétition<?php if ( $s['reglement_url'] ) : ?> et j'ai lu <a href="<?php echo esc_url( $s['reglement_url'] ); ?>" target="_blank" rel="noopener">le règlement</a><?php endif; ?>. *</label>
			<?php echo $err( 'rgpd' ); // phpcs:ignore ?>
		</div>

		<p><button type="submit" class="volski-btn">Valider mon inscription</button></p>
	</form>
	</div>
	<?php
	return ob_get_clean();
}

/** Vrai si au moins une catégorie ou une option a un prix. */
function volski_has_prices( $s = null ) {
	$s = $s ?: volski_settings();
	foreach ( volski_categories() as $k => $l ) {
		if ( volski_prix_categorie( $k, $s ) ) {
			return true;
		}
	}
	foreach ( volski_options( $s ) as $o ) {
		if ( $o['prix'] > 0 ) {
			return true;
		}
	}
	return false;
}

/**
 * Cartes de tarifs sélectionnables (Inscription, Souper, Abonnement télécabine…).
 * $interactive = false : simples cartes d'information (formulaire fermé ou déjà envoyé).
 * Les cases portent form="volski-form" : elles comptent dans le formulaire même placées ailleurs sur la page.
 */
function volski_tarif_cards( $s, $values = array(), $interactive = true ) {
	$opts = volski_options( $s );
	if ( ! $opts ) {
		return '';
	}
	$sent = ! empty( $values );
	ob_start();
	echo '<div class="volski-tarifs">';
	foreach ( $opts as $i => $o ) {
		$q        = isset( $values[ $i ] ) ? min( absint( $values[ $i ] ), $o['max'] ) : 0;
		$q        = $o['oblig'] ? max( 1, $q ) : $q;
		$price    = volski_option_price_label( $o, true );
		$req_attr = $o['requires'] ? ' data-volski-requires="' . esc_attr( $o['requires'] ) . '"' : '';
		$common   = ' form="volski-form" data-volski-opt="' . esc_attr( $o['label'] ) . '" data-prix="' . esc_attr( (string) $o['prix'] ) . '"';
		$classes  = 'volski-tcard' . ( $o['oblig'] ? ' is-oblig' : '' ) . ( $interactive ? '' : ' is-static' );
		if ( ! $interactive ) {
			echo '<div class="' . esc_attr( $classes ) . '"' . $req_attr . '><span class="volski-tcard-name">' . esc_html( $o['label'] ) . '</span>' . ( $price ? '<strong class="volski-tcard-price">' . esc_html( $price ) . '</strong>' : '' ) . ( $o['oblig'] ? '<span class="volski-tcard-state">Inclus</span>' : '' ) . '</div>'; // phpcs:ignore
			continue;
		}
		if ( 1 === $o['max'] ) {
			echo '<label class="' . esc_attr( $classes ) . '"' . $req_attr . '>'; // phpcs:ignore
			echo '<input type="checkbox" class="volski-tcard-input" name="opt[' . (int) $i . ']" value="1"' . $common . checked( $q > 0, true, false ) . ( $o['oblig'] ? ' disabled' : '' ) . '>'; // phpcs:ignore
			echo '<span class="volski-tcard-name">' . esc_html( $o['label'] ) . '</span>';
			echo $price ? '<strong class="volski-tcard-price">' . esc_html( $price ) . '</strong>' : '';
			echo '<span class="volski-tcard-state" aria-hidden="true" data-on="' . ( $o['oblig'] ? 'Inclus' : 'Choisi ✓' ) . '" data-off="Ajouter"></span>';
			if ( $o['requires'] ) {
				echo '<small class="volski-tcard-note">Avec « ' . esc_html( $o['requires'] ) . ' »</small>';
			}
			echo '</label>';
		} else {
			echo '<div class="' . esc_attr( $classes ) . ' is-qty"' . $req_attr . '>'; // phpcs:ignore
			echo '<label class="volski-tcard-name" for="volski-opt-' . (int) $i . '">' . esc_html( $o['label'] ) . '</label>';
			echo $price ? '<strong class="volski-tcard-price">' . esc_html( $price ) . '</strong>' : '';
			echo '<input id="volski-opt-' . (int) $i . '" class="volski-qty" type="number" inputmode="numeric" min="' . ( $o['oblig'] ? 1 : 0 ) . '" max="' . (int) $o['max'] . '" name="opt[' . (int) $i . ']" value="' . (int) $q . '"' . $common . '>'; // phpcs:ignore
			echo '</div>';
		}
	}
	echo '</div>';
	unset( $sent );
	return ob_get_clean();
}

/** Encadré du total (mis à jour en direct). */
function volski_total_box() {
	return '<div class="volski-total volski-total-big"><span>Total</span><strong data-volski-total data-currency="' . esc_attr( volski_currency() ) . '">0 ' . esc_html( volski_currency() ) . '</strong><small>Pas de paiement en ligne : l\'organisation te recontacte après l\'inscription pour finaliser le règlement.</small></div>';
}
