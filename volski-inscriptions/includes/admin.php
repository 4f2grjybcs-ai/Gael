<?php
/**
 * Back-office : inscriptions, infos de la compétition, comptes du comité.
 * Les membres du comité (rôle « Comité Vol & Ski ») ne voient que ces écrans.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once VOLSKI_DIR . 'includes/admin-list.php';

add_action( 'admin_menu', 'volski_admin_menu' );
add_action( 'admin_enqueue_scripts', 'volski_admin_assets' );
add_action( 'admin_post_volski_save', 'volski_admin_save' );
add_action( 'admin_post_volski_paiement', 'volski_admin_toggle_paiement' );
add_action( 'admin_post_volski_delete', 'volski_admin_delete' );
add_action( 'admin_post_volski_export', 'volski_admin_export' );
add_action( 'admin_post_volski_settings', 'volski_admin_save_settings' );
add_action( 'admin_post_volski_comite_add', 'volski_admin_comite_add' );
add_action( 'admin_post_volski_comite_remove', 'volski_admin_comite_remove' );

function volski_admin_menu() {
	add_menu_page( 'Vol & Ski', 'Vol & Ski', VOLSKI_CAP, 'volski', 'volski_page_inscriptions', 'dashicons-groups', 26 );
	add_submenu_page( 'volski', 'Inscriptions', 'Inscriptions', VOLSKI_CAP, 'volski', 'volski_page_inscriptions' );
	add_submenu_page( 'volski', 'Infos compétition', 'Infos compétition', VOLSKI_CAP, 'volski-infos', 'volski_page_settings' );
	add_submenu_page( 'volski', 'Comité', 'Comité', 'manage_options', 'volski-comite', 'volski_page_comite' );
}

function volski_admin_assets( $hook ) {
	if ( false !== strpos( (string) $hook, 'volski' ) ) {
		wp_enqueue_style( 'volski-admin', VOLSKI_URL . 'assets/admin.css', array(), VOLSKI_VERSION );
	}
}

/** Vérifie droit + jeton de sécurité, sinon arrête. */
function volski_check( $action, $cap = VOLSKI_CAP ) {
	if ( ! current_user_can( $cap ) ) {
		wp_die( 'Accès refusé.', 403 );
	}
	check_admin_referer( $action );
}

function volski_back( $args = array(), $page = 'volski' ) {
	wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=' . $page ) ) );
	exit;
}

