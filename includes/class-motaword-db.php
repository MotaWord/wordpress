<?php

/**
 * Class MotaWord_DB
 *
 * Provides an interface for database related transactions. By default, uses WP's builtin methods with post meta.
 *
 * @warning Do not switch to DB usage, it hasn't been tested after transition to post meta.
 */
class MotaWord_DB {
	/**
	 * class wide database table name
	 *
	 * @var string
	 */
	private $table_name;
	/**
	 * Should we use post meta or our own database table?
	 *
	 * @var bool
	 */
	private static $use_meta = true;
	public static $meta_prefix = 'motaword_';

	function __construct( $table_name ) {
		global $wpdb;
		$this->table_name = $wpdb->prefix . $table_name;
	}

	protected static function getDB() {
		global $wpdb;

		return $wpdb;
	}

	/**
	 * returns the list of motaword projects associated with input $wpPostId
	 *
	 * @param $wpPostId
	 *
	 * @return mixed
	 */
	function getMWProjects( $wpPostId ) {
		if ( static::$use_meta ) {
			// Returning the same resultset with get_results call.
			$id = get_post_meta( $wpPostId, static::$meta_prefix . 'project_id', true );

			if ( ! $id ) {
				return array();
			}

			$result = array( 'mw_project_id' => $id );

			$status = get_post_meta( $wpPostId, static::$meta_prefix . 'project_status', true );
			if ( ! ! $status ) {
				$result['status'] = $status;
			}

			$translation = get_post_meta( $wpPostId, static::$meta_prefix . 'progress_tra', true );
			if ( ! ! $translation ) {
				$result['translation'] = $translation;
			}

			$proofreading = get_post_meta( $wpPostId, static::$meta_prefix . 'progress_prf', true );
			if ( ! ! $proofreading ) {
				$result['proofreading'] = $proofreading;
			}

			return array( (object) $result );
		} else {
			return static::getDB()->get_results( "SELECT * FROM " . $this->table_name . " WHERE post_wp_id = " . $wpPostId );
		}
	}

	function getPostIDByProjectID( $projectId, $status = null ) {
		if ( static::$use_meta ) {
			// $status must be 'any' on callback query. Callbacks are made as a guest user and WP_Query will only
			// include published posts and not private posts.
			$args = array(
				'meta_key'    => static::$meta_prefix . 'project_id',
				'meta_value'  => $projectId,
				'post_status' => $status,
				'fields'      => 'ids'
			);

			$query = new WP_Query( $args );

			if ( isset( $query->posts ) && ! empty( $query->posts ) ) {
				foreach ( (array) $query->posts as $id ) {
					return $id;
				}
			}

			return null;
		} else {
			return static::getDB()->get_var( "SELECT post_wp_id FROM " . $this->table_name . " WHERE mw_project_id = " . $projectId );
		}
	}

	/**
	 * add new lanched project in to local db
	 *
	 * @param integer $postId WP post ID.
	 * @param integer $mwProjectId MotaWord project ID.
	 * @param string $status started, completed.
	 *
	 * @return mixed
	 */
	function addProject( $postId, $mwProjectId, $status = null ) {
		if ( ! $status ) {
			$status = 'started';
		}

		if ( static::$use_meta ) {
			update_post_meta( $postId, static::$meta_prefix . 'project_id', $mwProjectId );
			return $this->updateProject( $postId, $status, 0, 0 );
		} else {
			return static::getDB()->insert( $this->table_name, array(
				'post_wp_id'    => $postId,
				'mw_project_id' => $mwProjectId,
				'status'        => $status,
				'translation'   => 0,
				'proofreading'  => 0
			) );
		}
	}

	function updateProject( $postId, $status, $translation, $proofreading ) {
		if ( static::$use_meta ) {
			update_post_meta( $postId, static::$meta_prefix . 'project_status', $status );
			update_post_meta( $postId, static::$meta_prefix . 'progress_tra', $translation );
			update_post_meta( $postId, static::$meta_prefix . 'progress_prf', $proofreading );

			return true;
		} else {
			return static::getDB()->update( $this->table_name, array(
				'status'       => $status,
				'translation'  => $translation,
				'proofreading' => $proofreading
			), array( 'post_wp_id' => $postId ) );
		}
	}

	function deleteProject( $postId ) {
		if ( static::$use_meta ) {
			delete_post_meta( $postId, static::$meta_prefix . 'project_id' );
			delete_post_meta( $postId, static::$meta_prefix . 'project_status' );
			delete_post_meta( $postId, static::$meta_prefix . 'progress_tra' );
			delete_post_meta( $postId, static::$meta_prefix . 'progress_prf' );

			return true;
		} else {
			return static::getDB()->delete( $this->table_name, array( 'post_wp_id' => $postId ) );
		}
	}
}