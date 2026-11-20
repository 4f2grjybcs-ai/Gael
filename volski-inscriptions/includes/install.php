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
	if ( false === get_option( 'volski_settings' ) ) {
		add_option( 'volski_settings', volski_settings() );
	}
}

function volski_maybe_upgrade() {
	if ( get_option( 'volski_db_version' ) !== VOLSKI_DB_VERSION ) {
		volski_install_table();
		volski_install_roles();
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
			PRIMARY KEY  (id),
			KEY email (email)
		) $charset;"
	);
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
