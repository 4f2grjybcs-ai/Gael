<?php
/**
 * Comptes du comité : connexion simple, accès limité à l'extension.
 * Un compte qui n'a que le rôle « Comité Vol & Ski » ne voit que les écrans Vol & Ski
 * (et son profil, pour changer son mot de passe). Tout le reste du site lui est fermé.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vrai pour un membre du comité qui n'a aucun autre droit d'édition sur le site
 * (rôle « Comité Vol & Ski », éventuellement avec un rôle client ou abonné à côté).
 */
function volski_is_comite_only( $user = null ) {
	$user = $user ?: wp_get_current_user();
	if ( ! $user || ! $user->exists() ) {
		return false;
	}
	return user_can( $user, VOLSKI_CAP ) && ! user_can( $user, 'edit_posts' ) && ! user_can( $user, 'manage_options' );
}

// Répare le rôle s'il a disparu ou perdu son droit (mise à jour par téléversement, extension de rôles…).
add_action(
	'init',
	function () {
		$role  = get_role( VOLSKI_ROLE );
		$admin = get_role( 'administrator' );
		if ( ! $role || ! $role->has_cap( VOLSKI_CAP ) || ( $admin && ! $admin->has_cap( VOLSKI_CAP ) ) ) {
			volski_install_roles();
		}
	}
);

// WooCommerce et certaines extensions renvoient hors de l'administration les comptes qui ne sont pas « staff ».
add_filter(
	'woocommerce_prevent_admin_access',
	function ( $prevent ) {
		return volski_is_comite_only() ? false : $prevent;
	}
);

// Après la connexion : directement sur les inscriptions.
add_filter(
	'login_redirect',
	function ( $to, $requested, $user ) {
		if ( $user instanceof WP_User && volski_is_comite_only( $user ) ) {
			return volski_comite_page_url() ?: admin_url( 'admin.php?page=volski' );
		}
		return $to;
	},
	10,
	3
);

// Bloque tous les autres écrans d'administration.
add_action(
	'admin_init',
	function () {
		if ( ! volski_is_comite_only() || wp_doing_ajax() ) {
			return;
		}
		global $pagenow;
		$page    = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$allowed = ( 'admin.php' === $pagenow && 0 === strpos( $page, 'volski' ) )
			|| 'admin-post.php' === $pagenow
			|| 'profile.php' === $pagenow;
		if ( ! $allowed ) {
			wp_safe_redirect( admin_url( 'admin.php?page=volski' ) );
			exit;
		}
	},
	1
);

// Pages que WordPress refuse déjà (articles, réglages, extensions…) : retour aux inscriptions plutôt qu'un message d'erreur.
add_action(
	'admin_page_access_denied',
	function () {
		if ( ! volski_is_comite_only() ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( 0 !== strpos( $page, 'volski' ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=volski' ) );
			exit;
		}
		// Déjà sur une page Vol & Ski refusée : pas de boucle, on propose l'espace comité du site.
		$front = volski_comite_page_url();
		wp_die(
			'Cet écran n\'est pas accessible avec ce compte.' . ( $front ? ' <a href="' . esc_url( $front ) . '">Ouvrir l\'espace comité</a>' : '' ),
			'Accès limité',
			array( 'response' => 403 )
		);
	}
);

// Menu réduit à Vol & Ski + Profil.
add_action(
	'admin_menu',
	function () {
		if ( ! volski_is_comite_only() ) {
			return;
		}
		global $menu;
		foreach ( (array) $menu as $item ) {
			if ( isset( $item[2] ) && ! in_array( $item[2], array( 'volski', 'profile.php' ), true ) ) {
				remove_menu_page( $item[2] );
			}
		}
	},
	999
);

// Barre du haut épurée, et masquée sur le site public.
add_action(
	'admin_bar_menu',
	function ( $bar ) {
		if ( ! volski_is_comite_only() ) {
			return;
		}
		foreach ( array( 'wp-logo', 'comments', 'new-content', 'updates', 'customize', 'search', 'dashboard', 'appearance' ) as $id ) {
			$bar->remove_node( $id );
		}
	},
	999
);
add_filter(
	'show_admin_bar',
	function ( $show ) {
		return volski_is_comite_only() ? false : $show;
	}
);

// Profil simplifié : pas de couleurs d'admin ni de raccourcis clavier.
add_action(
	'admin_head-profile.php',
	function () {
		if ( volski_is_comite_only() ) {
			remove_action( 'admin_color_scheme_picker', 'admin_color_scheme_picker' );
		}
	}
);

// Message d'accueil sur la page de connexion quand on vient d'un lien du comité.
add_filter(
	'login_message',
	function ( $msg ) {
		if ( isset( $_GET['volski'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$s    = volski_settings();
			$msg .= '<p class="message">Espace comité – ' . esc_html( $s['competition'] ) . '</p>';
		}
		return $msg;
	}
);
