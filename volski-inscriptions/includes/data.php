<?php
/**
 * Données : table des inscriptions, réglages, listes de valeurs.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Nom complet de la table des inscriptions. */
function volski_table() {
	global $wpdb;
	return $wpdb->prefix . 'volski_inscriptions';
}

/** Catégories proposées (clé => libellé). Un participant peut en cocher plusieurs. */
function volski_categories() {
	return apply_filters(
		'volski_categories',
		array(
			'solo'     => 'Solo',
			'biplace'  => 'Biplace',
			'parakite' => 'Parakite',
		)
	);
}

/** Sexe (clé => libellé). */
function volski_sexes() {
	return array(
		'homme' => 'Homme',
		'femme' => 'Femme',
	);
}

/** Statuts de paiement (clé => libellé). */
function volski_paiements() {
	return array(
		'a_payer' => 'À payer',
		'paye'    => 'Payé',
	);
}

/** Réglages avec leurs valeurs par défaut. */
function volski_settings() {
	$defaults = array(
		'competition'     => 'Compétition Vol & Ski',
		'infos'           => '',
		'ouvert'          => 1,
		'email_orga'      => get_option( 'admin_email' ),
		'message_confirm' => "Merci pour ton inscription !\nNous te recontacterons prochainement pour finaliser le paiement.",
		'reglement_url'   => '',
		'liste_publique'  => 1,
	);
	$saved = get_option( 'volski_settings', array() );
	return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
}

/** Libellés lisibles des catégories stockées ("solo,biplace" => "Solo, Biplace"). */
function volski_categories_label( $stored ) {
	$all = volski_categories();
	$out = array();
	foreach ( array_filter( explode( ',', (string) $stored ) ) as $key ) {
		$out[] = isset( $all[ $key ] ) ? $all[ $key ] : $key;
	}
	return implode( ', ', $out );
}

/** Récupère une inscription par son id. */
function volski_get( $id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . volski_table() . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

/**
 * Nettoie et valide les champs d'une inscription.
 *
 * @param array $raw    Données brutes (formulaire public ou admin).
 * @param bool  $public true pour le formulaire public (consentements obligatoires).
 * @return array [ données propres, erreurs (champ => message) ]
 */
function volski_validate( $raw, $public = true ) {
	$raw  = wp_unslash( $raw );
	$text = function ( $key, $max ) use ( $raw ) {
		return isset( $raw[ $key ] ) ? mb_substr( sanitize_text_field( $raw[ $key ] ), 0, $max ) : '';
	};

	$cats = isset( $raw['categories'] ) && is_array( $raw['categories'] ) ? array_map( 'sanitize_key', $raw['categories'] ) : array();
	$cats = array_values( array_intersect( array_keys( volski_categories() ), $cats ) );

	$data = array(
		'prenom'         => $text( 'prenom', 100 ),
		'nom'            => $text( 'nom', 100 ),
		'email'          => isset( $raw['email'] ) ? sanitize_email( $raw['email'] ) : '',
		'telephone'      => $text( 'telephone', 40 ),
		'sexe'           => isset( $raw['sexe'] ) ? sanitize_key( $raw['sexe'] ) : '',
		'categories'     => implode( ',', $cats ),
		'passager'       => in_array( 'biplace', $cats, true ) ? $text( 'passager', 150 ) : '',
		'urgence_nom'    => $text( 'urgence_nom', 150 ),
		'urgence_tel'    => $text( 'urgence_tel', 40 ),
		'commentaire'    => isset( $raw['commentaire'] ) ? mb_substr( sanitize_textarea_field( $raw['commentaire'] ), 0, 1000 ) : '',
		'afficher_public' => ! empty( $raw['afficher_public'] ) ? 1 : 0,
	);

	$errors = array();
	if ( '' === $data['prenom'] ) {
		$errors['prenom'] = 'Indique ton prénom.';
	}
	if ( '' === $data['nom'] ) {
		$errors['nom'] = 'Indique ton nom.';
	}
	if ( ! is_email( $data['email'] ) ) {
		$errors['email'] = 'Cette adresse e-mail n\'est pas valide.';
	}
	if ( strlen( preg_replace( '/\D/', '', $data['telephone'] ) ) < 6 ) {
		$errors['telephone'] = 'Indique un numéro de téléphone valide.';
	}
	if ( ! isset( volski_sexes()[ $data['sexe'] ] ) ) {
		$errors['sexe'] = 'Choisis Homme ou Femme.';
	}
	if ( ! $cats ) {
		$errors['categories'] = 'Choisis au moins une catégorie : Solo, Biplace ou Parakite.';
	}
	if ( '' === $data['urgence_nom'] ) {
		$errors['urgence_nom'] = 'Indique une personne à prévenir en cas d\'accident.';
	}
	if ( strlen( preg_replace( '/\D/', '', $data['urgence_tel'] ) ) < 6 ) {
		$errors['urgence_tel'] = 'Indique le téléphone de cette personne.';
	}
	if ( $public && empty( $raw['rgpd'] ) ) {
		$errors['rgpd'] = 'Coche cette case pour valider ton inscription.';
	}
	return array( $data, $errors );
}
