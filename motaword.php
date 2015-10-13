<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://www.motaword.com/developer
 * @package           MotaWord
 *
 * @wordpress-plugin
 * Plugin Name:       MotaWord
 * Plugin URI:        https://www.motaword.com/developer
 * Description:       MotaWord plugin allows you to seamlessly submit your posts for translation to MotaWord.
 * Version:           1.0.0
 * Author:            Oytun Tez <oytun@motaword.com>
 * Author URI:        https://www.motaword.com/developer
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       motaword
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-motaword.php';

/**
 * The code that runs during plugin activation.
 *
 * @param string $plugin		Plugin (file) name
 */
function activated_motaword( $plugin ) {
	if ( strpos($plugin, '/motaword.php') > -1 && ! isset( $_GET['activate-multi'] ) ) {
		wp_redirect( admin_url( 'options-general.php?page=motaword&activated=1' ) );
		exit();
	}
}

// Can't use register_activation_hook as wp_redirect is triggered before the actual plugin activation.
add_action( 'activated_plugin', 'activated_motaword' );

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 */
function initializeMotaWord() {
	$plugin = new MotaWord(plugin_basename(__FILE__));
	$plugin->run();

	return $plugin;
}

$motawordPlugin = initializeMotaWord();