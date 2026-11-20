<?php
/**
 * E-mails automatiques : confirmation au participant, alerte à l'organisateur.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Récapitulatif texte d'une inscription. */
function volski_recap( $r ) {
	$lines = array(
		'Nom : ' . $r['prenom'] . ' ' . $r['nom'],
		'E-mail : ' . $r['email'],
		'Téléphone : ' . $r['telephone'],
		'Sexe : ' . ( volski_sexes()[ $r['sexe'] ] ?? $r['sexe'] ),
		'Catégorie : ' . volski_categories_label( $r['categories'] ),
	);
	if ( '' !== $r['passager'] ) {
		$lines[] = 'Passager biplace : ' . $r['passager'];
	}
	$lines[] = 'Contact d\'urgence : ' . $r['urgence_nom'] . ' – ' . $r['urgence_tel'];
	$opts    = json_decode( (string) ( $r['options_choisies'] ?? '' ), true );
	if ( $opts ) {
		$lines[] = 'Options :';
		foreach ( $opts as $o ) {
			$lines[] = '  - ' . $o['label'] . ( (int) $o['qty'] > 1 ? ' ×' . (int) $o['qty'] : '' ) . ( $o['prix'] > 0 ? ' : ' . volski_money( $o['prix'] * $o['qty'] ) : '' );
		}
	}
	if ( isset( $r['total'] ) && (float) $r['total'] > 0 ) {
		$lines[] = 'Total à régler : ' . volski_money( $r['total'] ) . ' (paiement à finaliser avec l\'organisation)';
	}
	if ( '' !== $r['commentaire'] ) {
		$lines[] = 'Commentaire : ' . $r['commentaire'];
	}
	return implode( "\n", $lines );
}

function volski_send_emails( $r ) {
	$s    = volski_settings();
	$name = $s['competition'];
	$orga = is_email( $s['email_orga'] ) ? $s['email_orga'] : get_option( 'admin_email' );

	// Au participant. Les réponses partent vers l'organisateur.
	$body = $s['message_confirm'] . "\n\n— Récapitulatif de ton inscription —\n" . volski_recap( $r );
	if ( '' !== trim( $s['infos'] ) ) {
		$body .= "\n\n" . $s['infos'];
	}
	wp_mail( $r['email'], 'Inscription confirmée – ' . $name, $body, array( 'Reply-To: ' . $orga ) );

	// À l'organisateur.
	$link = admin_url( 'admin.php?page=volski&action=edit&id=' . (int) $r['id'] );
	wp_mail(
		$orga,
		'Nouvelle inscription : ' . $r['prenom'] . ' ' . $r['nom'] . ' (' . volski_categories_label( $r['categories'] ) . ')',
		volski_recap( $r ) . "\n\nVoir l'inscription : " . $link,
		array( 'Reply-To: ' . $r['prenom'] . ' ' . $r['nom'] . ' <' . $r['email'] . '>' )
	);
}
