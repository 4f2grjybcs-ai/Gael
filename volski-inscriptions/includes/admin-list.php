<?php
/**
 * Tableau des inscriptions dans l'admin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Volski_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'inscription',
				'plural'   => 'inscriptions',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox">',
			'nom'        => 'Participant',
			'categories' => 'Catégorie(s)',
			'contact'    => 'Contact',
			'urgence'    => 'Urgence',
			'paiement'   => 'Paiement',
			'created_at' => 'Inscrit le',
		);
	}

	protected function get_sortable_columns() {
		return array(
			'nom'        => array( 'nom', false ),
			'paiement'   => array( 'paiement', false ),
			'created_at' => array( 'created_at', true ),
		);
	}

	protected function get_bulk_actions() {
		return array(
			'paye'      => 'Marquer payé',
			'a_payer'   => 'Marquer à payer',
			'supprimer' => 'Supprimer',
		);
	}

	public function process_bulk_action() {
		$action = $this->current_action();
		if ( ! $action || empty( $_REQUEST['ids'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		check_admin_referer( 'bulk-inscriptions' );
		if ( ! current_user_can( VOLSKI_CAP ) ) {
			wp_die( 'Accès refusé.', 403 );
		}
		global $wpdb;
		$ids = array_filter( array_map( 'absint', (array) $_REQUEST['ids'] ) );
		foreach ( $ids as $id ) {
			if ( 'supprimer' === $action ) {
				$wpdb->delete( volski_table(), array( 'id' => $id ) );
			} elseif ( isset( volski_paiements()[ $action ] ) ) {
				$wpdb->update( volski_table(), array( 'paiement' => $action ), array( 'id' => $id ) );
			}
		}
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( count( $ids ) . ' inscription(s) mise(s) à jour.' ) . '</p></div>';
	}

	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$f   = volski_current_filters();
		$sel = function ( $name, $label, $opts ) use ( $f ) {
			echo '<label class="screen-reader-text" for="vs-f-' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label>';
			echo '<select name="' . esc_attr( $name ) . '" id="vs-f-' . esc_attr( $name ) . '"><option value="">' . esc_html( $label ) . '</option>';
			foreach ( $opts as $k => $l ) {
				echo '<option value="' . esc_attr( $k ) . '"' . selected( $f[ $name ] ?? '', $k, false ) . '>' . esc_html( $l ) . '</option>';
			}
			echo '</select>';
		};
		echo '<div class="alignleft actions">';
		$sel( 'cat', 'Toutes catégories', volski_categories() );
		$sel( 'sexe', 'Homme et femme', volski_sexes() );
		$sel( 'paiement', 'Tous paiements', volski_paiements() );
		submit_button( 'Filtrer', '', 'filtrer', false );
		echo '</div>';
	}

	public function prepare_items() {
		global $wpdb;
		$per   = 50;
		$page  = $this->get_pagenum();
		$where = volski_where( volski_current_filters() );
		// phpcs:disable WordPress.Security.NonceVerification
		$orderby = isset( $_GET['orderby'] ) && in_array( $_GET['orderby'], array( 'nom', 'paiement', 'created_at' ), true ) ? sanitize_key( $_GET['orderby'] ) : 'created_at';
		$order   = isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_key( $_GET['order'] ) ) ? 'ASC' : 'DESC';
		// phpcs:enable
		$sort  = 'nom' === $orderby ? "nom $order, prenom $order" : "$orderby $order";
		$t     = volski_table();
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE $where" ); // phpcs:ignore
		$this->items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE $where ORDER BY $sort LIMIT %d OFFSET %d", $per, ( $page - 1 ) * $per ), ARRAY_A ); // phpcs:ignore
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'nom' );
		$this->set_pagination_args( array( 'total_items' => $total, 'per_page' => $per ) );
	}

	public function no_items() {
		echo 'Aucune inscription pour l\'instant. Elles apparaîtront ici dès que le formulaire sera rempli.';
	}

	protected function column_cb( $r ) {
		return '<input type="checkbox" name="ids[]" value="' . (int) $r['id'] . '">';
	}

	protected function column_nom( $r ) {
		$edit = admin_url( 'admin.php?page=volski&action=edit&id=' . (int) $r['id'] );
		$out  = '<strong><a class="row-title" href="' . esc_url( $edit ) . '">' . esc_html( $r['prenom'] . ' ' . $r['nom'] ) . '</a></strong>';
		$out .= '<span class="volski-sub">' . esc_html( volski_sexes()[ $r['sexe'] ] ?? '' ) . '</span>';
		if ( '' !== $r['note_admin'] ) {
			$out .= '<span class="volski-note" title="' . esc_attr( $r['note_admin'] ) . '">Note : ' . esc_html( wp_trim_words( $r['note_admin'], 8 ) ) . '</span>';
		}
		$pay  = wp_nonce_url( admin_url( 'admin-post.php?action=volski_paiement&id=' . (int) $r['id'] ), 'volski_paiement_' . (int) $r['id'] );
		$del  = wp_nonce_url( admin_url( 'admin-post.php?action=volski_delete&id=' . (int) $r['id'] ), 'volski_delete_' . (int) $r['id'] );
		$acts = array(
			'edit'     => '<a href="' . esc_url( $edit ) . '">Ouvrir</a>',
			'paiement' => '<a href="' . esc_url( $pay ) . '">' . ( 'paye' === $r['paiement'] ? 'Marquer à payer' : 'Marquer payé' ) . '</a>',
			'delete'   => '<a class="submitdelete" href="' . esc_url( $del ) . '" onclick="return window.confirm(\'Supprimer cette inscription ?\');">Supprimer</a>',
		);
		return $out . $this->row_actions( $acts );
	}

	protected function column_categories( $r ) {
		$out = '';
		foreach ( array_filter( explode( ',', $r['categories'] ) ) as $k ) {
			$out .= '<span class="volski-chip volski-chip-' . esc_attr( $k ) . '">' . esc_html( volski_categories()[ $k ] ?? $k ) . '</span> ';
		}
		if ( '' !== $r['passager'] ) {
			$out .= '<span class="volski-sub">Passager : ' . esc_html( $r['passager'] ) . '</span>';
		}
		return $out;
	}

	protected function column_contact( $r ) {
		return '<a href="mailto:' . esc_attr( $r['email'] ) . '">' . esc_html( $r['email'] ) . '</a><span class="volski-sub"><a href="tel:' . esc_attr( preg_replace( '/[^\d+]/', '', $r['telephone'] ) ) . '">' . esc_html( $r['telephone'] ) . '</a></span>';
	}

	protected function column_urgence( $r ) {
		return esc_html( $r['urgence_nom'] ) . '<span class="volski-sub">' . esc_html( $r['urgence_tel'] ) . '</span>';
	}

	protected function column_paiement( $r ) {
		$paid = 'paye' === $r['paiement'];
		$out = '<span class="volski-pay ' . ( $paid ? 'is-paid' : 'is-due' ) . '">' . esc_html( volski_paiements()[ $r['paiement'] ] ?? $r['paiement'] ) . '</span>';
		if ( $paid && '' !== $r['paiement_mode'] ) {
			$out .= ' <span class="volski-sub-inline">' . esc_html( volski_modes()[ $r['paiement_mode'] ] ?? '' ) . '</span>';
		}
		if ( (float) $r['total'] > 0 ) {
			$out .= '<span class="volski-sub">' . esc_html( volski_money( $r['total'] ) ) . '</span>';
		}
		$opts = volski_options_label( $r['options_choisies'] );
		if ( '' !== $opts ) {
			$out .= '<span class="volski-sub">' . esc_html( $opts ) . '</span>';
		}
		return $out;
	}

	protected function column_created_at( $r ) {
		return esc_html( mysql2date( 'd/m/Y', $r['created_at'] ) ) . '<span class="volski-sub">' . esc_html( mysql2date( 'H:i', $r['created_at'] ) ) . '</span>';
	}
}
