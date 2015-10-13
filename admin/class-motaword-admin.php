<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    motaword
 * @subpackage motaword/admin
 * @author     Oytun Tez <oytun@motaword.com>
 */
class MotaWord_Admin {
	/**
	 * @var MotaWord_API
	 */
	protected $api;
	/**
	 * @var MotaWord_DB
	 */
	protected $db;
	/**
	 * Is Polylang plugin installed?
	 *
	 * @var bool
	 */
	protected $isPolylang = false;
	/**
	 * Should we show the source language dropdown? Or are we going to interfere it from other sources?
	 *
	 * @var bool
	 */
	protected $showSourceLanguage = false;
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
	 * Progress cache for API calls, per request. This is not a persistent cache.
	 *
	 * @var array
	 */
	private $cache = array();
	/**
	 * Did we put MotaWord image on the list row for this post? A list of postID => true.
	 * When true, we will put a <span> to align Polylang icons:
	 *
	 *      This helps to style a row from this:
	 *          http://prntscr.com/8o0yk5
	 *      to this:
	 *          http://prntscr.com/8o10kd
	 * @var array
	 */
	private $columnList = array();

	/**
	 * Should we exit when an error occurs? If we exit, we will out print our logo image.
	 * @var bool
	 */
	private $exitOnError = false;
	/**
	 * When true, we will show an error icon instead of a message. This icon will have the message as a title.
	 * @var bool
	 */
	private $iconOnError = false;
	/**
	 * A list of language codes.
	 *
	 * @see includes/language-map.php
	 * @var array
	 */
	private $languages = array();
	/**
	 * A flipped list of language codes.
	 *
	 * @see includes/language-map.php
	 * @var array
	 */
	private $reversedLanguages = array();

	public static $options = array(
		'client_id'     => 'mw_api_client_id',
		'client_secret' => 'mw_api_client_secret',
		'sandbox'       => 'mw_is_sandbox'
	);

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 *
	 * @param      string $motaword The name of this plugin.
	 * @param      string $version The version of this plugin.
	 */
	public function __construct( $motaword, $version ) {
		$this->motaword = $motaword;
		$this->version  = $version;

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
		}

		if ( is_plugin_active( 'polylang/polylang.php' ) ) {
			$this->isPolylang = true;
		}

