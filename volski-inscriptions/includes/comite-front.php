<?php
/**
 * Espace comité sur le site : shortcode [volski_comite].
 * Connexion simple (identifiant + mot de passe) puis tableau de suivi, export Excel et infos de la compétition,
 * sans passer par l'administration WordPress.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'volski_comite', 'volski_comite_shortcode' );
add_action( 'template_redirect', 'volski_comite_login' );
add_filter( 'wp_robots', 'volski_comite_noindex' );

/** Adresse de la page « Espace comité » (créée automatiquement à l'installation). */
function volski_comite_page_url() {
	$id = (int) get_option( 'volski_comite_page' );
	return $id && 'publish' === get_post_status( $id ) ? get_permalink( $id ) : '';
}

/** Crée la page « Espace comité » si elle n'existe pas encore. */
function volski_ensure_comite_page() {
	$id = (int) get_option( 'volski_comite_page' );
	if ( $id && get_post( $id ) && 'trash' !== get_post_status( $id ) ) {
		return;
	}
	$id = wp_insert_post(
		array(
			'post_title'   => 'Espace comité',
			'post_name'    => 'espace-comite',
			'post_content' => '<!-- wp:shortcode -->[volski_comite]<!-- /wp:shortcode -->',
			'post_status'  => 'publish',
			'post_type'    => 'page',
		)
	);
	if ( $id && ! is_wp_error( $id ) ) {
		update_option( 'volski_comite_page', (int) $id );
	}
}

function volski_comite_noindex( $robots ) {
	if ( is_singular() && (int) get_queried_object_id() === (int) get_option( 'volski_comite_page' ) ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
	}
	return $robots;
}

/** Traite le formulaire de connexion de l'espace comité. */
function volski_comite_login() {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['volski_login'] ) ) {
		return;
	}
	$user = wp_signon(
		array(
			'user_login'    => sanitize_text_field( wp_unslash( $_POST['log'] ?? '' ) ),
			'user_password' => (string) wp_unslash( $_POST['pwd'] ?? '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'remember'      => ! empty( $_POST['rememberme'] ),
		),
		is_ssl()
	);
	if ( is_wp_error( $user ) ) {
		$GLOBALS['volski_login_error'] = 'Identifiant ou mot de passe incorrect.';
		return;
	}
	$here = wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['volski_here'] ?? '' ) ), home_url( '/' ) );
	wp_safe_redirect( $here );
	exit;
}

/** Adresse actuelle de la page, sans les messages. */
function volski_comite_here() {
	$id = get_queried_object_id();
	return $id ? get_permalink( $id ) : home_url( '/' );
}

