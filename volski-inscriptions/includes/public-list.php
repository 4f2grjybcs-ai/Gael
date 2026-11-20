<?php
/**
 * Liste publique des inscrits : shortcode [volski_inscrits].
 * N'affiche que les personnes qui l'ont accepté, sans leurs coordonnées.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'volski_inscrits', 'volski_public_list' );

function volski_public_list() {
	$s = volski_settings();
	if ( empty( $s['liste_publique'] ) ) {
		return '';
	}
	wp_enqueue_style( 'volski' );
	global $wpdb;
	$t     = volski_table();
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" ); // phpcs:ignore
	$rows  = $wpdb->get_results( "SELECT prenom, nom, sexe, categories FROM $t WHERE afficher_public = 1 ORDER BY nom, prenom", ARRAY_A ); // phpcs:ignore

	$counts = array_fill_keys( array_keys( volski_categories() ), 0 );
	foreach ( $wpdb->get_col( "SELECT categories FROM $t" ) as $c ) { // phpcs:ignore
		foreach ( array_filter( explode( ',', $c ) ) as $k ) {
			if ( isset( $counts[ $k ] ) ) {
				$counts[ $k ]++;
			}
		}
	}

	ob_start();
	echo '<div class="volski volski-list">';
	echo '<p class="volski-count"><strong>' . esc_html( $total ) . '</strong> inscrit' . ( $total > 1 ? 's' : '' );
	$parts = array();
	foreach ( volski_categories() as $k => $label ) {
		$parts[] = esc_html( $label ) . ' : ' . (int) $counts[ $k ];
	}
	echo ' · ' . implode( ' · ', $parts ) . '</p>'; // phpcs:ignore
	if ( $rows ) {
		echo '<div class="volski-table-wrap"><table class="volski-table"><thead><tr><th>Nom</th><th>Catégorie(s)</th><th></th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr><td>' . esc_html( $r['prenom'] . ' ' . $r['nom'] ) . '</td><td>' . esc_html( volski_categories_label( $r['categories'] ) ) . '</td><td>' . esc_html( volski_sexes()[ $r['sexe'] ] ?? '' ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
		if ( count( $rows ) < $total ) {
			echo '<p class="volski-help">Seuls les inscrits qui l\'ont accepté apparaissent dans cette liste.</p>';
		}
	} else {
		echo '<p class="volski-help">La liste des inscrits sera affichée ici.</p>';
	}
	echo '</div>';
	return ob_get_clean();
}
