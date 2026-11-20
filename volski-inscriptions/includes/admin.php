<?php
/**
 * Back-office : inscriptions, infos de la compétition, comptes du comité.
 * Les membres du comité (rôle « Comité Vol & Ski ») ne voient que ces écrans.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once VOLSKI_DIR . 'includes/admin-list.php';
require_once VOLSKI_DIR . 'includes/admin-grid.php';

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
	add_submenu_page( 'volski', 'Tableau de suivi', 'Tableau de suivi', VOLSKI_CAP, 'volski-suivi', 'volski_page_grid' );
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

/** Bouton d'envoi (fonctionne aussi hors de l'administration, dans l'espace comité). */
function volski_submit( $label ) {
	echo '<p class="submit"><button type="submit" id="submit" class="button button-primary">' . esc_html( $label ) . '</button></p>';
}

function volski_back( $args = array(), $page = 'volski' ) {
	// Depuis l'espace comité du site : on y revient.
	$ret = isset( $_POST['volski_return'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['volski_return'] ) ), '' ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$url = $ret ? remove_query_arg( array( 'msg', 'err', 'n', 'vq', 'vcat', 'vpay' ), $ret ) : admin_url( 'admin.php?page=' . $page );
	wp_safe_redirect( add_query_arg( $args, $url ) );
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
		'grid'     => 'Modifications enregistrées.',
		'member'   => 'Membre ajouté. Il a reçu un e-mail pour choisir son mot de passe.',
		'member_pw' => 'Compte créé avec le mot de passe choisi. Il peut se connecter tout de suite.',
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
		'du'    => (float) $wpdb->get_var( "SELECT SUM(total) FROM $t" ), // phpcs:ignore
		'recu'  => (float) $wpdb->get_var( "SELECT SUM(total) FROM $t WHERE paiement = 'paye'" ), // phpcs:ignore
	);
	foreach ( volski_sexes() as $k => $l ) {
		$out[ $k ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE sexe = %s", $k ) ); // phpcs:ignore
	}
	foreach ( volski_categories() as $k => $l ) {
		$out[ $k ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE CONCAT(',', categories, ',') LIKE %s", '%,' . $k . ',%' ) ); // phpcs:ignore
	}
	$out['opts'] = array();
	$all         = $wpdb->get_col( "SELECT options_choisies FROM $t" ); // phpcs:ignore
	foreach ( volski_options() as $o ) {
		$q = 0;
		foreach ( $all as $json ) {
			$q += volski_opt_qty( $json, $o['label'] );
		}
		$out['opts'][ $o['label'] ] = $q;
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
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=volski-suivi' ) ); ?>" class="page-title-action volski-cta">Tableau de suivi (payé, souper…)</a>
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
			<?php foreach ( $c['opts'] as $label => $q ) : ?>
				<div class="volski-stat"><b><?php echo (int) $q; ?></b><span><?php echo esc_html( $label ); ?></span></div>
			<?php endforeach; ?>
			<?php if ( $c['du'] > 0 ) : ?>
			<div class="volski-stat volski-stat-pay"><b><?php echo esc_html( volski_money( $c['recu'] ) ); ?><small> / <?php echo esc_html( volski_money( $c['du'] ) ); ?></small></b><span>encaissés</span></div>
			<?php endif; ?>
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
	// Noms courts dans l'admin ; préfixés « v… » dans le tableau de suivi (s et cat sont déjà pris par WordPress sur le site).
	$f   = array();
	$map = array( 'cat' => array( 'cat', 'vcat' ), 'sexe' => array( 'sexe', 'vsexe' ), 'paiement' => array( 'paiement', 'vpay' ) );
	foreach ( $map as $k => $names ) {
		foreach ( $names as $n ) {
			if ( ! empty( $_REQUEST[ $n ] ) ) {
				$f[ $k ] = sanitize_key( wp_unslash( $_REQUEST[ $n ] ) );
			}
		}
	}
	foreach ( array( 's', 'vq' ) as $n ) {
		if ( ! empty( $_REQUEST[ $n ] ) && is_string( $_REQUEST[ $n ] ) ) {
			$f['s'] = sanitize_text_field( wp_unslash( $_REQUEST[ $n ] ) );
		}
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
				<tr><th scope="row"><label for="vs-mode">Moyen de paiement</label></th><td><select id="vs-mode" name="paiement_mode"><option value="">—</option><?php foreach ( volski_modes() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $r['paiement_mode'] ?? '', $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></td></tr>
				<?php if ( $id ) : ?>
				<tr><th scope="row">Options choisies</th><td><?php echo esc_html( volski_options_label( $r['options_choisies'] ?? '' ) ?: 'Aucune' ); ?></td></tr>
				<tr><th scope="row"><label for="vs-total">Total à régler</label></th><td><input id="vs-total" name="total" class="small-text" inputmode="decimal" value="<?php echo esc_attr( number_format( (float) ( $r['total'] ?? 0 ), 2, '.', '' ) ); ?>"> <?php echo esc_html( volski_currency() ); ?><p class="description">Calculé à l'inscription. Tu peux le corriger (remise, option ajoutée…).</p></td></tr>
				<?php else : ?>
				<tr><th scope="row">Options</th><td>
					<?php foreach ( volski_options() as $i => $o ) : ?>
						<label class="volski-radio"><?php echo esc_html( $o['label'] ); ?> <input type="number" min="0" max="<?php echo (int) $o['max']; ?>" name="opt[<?php echo (int) $i; ?>]" value="0" class="small-text"></label>
					<?php endforeach; ?>
					<?php if ( ! volski_options() ) : ?>Aucune option définie.<?php endif; ?>
					<p class="description">Le total est calculé automatiquement à l'enregistrement.</p>
				</td></tr>
				<?php endif; ?>
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
				<tr><th scope="row">Catégorie</th><td><fieldset>
					<?php foreach ( volski_categories() as $k => $l ) : ?>
						<label class="volski-radio"><input type="radio" name="categories[]" value="<?php echo esc_attr( $k ); ?>" <?php checked( isset( $cats[0] ) && $cats[0] === $k ); ?>> <?php echo esc_html( $l ); ?></label>
					<?php endforeach; ?>
				</fieldset></td></tr>
				<?php $f( 'passager', 'Passager' ); ?>
				<?php $f( 'urgence_nom', 'Contact d\'urgence' ); ?>
				<?php $f( 'urgence_tel', 'Tél. d\'urgence', 'tel' ); ?>
				<tr><th scope="row"><label for="vs-com">Commentaire du participant</label></th><td><textarea id="vs-com" name="commentaire" rows="3" class="large-text"><?php echo esc_textarea( $r['commentaire'] ); ?></textarea></td></tr>
				<tr><th scope="row">Liste publique</th><td><label><input type="checkbox" name="afficher_public" value="1" <?php checked( (int) $r['afficher_public'], 1 ); ?>> Afficher dans la liste publique des inscrits</label></td></tr>
			</table>
			<?php volski_submit( $id ? 'Enregistrer' : 'Ajouter l\'inscription' ); ?>
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
	$mode                  = sanitize_key( $_POST['paiement_mode'] ?? '' );
	$data['paiement_mode'] = isset( volski_modes()[ $mode ] ) ? $mode : '';
	$data['note_admin'] = mb_substr( sanitize_textarea_field( wp_unslash( $_POST['note_admin'] ?? '' ) ), 0, 2000 );
	if ( ! isset( volski_sexes()[ $data['sexe'] ] ) ) {
		$data['sexe'] = '';
	}
	if ( $id ) {
		// Les options restent celles choisies à l'inscription ; seul le total peut être corrigé.
		unset( $data['options_choisies'] );
		$data['total'] = (float) volski_parse_amount( wp_unslash( $_POST['total'] ?? '0' ) );
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
	$head = array_merge( $head, array( 'Passager', 'E-mail', 'Téléphone', 'Contact d\'urgence', 'Tél. d\'urgence', 'Options' ) );
	$opts = volski_options();
	foreach ( $opts as $o ) {
		$head[] = $o['label'];
	}
	$head = array_merge( $head, array( 'Total (' . volski_currency() . ')', 'Paiement', 'Moyen de paiement', 'Liste publique', 'Commentaire', 'Note du comité' ) );
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
		$line = array_merge( $line, array( $r['passager'], $r['email'], $r['telephone'], $r['urgence_nom'], $r['urgence_tel'], volski_options_label( $r['options_choisies'] ) ) );
		foreach ( $opts as $o ) {
			$q      = volski_opt_qty( $r['options_choisies'], $o['label'] );
			$line[] = $q ? ( 1 === $o['max'] ? 'Oui' : $q ) : '';
		}
		$line = array_merge( $line, array( number_format( (float) $r['total'], 2, '.', '' ), volski_paiements()[ $r['paiement'] ] ?? $r['paiement'], volski_modes()[ $r['paiement_mode'] ] ?? '', $r['afficher_public'] ? 'Oui' : 'Non', $r['commentaire'], $r['note_admin'] ) );
		fputcsv( $out, array_map( $cell, $line ), ';' );
	}
	fclose( $out );
	exit;
}

/* ---------------------------------------------------------------- Infos compétition */

function volski_page_settings( $front_url = '' ) {
	if ( ! current_user_can( VOLSKI_CAP ) ) {
		return;
	}
	$front_url = is_string( $front_url ) ? $front_url : '';
	$s         = volski_settings();
	?>
	<div class="wrap volski-admin">
		<?php if ( ! $front_url ) : ?><h1>Infos compétition</h1><?php endif; ?>
		<p class="volski-intro">Tout ce qui s'affiche sur la page de l'événement. Les champs laissés vides ne sont pas affichés.</p>
		<?php volski_admin_notice(); ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="volski_settings">
			<?php if ( $front_url ) : ?><input type="hidden" name="volski_return" value="<?php echo esc_url( $front_url ); ?>"><?php endif; ?>
			<?php wp_nonce_field( 'volski_settings' ); ?>
			<h2>La page de l'événement</h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="vs-comp">Nom de la compétition</label></th><td><input id="vs-comp" name="competition" class="regular-text" value="<?php echo esc_attr( $s['competition'] ); ?>"></td></tr>
				<tr><th scope="row"><label for="vs-date">Dates</label></th><td><input id="vs-date" name="date_txt" class="regular-text" value="<?php echo esc_attr( $s['date_txt'] ); ?>" placeholder="ex. Samedi 13 et dimanche 14 mars 2027"></td></tr>
				<tr><th scope="row"><label for="vs-lieu">Lieu</label></th><td><input id="vs-lieu" name="lieu" class="regular-text" value="<?php echo esc_attr( $s['lieu'] ); ?>" placeholder="ex. Station de La Clusaz"></td></tr>
				<tr><th scope="row"><label for="vs-img">Photo de la bannière</label></th><td><input id="vs-img" type="url" name="image_url" class="large-text" value="<?php echo esc_attr( $s['image_url'] ); ?>" placeholder="https://…/photo.jpg"><p class="description">Adresse d'une photo (format paysage). Pour une photo de la médiathèque : clic sur l'image, puis « Copier l'URL ». Laisse vide pour un bandeau de couleur.</p></td></tr>
				<tr><th scope="row"><label for="vs-pres">Présentation</label></th><td><textarea id="vs-pres" name="presentation" rows="5" class="large-text" placeholder="Quelques lignes pour donner envie : l'esprit de la compétition, les épreuves, le niveau attendu…"><?php echo esc_textarea( $s['presentation'] ); ?></textarea></td></tr>
				<tr><th scope="row"><label for="vs-prog">Programme</label></th><td><textarea id="vs-prog" name="programme" rows="8" class="large-text" placeholder="# Samedi 13 mars&#10;7h30 : Accueil et retrait des dossards&#10;8h30 : Briefing&#10;9h00 : Première manche&#10;# Dimanche 14 mars&#10;9h00 : Finales&#10;17h00 : Remise des prix"><?php echo esc_textarea( $s['programme'] ); ?></textarea><p class="description">Une ligne par moment, sous la forme « heure : activité ». Une ligne qui commence par # crée un titre de jour.</p></td></tr>
				<tr><th scope="row"><label for="vs-infos">Infos pratiques</label></th><td><textarea id="vs-infos" name="infos" rows="5" class="large-text" placeholder="Matériel obligatoire, forfait, hébergement, météo, repli en cas de mauvais temps…"><?php echo esc_textarea( $s['infos'] ); ?></textarea><p class="description">Affichées sur la page et ajoutées à l'e-mail de confirmation.</p></td></tr>
				<tr><th scope="row"><label for="vs-adr">Adresse du rendez-vous</label></th><td><textarea id="vs-adr" name="adresse" rows="2" class="large-text" placeholder="ex. Parking du télésiège de l'Étale, 74220 La Clusaz"><?php echo esc_textarea( $s['adresse'] ); ?></textarea><p class="description">Un lien « Voir sur la carte » est ajouté automatiquement.</p></td></tr>
				<tr><th scope="row"><label for="vs-contact">Contact</label></th><td><textarea id="vs-contact" name="contact" rows="2" class="large-text" placeholder="ex. Julien – 06 12 34 56 78"><?php echo esc_textarea( $s['contact'] ); ?></textarea></td></tr>
			</table>

			<h2>Catégories</h2>
			<p class="description">Chaque pilote choisit une seule catégorie. Renomme, ajoute ou retire librement. Coche « Passager » pour demander le nom du passager (ex. Biplace). Le prix est facultatif : laisse-le vide si le prix se règle avec les tarifs ci-dessous.</p>
			<table class="widefat volski-opts-admin volski-repeat" id="volski-cats">
				<thead><tr><th>Catégorie</th><th>Prix (<?php echo esc_html( volski_currency() ); ?>)</th><th>Passager</th><th></th></tr></thead>
				<tbody>
				<?php
				$crows   = volski_category_rows( $s );
				$crows[] = array( 'key' => '', 'label' => '', 'prix' => '', 'passager' => 0 );
				foreach ( $crows as $i => $c ) :
					?>
					<tr>
						<td data-label="Catégorie"><input type="hidden" name="cats[<?php echo (int) $i; ?>][key]" value="<?php echo esc_attr( $c['key'] ); ?>"><input name="cats[<?php echo (int) $i; ?>][label]" class="regular-text" value="<?php echo esc_attr( $c['label'] ); ?>" placeholder="ex. Solo normal" aria-label="Nom de la catégorie"></td>
						<td data-label="Prix"><input name="cats[<?php echo (int) $i; ?>][prix]" class="small-text" inputmode="decimal" value="<?php echo esc_attr( $c['prix'] ); ?>" aria-label="Prix"></td>
						<td data-label="Passager"><label><input type="checkbox" name="cats[<?php echo (int) $i; ?>][passager]" value="1" <?php checked( $c['passager'], 1 ); ?>> Demander le passager</label></td>
						<td class="volski-row-tools"><button type="button" class="button volski-row-up" aria-label="Monter">↑</button><button type="button" class="button volski-row-down" aria-label="Descendre">↓</button><button type="button" class="button-link volski-row-del">Retirer</button></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p><button type="button" class="button volski-row-add" data-table="volski-cats">+ Ajouter une catégorie</button></p>
			<table class="form-table" role="presentation">


			<h2>Tarifs</h2>
			<p class="description">Les cartes à sélectionner dans la section « Tarifs » de la page, avec le total en direct. Ex. Inscription 35, Souper 40, Abonnement télécabine. Les flèches ↑ ↓ règlent l'ordre d'affichage.<br>
			« Obligatoire » : toujours compté, carte marquée « Inclus » (ex. Inscription). « Max » : quantité maximale par personne (1 = à cocher). « Seulement avec » : proposé seulement si l'autre est choisi (ex. « +1 au souper » avec « Souper »). Prix vide = pas de prix affiché ; 0 = « Gratuit ».</p>
			<table class="widefat volski-opts-admin volski-repeat" id="volski-opts">
				<thead><tr><th>Tarif</th><th>Prix (<?php echo esc_html( volski_currency() ); ?>)</th><th>Obligatoire</th><th>Max</th><th>Seulement avec</th><th></th></tr></thead>
				<tbody>
				<?php
				$rows = array_values( (array) $s['options'] );
				$rows[] = array( 'label' => '', 'prix' => '', 'max' => 1, 'requires' => '', 'oblig' => 0 );
				$parents = array_filter( wp_list_pluck( (array) $s['options'], 'label' ) );
				foreach ( $rows as $i => $o ) :
					?>
					<tr>
						<td data-label="Tarif"><input name="options[<?php echo (int) $i; ?>][label]" class="regular-text" value="<?php echo esc_attr( $o['label'] ?? '' ); ?>" placeholder="ex. Souper"></td>
						<td data-label="Prix"><input name="options[<?php echo (int) $i; ?>][prix]" class="small-text" inputmode="decimal" value="<?php echo esc_attr( $o['prix'] ?? '' ); ?>"></td>
						<td data-label="Obligatoire"><label><input type="checkbox" name="options[<?php echo (int) $i; ?>][oblig]" value="1" <?php checked( ! empty( $o['oblig'] ) ); ?>> Toujours inclus</label></td>
						<td data-label="Max"><input name="options[<?php echo (int) $i; ?>][max]" type="number" min="1" max="20" class="small-text" value="<?php echo esc_attr( $o['max'] ?? 1 ); ?>"></td>
						<td data-label="Seulement avec"><select name="options[<?php echo (int) $i; ?>][requires]"><option value="">—</option>
							<?php foreach ( $parents as $pl ) : ?>
								<?php if ( $pl !== ( $o['label'] ?? '' ) ) : ?><option value="<?php echo esc_attr( $pl ); ?>" <?php selected( $o['requires'] ?? '', $pl ); ?>><?php echo esc_html( $pl ); ?></option><?php endif; ?>
							<?php endforeach; ?>
						</select></td>
						<td class="volski-row-tools"><button type="button" class="button volski-row-up" aria-label="Monter">↑</button><button type="button" class="button volski-row-down" aria-label="Descendre">↓</button><button type="button" class="button-link volski-row-del">Retirer</button></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p><button type="button" class="button volski-row-add" data-table="volski-opts">+ Ajouter un tarif</button></p>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="vs-tnote">Précision sur les tarifs</label></th><td><input id="vs-tnote" name="tarif_note" class="large-text" value="<?php echo esc_attr( $s['tarif_note'] ); ?>" placeholder="ex. Forfait de ski non compris."></td></tr>
			</table>
			<script>
			(function () {
				// Lignes à ajouter / retirer dans les tableaux Catégories et Options.
				var reset = function (row, n) {
					row.querySelectorAll('input,select').forEach(function (i) {
						i.name = i.name.replace(/\[\d+\]/, '[' + n + ']');
						if (i.type === 'checkbox') i.checked = false;
						else if (i.name.indexOf('[max]') > 0) i.value = 1;
						else i.value = '';
					});
				};
				document.querySelectorAll('.volski-row-add').forEach(function (btn) {
					btn.addEventListener('click', function () {
						var tb = document.querySelector('#' + btn.getAttribute('data-table') + ' tbody');
						var row = tb.lastElementChild.cloneNode(true);
						reset(row, tb.children.length + 100);
						tb.appendChild(row);
						row.querySelector('input:not([type=hidden])').focus();
					});
				});
				document.querySelectorAll('.volski-repeat tbody').forEach(function (tb) {
					tb.addEventListener('click', function (e) {
						var btn = e.target.closest('button'); if (!btn) return;
						var tr = btn.closest('tr');
						if (btn.classList.contains('volski-row-del')) {
							if (tb.children.length > 1) tr.remove(); else reset(tr, 0);
						} else if (btn.classList.contains('volski-row-up') && tr.previousElementSibling) {
							tb.insertBefore(tr, tr.previousElementSibling); btn.focus();
						} else if (btn.classList.contains('volski-row-down') && tr.nextElementSibling) {
							tb.insertBefore(tr.nextElementSibling, tr); btn.focus();
						}
					});
				});
				// À l'enregistrement, numérote les lignes dans l'ordre affiché : c'est l'ordre de la page.
				var formEl = document.querySelector('#volski-cats') && document.querySelector('#volski-cats').closest('form');
				if (formEl) formEl.addEventListener('submit', function () {
					document.querySelectorAll('.volski-repeat tbody').forEach(function (tb) {
						Array.prototype.forEach.call(tb.children, function (tr, n) {
							tr.querySelectorAll('input,select').forEach(function (i) { i.name = i.name.replace(/\[\d+\]/, '[' + n + ']'); });
						});
					});
				});
			})();
			</script>

			<h2>Inscriptions</h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row">État</th><td><label><input type="checkbox" name="ouvert" value="1" <?php checked( $s['ouvert'], 1 ); ?>> Inscriptions ouvertes (décocher pour fermer le formulaire)</label></td></tr>
				<tr><th scope="row"><label for="vs-msg">Message de confirmation</label></th><td><textarea id="vs-msg" name="message_confirm" rows="4" class="large-text"><?php echo esc_textarea( $s['message_confirm'] ); ?></textarea><p class="description">Début de l'e-mail reçu par chaque participant, suivi du récapitulatif de son inscription.</p></td></tr>
				<tr><th scope="row"><label for="vs-mail">E-mail de l'organisation</label></th><td><input id="vs-mail" type="email" name="email_orga" class="regular-text" value="<?php echo esc_attr( $s['email_orga'] ); ?>"><p class="description">Reçoit une alerte à chaque inscription. Les participants qui répondent à leur e-mail écrivent à cette adresse.</p></td></tr>
				<tr><th scope="row"><label for="vs-liste">Page « Liste des inscrits »</label></th><td><input id="vs-liste" type="url" name="liste_url" class="regular-text" value="<?php echo esc_attr( $s['liste_url'] ); ?>" placeholder="<?php echo esc_attr( volski_liste_url() ?: 'https://…' ); ?>"><p class="description">Le bouton de la bannière mène à cette page. Laisse vide : l'extension prend la page qui contient [volski_inscrits]<?php echo volski_liste_url() ? ' (actuellement : ' . esc_html( volski_liste_url() ) . ')' : ' (aucune trouvée pour l\'instant : crée-la pour que le bouton apparaisse)'; ?>.</p></td></tr>
				<tr><th scope="row"><label for="vs-regl">Lien du règlement</label></th><td><input id="vs-regl" type="url" name="reglement_url" class="regular-text" value="<?php echo esc_attr( $s['reglement_url'] ); ?>" placeholder="https://…"><p class="description">Facultatif. Affiché dans la case à cocher du formulaire.</p></td></tr>
				<tr><th scope="row">Liste publique</th><td><label><input type="checkbox" name="liste_publique" value="1" <?php checked( $s['liste_publique'], 1 ); ?>> Activer la liste publique des inscrits</label></td></tr>
			</table>
			<?php volski_submit( 'Enregistrer' ); ?>
		</form>
		<?php if ( current_user_can( 'manage_options' ) ) : ?>
		<h2>Afficher sur le site</h2>
		<p>Colle un de ces codes dans une page (bloc « Code court ») :</p>
		<p><code>[volski_evenement]</code> : la page complète de l'événement (bannière, programme, infos, tarifs et formulaire)<br><code>[volski_inscription]</code> : le formulaire seul<br><code>[volski_inscrits]</code> : la liste publique des inscrits</p>
		<?php endif; ?>
	</div>
	<?php
}

function volski_admin_save_settings() {
	volski_check( 'volski_settings' );
	$p = wp_unslash( $_POST );
	$cats = array();
	$used = array();
	foreach ( (array) ( $p['cats'] ?? array() ) as $c ) {
		$label = mb_substr( sanitize_text_field( $c['label'] ?? '' ), 0, 60 );
		if ( '' === $label ) {
			continue;
		}
		// La clé reste la même quand on renomme : les inscriptions existantes suivent le nouveau nom.
		$key = sanitize_key( $c['key'] ?? '' );
		if ( '' === $key || isset( $used[ $key ] ) ) {
			$base = sanitize_key( sanitize_title( $label ) ) ?: 'categorie';
			$key  = $base;
			for ( $n = 2; isset( $used[ $key ] ); $n++ ) {
				$key = $base . '-' . $n;
			}
		}
		$used[ $key ] = true;
		$v            = volski_parse_amount( $c['prix'] ?? '' );
		$cats[]       = array(
			'key'      => $key,
			'label'    => $label,
			'prix'     => '' === $v ? '' : (string) $v,
			'passager' => empty( $c['passager'] ) ? 0 : 1,
		);
	}
	$options = array();
	foreach ( (array) ( $p['options'] ?? array() ) as $o ) {
		$label = sanitize_text_field( $o['label'] ?? '' );
		if ( '' === $label ) {
			continue;
		}
		$praw      = volski_parse_amount( $o['prix'] ?? '' );
		$options[] = array(
			'label'    => mb_substr( $label, 0, 80 ),
			'prix'     => '' === $praw ? '' : (string) $praw,
			'max'      => min( 20, max( 1, absint( $o['max'] ?? 1 ) ) ),
			'requires' => sanitize_text_field( $o['requires'] ?? '' ),
			'oblig'    => empty( $o['oblig'] ) ? 0 : 1,
		);
	}
	$s = array(
		'competition'     => sanitize_text_field( $p['competition'] ?? '' ) ?: 'Compétition Vol & Ski',
		'date_txt'        => sanitize_text_field( $p['date_txt'] ?? '' ),
		'lieu'            => sanitize_text_field( $p['lieu'] ?? '' ),
		'adresse'         => sanitize_textarea_field( $p['adresse'] ?? '' ),
		'image_url'       => esc_url_raw( $p['image_url'] ?? '' ),
		'presentation'    => sanitize_textarea_field( $p['presentation'] ?? '' ),
		'programme'       => sanitize_textarea_field( $p['programme'] ?? '' ),
		'contact'         => sanitize_textarea_field( $p['contact'] ?? '' ),
		'prix'            => array(),
		'categories'      => $cats,
		'tarif_note'      => sanitize_text_field( $p['tarif_note'] ?? '' ),
		'options'         => $options,
		'infos'           => sanitize_textarea_field( $p['infos'] ?? '' ),
		'ouvert'          => empty( $p['ouvert'] ) ? 0 : 1,
		'email_orga'      => sanitize_email( $p['email_orga'] ?? '' ),
		'message_confirm' => sanitize_textarea_field( $p['message_confirm'] ?? '' ),
		'reglement_url'   => esc_url_raw( $p['reglement_url'] ?? '' ),
		'liste_url'       => esc_url_raw( $p['liste_url'] ?? '' ),
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
		<?php $front = volski_comite_page_url(); ?>
		<p class="volski-intro">
		<?php if ( $front ) : ?>
			<strong>Adresse à donner au comité : <a href="<?php echo esc_url( $front ); ?>"><?php echo esc_html( $front ); ?></a></strong><br>
			C'est une page du site avec sa propre connexion (identifiant + mot de passe). Elle marche même si l'administration WordPress est bloquée par une autre extension.<br>
		<?php endif; ?>
		Les membres peuvent aussi passer par <a href="<?php echo esc_url( wp_login_url() ); ?>"><?php echo esc_html( wp_login_url() ); ?></a>.
		Dans tous les cas, ils ne voient rien d'autre du site : ni pages, ni articles, ni réglages.
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
				<tr><th scope="row"><label for="vc-prenom">Prénom ou nom du compte</label></th><td><input id="vc-prenom" name="prenom" class="regular-text" required></td></tr>
				<tr><th scope="row"><label for="vc-nom">Nom</label></th><td><input id="vc-nom" name="nom" class="regular-text"></td></tr>
				<tr><th scope="row"><label for="vc-email">E-mail</label></th><td><input id="vc-email" type="email" name="email" class="regular-text"><p class="description">Facultatif si tu choisis le mot de passe ci-dessous. Sinon, la personne reçoit un e-mail pour choisir elle-même son mot de passe.</p></td></tr>
				<tr><th scope="row"><label for="vc-login">Identifiant</label></th><td><input id="vc-login" name="login" class="regular-text" autocomplete="off"><p class="description">Facultatif. Laisse vide pour « prénom.nom ». Pour un compte partagé par tout le comité, par exemple : Comité.</p></td></tr>
				<tr><th scope="row"><label for="vc-pass">Mot de passe</label></th><td><input id="vc-pass" type="text" name="pass" class="regular-text" autocomplete="new-password" minlength="8"><p class="description">Facultatif. Au moins 8 caractères. Tu le communiques toi-même au comité.</p></td></tr>
			</table>
			<?php volski_submit( 'Ajouter au comité' ); ?>
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
	$pass   = (string) ( $p['pass'] ?? '' );
	$login  = sanitize_user( trim( (string) ( $p['login'] ?? '' ) ), true );
	$err    = function ( $m ) {
		volski_back( array( 'err' => $m ), 'volski-comite' );
	};
	if ( ! $prenom ) {
		$err( 'Indique au moins un prénom ou un nom pour le compte.' );
	}
	if ( '' !== $pass && strlen( $pass ) < 8 ) {
		$err( 'Le mot de passe doit faire au moins 8 caractères.' );
	}
	if ( '' === $pass && ! is_email( $email ) ) {
		$err( 'Indique une adresse e-mail valide, ou choisis un mot de passe.' );
	}
	if ( '' !== $email && ! is_email( $email ) ) {
		$err( 'Cette adresse e-mail n\'est pas valide.' );
	}

	$existing = $email ? get_user_by( 'email', $email ) : false;
	if ( ! $existing && '' !== $login ) {
		$existing = get_user_by( 'login', $login );
	}
	if ( $existing ) {
		if ( user_can( $existing, 'manage_options' ) ) {
			$err( 'Ce compte appartient à un administrateur : il a déjà accès à tout.' );
		}
		// Compte existant : on lui donne seulement le rôle du comité (et le mot de passe choisi, s'il y en a un).
		$existing->set_role( VOLSKI_ROLE );
		if ( '' !== $pass ) {
			wp_set_password( $pass, $existing->ID );
		}
		volski_back( array( 'msg' => '' !== $pass ? 'member_pw' : 'member' ), 'volski-comite' );
	}

	if ( '' === $login ) {
		$base  = sanitize_user( strtolower( $prenom . ( $nom ? '.' . $nom : '' ) ), true );
		$base  = preg_replace( '/[^a-z0-9._-]/', '', str_replace( ' ', '', $base ) ) ?: 'comite';
		$login = $base;
		for ( $i = 2; username_exists( $login ); $i++ ) {
			$login = $base . $i;
		}
	}
	$uid = wp_insert_user(
		array(
			'user_login'   => $login,
			'user_email'   => $email,
			'user_pass'    => '' !== $pass ? $pass : wp_generate_password( 24 ),
			'first_name'   => $prenom,
			'last_name'    => $nom,
			'display_name' => trim( $prenom . ' ' . $nom ),
			'role'         => VOLSKI_ROLE,
		)
	);
	if ( is_wp_error( $uid ) ) {
		$err( $uid->get_error_message() );
	}
	if ( '' === $pass ) {
		wp_new_user_notification( $uid, null, 'user' );
		volski_back( array( 'msg' => 'member' ), 'volski-comite' );
	}
	volski_back( array( 'msg' => 'member_pw' ), 'volski-comite' );
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