function volski_admin_notice() {
	$msgs = array(
		'saved'    => 'Inscription enregistrée.',
		'added'    => 'Inscription ajoutée.',
		'deleted'  => 'Inscription supprimée.',
		'paid'     => 'Statut de paiement mis à jour.',
		'settings' => 'Infos enregistrées.',
		'bulk'     => 'Inscriptions mises à jour.',
		'member'   => 'Membre ajouté. Il a reçu un e-mail pour choisir son mot de passe.',
		'removed'  => 'Membre retiré du comité.',
	);
	$k = isset( $_GET['msg'] ) ? sanitize_key( $_GET['msg'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( isset( $msgs[ $k ] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msgs[ $k ] ) . '</p></div>';
	}
	if ( isset( $_GET['err'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-error"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['err'] ) ) ) . '</p></div>'; // phpcs:ignore
	}
}

/* ---------------------------------------------------------------- Inscriptions */

function volski_counts() {
	global $wpdb;
	$t   = volski_table();
	$out = array(
		'total' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" ), // phpcs:ignore
		'paye'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE paiement = 'paye'" ), // phpcs:ignore
	);
	foreach ( volski_sexes() as $k => $l ) {
		$out[ $k ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE sexe = %s", $k ) ); // phpcs:ignore
	}
	foreach ( volski_categories() as $k => $l ) {
		$out[ $k ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE CONCAT(',', categories, ',') LIKE %s", '%,' . $k . ',%' ) ); // phpcs:ignore
	}
	return $out;
}

function volski_page_inscriptions() {
	if ( ! current_user_can( VOLSKI_CAP ) ) {
		return;
	}
	$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( 'edit' === $action || 'new' === $action ) {
		volski_page_edit( 'edit' === $action ? absint( $_GET['id'] ?? 0 ) : 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}

	$table = new Volski_List_Table();
	$table->process_bulk_action();
	$table->prepare_items();
	$c = volski_counts();
	$s = volski_settings();
	?>
	<div class="wrap volski-admin">
		<h1 class="wp-heading-inline"><?php echo esc_html( $s['competition'] ); ?></h1>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=volski&action=new' ) ); ?>" class="page-title-action">Ajouter une inscription</a>
		<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( volski_current_filters(), admin_url( 'admin-post.php?action=volski_export' ) ), 'volski_export' ) ); ?>" class="page-title-action">Exporter pour Excel</a>
		<hr class="wp-header-end">
		<?php volski_admin_notice(); ?>

		<div class="volski-stats">
			<div class="volski-stat volski-stat-main"><b><?php echo (int) $c['total']; ?></b><span>inscrit<?php echo $c['total'] > 1 ? 's' : ''; ?></span></div>
			<?php foreach ( volski_categories() as $k => $l ) : ?>
				<div class="volski-stat"><b><?php echo (int) $c[ $k ]; ?></b><span><?php echo esc_html( $l ); ?></span></div>
			<?php endforeach; ?>
			<?php foreach ( volski_sexes() as $k => $l ) : ?>
				<div class="volski-stat"><b><?php echo (int) $c[ $k ]; ?></b><span><?php echo esc_html( $l . ( $c[ $k ] > 1 ? 's' : '' ) ); ?></span></div>
			<?php endforeach; ?>
			<div class="volski-stat volski-stat-pay"><b><?php echo (int) $c['paye']; ?><small> / <?php echo (int) $c['total']; ?></small></b><span>payé<?php echo $c['paye'] > 1 ? 's' : ''; ?></span></div>
			<div class="volski-stat"><b class="<?php echo $s['ouvert'] ? 'is-open' : 'is-closed'; ?>"><?php echo $s['ouvert'] ? 'Ouvertes' : 'Fermées'; ?></b><span>inscriptions</span></div>
		</div>

		<form method="get">
			<input type="hidden" name="page" value="volski">
			<?php
			$table->search_box( 'Rechercher', 'volski-search' );
			$table->display();
			?>
		</form>
	</div>
	<?php
}

/** Filtres de la liste (catégorie, sexe, paiement, recherche) lus dans l'URL. */
function volski_current_filters() {
	// phpcs:disable WordPress.Security.NonceVerification
	$f = array();
	foreach ( array( 'cat', 'sexe', 'paiement' ) as $k ) {
		if ( ! empty( $_REQUEST[ $k ] ) ) {
			$f[ $k ] = sanitize_key( wp_unslash( $_REQUEST[ $k ] ) );
		}
	}
	if ( ! empty( $_REQUEST['s'] ) ) {
		$f['s'] = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) );
	}
	// phpcs:enable
	return $f;
}

/** Clause WHERE préparée pour des filtres. */
function volski_where( $f ) {
	global $wpdb;
	$w = array( '1=1' );
	if ( ! empty( $f['cat'] ) && isset( volski_categories()[ $f['cat'] ] ) ) {
		$w[] = $wpdb->prepare( "CONCAT(',', categories, ',') LIKE %s", '%,' . $f['cat'] . ',%' );
	}
	if ( ! empty( $f['sexe'] ) && isset( volski_sexes()[ $f['sexe'] ] ) ) {
		$w[] = $wpdb->prepare( 'sexe = %s', $f['sexe'] );
	}
	if ( ! empty( $f['paiement'] ) && isset( volski_paiements()[ $f['paiement'] ] ) ) {
		$w[] = $wpdb->prepare( 'paiement = %s', $f['paiement'] );
	}
	if ( ! empty( $f['s'] ) ) {
		$like = '%' . $wpdb->esc_like( $f['s'] ) . '%';
		$w[]  = $wpdb->prepare( '(nom LIKE %s OR prenom LIKE %s OR email LIKE %s OR telephone LIKE %s OR passager LIKE %s)', $like, $like, $like, $like, $like );
	}
	return implode( ' AND ', $w );
}

function volski_page_edit( $id ) {
	$r = $id ? volski_get( $id ) : null;
	if ( $id && ! $r ) {
		echo '<div class="wrap"><h1>Inscription introuvable</h1><p><a href="' . esc_url( admin_url( 'admin.php?page=volski' ) ) . '">Retour à la liste</a></p></div>';
		return;
	}
	$r    = $r ?: array( 'prenom' => '', 'nom' => '', 'email' => '', 'telephone' => '', 'sexe' => '', 'categories' => '', 'passager' => '', 'urgence_nom' => '', 'urgence_tel' => '', 'commentaire' => '', 'afficher_public' => 0, 'paiement' => 'a_payer', 'note_admin' => '' );
	$cats = array_filter( explode( ',', $r['categories'] ) );
	$f    = function ( $k, $label, $type = 'text' ) use ( $r ) {
		printf( '<tr><th scope="row"><label for="vs-%1$s">%2$s</label></th><td><input type="%3$s" class="regular-text" id="vs-%1$s" name="%1$s" value="%4$s"></td></tr>', esc_attr( $k ), esc_html( $label ), esc_attr( $type ), esc_attr( $r[ $k ] ) );
	};
	?>
	<div class="wrap volski-admin">
		<h1><?php echo $id ? esc_html( 'Inscription de ' . $r['prenom'] . ' ' . $r['nom'] ) : 'Nouvelle inscription'; ?></h1>
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=volski' ) ); ?>">← Retour à la liste</a><?php if ( $id ) : ?> · Inscrit le <?php echo esc_html( mysql2date( 'j F Y à H:i', $r['created_at'] ) ); ?><?php endif; ?></p>
		<?php volski_admin_notice(); ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="volski_save">
			<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
			<?php wp_nonce_field( 'volski_save' ); ?>
			<h2>Suivi</h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row">Paiement</th><td><fieldset>
					<?php foreach ( volski_paiements() as $k => $l ) : ?>
						<label class="volski-radio"><input type="radio" name="paiement" value="<?php echo esc_attr( $k ); ?>" <?php checked( $r['paiement'], $k ); ?>> <?php echo esc_html( $l ); ?></label>
					<?php endforeach; ?>
				</fieldset></td></tr>
				<tr><th scope="row"><label for="vs-note">Note du comité</label></th><td><textarea id="vs-note" name="note_admin" rows="4" class="large-text" placeholder="Visible uniquement par le comité : relances, échanges, remarques…"><?php echo esc_textarea( $r['note_admin'] ); ?></textarea></td></tr>
			</table>
			<h2>Participant</h2>
			<table class="form-table" role="presentation">
				<?php $f( 'prenom', 'Prénom' ); ?>
				<?php $f( 'nom', 'Nom' ); ?>
				<?php $f( 'email', 'E-mail', 'email' ); ?>
				<?php $f( 'telephone', 'Téléphone', 'tel' ); ?>
				<tr><th scope="row">Sexe</th><td><fieldset>
					<?php foreach ( volski_sexes() as $k => $l ) : ?>
						<label class="volski-radio"><input type="radio" name="sexe" value="<?php echo esc_attr( $k ); ?>" <?php checked( $r['sexe'], $k ); ?>> <?php echo esc_html( $l ); ?></label>
					<?php endforeach; ?>
				</fieldset></td></tr>
				<tr><th scope="row">Catégorie(s)</th><td><fieldset>
					<?php foreach ( volski_categories() as $k => $l ) : ?>
						<label class="volski-radio"><input type="checkbox" name="categories[]" value="<?php echo esc_attr( $k ); ?>" <?php checked( in_array( $k, $cats, true ) ); ?>> <?php echo esc_html( $l ); ?></label>
					<?php endforeach; ?>
				</fieldset></td></tr>
				<?php $f( 'passager', 'Passager biplace' ); ?>
				<?php $f( 'urgence_nom', 'Contact d\'urgence' ); ?>
				<?php $f( 'urgence_tel', 'Tél. d\'urgence', 'tel' ); ?>
				<tr><th scope="row"><label for="vs-com">Commentaire du participant</label></th><td><textarea id="vs-com" name="commentaire" rows="3" class="large-text"><?php echo esc_textarea( $r['commentaire'] ); ?></textarea></td></tr>
				<tr><th scope="row">Liste publique</th><td><label><input type="checkbox" name="afficher_public" value="1" <?php checked( (int) $r['afficher_public'], 1 ); ?>> Afficher dans la liste publique des inscrits</label></td></tr>
			</table>
			<?php submit_button( $id ? 'Enregistrer' : 'Ajouter l\'inscription' ); ?>
		</form>
		<?php if ( $id ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="volski-delete" onsubmit="return window.confirm('Supprimer définitivement cette inscription ?');">
				<input type="hidden" name="action" value="volski_delete">
				<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
				<?php wp_nonce_field( 'volski_delete_' . $id ); ?>
				<button type="submit" class="button button-link-delete">Supprimer cette inscription</button>
			</form>
		<?php endif; ?>
	</div>
	<?php
}

function volski_admin_save() {
	volski_check( 'volski_save' );
	global $wpdb;
	$id = absint( $_POST['id'] ?? 0 );
	list( $data, $errors ) = volski_validate( $_POST, false );
	// Au back-office, seuls nom, prénom et catégorie sont obligatoires (inscription par téléphone, etc.).
	$errors = array_intersect_key( $errors, array_flip( array( 'prenom', 'nom', 'categories' ) ) );
	$back   = array( 'action' => $id ? 'edit' : 'new' ) + ( $id ? array( 'id' => $id ) : array() );
	if ( $errors ) {
		volski_back( $back + array( 'err' => implode( ' ', $errors ) ) );
	}
	$pay                = sanitize_key( $_POST['paiement'] ?? 'a_payer' );
	$data['paiement']   = isset( volski_paiements()[ $pay ] ) ? $pay : 'a_payer';
	$data['note_admin'] = mb_substr( sanitize_textarea_field( wp_unslash( $_POST['note_admin'] ?? '' ) ), 0, 2000 );
	if ( ! isset( volski_sexes()[ $data['sexe'] ] ) ) {
		$data['sexe'] = '';
	}
	if ( $id ) {
		$wpdb->update( volski_table(), $data, array( 'id' => $id ) );
		volski_back( array( 'action' => 'edit', 'id' => $id, 'msg' => 'saved' ) );
	}
	$data['created_at'] = current_time( 'mysql' );
	$wpdb->insert( volski_table(), $data );
	volski_back( array( 'action' => 'edit', 'id' => $wpdb->insert_id, 'msg' => 'added' ) );
}

function volski_admin_toggle_paiement() {
	$id = absint( $_GET['id'] ?? 0 );
	volski_check( 'volski_paiement_' . $id );
	$r = volski_get( $id );
	if ( $r ) {
		global $wpdb;
		$wpdb->update( volski_table(), array( 'paiement' => 'paye' === $r['paiement'] ? 'a_payer' : 'paye' ), array( 'id' => $id ) );
	}
	$back = wp_get_referer();
	wp_safe_redirect( add_query_arg( 'msg', 'paid', remove_query_arg( array( 'msg', 'err' ), $back ?: admin_url( 'admin.php?page=volski' ) ) ) );
	exit;
}

function volski_admin_delete() {
	$id = absint( $_REQUEST['id'] ?? 0 );
	volski_check( 'volski_delete_' . $id );
	global $wpdb;
	$wpdb->delete( volski_table(), array( 'id' => $id ) );
	volski_back( array( 'msg' => 'deleted' ) );
}

/** Export CSV lisible directement par Excel (point-virgule, UTF-8 avec BOM). */
function volski_admin_export() {
	volski_check( 'volski_export' );
	global $wpdb;
	$f    = volski_current_filters();
	$rows = $wpdb->get_results( 'SELECT * FROM ' . volski_table() . ' WHERE ' . volski_where( $f ) . ' ORDER BY nom, prenom', ARRAY_A ); // phpcs:ignore
	$s    = volski_settings();
	$file = sanitize_file_name( 'inscrits-' . $s['competition'] . '-' . gmdate( 'Y-m-d' ) . '.csv' );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $file . '"' );
	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" );
	$cats = volski_categories();
	$head = array( 'Inscrit le', 'Prénom', 'Nom', 'Sexe', 'Catégories' );
	foreach ( $cats as $l ) {
		$head[] = $l;
	}
	$head = array_merge( $head, array( 'Passager biplace', 'E-mail', 'Téléphone', 'Contact d\'urgence', 'Tél. d\'urgence', 'Paiement', 'Liste publique', 'Commentaire', 'Note du comité' ) );
	fputcsv( $out, $head, ';' );
	// Une cellule qui commence par = + - @ serait lue comme une formule par Excel : on la neutralise.
	$cell = function ( $v ) {
		$v = (string) $v;
		return preg_match( '/^[=+\-@\t\r]/', $v ) ? "'" . $v : $v;
	};
	foreach ( $rows as $r ) {
		$mine = array_filter( explode( ',', $r['categories'] ) );
		$line = array( mysql2date( 'd/m/Y H:i', $r['created_at'] ), $r['prenom'], $r['nom'], volski_sexes()[ $r['sexe'] ] ?? '', volski_categories_label( $r['categories'] ) );
		foreach ( $cats as $k => $l ) {
			$line[] = in_array( $k, $mine, true ) ? 'Oui' : '';
		}
		$line = array_merge( $line, array( $r['passager'], $r['email'], $r['telephone'], $r['urgence_nom'], $r['urgence_tel'], volski_paiements()[ $r['paiement'] ] ?? $r['paiement'], $r['afficher_public'] ? 'Oui' : 'Non', $r['commentaire'], $r['note_admin'] ) );
		fputcsv( $out, array_map( $cell, $line ), ';' );
	}
	fclose( $out );
	exit;
}

/* ---------------------------------------------------------------- Infos compétition */

function volski_page_settings() {
	if ( ! current_user_can( VOLSKI_CAP ) ) {
		return;
	}
	$s = volski_settings();
	?>
	<div class="wrap volski-admin">
		<h1>Infos compétition</h1>
		<?php volski_admin_notice(); ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="volski_settings">
			<?php wp_nonce_field( 'volski_settings' ); ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="vs-comp">Nom de la compétition</label></th><td><input id="vs-comp" name="competition" class="regular-text" value="<?php echo esc_attr( $s['competition'] ); ?>"></td></tr>
				<tr><th scope="row">Inscriptions</th><td><label><input type="checkbox" name="ouvert" value="1" <?php checked( $s['ouvert'], 1 ); ?>> Ouvertes (décocher pour fermer le formulaire)</label></td></tr>
				<tr><th scope="row"><label for="vs-infos">Infos pratiques</label></th><td><textarea id="vs-infos" name="infos" rows="5" class="large-text" placeholder="Dates, lieu, horaires du briefing, matériel obligatoire…"><?php echo esc_textarea( $s['infos'] ); ?></textarea><p class="description">Ajoutées à la fin de l'e-mail de confirmation envoyé aux participants.</p></td></tr>
				<tr><th scope="row"><label for="vs-msg">Message de confirmation</label></th><td><textarea id="vs-msg" name="message_confirm" rows="4" class="large-text"><?php echo esc_textarea( $s['message_confirm'] ); ?></textarea><p class="description">Début de l'e-mail reçu par chaque participant, suivi du récapitulatif de son inscription.</p></td></tr>
				<tr><th scope="row"><label for="vs-mail">E-mail de l'organisation</label></th><td><input id="vs-mail" type="email" name="email_orga" class="regular-text" value="<?php echo esc_attr( $s['email_orga'] ); ?>"><p class="description">Reçoit une alerte à chaque inscription. Les participants qui répondent à leur e-mail écrivent à cette adresse.</p></td></tr>
				<tr><th scope="row"><label for="vs-regl">Lien du règlement</label></th><td><input id="vs-regl" type="url" name="reglement_url" class="regular-text" value="<?php echo esc_attr( $s['reglement_url'] ); ?>" placeholder="https://…"><p class="description">Facultatif. Affiché dans la case à cocher du formulaire.</p></td></tr>
				<tr><th scope="row">Liste publique</th><td><label><input type="checkbox" name="liste_publique" value="1" <?php checked( $s['liste_publique'], 1 ); ?>> Activer la liste publique des inscrits</label></td></tr>
			</table>
			<?php submit_button( 'Enregistrer' ); ?>
		</form>
		<?php if ( current_user_can( 'manage_options' ) ) : ?>
		<h2>Afficher sur le site</h2>
		<p>Colle ces codes dans une page (bloc « Code court ») :</p>
		<p><code>[volski_inscription]</code> : le formulaire d'inscription<br><code>[volski_inscrits]</code> : la liste publique des inscrits</p>
		<?php endif; ?>
	</div>
	<?php
}

function volski_admin_save_settings() {
	volski_check( 'volski_settings' );
	$p = wp_unslash( $_POST );
	$s = array(
		'competition'     => sanitize_text_field( $p['competition'] ?? '' ) ?: 'Compétition Vol & Ski',
		'infos'           => sanitize_textarea_field( $p['infos'] ?? '' ),
		'ouvert'          => empty( $p['ouvert'] ) ? 0 : 1,
		'email_orga'      => sanitize_email( $p['email_orga'] ?? '' ),
		'message_confirm' => sanitize_textarea_field( $p['message_confirm'] ?? '' ),
		'reglement_url'   => esc_url_raw( $p['reglement_url'] ?? '' ),
		'liste_publique'  => empty( $p['liste_publique'] ) ? 0 : 1,
	);
	update_option( 'volski_settings', $s );
	volski_back( array( 'msg' => 'settings' ), 'volski-infos' );
}

/* ---------------------------------------------------------------- Comité */

function volski_page_comite() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$members = get_users( array( 'role' => VOLSKI_ROLE, 'orderby' => 'display_name' ) );
	?>
	<div class="wrap volski-admin">
		<h1>Comité</h1>
		<?php volski_admin_notice(); ?>
		<p class="volski-intro">Les membres du comité se connectent sur <a href="<?php echo esc_url( wp_login_url() ); ?>"><?php echo esc_html( wp_login_url() ); ?></a>.
		Ils arrivent directement sur les inscriptions et ne voient rien d'autre du site : ni pages, ni articles, ni réglages.
		Ils peuvent consulter et modifier les inscriptions, noter les paiements, exporter la liste et changer les infos de la compétition.</p>

		<h2>Membres</h2>
		<?php if ( $members ) : ?>
		<table class="widefat striped volski-members">
			<thead><tr><th>Nom</th><th>E-mail</th><th>Identifiant</th><th></th></tr></thead>
			<tbody>
			<?php foreach ( $members as $u ) : ?>
				<tr>
					<td><?php echo esc_html( $u->display_name ); ?></td>
					<td><?php echo esc_html( $u->user_email ); ?></td>
					<td><?php echo esc_html( $u->user_login ); ?></td>
					<td><a class="button-link-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=volski_comite_remove&user=' . $u->ID ), 'volski_comite_remove_' . $u->ID ) ); ?>" onclick="return window.confirm('Retirer cette personne du comité ? Elle ne pourra plus se connecter.');">Retirer</a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php else : ?>
			<p>Aucun membre pour l'instant.</p>
		<?php endif; ?>

		<h2>Ajouter un membre</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="volski_comite_add">
			<?php wp_nonce_field( 'volski_comite_add' ); ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="vc-prenom">Prénom</label></th><td><input id="vc-prenom" name="prenom" class="regular-text" required></td></tr>
				<tr><th scope="row"><label for="vc-nom">Nom</label></th><td><input id="vc-nom" name="nom" class="regular-text"></td></tr>
				<tr><th scope="row"><label for="vc-email">E-mail</label></th><td><input id="vc-email" type="email" name="email" class="regular-text" required><p class="description">La personne reçoit un e-mail avec son identifiant et un lien pour choisir son mot de passe.</p></td></tr>
			</table>
			<?php submit_button( 'Ajouter au comité' ); ?>
		</form>
	</div>
	<?php
}

function volski_admin_comite_add() {
	volski_check( 'volski_comite_add', 'manage_options' );
	$p      = wp_unslash( $_POST );
	$prenom = sanitize_text_field( $p['prenom'] ?? '' );
	$nom    = sanitize_text_field( $p['nom'] ?? '' );
	$email  = sanitize_email( $p['email'] ?? '' );
	if ( ! $prenom || ! is_email( $email ) ) {
		volski_back( array( 'err' => 'Indique au moins un prénom et une adresse e-mail valide.' ), 'volski-comite' );
	}
	$existing = get_user_by( 'email', $email );
	if ( $existing ) {
		if ( user_can( $existing, 'manage_options' ) ) {
			volski_back( array( 'err' => 'Cette adresse appartient à un administrateur : il a déjà accès à tout.' ), 'volski-comite' );
		}
		// Compte existant (ex. abonné) : on lui donne seulement le rôle du comité.
		$existing->set_role( VOLSKI_ROLE );
		volski_back( array( 'msg' => 'member' ), 'volski-comite' );
	}
	$base  = sanitize_user( remove_accents( strtolower( $prenom . ( $nom ? '.' . $nom : '' ) ) ), true );
	$base  = preg_replace( '/[^a-z0-9._-]/', '', str_replace( ' ', '', $base ) ) ?: 'comite';
	$login = $base;
	for ( $i = 2; username_exists( $login ); $i++ ) {
		$login = $base . $i;
	}
	$uid = wp_insert_user(
		array(
			'user_login'   => $login,
			'user_email'   => $email,
			'user_pass'    => wp_generate_password( 24 ),
			'first_name'   => $prenom,
			'last_name'    => $nom,
			'display_name' => trim( $prenom . ' ' . $nom ),
			'role'         => VOLSKI_ROLE,
		)
	);
	if ( is_wp_error( $uid ) ) {
		volski_back( array( 'err' => $uid->get_error_message() ), 'volski-comite' );
	}
	wp_new_user_notification( $uid, null, 'user' );
	volski_back( array( 'msg' => 'member' ), 'volski-comite' );
}

function volski_admin_comite_remove() {
	$uid = absint( $_GET['user'] ?? 0 );
	volski_check( 'volski_comite_remove_' . $uid, 'manage_options' );
	$u = get_userdata( $uid );
	if ( $u && in_array( VOLSKI_ROLE, (array) $u->roles, true ) ) {
		if ( array( VOLSKI_ROLE ) === array_values( (array) $u->roles ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $uid );
		} else {
			$u->remove_role( VOLSKI_ROLE );
		}
	}
	volski_back( array( 'msg' => 'removed' ), 'volski-comite' );
}
