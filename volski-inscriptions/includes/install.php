<?php
/**
 * Création et mise à jour de la table.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function volski_activate() {
	volski_install_table();
	volski_install_roles();
	volski_ensure_comite_page();
	if ( false === get_option( 'volski_settings' ) ) {
		add_option( 'volski_settings', volski_settings() );
	}
}

function volski_maybe_upgrade() {
	if ( get_option( 'volski_db_version' ) !== VOLSKI_DB_VERSION ) {
		volski_install_table();
		volski_install_roles();
		add_action( 'init', 'volski_ensure_comite_page' );
	}
}

function volski_install_table() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();
	$table   = volski_table();
	dbDelta(
		"CREATE TABLE $table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			prenom varchar(100) NOT NULL DEFAULT '',
			nom varchar(100) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL DEFAULT '',
			telephone varchar(40) NOT NULL DEFAULT '',
			sexe varchar(10) NOT NULL DEFAULT '',
			categories varchar(100) NOT NULL DEFAULT '',
			passager varchar(150) NOT NULL DEFAULT '',
			urgence_nom varchar(150) NOT NULL DEFAULT '',
			urgence_tel varchar(40) NOT NULL DEFAULT '',
			commentaire text NOT NULL,
			afficher_public tinyint(1) NOT NULL DEFAULT 0,
			paiement varchar(20) NOT NULL DEFAULT 'a_payer',
			note_admin text NOT NULL,
			options_choisies text NOT NULL,
			total decimal(10,2) NOT NULL DEFAULT 0,
			paiement_mode varchar(30) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY email (email)
		) $charset;"
	);
	volski_add_missing_columns();
	update_option( 'volski_db_version', VOLSKI_DB_VERSION );
}

/** Rôle « Comité Vol & Ski » et droit de gérer les inscriptions (donné aussi aux administrateurs). */
function volski_install_roles() {
	add_role( VOLSKI_ROLE, 'Comité Vol & Ski', array( 'read' => true, VOLSKI_CAP => true ) );
	$admin = get_role( 'administrator' );
	if ( $admin ) {
		$admin->add_cap( VOLSKI_CAP );
	}
}

/**
 * Filet de sécurité pour les mises à jour : ajoute les colonnes que dbDelta n'aurait pas créées
 * (certains hébergements ou bases SQLite).
 */
function volski_add_missing_columns() {
	global $wpdb;
	$t    = volski_table();
	$cols = array(
		'options_choisies' => 'text NOT NULL DEFAULT \'\'',
		'total'            => array( 'decimal(10,2) NOT NULL DEFAULT 0', 'float NOT NULL DEFAULT 0' ),
		'paiement_mode'    => 'varchar(30) NOT NULL DEFAULT \'\'',
	);
	$show = $wpdb->suppress_errors( true );
	foreach ( $cols as $col => $def ) {
		$wpdb->query( "SELECT $col FROM $t LIMIT 1" ); // phpcs:ignore
		if ( ! $wpdb->last_error ) {
			continue;
		}
		foreach ( (array) $def as $try ) {
			$wpdb->last_error = '';
			$wpdb->query( "ALTER TABLE $t ADD COLUMN $col $try" ); // phpcs:ignore
			if ( ! $wpdb->last_error ) {
				break;
			}
		}
	}
	$wpdb->suppress_errors( $show );
}
