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

/**
 * Catégories définies dans « Infos compétition » : liste de [ key, label, prix, passager ].
 * Sans réglage enregistré : Solo normal, Parakite, Biplace (en reprenant les anciens prix).
 */
function volski_category_rows( $s = null ) {
	$s    = $s ?: volski_settings();
	$rows = isset( $s['categories'] ) && is_array( $s['categories'] ) ? $s['categories'] : array();
	if ( ! $rows ) {
		$old  = isset( $s['prix'] ) && is_array( $s['prix'] ) ? $s['prix'] : array();
		$rows = array(
			array( 'key' => 'solo', 'label' => 'Solo normal', 'prix' => $old['solo'] ?? '', 'passager' => 0 ),
			array( 'key' => 'parakite', 'label' => 'Parakite', 'prix' => $old['parakite'] ?? '', 'passager' => 0 ),
			array( 'key' => 'biplace', 'label' => 'Biplace', 'prix' => $old['biplace'] ?? '', 'passager' => 1 ),
		);
	}
	$out = array();
	foreach ( $rows as $r ) {
		if ( ! empty( $r['key'] ) && ! empty( $r['label'] ) ) {
			$out[] = array(
				'key'      => (string) $r['key'],
				'label'    => (string) $r['label'],
				'prix'     => (string) ( $r['prix'] ?? '' ),
				'passager' => empty( $r['passager'] ) ? 0 : 1,
			);
		}
	}
	return $out;
}

/** Catégories proposées (clé => libellé). Chaque pilote en choisit une seule. */
function volski_categories() {
	$out = array();
	foreach ( volski_category_rows() as $r ) {
		$out[ $r['key'] ] = $r['label'];
	}
	return apply_filters( 'volski_categories', $out );
}

/** Vrai si la catégorie demande le nom d'un passager (ex. Biplace). */
function volski_cat_passager( $key ) {
	foreach ( volski_category_rows() as $r ) {
		if ( $r['key'] === $key ) {
			return (bool) $r['passager'];
		}
	}
	return false;
}

/** Sexe (clé => libellé). */
function volski_sexes() {
	return array(
		'homme' => 'Homme',
		'femme' => 'Femme',
	);
}

