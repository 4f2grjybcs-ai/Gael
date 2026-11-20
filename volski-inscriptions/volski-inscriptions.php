<?php
/**
 * Plugin Name:       Inscriptions Vol & Ski
 * Description:       Formulaire d'inscription à une compétition de vol et ski (Solo, Biplace, Parakite), liste des inscrits dans l'admin avec export Excel, e-mails automatiques et liste publique.
 * Version:           1.9.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Gael
 * License:           GPL-2.0-or-later
 * Text Domain:       volski
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VOLSKI_VERSION', '1.9.0' );
define( 'VOLSKI_DB_VERSION', '4' );
define( 'VOLSKI_FILE', __FILE__ );
define( 'VOLSKI_DIR', plugin_dir_path( __FILE__ ) );
define( 'VOLSKI_URL', plugin_dir_url( __FILE__ ) );

define( 'VOLSKI_CAP', 'volski_manage' );
define( 'VOLSKI_ROLE', 'volski_comite' );

require_once VOLSKI_DIR . 'includes/data.php';
require_once VOLSKI_DIR . 'includes/comite.php';
require_once VOLSKI_DIR . 'includes/install.php';
require_once VOLSKI_DIR . 'includes/emails.php';
require_once VOLSKI_DIR . 'includes/form.php';
require_once VOLSKI_DIR . 'includes/public-list.php';
require_once VOLSKI_DIR . 'includes/event.php';
require_once VOLSKI_DIR . 'includes/comite-front.php';
// Les écrans de gestion servent aussi dans l'espace comité du site.
require_once VOLSKI_DIR . 'includes/admin.php';

register_activation_hook( __FILE__, 'volski_activate' );
add_action( 'plugins_loaded', 'volski_maybe_upgrade' );
