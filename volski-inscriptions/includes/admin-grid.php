<?php
/**
 * Tableau de suivi : toutes les inscriptions modifiables d'un coup (payé, moyen de paiement,
 * options comme le souper, total, note), comme une feuille de calcul.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_post_volski_grid', 'volski_grid_save' );

/** Quantité d'une option (par libellé) dans le JSON stocké. */
function volski_opt_qty( $json, $label ) {
	foreach ( (array) json_decode( (string) $json, true ) as $o ) {
		if ( ( $o['label'] ?? '' ) === $label ) {
			return (int) $o['qty'];
		}
	}
	return 0;
}

function volski_page_grid( $front_url = '' ) {
	if ( ! current_user_can( VOLSKI_CAP ) ) {
		return;
	}
	$front_url = is_string( $front_url ) ? $front_url : '';
	$base      = $front_url ? remove_query_arg( array( 'vq', 'vcat', 'vpay', 'msg', 'err', 'n' ), $front_url ) : admin_url( 'admin.php?page=volski-suivi' );
	global $wpdb;
	$f     = volski_current_filters();
	$rows  = $wpdb->get_results( 'SELECT * FROM ' . volski_table() . ' WHERE ' . volski_where( $f ) . ' ORDER BY nom, prenom', ARRAY_A ); // phpcs:ignore
	$opts  = volski_options();
	$modes = volski_modes();
	$sum   = array( 'paye' => 0, 'du' => 0.0, 'recu' => 0.0, 'opt' => array_fill( 0, count( $opts ), 0 ) );
	?>
	<div class="wrap volski-admin volski-grid-wrap">
		<?php if ( ! $front_url ) : ?><h1 class="wp-heading-inline">Tableau de suivi</h1><?php endif; ?>
		<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( $f, admin_url( 'admin-post.php?action=volski_export' ) ), 'volski_export' ) ); ?>" class="page-title-action">Exporter pour Excel</a>
		<hr class="wp-header-end">
		<?php volski_admin_notice(); ?>
		<p class="volski-intro">Coche, choisis, corrige directement, puis clique sur <strong>Enregistrer les modifications</strong>. Les fiches modifiées sont surlignées.<span class="volski-hide-mobile">
		Si tu changes les options d'une personne, son total est recalculé (sauf si tu modifies aussi le total à la main).</span></p>

		<form method="get" class="volski-grid-filters" action="<?php echo esc_url( strtok( $base, '?' ) ); ?>">
			<?php
			// Garde les paramètres de l'adresse (page=…, page_id=…, vs=…) quand on filtre.
			wp_parse_str( (string) wp_parse_url( $base, PHP_URL_QUERY ), $keep );
			foreach ( $keep as $k => $v ) {
				echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
			}
			?>
			<label class="screen-reader-text" for="vg-s">Rechercher</label>
			<input type="search" id="vg-s" name="vq" value="<?php echo esc_attr( $f['s'] ?? '' ); ?>" placeholder="Nom, e-mail, téléphone…">
			<select name="vcat" aria-label="Catégorie"><option value="">Toutes catégories</option><?php foreach ( volski_categories() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $f['cat'] ?? '', $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
			<select name="vpay" aria-label="Paiement"><option value="">Payé et à payer</option><?php foreach ( volski_paiements() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $f['paiement'] ?? '', $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
			<button class="button">Filtrer</button>
			<?php if ( $f ) : ?><a href="<?php echo esc_url( $base ); ?>">Tout afficher</a><?php endif; ?>
		</form>

		<?php if ( ! $rows ) : ?>
			<p><?php echo $f ? 'Aucune inscription ne correspond à ces filtres.' : 'Aucune inscription pour l\'instant.'; ?></p>
		<?php else : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="volski-grid">
			<input type="hidden" name="action" value="volski_grid">
			<?php if ( $front_url ) : ?><input type="hidden" name="volski_return" value="<?php echo esc_url( $base ); ?>"><?php endif; ?>
			<input type="hidden" name="back" value="<?php echo esc_attr( http_build_query( array_filter( array( 'vq' => $f['s'] ?? '', 'vcat' => $f['cat'] ?? '', 'vpay' => $f['paiement'] ?? '' ) ) ) ); ?>">
			<?php wp_nonce_field( 'volski_grid' ); ?>
			<div class="volski-grid-scroll">
			<table class="widefat volski-grid">
				<thead><tr>
					<th class="vg-name">Participant</th>
					<th>Catégorie</th>
					<?php foreach ( $opts as $o ) : ?><th class="vg-opt"><?php echo esc_html( $o['label'] ); ?></th><?php endforeach; ?>
					<th class="vg-paid">Payé</th>
					<th>Moyen</th>
					<th class="vg-total">Total <?php echo esc_html( volski_currency() ); ?></th>
					<th>Note du comité</th>
				</tr></thead>
				<tbody>
				<?php
				foreach ( $rows as $r ) :
					$id   = (int) $r['id'];
					$paid = 'paye' === $r['paiement'];
					$sum['du'] += (float) $r['total'];
					if ( $paid ) {
						$sum['paye']++;
						$sum['recu'] += (float) $r['total'];
					}
					?>
					<tr data-row>
						<td class="vg-name">
							<input type="hidden" name="rows[<?php echo $id; ?>][on]" value="1">
							<?php if ( $front_url ) : ?>
								<strong><?php echo esc_html( $r['prenom'] . ' ' . $r['nom'] ); ?></strong>
								<span class="volski-sub"><?php echo esc_html( volski_sexes()[ $r['sexe'] ] ?? '' ); ?> · <a href="mailto:<?php echo esc_attr( $r['email'] ); ?>"><?php echo esc_html( $r['email'] ); ?></a></span>
							<?php else : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=volski&action=edit&id=' . $id ) ); ?>"><strong><?php echo esc_html( $r['prenom'] . ' ' . $r['nom'] ); ?></strong></a>
							<?php endif; ?>
							<span class="volski-sub"><?php echo esc_html( $r['telephone'] ); ?></span>
						</td>
						<td class="vg-cat" data-label="Catégorie"><?php echo esc_html( volski_categories_label( $r['categories'] ) ); ?></td>
						<?php
						foreach ( $opts as $i => $o ) :
							$q             = volski_opt_qty( $r['options_choisies'], $o['label'] );
							$sum['opt'][ $i ] += $q;
							?>
							<td class="vg-opt" data-label="<?php echo esc_attr( $o['label'] ); ?>">
							<?php if ( 1 === $o['max'] ) : ?>
								<input type="checkbox" name="rows[<?php echo $id; ?>][opt][<?php echo (int) $i; ?>]" value="1" <?php checked( $q > 0 ); ?> aria-label="<?php echo esc_attr( $o['label'] . ' – ' . $r['prenom'] . ' ' . $r['nom'] ); ?>">
							<?php else : ?>
								<input type="number" min="0" max="<?php echo (int) $o['max']; ?>" class="vg-num" name="rows[<?php echo $id; ?>][opt][<?php echo (int) $i; ?>]" value="<?php echo (int) $q; ?>" aria-label="<?php echo esc_attr( $o['label'] . ' – ' . $r['prenom'] . ' ' . $r['nom'] ); ?>">
							<?php endif; ?>
							</td>
						<?php endforeach; ?>
						<td class="vg-paid" data-label="Payé"><input type="checkbox" name="rows[<?php echo $id; ?>][paye]" value="1" <?php checked( $paid ); ?> aria-label="<?php echo esc_attr( 'Payé – ' . $r['prenom'] . ' ' . $r['nom'] ); ?>"></td>
						<td class="vg-mode" data-label="Moyen"><select name="rows[<?php echo $id; ?>][mode]" aria-label="Moyen de paiement"><option value="">—</option><?php foreach ( $modes as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $r['paiement_mode'], $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></td>
						<td class="vg-total" data-label="Total <?php echo esc_attr( volski_currency() ); ?>"><input type="text" inputmode="decimal" class="vg-num vg-money" name="rows[<?php echo $id; ?>][total]" value="<?php echo esc_attr( number_format( (float) $r['total'], 2, '.', '' ) ); ?>" aria-label="Total"></td>
						<td class="vg-notecell" data-label="Note"><input type="text" class="vg-note" name="rows[<?php echo $id; ?>][note]" value="<?php echo esc_attr( $r['note_admin'] ); ?>" aria-label="Note du comité"></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
				<tfoot><tr>
					<th class="vg-name"><?php echo count( $rows ); ?> participant<?php echo count( $rows ) > 1 ? 's' : ''; ?></th>
					<th class="vg-empty"></th>
					<?php foreach ( $opts as $i => $o ) : ?><th class="vg-opt" data-label="<?php echo esc_attr( $o['label'] ); ?>"><?php echo (int) $sum['opt'][ $i ]; ?></th><?php endforeach; ?>
					<th class="vg-paid" data-label="Payés"><?php echo (int) $sum['paye']; ?> / <?php echo count( $rows ); ?></th>
					<th class="vg-empty"></th>
					<th class="vg-total" data-label="Encaissé"><?php echo esc_html( volski_money( $sum['recu'] ) ); ?> <span class="volski-sub">sur <?php echo esc_html( volski_money( $sum['du'] ) ); ?></span></th>
					<th class="vg-empty"></th>
				</tr></tfoot>
			</table>
			</div>
			<p class="volski-grid-save"><button type="submit" class="button button-primary button-hero">Enregistrer les modifications</button> <span id="volski-dirty" hidden>Non enregistré</span></p>
		</form>
		<script>
		(function () {
			var form = document.getElementById('volski-grid'), dirty = false, msg = document.getElementById('volski-dirty');
			form.addEventListener('input', mark); form.addEventListener('change', mark);
			function mark(e) {
				var tr = e.target.closest('tr[data-row]'); if (tr) tr.classList.add('is-changed');
				if (e.target.name && /\[total\]$/.test(e.target.name)) e.target.dataset.touched = '1';
				dirty = true; msg.hidden = false;
			}
			form.addEventListener('submit', function () {
				dirty = false;
				// N'envoie que les lignes modifiées (plus rapide, et évite d'écraser le travail d'un autre membre).
				form.querySelectorAll('tr[data-row]:not(.is-changed)').forEach(function (tr) { tr.querySelectorAll('input,select').forEach(function (i) { i.disabled = true; }); });
				form.querySelectorAll('input[data-touched]').forEach(function (i) {
					var h = document.createElement('input'); h.type = 'hidden'; h.name = i.name.replace('[total]', '[total_manuel]'); h.value = '1'; form.appendChild(h);
				});
			});
			window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
		})();
		</script>
		<?php endif; ?>
	</div>
	<?php
}

function volski_grid_save() {
	volski_check( 'volski_grid' );
	global $wpdb;
	$post  = wp_unslash( $_POST );
	$modes = volski_modes();
	$n     = 0;
	foreach ( (array) ( $post['rows'] ?? array() ) as $id => $row ) {
		$id = absint( $id );
		$r  = $id ? volski_get( $id ) : null;
		if ( ! $r || empty( $row['on'] ) ) {
			continue;
		}
		$picked  = volski_pick_options( isset( $row['opt'] ) && is_array( $row['opt'] ) ? $row['opt'] : array() );
		$new_opt = wp_json_encode( $picked );
		$old     = array();
		foreach ( (array) json_decode( (string) $r['options_choisies'], true ) as $o ) {
			$old[ $o['label'] ] = (int) $o['qty'];
		}
		$now = array();
		foreach ( $picked as $o ) {
			$now[ $o['label'] ] = (int) $o['qty'];
		}
		ksort( $old );
		ksort( $now );
		$opts_changed = $old !== $now;

		$total = (float) volski_parse_amount( $row['total'] ?? $r['total'] );
		if ( $opts_changed && empty( $row['total_manuel'] ) ) {
			$total = volski_total( $r['categories'], $picked );
		}
		$mode = sanitize_key( $row['mode'] ?? '' );
		$data = array(
			'paiement'      => empty( $row['paye'] ) ? 'a_payer' : 'paye',
			'paiement_mode' => isset( $modes[ $mode ] ) ? $mode : '',
			'total'         => $total,
			'note_admin'    => mb_substr( sanitize_text_field( $row['note'] ?? '' ), 0, 2000 ),
		);
		if ( $opts_changed ) {
			$data['options_choisies'] = $new_opt;
		}
		$wpdb->update( volski_table(), $data, array( 'id' => $id ) );
		$n++;
	}
	$back = array();
	parse_str( (string) ( $post['back'] ?? '' ), $back );
	volski_back( array_merge( array_map( 'sanitize_text_field', (array) $back ), array( 'msg' => 'grid', 'n' => $n ) ), 'volski-suivi' );
}