/** Moyens de paiement (clé => libellé). */
function volski_modes() {
	return apply_filters(
		'volski_modes',
		array(
			'virement' => 'Virement',
			'especes'  => 'Espèces',
			'cheque'   => 'Chèque',
			'carte'    => 'Carte',
			'twint'    => 'Twint',
			'autre'    => 'Autre',
		)
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
		'date_txt'        => '',
		'lieu'            => '',
		'adresse'         => '',
		'image_url'       => '',
		'presentation'    => '',
		'programme'       => '',
		'infos'           => '',
		'contact'         => '',
		'prix'            => array(),
		'categories'      => array(),
		'tarif_note'      => '',
		'options'         => array(
			array( 'label' => 'Inscription', 'prix' => '35', 'max' => 1, 'requires' => '', 'oblig' => 1 ),
			array( 'label' => 'Souper', 'prix' => '40', 'max' => 1, 'requires' => '', 'oblig' => 0 ),
			array( 'label' => '+1 au souper (accompagnant)', 'prix' => '40', 'max' => 1, 'requires' => 'Souper', 'oblig' => 0 ),
			array( 'label' => 'Abonnement télécabine', 'prix' => '', 'max' => 1, 'requires' => '', 'oblig' => 0 ),
		),
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
		$out[] = isset( $all[ $key ] ) ? $all[ $key ] : ucfirst( str_replace( array( '-', '_' ), ' ', $key ) );
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
		'passager'       => ( $cats && volski_cat_passager( $cats[0] ) ) ? $text( 'passager', 150 ) : '',
		'urgence_nom'    => $text( 'urgence_nom', 150 ),
		'urgence_tel'    => $text( 'urgence_tel', 40 ),
		'commentaire'    => isset( $raw['commentaire'] ) ? mb_substr( sanitize_textarea_field( $raw['commentaire'] ), 0, 1000 ) : '',
		'afficher_public' => ! empty( $raw['afficher_public'] ) ? 1 : 0,
	);
	$picked                  = volski_pick_options( isset( $raw['opt'] ) && is_array( $raw['opt'] ) ? $raw['opt'] : array() );
	$data['options_choisies'] = wp_json_encode( $picked );
	$data['total']            = volski_total( $data['categories'], $picked );

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
	// Une seule catégorie par pilote : Solo normal, Parakite ou Biplace.
	if ( 1 !== count( $cats ) ) {
		$errors['categories'] = 'Choisis ta catégorie : ' . implode( ', ', volski_categories() ) . ' (une seule).';
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


/** Prix d'une catégorie (float), ou null si aucun prix n'est fixé. */
function volski_prix_categorie( $key, $s = null ) {
	foreach ( volski_category_rows( $s ) as $r ) {
		if ( $r['key'] === $key ) {
			$v = volski_parse_amount( $r['prix'] );
			return '' === $v ? null : (float) $v;
		}
	}
	return null;
}

/** Options proposées : liste de [ label, prix (float), max (int) ]. */
function volski_options( $s = null ) {
	$s   = $s ?: volski_settings();
	$out = array();
	foreach ( (array) $s['options'] as $o ) {
		if ( ! empty( $o['label'] ) ) {
			$amt   = volski_parse_amount( $o['prix'] ?? '' );
			$out[] = array(
				'label'    => (string) $o['label'],
				'prix'     => (float) $amt,
				'has_prix' => '' !== $amt,
				'max'      => max( 1, (int) ( $o['max'] ?? 1 ) ),
				'requires' => (string) ( $o['requires'] ?? '' ),
				'oblig'    => ! empty( $o['oblig'] ),
			);
		}
	}
	return $out;
}

/** Devise affichée (modifiable par le filtre « volski_currency »). */
function volski_currency() {
	return apply_filters( 'volski_currency', 'CHF' );
}

/** Montant lisible, format suisse : 45 CHF, 45.50 CHF, 1'200 CHF. */
function volski_money( $v ) {
	$v = (float) $v;
	return ( floor( $v ) == $v ? number_format( $v, 0, '.', "'" ) : number_format( $v, 2, '.', "'" ) ) . ' ' . volski_currency(); // phpcs:ignore Universal.Operators.StrictComparisons
}

/** Lit un montant saisi : « 45 », « 45.50 », « 45,50 », « CHF 45.– », « 1'200 ». '' si vide. */
function volski_parse_amount( $raw ) {
	$s = strtoupper( trim( (string) $raw ) );
	$s = str_replace( array( 'CHF', 'FR.', 'FR', '€', "'", '’', ' ', "\u{00A0}", '.–', '.-', '–' ), '', $s );
	if ( '' === $s ) {
		return '';
	}
	return max( 0, round( (float) str_replace( ',', '.', $s ), 2 ) );
}

/**
 * Options choisies (quantités envoyées par le formulaire, indexées comme volski_options()).
 * Retourne la liste figée [ label, prix, qty ] : le prix reste celui du jour de l'inscription.
 */
function volski_pick_options( $raw ) {
	$opts  = volski_options();
	$qty   = array();
	foreach ( $opts as $i => $o ) {
		$qty[ $i ] = min( isset( $raw[ $i ] ) ? absint( $raw[ $i ] ) : 0, $o['max'] );
		if ( $o['oblig'] ) {
			$qty[ $i ] = max( 1, $qty[ $i ] ); // Produit obligatoire (ex. Inscription) : toujours compté.
		}
	}
	// Une option qui dépend d'une autre (ex. « +1 au souper ») n'est gardée que si l'autre est choisie.
	$labels = array();
	foreach ( $opts as $i => $o ) {
		if ( $qty[ $i ] > 0 ) {
			$labels[ $o['label'] ] = true;
		}
	}
	$picked = array();
	foreach ( $opts as $i => $o ) {
		if ( $qty[ $i ] > 0 && ( '' === $o['requires'] || isset( $labels[ $o['requires'] ] ) ) ) {
			$picked[] = array( 'label' => $o['label'], 'prix' => $o['prix'], 'qty' => $qty[ $i ] );
		}
	}
	return $picked;
}

/** Total : prix de la catégorie + options. */
function volski_total( $categories_csv, $picked ) {
	$t = 0.0;
	foreach ( array_filter( explode( ',', (string) $categories_csv ) ) as $k ) {
		$t += (float) volski_prix_categorie( $k );
	}
	foreach ( (array) $picked as $o ) {
		$t += (float) $o['prix'] * (int) $o['qty'];
	}
	return round( $t, 2 );
}

/** Options stockées (JSON) en texte : « Repas ×2, T-shirt ». */
function volski_options_label( $json ) {
	$list = json_decode( (string) $json, true );
	$out  = array();
	foreach ( (array) $list as $o ) {
		$out[] = $o['label'] . ( (int) $o['qty'] > 1 ? ' ×' . (int) $o['qty'] : '' );
	}
	return implode( ', ', $out );
}

/** Prix d'une option à afficher : « 15 CHF », « Gratuit », ou rien si aucun prix n'est fixé. */
function volski_option_price_label( $o, $unit = false ) {
	if ( empty( $o['has_prix'] ) ) {
		return '';
	}
	if ( $o['prix'] <= 0 ) {
		return 'Gratuit';
	}
	return volski_money( $o['prix'] ) . ( $unit && $o['max'] > 1 ? ' / unité' : '' );
}