		if(!!get_option( static::$options['sandbox'] )) {
			MotaWord_DB::$meta_prefix = MotaWord_DB::$meta_prefix.'sandbox_';
		}
	}

	/**
	 * Register the stylesheets for the admin area.
	 */
	public function enqueue_styles() {
		wp_enqueue_style( $this->motaword, plugin_dir_url( __FILE__ ) . 'css/motaword-admin.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the admin area.
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( $this->motaword, plugin_dir_url( __FILE__ ) . 'js/motaword-admin.js', array('jquery'), $this->version, false );
	}

	public function add_meta_boxes( $post_type ) {
		add_meta_box( 'motaword_box', __( 'MotaWord', 'motaword' ), array(
			&$this,
			'show_side_box'
		), $post_type, 'side', 'high' );
	}

	/**
	 * designate MotaWord admin plugin box content
	 */
	public function show_side_box() {
		global $post;

		$this->exitOnError = false;

		if ( ! get_option( static::$options['client_id'] ) || ! get_option( static::$options['client_secret'] ) ) {
			$this->credentials_error();

			return false;
		}

		/**
		 * current wordpress post id
		 */
		$wpPostId = $post->ID;

		// Is this post type supported by Polylang? If not, we'll fall back to regular non-i18n workflow.
		if ( function_exists( 'pll_is_translated_post_type' ) ) {
			$postType = get_post_type( $wpPostId );

			if ( ! pll_is_translated_post_type( $postType ) ) {
				$this->isPolylang = false;
			}
		}

		$languages = $this->get_languages();

		/**
		 * if motaword credentials are not correct, $languages will return an error
		 */
		if ( ! is_array( $languages ) ) {
			/**
			 * user credentials are wrong
			 */
			$this->credentials_error();

			return false;
		}

		/**
		 * prepare new project approve dialog
		 */
		add_thickbox();

		$progress           = $this->get_progress_partial( $wpPostId );
		$mainSourceLanguage = null;

		if ( isset( $progress['projects'] ) && isset( $progress['projects'][0]->source_language ) ) {
			if ( ! $this->isPolylang ) {
				echo $progress['html'];

				return true;
			}

			$mainSourceLanguage = @$progress['projects'][0]->source_language;
		}

		/** @var Array $sourceLanguages */
		$sourceLanguages = $languages;
		/** @var Array $targetLanguages */
		$targetLanguages = $languages;

		if ( ! ! isset( $progress['languages'] ) ) {
			$targetLanguages = array_filter( $targetLanguages, function ( $language ) use ( $progress ) {
				if ( ( in_array( $language->code, $progress['languages'] )
				       || @$progress['projects'][0]->source_language === $language->code )
				) {
					return false;
				}

				return true;
			} );
		}

		// Polylang specific modifications
		if ( $this->isPolylang ) {
			// Get Polylang's Default Language
			$pll_main_lang = null;
			$pll_languages = array();

			if ( function_exists( 'pll_get_post_language' ) ) {
				$pll_main_lang = pll_get_post_language( $wpPostId );
			}

			if ( function_exists( 'pll_languages_list' ) ) {
				$pll_languages = pll_languages_list();
			}

			$mainSourceLanguage = $this->mapLanguageCode( $pll_main_lang );
			$pll_mw_languages   = array_map( array( $this, 'mapLanguageCode' ), $pll_languages );

			$targetLanguages = array_filter( $targetLanguages, function ( $language ) use ( $progress, $pll_mw_languages ) {
				if ( ! in_array( $language->code, $pll_mw_languages ) ) {
					return false;
				}

				return true;
			} );

			// If there is left no other languages supported by Polylang configuration to translate...
			if ( count( $pll_languages ) < 2 || count( $targetLanguages ) < 1 ) {

				if ( ! $progress ) {
					echo '<div class="mw_warning">' . __( 'Save this post in order to send to MotaWord for translation.', 'motaword' ) . '</div>';
				} else {
					echo $progress['html'];
				}

				return true;
			}
		} else {
			$this->showSourceLanguage = true;
		}

		if ( isset( $_GET['post'] ) && ! empty( $_GET['post'] ) ) {
			// ===> Post edit page

			if ( ! $this->showSourceLanguage ) {
				// We don't need to ask for source language as we already know it from Polylang.
				echo '<input type="hidden" id="mw_source_language" name="source_language" value="' . $mainSourceLanguage . '"/>';
			}

			echo '<input type="hidden" name="post_ids" id="post_ids" value="' . $wpPostId . '"/>';

			$targetBox      = '<select id="mw_target_language" name="target_language" style="' . ( $this->showSourceLanguage ? 'width:115px;' : 'width: 100%;' ) . '">';
			$targetSelected = false;

			foreach ( $targetLanguages as $language ) {
				if ( $mainSourceLanguage === $language->code ) {
					continue;
				}

				$code = $language->code;
				$name = $language->name;

				if ( ! $targetSelected && $code !== $mainSourceLanguage ) {
					$targetBox .= '<option value="' . $code . '" selected="selected">' . __( $name, 'motaword' ) . '</option>';
					$targetSelected = true;
				} else {
					$targetBox .= '<option value="' . $code . '">' . __( $name, 'motaword' ) . '</option>';
				}
			}

			$targetBox .= '</select>';

			if ( $this->showSourceLanguage ) {
				/**
				 * source languages combobox
				 */
				$sourceBox = '<select id="mw_source_language" name="source_language" style="width: 115px;">';

				foreach ( $sourceLanguages as $language ) {
					$code = $language->code;
					$name = $language->name;

					if ( ! ! $mainSourceLanguage && $code === $mainSourceLanguage ) {
						$sourceBox .= '<option value="' . $code . '" selected="selected">' . __( $name, 'motaword' ) . '</option>';
					} else {
						$sourceBox .= '<option value="' . $code . '">' . __( $name, 'motaword' ) . '</option>';
					}
				}

				$sourceBox .= '</select>';

				/** translators: 1: Drop-down select for source language 2: Drop-down select for target language */
				echo '<p>' . sprintf( __( '%1$s to %2$s', 'motaword' ), $sourceBox, $targetBox ) . '</p>';
			} else {
				echo '<p>' . $targetBox . '</p>';
			}

			echo '<p style="text-align: center;"><a id="mw_start_link" href="' . plugin_dir_url( __FILE__ ) . 'get_quote.php" class="thickbox button button-primary button-large">' . __( 'Send to MotaWord', 'motaword' ) . '</a></p>';

			if ( ! ! $progress && isset( $progress['html'] ) && ! ! $progress['html'] ) {
				echo '<hr/>';
			}
		} elseif ( isset( $_GET['new_lang'] ) && isset( $_GET['from_post'] ) ) {
			// ===> New Polylang language for a post.
			//
			// Prepare some variables to be used by JavaScript right after requesting
			// a new post language by a Polylang button.
			//
			// We are modifying the default Polylang behavior here. We will try to save the post as draft on new post screen.

			// Let's delete meta values of this new post, because Polylang is saving the post as draft and copies all
			// meta data from previous post. So if previous post has a MotaWord meta data, it will be copied to the new
			// post... We wouldn't want that.
			$this->delete_project( $wpPostId );

			$originalTitle = get_the_title( sanitize_text_field( $_GET['from_post'] ) );
			$newTitle      = $originalTitle;

			if ( ! $newTitle ) {
				// = [Translating...]
				$newTitle = __( '[Translating...]', 'motaword' );
			} else {
				// = This is my original post title (translating...)
				$newTitle = sprintf( __( '%s [translating]', 'motaword' ), $newTitle );
			}

			echo '<input name="pllMainPostTitle" id="pllMainPostTitle" type="hidden" value="' . $originalTitle . '" />';
			echo '<input name="pllNewPostTitle" id="pllNewPostTitle" type="hidden" value="' . $newTitle . '" />';
			echo '<div class="mw_warning">' . __( 'Save this post in order to send to MotaWord for translation.', 'motaword' ) . '</div>';
		} else {
			// ===> New Post Page
			echo '<div class="mw_warning">' . __( 'Save this post in order to send to MotaWord for translation.', 'motaword' ) . '</div>';
		}

		/**
		 * show previous projects and their statuses to the user
		 */
		echo $progress['html'];

		return true;
	}

	protected function credentials_error() {
		$this->issue_error( sprintf(
			__( 'MotaWord API credentials that you provided are wrong. Please check them on <a href="%s">settings page</a>.', 'motaword' ),
			admin_url( 'options-general.php?page=motaword' )
		) );
	}

	protected function issue_error( $message = null ) {
		if ( ! $message ) {
			$message = __( 'We encountered an error while processing your request. Please try again or contact us at info@motaword.com.', 'motaword' );
		}

		if ( $this->exitOnError ) {
			echo '<div style="margin-bottom: 20px; text-align: center;">
  		<a href="https://www.motaword.com" target="_blank"><img class="mw_logo" src="' . plugin_dir_url( __FILE__ ) . '/css/logo.png"></a>
  	</div>';
		}

		if ( $this->iconOnError ) {
			$message = strip_tags( $message );

			echo '<img src="' . plugin_dir_url( __FILE__ ) . '/css/icon_error.png" style="width: 20px; height: 20px;" title="' . $message . '" alt="' . $message . '"/>';
		} else {
			echo '<div class="mw_error">' . $message . '</div>';
		}

		if ( $this->exitOnError ) {
			exit();
		}
	}

	protected function getDB() {
		if ( $this->db ) {
			return $this->db;
		}

		$this->db = new MotaWord_DB( MotaWord::getProjectsTableName() );

		return $this->db;
	}

	protected function getAPI() {
		if ( $this->api ) {
			return $this->api;
		}

		if ( ! get_option( static::$options['client_id'] ) || ! get_option( static::$options['client_secret'] ) ) {
			$this->credentials_error();
		}

		$this->api = new MotaWord_API( get_option( static::$options['client_id'] ), get_option( static::$options['client_secret'] ),
			get_option( static::$options['sandbox'] ) );

		return $this->api;
	}

	public function get_progress_partial( $postId ) {
		$posts                = array();
		$projects             = array();
		$languagesWithProject = array();
		$languages            = array();

		$mwApiHelper = $this->getAPI();
		$mwdbhelper  = $this->getDB();

		if ( $this->isPolylang && function_exists( 'pll_get_post' ) && function_exists( 'pll_languages_list' ) ) {
			$languages = (array) pll_languages_list();

			foreach ( $languages as $language ) {
				$post = pll_get_post( $postId, $language );

				if ( ! ! $post ) {
					$posts[ $language ] = $post;
				}
			}

			if ( ! $posts ) {
				return null;
			}

			foreach ( $posts as $language => $postId ) {
				$currentProjects = $mwdbhelper->getMWProjects( $postId );

				if ( ! ! $currentProjects ) {
					$languagesWithProject[] = $language;
					$projects               = array_merge( $projects, $currentProjects );
				}
			}
		} else {
			$projects = $mwdbhelper->getMWProjects( $postId );

			if ( ! ! $projects ) {
				$projectDetail = $mwApiHelper->getProject( $projects[0]->mw_project_id );

				if ( $projectDetail ) {
					$languagesWithProject[]                       = $projectDetail->target_languages[0];
					$posts[ $projectDetail->target_languages[0] ] = $postId;
				}
			}

			if ( ! $posts ) {
				return null;
			}
		}

		if ( ! $projects ) {
			return null;
		}

		$htmlProgress = '';

		foreach ( $projects as $i => $project ) {
			$projectDetail   = $this->get_detail( $project->mw_project_id );
			$projectProgress = $this->get_progress( $project->mw_project_id );

			if ( ! $projectDetail ) {
				continue;
			}

			$projects[ $i ] = $projectDetail;

			$targetLanguages = array();

			foreach ( $projectDetail->target_languages as $language ) {
				$targetLanguages[] = __( MotaWord_i18n::getLanguage( $language ), 'motaword' );
			}

			$htmlProgress .= '
                <div class="mwProgress">
                    <p style="text-align: center; font-weight: bold;">' .
			                 sprintf(
				                 __( 'From %1$s to %2$s', 'motaword' ),
				                 __( MotaWord_i18n::getLanguage( $projectDetail->source_language ), 'motaword' ),
				                 implode( ', ', $targetLanguages )
			                 ) . '
				</p>';

			if ( $project->status === 'completed' ) {
				$htmlProgress .= '<div class="mwProgress">' . __( 'Completed.', 'motaword' ) . '</div>';
			} else {
				if ( (int) $projectProgress->translation < 100 ) {
					$htmlProgress .= '<p>' . sprintf(
							__( 'Translation progress: <strong>%d</strong>%%', 'motaword' ),
							$projectProgress->translation
						) . '</p>';
				} else {
					if ( (int) $projectProgress->proofreading < 100 ) {
						$htmlProgress .= '<div class="mwProgress">' . sprintf(
								__( 'Translated and currently being proofread: <strong>%d</strong>%% completed.', 'motaword' ),
								$projectProgress->proofreading
							) . '</div>';
					} else {
						$htmlProgress .= '<div class="mwProgress">' . __( 'Completed. Waiting to be finalized.', 'motaword' ) . '</div>';
					}
				}
			}

			$htmlProgress .= '<p style="text-align: right;"><em><small>' .
			                 sprintf(
				                 __( 'Translation by <a href="%s" target="_blank">MotaWord</a>', 'motaword' ),
				                 'https://www.motaword.com'
			                 )
			                 . '</small></em></p></div>';

			if ( ( $i + 1 ) < count( $projects ) ) {
				$htmlProgress .= '<hr/>';
			}
		}

		return array( 'html' => $htmlProgress, 'languages' => $languagesWithProject, 'projects' => $projects );
	}

	protected function get_detail( $id ) {
		$result = null;

		if ( ! ( $result = $this->get_cache( 'project', $id ) ) ) {
			$result = $this->getAPI()->getProject( $id );
			$this->set_cache( 'project', $id, $result );
		}

		return $result;
	}

	protected function get_progress( $id ) {
		$result = null;

		if ( ! ( $result = $this->get_cache( 'progress', $id ) ) ) {
			$result = $this->getAPI()->getProgress( $id );
			$this->set_cache( 'progress', $id, $result );
		}

		return $result;
	}

	protected function get_languages() {
		$result = null;

		if ( ! ( $result = $this->get_cache( 'api_languages' ) ) ) {
			$result = $this->getAPI()->getLanguages();
			$this->set_cache( 'api_languages', $result );
		}

		return $result;
	}

	protected function delete_project( $id ) {
		return $this->getDB()->deleteProject( $id );
	}

	/**
	 * Return the request-specific temporary cache values.
	 *
	 * @warning If $identifier is not sent, this returns the whole cache category.
	 *
	 * @param string $category
	 * @param string $identifier
	 *
	 * @return mixed
	 */
	protected function get_cache( $category, $identifier = null ) {
		if ( ! isset( $category ) || ! $category
		     || ! isset( $this->cache[ $category ] )
		     || ( isset( $identifier )
		          && ( ! $identifier || ! isset( $this->cache[ $category ][ $identifier ] ) ) )
		) {
			return false;
		}

		if ( ! ! $identifier ) {
			return $this->cache[ $category ][ $identifier ];
		} else {
			return $this->cache[ $category ];
		}
	}

	/**
	 * Sets the cache for this request. This is not a request persistent cache storage.
	 *
	 * @warning When two parameters are given $identifier becomes $value.
	 *
	 * @param string $category
	 * @param mixed $identifier
	 * @param mixed $value
	 *
	 * @return bool
	 */
	protected function set_cache( $category, $identifier, $value = null ) {
		if ( ! isset( $this->cache[ $category ] ) ) {
			if ( isset( $value ) ) {
				$this->cache[ $category ] = array();
			} else {
				$this->cache[ $category ] = $identifier;

				return true;
			}
		}

		if ( isset( $value ) ) {
			if ( ! is_array( $this->cache[ $category ] ) ) {
				$this->cache[ $category ] = array();
			}

			$this->cache[ $category ][ $identifier ] = $value;
		} else {
			$this->cache[ $category ] = $identifier;
		}

		return true;
	}

	function mapLanguageCode( $code, $reverse = false ) {
		if ( ! $this->languages ) {
			include_once __DIR__ . "/../includes/language-map.php";

			if( isset( $map ) ) {
				$this->languages         = $map;
			}

			if( isset( $reversedMap ) ) {
				$this->reversedLanguages = $reversedMap;
			}
		}

		if ( $reverse ) {
			return isset( $this->reversedLanguages[ $code ] ) ? $this->reversedLanguages[ $code ] : null;
		} else {
			return isset( $this->languages[ $code ] ) ? $this->languages[ $code ] : null;
		}
	}

	public function admin_init() {
		// Register settings
		register_setting( 'mw_options', MotaWord::getOptionsKey() );
	}

	public function add_bulk_thickbox() {
		add_thickbox();
	}

	public function get_quote() {
		global $motawordPlugin;

		$this->exitOnError = true;

		if ( ! isset( $_REQUEST['post_ids'] ) || ! isset( $_REQUEST['source_language'] ) || ! isset( $_REQUEST['target_language'] ) ) {
			$this->issue_error();
		}

		$defaultType   = 'json';
		$wpPostIds     = isset($_REQUEST['post_ids']) ? $_REQUEST['post_ids'] : array();
		$sourceLang    = isset($_REQUEST['source_language']) ? sanitize_text_field( $_REQUEST['source_language'] ) : null;
		$targetLang    = isset($_REQUEST['target_language']) ? sanitize_text_field( $_REQUEST['target_language'] ) : null;
		$pllMainPostID = isset($_REQUEST['pllMainPostID']) ? sanitize_text_field( $_REQUEST['pllMainPostID'] ) : null;

		$mwApiHelper = $this->getAPI();

		$totalWordCount = 0;
		$totalCost      = 0;

		$currency = '';
		$projects = array();

		// Is this post type supported by Polylang? If not, we'll fall back to regular non-i18n workflow.
		if ( isset( $wpPostIds[0] ) && ! ! $wpPostIds[0] && function_exists( 'pll_is_translated_post_type' ) ) {
			// Check post type.
			$postType = get_post_type( $wpPostIds[0] );
			if ( ! pll_is_translated_post_type( $postType ) ) {
				$this->isPolylang = false;
			}
		}

		echo '<form method="post" action="' . admin_url( 'admin-ajax.php' ) . '" id="mw_quote_form">';
		wp_nonce_field( 'pll_language', '_pll_nonce' );
		echo '<input type="hidden" id="action" name="action" value="mw_submit_quote">';

		foreach ( $wpPostIds as $wpPostId ) {
			$wpPostId = sanitize_text_field( $wpPostId );
			// Get from main post
			if ( $this->isPolylang && ! empty( $pllMainPostID ) && (int)$pllMainPostID > 0 ) {
				$wpPostTitle  = get_the_title( $pllMainPostID );
				$content_post = get_post( $pllMainPostID );
			} else {
				$wpPostTitle  = get_the_title( $wpPostId );
				$content_post = get_post( $wpPostId );
			}

			$thePostId      = $content_post->ID;
			$wpPostContent  = $content_post->post_content;
			$thePostExcerpt = $content_post->post_excerpt;
			$wpPostContent  = apply_filters( 'the_content', $wpPostContent );
			$wpPostContent  = str_replace( ']]>', ']]&gt;', $wpPostContent );

			$temp = tmpfile();

			$data = array(
				'TITLE'   => $wpPostTitle,
				'CONTENT' => $wpPostContent,
				'EXCERPT' => $thePostExcerpt
			);

			// Handle Custom Fields
			$wpPostCustomFields = get_post_meta( $thePostId, false );

			foreach ( $wpPostCustomFields as $key => $value ) {
				if ( $key != "_edit_lock" && $key != "_edit_last" ) {
					$data[ 'CUSTOMFIELD_' . $key ] = $value[0];
				}
			}

			// Handle Attached Media
			$attachments = get_posts( array(
				'post_parent'    => $thePostId,
				'post_type'      => 'attachment',
				'posts_per_page' => - 1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			) );

			if ( ! ! $attachments ) {
				foreach ( $attachments as $attachment ) {
					$alt = get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true );

					$data[ 'ATTACHMENT_' . $attachment->ID . '_TITLE' ]   = $attachment->post_title;
					$data[ 'ATTACHMENT_' . $attachment->ID . '_EXCERPT' ] = $attachment->post_excerpt;

					if ( ! ! $alt ) {
						$data[ 'ATTACHMENT_' . $attachment->ID . '_ALT' ] = $alt;
					}
				}
			}

			fwrite( $temp, json_encode( $data ) );

			$metaData     = stream_get_meta_data( $temp );
			$callbackEndpoint = ! ! $motawordPlugin ? $motawordPlugin->getCallbackEndpoint() : 'mw-callback';
			$callback_url = get_home_url() . '?'.$callbackEndpoint.'=1';

			$project = $mwApiHelper->submitProject( array(
				'source_language'    => $sourceLang,
				'target_languages[]' => $targetLang,
				'documents'          => array(
					'@' . $metaData['uri'] . ';filename=' . $wpPostId . '.' . $defaultType
				),
				'callback_url'       => $callback_url,
				'custom[wp_post_id]' => $wpPostId
			) );

			if ( ! $project || ! isset( $project->id ) ) {
				$this->issue_error( sprintf(
						__( 'An error occurred while submitting your posts to MotaWord for a quote: %s', 'motaword' ),
						'<br><br><pre>' . json_encode( $project, JSON_PRETTY_PRINT ) . '</pre>' )
				);
			}

			$projects[] = $project;

			$totalWordCount += $project->word_count;
			$totalCost += $project->price->amount;
			$currency = $project->price->currency;

			echo '<input type="hidden" name="mwProjectIds[]" value="' . $project->id . '"/>';
			echo '<input type="hidden" name="wpPostIds[]" value="' . $wpPostId . '"/>';
			echo '<input type="hidden" name="pllMainPostIDs" value="' . $pllMainPostID . '"/>';
			echo '<input type="hidden" name="source_language" value="' . $sourceLang . '"/>';
			echo '<input type="hidden" name="target_language" value="' . $targetLang . '"/>';
		}

		$title = sprintf(
			_n(
				'We can translate this WordPress post for just <strong>%1$s%2$s</strong>.',
				'We can translate these WordPress posts for just <strong>%1$s%2$s</strong>.',
				count( $projects ),
				'motaword'
			),
			MotaWord_i18n::getCurrency( $currency ),
			$totalCost
		);

		$subTitle = sprintf(
			_n(
				'OUR QUOTE IS BASED ON <strong>%1$d</strong> WORDS.',
				'OUR QUOTE IS BASED ON <strong>%1$d</strong> WORDS FOR %2$d POSTS.',
				count( $projects ),
				'motaword'
			),
			$totalWordCount,
			count( $projects )
		);

		echo '
<div class="mw_container">
	<div>
  		<a href="https://www.motaword.com" target="_blank"><img src="' . plugin_dir_url( __FILE__ ) . '/css/logo.png" class="mw_logo"></a>
  	</div>

	<h2 style="font-weight: normal;">' . $title . '</h2>
  	<h4 style="font-weight: normal;">' . $subTitle . '</h4>

<p class="submit" style="text-align: center;">
	<input type="submit" name="submit" id="submit" class="button button-primary button-large" value="' . __( 'Start Project', 'motaword' ) . '">
</p>
<p>
	<em>' . __( 'Have questions? <a href="https://www.motaword.com/contact" target="_blank">We are one email away.</a>', 'motaword' ) . '</em>
</p>

	</div>';

		exit();
	}

	public function prepare_bulk_quote() {
		$this->exitOnError = true;

		$languages = $this->get_languages();

		/**
		 * if motaword credentials are not correct, $languages will return an error
		 */
		if ( isset( $languages->error ) ) {
			$this->credentials_error();
		}

		$wpPostIds = $_REQUEST['post_ids'];

		// Is this post type supported by Polylang? If not, we'll fall back to regular non-i18n workflow.
		if ( isset( $wpPostIds[0] ) && ! ! $wpPostIds[0] && function_exists( 'pll_is_translated_post_type' ) ) {
			// Check post type.
			$postType = get_post_type( sanitize_text_field( $wpPostIds[0] ) );
			if ( ! pll_is_translated_post_type( $postType ) ) {
				$this->isPolylang = false;
			}
		}

		// Check Post Langs
		$pll_languages      = array();
		$mainSourceLanguage = 'en-US';
		/** @var Array $sourceLanguages */
		$sourceLanguages = $languages;
		/** @var Array $targetLanguages */
		$targetLanguages = $languages;

		if ( $this->isPolylang ) {
			$pll_languages = array();

			if ( function_exists( 'pll_languages_list' ) ) {
				$pll_languages = pll_languages_list();
			}

			$pll_languages = array_map( array( $this, 'mapLanguageCode' ), $pll_languages );

			// Get the source languages of selected posts
			$wpPostLangs = array();

			foreach ( $wpPostIds as $wpPostId ) {
				$thePostLang = null;

				if ( function_exists( 'pll_get_post_language' ) ) {
					$thePostLang = pll_get_post_language( sanitize_text_field( $wpPostId ) );
				}

				array_push( $wpPostLangs, $thePostLang );
			}

			$wpPostLangs = array_map( array( $this, 'mapLanguageCode' ), $wpPostLangs );

			// If there is more than one source language among the selected posts, then this won't work.
			// This is solely a precaution for clumsy actions.
			$uniqueLangs = array_unique( $wpPostLangs );

			if ( count( $uniqueLangs ) > 1 || count( $uniqueLangs ) < 1 ) {
				$this->issue_error( __( 'Source languages of the posts you selected don\'t match. Please select the posts with the same language.', 'motaword' ) );
			}

			// Source language is the common language of selected posts
			$mainSourceLanguage = array_unique( $wpPostLangs );
			$mainSourceLanguage = $mainSourceLanguage[0];

			// Filter source languages
			foreach ( $sourceLanguages as $i => $language ) {
				if ( ! in_array( $language->code, $pll_languages ) ) {
					unset( $sourceLanguages[ $i ] );
				}
			}

			$sourceLanguages = array_filter( $sourceLanguages );

			// Filter target languages
			foreach ( $targetLanguages as $i => $language ) {
				if ( ! in_array( $language->code, $pll_languages ) ) {
					unset( $targetLanguages[ $i ] );
				}
			}

			$targetLanguages = array_filter( $targetLanguages );
		}

		echo '<div class="mw_container">
	<div>
  		<a href="https://www.motaword.com" target="_blank"><img src="' . plugin_dir_url( __FILE__ ) . '/css/logo.png" class="mw_logo"></a>
  	</div>';

		echo '<h4>' . sprintf(
				_n(
					'You have selected %d post.',
					'You have selected %d posts.',
					count( $wpPostIds ),
					'motaword'
				),
				count( $wpPostIds )
			) . '</h4>';

		echo '<p>' . __( 'To get your quote, please select your translation languages and continue.', 'motaword' ) . '</p>';

		echo '<form method="get" action="' . admin_url( 'admin-ajax.php' ) . '" id="mw_quote_form">';
		echo '<input type="hidden" name="action" value="mw_get_bulk_quote">';
		foreach ( $wpPostIds as $wpPostId ) {
			echo '<input type="hidden" name="post_ids[]" value=' . $wpPostId . '>';
		}

		/**
		 * source languages combobox
		 */
		$sourceBox = '<select id="mw_source_language" name="source_language" style="width:110px;">';
		foreach ( $sourceLanguages as $language ) {
			if ( ! ! $pll_languages && ! in_array( $language->code, $pll_languages ) ) {
				continue;
			}

			$code = $language->code;
			$name = $language->name;
			if ( $code === $mainSourceLanguage ) {
				$sourceBox .= '<option value="' . $code . '" selected="selected">' . __( $name, 'motaword' ) . '</option>';
			} else {
				$sourceBox .= '<option value="' . $code . '">' . __( $name, 'motaword' ) . '</option>';
			}
		}
		$sourceBox .= '</select>';
		/**
		 * target languages combobox
		 */
		$targetBox = '<select id="mw_target_language" name="target_language" style="width:110px;">';

		$targetSelected = false;
		foreach ( $targetLanguages as $language ) {
			$code = $language->code;
			$name = $language->name;

			if ( $targetSelected === false && $code !== $mainSourceLanguage ) {
				$targetBox .= '<option value="' . $code . '" selected="selected">' . __( $name, 'motaword' ) . '</option>';
				$targetSelected = true;
			} else {
				$targetBox .= '<option value="' . $code . '">' . __( $name, 'motaword' ) . '</option>';
			}
		}

		$targetBox .= '</select>';

		echo '<div>';
		printf( __( '%1$s to %2$s', 'motaword' ), $sourceBox, $targetBox );
		echo '</div>';

		echo '<div>
		<p class="submit" style="text-align: center;">
			<input type="submit" name="submit" id="submit" class="button button-primary button-large" value="' . __( 'Continue to get a quote', 'motaword' ) . '">
		</p>
		<p>
			<em>' . __( 'Have questions? <a href="https://www.motaword.com/contact" target="_blank">We are one email away.</a>', 'motaword' ) . '</em>
		</p>
		</div></form>';

		exit();
	}

	public function start_project() {
		$this->exitOnError = true;

		$wpPostIds    = $_REQUEST['wpPostIds'];
		$mwProjectIds = $_REQUEST['mwProjectIds'];

		$mwApiHelper = $this->getAPI();
		$mwdbhelper  = $this->getDB();

		if ( function_exists( 'pll_languages_list' ) ) {
			$pll_languages = pll_languages_list();
		}

		// Is this post type supported by Polylang? If not, we'll fall back to regular non-i18n workflow.
		if ( isset( $wpPostIds[0] ) && ! ! $wpPostIds[0] && function_exists( 'pll_is_translated_post_type' ) ) {
			// Check post type.
			$postType = get_post_type( $wpPostIds[0] );
			if ( ! pll_is_translated_post_type( $postType ) ) {
				$this->isPolylang = false;
			}
		}

		$success       = 0;
		$rejected      = 0;
		$errorResponse = null;

		foreach ( $mwProjectIds as $i => $mwProjectId ) {
			$response = $mwApiHelper->launchProject( $mwProjectId );

			if ( ! $response || ! isset( $response->status ) || ! $response->status === 'started' ) {
				$errorResponse = $response;
				$rejected ++;
			} else {
				$success ++;

				if ( ! $this->isPolylang ) {
					$mwdbhelper->addProject( $wpPostIds[ $i ], $mwProjectId );
					continue;
				}

				$thisPostId   = $wpPostIds[ $i ];
				$selectedPost = get_post( $thisPostId );

				//$sourceLanguage = null;
				//if(function_exists('pll_get_post_language')) {
				//	$sourceLanguage = pll_get_post_language( $thisPostId );
				//}

				$targetLanguage = $this->mapLanguageCode( sanitize_text_field( $_REQUEST['target_language'] ), true );
				$targetPostId   = null;
				$targetPost     = null;

				if ( function_exists( 'pll_get_post' ) ) {
					$targetPostId = pll_get_post( $thisPostId, $targetLanguage );
				}

				// If we don't already have a post for this target language.
				if ( ! $targetPostId ) {
					$targetPostData = array(
						'post_title'   => $selectedPost->post_title . ' (translating...)',
						'post_content' => $selectedPost->post_content,
						'post_status'  => 'draft',
						'post_type'    => $selectedPost->post_type
					);

					$targetPostId = wp_insert_post( $targetPostData );

					if ( function_exists( 'pll_set_post_language' ) ) {
						pll_set_post_language( $targetPostId, $targetLanguage );
					}

					if ( function_exists( 'pll_save_post_translations' ) && function_exists( 'pll_get_post' ) ) {
						$postLanguages = array();

						if ( isset( $pll_languages ) && is_array( $pll_languages ) ) {
							foreach ( $pll_languages as $language ) {
								$id = (int) pll_get_post( $thisPostId, $language );

								if ( $id > 0 ) {
									$postLanguages[ $language ] = $id;
								}
							}
						}

						$postLanguages[ $targetLanguage ] = (int) $targetPostId;

						pll_save_post_translations( $postLanguages );
					}
				}

				$mwdbhelper->addProject( $targetPostId, $mwProjectId );
			}
		}

		$this->exitOnError = false;

		echo '<div class="mw_container">
	<div>
  		<a href="https://www.motaword.com" target="_blank"><img src="' . plugin_dir_url( __FILE__ ) . '/css/logo.png" class="mw_logo"></a>
  	</div>';

		if ( (int) $success > 0 ) {
			$title = _n(
				'Your post has been submitted for translation!',
				'Your posts have been submitted for translation!',
				$success,
				'motaword'
			);

			$subTitle = _n(
				'You can track its progress on post editing screen or <a href="https://www.motaword.com/projects" target="_blank">your MotaWord dashboard</a>.',
				'You can track their progress on post editing screen or <a href="https://www.motaword.com/projects" target="_blank">your MotaWord dashboard</a>.',
				$success,
				'motaword'
			);

			echo '<h2 style="font-weight: normal;">' . $title . '</h2>
			<h4 style="font-weight: normal;">' . $subTitle . '</h4>';
		}

		if ( $rejected || ! $success ) {
			echo '<br/>';
			$this->issue_error( __( 'Your job(s) has been rejected.' . ( $errorResponse ? '<br/><br/><pre style="text-align: left;">' . json_encode( $errorResponse, JSON_PRETTY_PRINT ) . '</pre>' : '' ), 'motaword' ) );
		}

		echo '<p><em>' . __( 'Have questions? <a href="https://www.motaword.com/contact" target="_blank">We are one email away.</a>', 'motaword' ) . '</em></p></div>';

		exit();
	}

	public function bulk_action() {
		?>
		<script type="text/javascript">
			jQuery(document).ready(motaword.init_bulk);
		</script>
		<?php
	}

	/**
	 * register MotaWord settings.
	 */
	public function register_my_custom_menu_page() {
		add_options_page( __( 'MotaWord', 'motaword' ), __( 'MotaWord', 'motaword' ), 'manage_options', 'motaword', array(
			$this,
			'settings'
		) );
	}

	/**
	 * MW Settings page
	 */
	public function settings() {
		$this->exitOnError = true;

		if ( $_POST ) {
			$this->save_settings();
		}

		$options = array(
			'id'      => get_option( static::$options['client_id'] ),
			'secret'  => get_option( static::$options['client_secret'] ),
			'sandbox' => (bool) get_option( static::$options['sandbox'] ),
		);
		?>

		<div class="wrap">
			<h2>MotaWord Settings</h2>

			<?php if ( isset( $_GET['activated'] ) ): ?>
				<div class="updated"><p>Let's make sure you put your API client ID and secret here. If you have any
						questions, just head to <a href="https://www.motaword.com/developer" target="_blank">documentation</a>.
					</p></div>
			<?php endif; ?>

			<form method="post">
				<?php settings_fields( 'motaword' ); ?>

				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php _e( 'API Client ID:', 'motaword' ); ?></th>
						<td><input type="text" name="<?php echo MotaWord::getOptionsKey() ?>[api_client_id]"
						           value="<?php echo $options['id']; ?>"/></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'API Client Secret:', 'motaword' ); ?></th>
						<td><input type="text" name="<?php echo MotaWord::getOptionsKey() ?>[api_client_secret]"
						           value="<?php echo $options['secret']; ?>"/></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Sandbox mode:', 'motaword' ); ?></th>
						<td><input type="checkbox" value="1"
						           name="<?php echo MotaWord::getOptionsKey() ?>[is_sandbox]" <?php echo isset( $options['sandbox'] ) && ! ! $options['sandbox'] ? 'checked="checked"' : ''; ?> />
						</td>
					</tr>
				</table>
				<p class="submit">
					<input type="submit" class="button-primary" value="<?php _e( 'Save Changes', 'motaword' ) ?>"/>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Save MW settings
	 */
	public function save_settings() {
		$this->exitOnError = true;

		$sanitized = array();

		foreach ( $_POST[ MotaWord::getOptionsKey() ] as $key => $post ) {
			$key               = sanitize_text_field( $key );
			$sanitized[ $key ] = sanitize_text_field( $_POST[ MotaWord::getOptionsKey() ][ $key ] );
		}

		if ( isset( $sanitized ) ) {
			$update = $sanitized;

			if ( isset( $update['api_client_id'] ) ) {
				update_option( static::$options['client_id'], $update['api_client_id'] );
			}

			if ( isset( $update['api_client_secret'] ) ) {
				update_option( static::$options['client_secret'], $update['api_client_secret'] );
			}

			$newSandboxValue = null;

			if ( isset( $update['is_sandbox'] ) ) {
				$newSandboxValue = 1;
			} else {
				$newSandboxValue = 0;
			}

			if ( (int) get_option( static::$options['sandbox'] ) !== (int) $newSandboxValue ) {
				// Clear the API caches as we are changing the environment here.
				$api = $this->getAPI();
				$api::clear_cache();
			}

			update_option( static::$options['sandbox'], (int) $newSandboxValue );
		}
	}

	public function modify_column( $column, $postID ) {
		if ( strpos( $column, 'language_' ) !== 0 && $column !== 'motaword' ) {
			// This is not a Polylang column.
			return false;
		}

		$basePostID = $postID;

		// If this is not the `motaword` column which is activated on non-Polylang scenario,
		// then this is a language_* column setup by Polylang.
		if ( $column !== 'motaword' ) {
			$language = str_replace( 'language_', '', $column );

			if ( ! $language ) {
				$this->columnList[ $basePostID ] = false;

				return false;
			}

			$postID = pll_get_post( $postID, $language );

			if ( ! $postID ) {
				$this->columnList[ $basePostID ] = false;

				return false;
			}
		}

		$db       = $this->getDB();
		$projects = $db->getMWProjects( $postID );

		if ( ! $projects ) {
			if ( isset( $this->columnList[ $basePostID ] ) ) {
				/**
				 * This helps to format the row from http://prntscr.com/8o0yk5 to http://prntscr.com/8o10kd
				 */
				echo '<div style="height: 25px;">&nbsp;</div>';
			}

			return false;
		}

		$this->columnList[ $basePostID ] = true;
		$isAllCompleted                  = true;
		$progresses                      = array();
		$translationPercentage           = null; //int
		$proofreadingPercentage          = null; //int

		foreach ( $projects as $project ) {
			// Let's cache the results on post list page.

			$progress = $this->get_progress( $project->mw_project_id );

			if ( $project->status !== 'completed' ) {
				$isAllCompleted = false;
			}

			if ( ! $progress ) {
				continue;
			}

			$progresses[] = $progress;
			$translationPercentage += $progress->translation;
			$proofreadingPercentage += $progress->proofreading;
		}

		if ( $translationPercentage === null ) {
			$this->columnList[ $basePostID ] = false;

			return false;
		}

		$icon    = 'icon';
		$message = '';

		if ( $isAllCompleted ) {
			$message = __( 'Translation of this post was completed.', 'motaword' );
			$icon    = 'icon_completed';
		} else {
			$translationPercentage  = $translationPercentage / count( $progresses );
			$proofreadingPercentage = $proofreadingPercentage / count( $progresses );
			$icon                   = 'icon_ongoing';

			$message = sprintf(
				__( "We are still working on the translation of this post.\n\nTranslation: %1\$d%%\nProofreading: %2\$d%%", 'motaword' ),
				$translationPercentage,
				$proofreadingPercentage
			);
		}

		echo '<img src="' . plugin_dir_url( __FILE__ ) . '/css/' . $icon . '.png" style="width: 20px; height: 20px;" title="' . $message . '" alt="' . $message . '"/>';

		return true;
	}

	public function init_columns( $columns ) {
		$this->exitOnError = false;
		$this->iconOnError = true;

		global $current_screen;

		$type = $current_screen->post_type;

		if ( is_plugin_active( 'polylang/polylang.php' )
		     && ( function_exists( 'pll_is_translated_post_type' ) && pll_is_translated_post_type( $type ) )
		) {
			return $columns;
		}

		$n = array_search( 'comments', array_keys( $columns ) );
		if ( $n ) {
			$end     = array_slice( $columns, $n );
			$columns = array_slice( $columns, 0, $n );
		}

		$columns['motaword'] = '<img src="' . plugin_dir_url( __FILE__ ) . '/css/icon.png" style="width: 20px; height: 20px;" title="' . __( 'MotaWord Summary', 'motaword' ) . '" alt="' . __( 'MotaWord Summary', 'motaword' ) . '"/>';

		return isset( $end ) ? array_merge( $columns, $end ) : $columns;
	}

	public function add_plugin_links( $links ) {
		$mylinks = array(
			'<a href="' . admin_url( 'options-general.php?page=motaword' ) . '">Settings</a>',
		);

		return array_merge( $links, $mylinks );
	}
}
