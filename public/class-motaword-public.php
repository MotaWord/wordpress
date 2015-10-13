<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://www.motaword.com/developer
 * @since      1.0.0
 *
 * @package    motaword
 * @subpackage motaword/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    motaword
 * @subpackage motaword/public
 * @author     Oytun Tez <oytun@motaword.com>
 */
class MotaWord_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $motaword The ID of this plugin.
	 */
	private $motaword;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $version The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 *
	 * @param      string $motaword The name of the plugin.
	 * @param      string $version The version of this plugin.
	 */
	public function __construct( $motaword, $version ) {

		$this->motaword = $motaword;
		$this->version  = $version;

	}

	public function open_callback_endpoint() {
		global $motawordPlugin;

		$callbackEndpoint = ! ! $motawordPlugin ? MotaWord::getCallbackEndpoint() : 'mw-callback';

		add_rewrite_endpoint( $callbackEndpoint, EP_PERMALINK );
	}

	public function handle_callback() {
		global $motawordPlugin;

		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

		if ( !function_exists('is_plugin_active')
		     || ! is_plugin_active( !!$motawordPlugin ? $motawordPlugin->getPluginFile() : 'motaword/motaword.php' ) ) {
			return false;
		}

		$callbackEndpoint = ! ! $motawordPlugin ? $motawordPlugin->getCallbackEndpoint() : 'mw-callback';

		if ( isset( $_GET[ $callbackEndpoint ] ) ) {
			if ( isset( $_POST['type'] )
			     && isset( $_POST['project'] )
			     && isset( $_POST['action'] )
			     && $this->process_callback( sanitize_text_field($_POST['action']), $_POST['project'] )
			) {
				echo json_encode( array( 'status' => 'success' ) );
			}

			exit();
		}
	}

	protected function process_callback( $action, $project ) {
		if ( ! $action || ( $action !== 'completed' ) || ! $project ) {
			return false;
		}

		$mwProjectId = intval(sanitize_text_field($project['id']));

		if(!$mwProjectId) {
			return false;
		}

		$mwApiHelper = new MotaWord_API(
			get_option( MotaWord_Admin::$options['client_id'] ),
			get_option( MotaWord_Admin::$options['client_secret'] ),
			(bool) get_option( MotaWord_Admin::$options['sandbox'] )
		);

		//$wpPostId = $project['custom']['wp_post_id'];
		$DBHelper = new MotaWord_DB( MotaWord::getProjectsTableName() );
		$wpPostId = $DBHelper->getPostIDByProjectID( $mwProjectId, 'any' );

		$translatedProject = $mwApiHelper->downloadProject( $mwProjectId );

		// Update Post
		$updated_post = array(
			'ID'           => $wpPostId,
			'post_title'   => $translatedProject->TITLE,
			'post_content' => $translatedProject->CONTENT,
			'post_excerpt' => $translatedProject->EXCERPT
		);

		$translatedArray = (array) $translatedProject;

		foreach ( $translatedArray as $key => $val ) {
			// Update Meta Value
			if ( strpos( $key, 'CUSTOMFIELD_' ) ) {
				$metakeyArr = explode( "CUSTOMFIELD_", $key );
				update_post_meta( $wpPostId, $metakeyArr[1], $val );
			}

			// Update Media Posts
			/*
            if(strpos($key,'ATTACHMENT_'))
            {
                $mediakeyArr = explode("_",$key);

                // Update Post
                $updated_post = array(
                    'ID'           => $$mediakeyArr[1],
                    'post_title'   => $translatedProject->title,
                    'post_content' => $translatedProject->content,
                    'post_excerpt' => $translatedProject->excerpt
                );

                update_post_meta($wpPostId, $metakeyArr[1], $val);
            }
            */
		}

		$result = wp_update_post( $updated_post, true );

		$DBHelper->updateProject( $wpPostId, 'completed', 100, 100 );

		if ( (int) $result > 0 ) {
			return true;
		}

		return false;
	}

}