function volski_comite_shortcode() {
	wp_enqueue_style( 'volski' );
	wp_enqueue_style( 'volski-admin', VOLSKI_URL . 'assets/admin.css', array(), VOLSKI_VERSION );
	wp_enqueue_style( 'volski-comite', VOLSKI_URL . 'assets/comite.css', array( 'volski-admin' ), VOLSKI_VERSION );
	if ( ! get_option( 'volski_comite_page' ) && get_the_ID() ) {
		update_option( 'volski_comite_page', (int) get_the_ID() );
	}
	$s    = volski_settings();
	$here = volski_comite_here();
	ob_start();
	echo '<div class="volski volski-comite volski-admin">';

	if ( ! is_user_logged_in() ) {
		$err = $GLOBALS['volski_login_error'] ?? '';
		?>
		<div class="volski-login">
			<h2>Espace comité</h2>
			<p class="volski-help"><?php echo esc_html( $s['competition'] ); ?> – suivi des inscriptions</p>
			<?php if ( $err ) : ?><div class="volski-notice volski-bad" role="alert"><?php echo esc_html( $err ); ?></div><?php endif; ?>
			<form method="post" class="volski-form">
				<input type="hidden" name="volski_login" value="1">
				<input type="hidden" name="volski_here" value="<?php echo esc_url( $here ); ?>">
				<p class="volski-field"><label for="volski-log">Identifiant</label><input id="volski-log" name="log" autocomplete="username" autocapitalize="none" required value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_POST['log'] ?? '' ) ) ); // phpcs:ignore ?>"></p>
				<p class="volski-field"><label for="volski-pwd">Mot de passe</label><input id="volski-pwd" type="password" name="pwd" autocomplete="current-password" required></p>
				<label class="volski-check"><input type="checkbox" name="rememberme" value="1" checked> Rester connecté sur cet appareil</label>
				<p><button type="submit" class="volski-btn">Se connecter</button></p>
			</form>
		</div>
		<?php
		echo '</div>';
		return ob_get_clean();
	}

	if ( ! current_user_can( VOLSKI_CAP ) ) {
		echo '<div class="volski-notice volski-bad">Ce compte n\'a pas accès à l\'espace comité. <a href="' . esc_url( wp_logout_url( $here ) ) . '">Se déconnecter</a></div></div>';
		return ob_get_clean();
	}

	$tab  = isset( $_GET['vs'] ) && 'infos' === $_GET['vs'] ? 'infos' : 'suivi'; // phpcs:ignore WordPress.Security.NonceVerification
	$c    = volski_counts();
	$user = wp_get_current_user();
	?>
	<div class="volski-comite-bar">
		<strong>Espace comité</strong>
		<span><?php echo esc_html( $user->display_name ); ?> · <a href="<?php echo esc_url( wp_logout_url( $here ) ); ?>">Se déconnecter</a><?php if ( current_user_can( 'manage_options' ) ) : ?> · <a href="<?php echo esc_url( admin_url( 'admin.php?page=volski' ) ); ?>">Administration</a><?php endif; ?></span>
	</div>
	<div class="volski-stats">
		<div class="volski-stat volski-stat-main"><b><?php echo (int) $c['total']; ?></b><span>inscrit<?php echo $c['total'] > 1 ? 's' : ''; ?></span></div>
		<?php foreach ( volski_categories() as $k => $l ) : ?>
			<div class="volski-stat"><b><?php echo (int) ( $c[ $k ] ?? 0 ); ?></b><span><?php echo esc_html( $l ); ?></span></div>
		<?php endforeach; ?>
		<?php foreach ( volski_sexes() as $k => $l ) : ?>
			<div class="volski-stat"><b><?php echo (int) $c[ $k ]; ?></b><span><?php echo esc_html( $l . ( $c[ $k ] > 1 ? 's' : '' ) ); ?></span></div>
		<?php endforeach; ?>
		<?php foreach ( $c['opts'] as $label => $q ) : ?>
			<div class="volski-stat"><b><?php echo (int) $q; ?></b><span><?php echo esc_html( $label ); ?></span></div>
		<?php endforeach; ?>
		<div class="volski-stat volski-stat-pay"><b><?php echo (int) $c['paye']; ?><small> / <?php echo (int) $c['total']; ?></small></b><span>payés</span></div>
	</div>
	<nav class="volski-tabs" aria-label="Espace comité">
		<a href="<?php echo esc_url( add_query_arg( 'vs', 'suivi', $here ) ); ?>" <?php echo 'suivi' === $tab ? 'aria-current="page"' : ''; ?>>Tableau de suivi</a>
		<a href="<?php echo esc_url( add_query_arg( 'vs', 'infos', $here ) ); ?>" <?php echo 'infos' === $tab ? 'aria-current="page"' : ''; ?>>Infos compétition</a>
	</nav>
	<?php
	if ( 'infos' === $tab ) {
		volski_page_settings( add_query_arg( 'vs', 'infos', $here ) );
	} else {
		volski_page_grid( add_query_arg( 'vs', 'suivi', $here ) );
	}
	echo '</div>';
	return ob_get_clean();
}
