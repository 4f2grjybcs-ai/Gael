<?php
/**
 * Page d'événement complète : shortcode [volski_evenement].
 * Bannière, présentation, programme, infos pratiques, tarifs, options, puis le formulaire.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'volski_evenement', 'volski_event_shortcode' );

/** Texte libre en paragraphes (sans HTML saisi). */
function volski_paras( $text ) {
	return wpautop( esc_html( trim( (string) $text ) ) );
}

/**
 * Programme : une ligne par moment. « # Samedi 14 mars » fait un titre de jour,
 * « 8h00 : Briefing » met l'heure en évidence.
 */
function volski_programme_html( $text ) {
	$html = '';
	$open = false;
	foreach ( preg_split( '/\R/', trim( (string) $text ) ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		if ( '#' === $line[0] ) {
			if ( $open ) {
				$html .= '</ol>';
			}
			$html .= '<h4 class="volski-day">' . esc_html( trim( ltrim( $line, '#' ) ) ) . '</h4><ol class="volski-prog">';
			$open  = true;
			continue;
		}
		if ( ! $open ) {
			$html .= '<ol class="volski-prog">';
			$open  = true;
		}
		if ( preg_match( '/^(.{1,25}?)\s+[:–-]\s+(.+)$/u', $line, $m ) ) {
			$html .= '<li><span class="volski-time">' . esc_html( $m[1] ) . '</span><span>' . esc_html( $m[2] ) . '</span></li>';
		} else {
			$html .= '<li><span class="volski-time"></span><span>' . esc_html( $line ) . '</span></li>';
		}
	}
	return $html . ( $open ? '</ol>' : '' );
}

function volski_event_shortcode() {
	wp_enqueue_style( 'volski' );
	wp_enqueue_script( 'volski' );
	$s     = volski_settings();
	$opts  = volski_options( $s );
	$cats  = volski_categories();
	ob_start();
	?>
	<div class="volski volski-event">
		<header class="volski-hero<?php echo $s['image_url'] ? ' has-image' : ''; ?>"<?php if ( $s['image_url'] ) : ?> style="--volski-hero:url('<?php echo esc_url( $s['image_url'] ); ?>')"<?php endif; ?>>
			<div class="volski-hero-inner">
				<p class="volski-kicker">Compétition · Vol &amp; Ski</p>
				<h2 class="volski-title"><?php echo esc_html( $s['competition'] ); ?></h2>
				<ul class="volski-facts">
					<?php if ( $s['date_txt'] ) : ?><li><span>Quand</span><?php echo esc_html( $s['date_txt'] ); ?></li><?php endif; ?>
					<?php if ( $s['lieu'] ) : ?><li><span>Où</span><?php echo esc_html( $s['lieu'] ); ?></li><?php endif; ?>
					<li><span>Catégories</span><?php echo esc_html( implode( ' · ', $cats ) ); ?></li>
				</ul>
				<?php $liste = volski_liste_url(); ?>
				<?php if ( $liste ) : ?>
					<a class="volski-btn volski-btn-hero" href="<?php echo esc_url( $liste ); ?>">Liste des inscrits<?php $n = volski_count_all(); echo $n ? ' <span class="volski-btn-count">' . (int) $n . '</span>' : ''; // phpcs:ignore ?></a>
				<?php endif; ?>
				<?php if ( empty( $s['ouvert'] ) ) : ?>
					<p class="volski-closed">Inscriptions fermées</p>
				<?php endif; ?>
			</div>
		</header>

		<?php if ( trim( $s['presentation'] ) ) : ?>
		<section class="volski-sec volski-intro"><?php echo volski_paras( $s['presentation'] ); // phpcs:ignore ?></section>
		<?php endif; ?>

		<?php if ( trim( $s['programme'] ) ) : ?>
		<section class="volski-sec">
			<h3>Programme</h3>
			<?php echo volski_programme_html( $s['programme'] ); // phpcs:ignore ?>
		</section>
		<?php endif; ?>

		<?php if ( trim( $s['infos'] ) || $s['adresse'] || $s['contact'] ) : ?>
		<section class="volski-sec">
			<h3>Infos pratiques</h3>
			<div class="volski-info-grid">
				<?php if ( trim( $s['infos'] ) ) : ?><div class="volski-info-text"><?php echo volski_paras( $s['infos'] ); // phpcs:ignore ?></div><?php endif; ?>
				<?php if ( $s['adresse'] || $s['contact'] ) : ?>
				<dl class="volski-info-side">
					<?php if ( $s['adresse'] ) : ?>
						<dt>Rendez-vous</dt>
						<dd><?php echo nl2br( esc_html( $s['adresse'] ) ); ?><br><a href="<?php echo esc_url( 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $s['adresse'] ) ); ?>" target="_blank" rel="noopener">Voir sur la carte</a></dd>
					<?php endif; ?>
					<?php if ( $s['contact'] ) : ?>
						<dt>Contact</dt>
						<dd><?php echo nl2br( esc_html( $s['contact'] ) ); ?></dd>
					<?php endif; ?>
				</dl>
				<?php endif; ?>
			</div>
		</section>
		<?php endif; ?>

		<?php if ( $opts ) : ?>
		<?php
		$state       = $GLOBALS['volski_form_state'];
		$done        = isset( $_GET['volski'] ) && 'merci' === $_GET['volski']; // phpcs:ignore WordPress.Security.NonceVerification
		$interactive = ! empty( $s['ouvert'] ) && ! $done;
		?>
		<section class="volski-sec volski-sec-tarifs">
			<h3>Tarifs</h3>
			<?php if ( $interactive ) : ?><p class="volski-help">Touche les cartes pour composer ton inscription.</p><?php endif; ?>
			<?php echo volski_tarif_cards( $s, $state && isset( $state['values']['opt'] ) && is_array( $state['values']['opt'] ) ? $state['values']['opt'] : array(), $interactive ); // phpcs:ignore ?>
			<?php if ( $interactive ) : ?><?php echo volski_total_box(); // phpcs:ignore ?><?php endif; ?>
			<?php if ( $s['tarif_note'] ) : ?><p class="volski-help"><?php echo esc_html( $s['tarif_note'] ); ?></p><?php endif; ?>
		</section>
		<?php endif; ?>

		<section class="volski-sec volski-sec-form">
			<h3>Inscription</h3>
			<?php
			$GLOBALS['volski_cards_outside'] = (bool) $opts; // les tarifs sont déjà dans la section du dessus
			echo volski_form_shortcode(); // phpcs:ignore
			$GLOBALS['volski_cards_outside'] = false;
			?>
		</section>
	</div>
	<?php
	return ob_get_clean();
}
