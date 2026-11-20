<?php
/**
 * Suppression de l'extension (depuis Extensions → Supprimer) : efface la table et les réglages.
 * Une simple désactivation ne supprime rien.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'volski_inscriptions' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
delete_option( 'volski_settings' );
delete_option( 'volski_db_version' );
delete_option( 'volski_comite_page' );
delete_option( 'volski_liste_page' ); // La page « Espace comité » reste : supprime-la à la main si besoin.

// Les membres du comité perdent leur rôle (leurs comptes deviennent de simples abonnés).
foreach ( get_users( array( 'role' => 'volski_comite' ) ) as $u ) {
	$u->set_role( 'subscriber' );
}
remove_role( 'volski_comite' );
$admin = get_role( 'administrator' );
if ( $admin ) {
	$admin->remove_cap( 'volski_manage' );
}
