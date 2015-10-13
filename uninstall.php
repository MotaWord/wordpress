<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package    motaword
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! current_user_can( 'activate_plugins' ) )
	exit;

check_admin_referer( 'bulk-plugins' );

// Important: Check if the file is the one
// that was registered during the uninstall hook.
if ( strpos(WP_UNINSTALL_PLUGIN, 'motaword.php') < 0 )
	exit;

require plugin_dir_path( __FILE__ ) . 'includes/class-motaword.php';

$plugin = new MotaWord(plugin_basename(__FILE__));
$plugin->run();

MotaWord_API::clear_cache();
delete_option(MotaWord_Admin::$options['client_id']);
delete_option(MotaWord_Admin::$options['client_secret']);
delete_option(MotaWord_Admin::$options['sandbox']);