<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://www.motaword.com/developer
 * @since      1.0.0
 *
 * @package    motaword
 * @subpackage motaword/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    motaword
 * @subpackage motaword/includes
 * @author     Oytun Tez <oytun@motaword.com>
 */
class MotaWord {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      motaword_Loader $loader Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string $motaword The string used to uniquely identify this plugin.
	 */
	protected $motaword;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string $version The current version of the plugin.
	 */
	protected $version;

	/**
	 * @var MotaWord_i18n
	 */
	protected $i18n;

	/**
	 * Callback URL parameter name. Used such:
	 *          wordpress.com/?$callbackEndpoint=1
	 *
	 * @var string
	 */
	protected static $callbackEndpoint = 'mw-callback';
	/**
	 * Database table name to store MotaWord projects.
	 *
	 * @warning A separate table for MW projects is not enabled by default.
	 *
	 * @var string
	 */
	protected static $projectsTableName = 'motaword_projects';
	/**
	 * Settings key used in register_setting.
	 *
	 * @var string
	 */
	protected static $optionsKey = 'motaword_options';

	protected $pluginFile = 'motaword/motaword.php';

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @param string $pluginName
	 *
	 * @since    1.0.0
	 */
	public function __construct($pluginName = 'motaword/motaword.php') {

		$this->motaword = 'motaword';
		$this->version  = '1.0.0';

		$this->setPluginFile($pluginName);
		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - MotaWord_Loader. Orchestrates the hooks of the plugin.
	 * - MotaWord_i18n. Defines internationalization functionality.
	 * - MotaWord_Admin. Defines all hooks for the admin area.
	 * - MotaWord_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {
		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-motaword-loader.php';

		/**
		 * The class responsible for db interactions
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-motaword-db.php';

		/**
		 * The class responsible for motaword api interactions
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-motaword-api.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-motaword-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-motaword-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-motaword-public.php';

		$this->loader = new MotaWord_Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the motaword_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {
		$this->i18n = new MotaWord_i18n();
		$this->loader->add_action( 'plugins_loaded', $this->i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {
		$plugin_admin = new MotaWord_Admin( $this->get_motaword(), $this->get_version() );

		// Admin dependencies
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		// MotaWord call to actions
		$this->loader->add_action( 'add_meta_boxes', $plugin_admin, 'add_meta_boxes' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'register_my_custom_menu_page' );
		// @note admin_init also initializes post columns.
		$this->loader->add_action( 'admin_init', $plugin_admin, 'admin_init' );
		$this->loader->add_filter( 'plugin_action_links_'.$this->getPluginFile(), $plugin_admin, 'add_plugin_links' );

		// Add actions to post listing page.
		$this->loader->add_action( 'load-edit.php', $plugin_admin, 'add_bulk_thickbox' );
		$this->loader->add_action( 'admin_footer-edit.php', $plugin_admin, 'bulk_action' );

		// Quote and project functionality
		$this->loader->add_action( 'wp_ajax_mw_get_quote', $plugin_admin, 'get_quote' );
		$this->loader->add_action( 'wp_ajax_mw_prepare_bulk_quote', $plugin_admin, 'prepare_bulk_quote' );
		$this->loader->add_action( 'wp_ajax_mw_get_bulk_quote', $plugin_admin, 'get_quote' );
		$this->loader->add_action( 'wp_ajax_mw_submit_quote', $plugin_admin, 'start_project' );
		$this->loader->add_action( 'admin_action_mw_callback', $plugin_admin, 'callback' );

		$this->loader->add_filter( 'manage_posts_columns', $plugin_admin, 'init_columns', 9999, 2 );
		$this->loader->add_action( 'manage_posts_custom_column', $plugin_admin, 'modify_column', 2, 2 );

		$this->loader->add_filter( 'manage_pages_columns', $plugin_admin, 'init_columns', 9999, 2 );
		$this->loader->add_action( 'manage_pages_custom_column', $plugin_admin, 'modify_column', 2, 2 );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {
		//@todo Can we find a better alternative to the callback flow?
		// I don't want to run this block for each frontend request.
		$plugin_public = new MotaWord_Public( $this->get_motaword(), $this->get_version() );

		$this->loader->add_action( 'init', $plugin_public, 'open_callback_endpoint' );
		$this->loader->add_action( 'template_redirect', $plugin_public, 'handle_callback' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_motaword() {
		return $this->motaword;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    motaword_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Callback URL parameter name. Used such:
	 *          wordpress.com/?$callbackEndpoint=1
	 *
	 * @default mw-callback
	 *
	 * @return string
	 */
	public static function getCallbackEndpoint() {
		return static::$callbackEndpoint;
	}

	/**
	 * Database table name to store MotaWord projects.
	 *
	 * @warning A separate table for MW projects is not enabled by default.
	 *
	 * @return string
	 */
	public static function getProjectsTableName() {
		return static::$projectsTableName;
	}

	/**
	 * Settings key used in register_setting.
	 *
	 * @return string
	 */
	public static function getOptionsKey() {
		return static::$optionsKey;
	}

	public function setPluginFile($name) {
		$this->pluginFile = $name;

		return true;
	}

	public function getPluginFile() {
		return $this->pluginFile;
	}


}
