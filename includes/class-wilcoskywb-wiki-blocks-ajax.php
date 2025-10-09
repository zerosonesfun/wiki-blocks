<?php
/**
 * AJAX Handler for Wiki Blocks
 *
 * @package WikiBlocks
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX handler class for Wiki Blocks
 *
 * @since 1.0.0
 */
class Wilcoskywb_Wiki_Blocks_Ajax {

	/**
	 * Instance of this class
	 *
	 * @var Wilcoskywb_Wiki_Blocks_Ajax
	 */
	private static $instance = null;

	/**
	 * Get instance of this class
	 *
	 * @return Wilcoskywb_Wiki_Blocks_Ajax
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// AJAX actions for logged-in users
		add_action( 'wp_ajax_wilcoskywb_wiki_blocks_suggest_change', array( $this, 'suggest_change' ) );
		add_action( 'wp_ajax_wilcoskywb_wiki_blocks_get_versions', array( $this, 'get_versions' ) );
		add_action( 'wp_ajax_wilcoskywb_wiki_blocks_merge_version', array( $this, 'merge_version' ) );
		add_action( 'wp_ajax_wilcoskywb_wiki_blocks_get_current_version', array( $this, 'get_current_version' ) );
		add_action( 'wp_ajax_wilcoskywb_wiki_blocks_get_settings', array( $this, 'get_settings' ) );
		add_action( 'wp_ajax_wilcoskywb_wiki_blocks_save_settings', array( $this, 'save_settings' ) );

		// AJAX actions for non-logged-in users (if allowed)
		add_action( 'wp_ajax_nopriv_wilcoskywb_wiki_blocks_get_versions', array( $this, 'get_versions' ) );
		add_action( 'wp_ajax_nopriv_wilcoskywb_wiki_blocks_get_settings', array( $this, 'get_settings' ) );
	}

	/**
	 * Suggest a change to a wiki block
	 */
	public function suggest_change() {
		// Verify nonce
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wilcoskywb_wiki_blocks_suggest_change' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wiki-blocks' ) );
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You must be logged in to suggest changes.', 'wiki-blocks' ) ) );
		}

		// Validate required fields
		$block_id = sanitize_text_field( wp_unslash( $_POST['block_id'] ?? '' ) );
		$content = wp_kses_post( wp_unslash( $_POST['content'] ?? '' ) );
		$change_summary = sanitize_textarea_field( wp_unslash( $_POST['change_summary'] ?? '' ) );

		if ( empty( $block_id ) || empty( trim( $content ) ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Missing required fields.', 'wiki-blocks' ) ) );
		}

		// Validate block_id format (should be alphanumeric with hyphens)
		if ( ! preg_match( '/^[a-zA-Z0-9\-_]+$/', $block_id ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid block ID format.', 'wiki-blocks' ) ) );
		}

		// Check if user can suggest changes for this block
		if ( ! Wilcoskywb_Wiki_Blocks_Permissions::can_suggest_changes( $block_id ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to suggest changes for this block.', 'wiki-blocks' ) ) );
		}

		// Get current user ID
		$user_id = get_current_user_id();

		// Get post ID - try from POST data first, then from HTTP referer
		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		
		// If not in POST, try to extract from HTTP referer
		if ( ! $post_id && isset( $_SERVER['HTTP_REFERER'] ) ) {
			$referer = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
			$post_id = url_to_postid( $referer );
		}
		
		// Last resort: try to find post_id from existing versions of this block
		if ( ! $post_id ) {
			$existing_versions = Wilcoskywb_Wiki_Blocks_Database::get_block_versions( $block_id );
			if ( ! empty( $existing_versions ) && isset( $existing_versions[0]->post_id ) ) {
				$post_id = $existing_versions[0]->post_id;
			}
		}

		// Insert the new version
		$version_id = Wilcoskywb_Wiki_Blocks_Database::insert_version( $block_id, $content, $user_id, $change_summary, $post_id );

		if ( false === $version_id ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to save your suggestion.', 'wiki-blocks' ) ) );
		}

		// Get the new version data
		$versions = Wilcoskywb_Wiki_Blocks_Database::get_block_versions( $block_id );
		$new_version = null;
		foreach ( $versions as $v ) {
			if ( $v->id == $version_id ) {
				// Decode HTML entities so content displays properly
				$decoded_content = html_entity_decode( $v->content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				
				$new_version = array(
					'id' => $v->id,
					'version_number' => $v->version_number,
					'content' => $decoded_content,
					'change_summary' => $v->change_summary,
					'is_current' => (bool) $v->is_current,
					'created_at' => $v->created_at,
					'user' => array(
						'id' => $v->user_id,
						'display_name' => $v->display_name,
						'avatar_url' => Wilcoskywb_Wiki_Blocks_Permissions::get_user_avatar_url( $v->user_id, 48 ),
					),
				);
				break;
			}
		}

		wp_send_json_success( array(
			'message' => esc_html__( 'Your suggestion has been submitted successfully. It will be reviewed and may be accepted as the new version.', 'wiki-blocks' ),
			'version' => $new_version,
		) );
	}

	/**
	 * Get all versions for a wiki block
	 */
	public function get_versions() {
		// Verify nonce
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wilcoskywb_wiki_blocks_get_versions' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wiki-blocks' ) );
		}

		// Validate required fields
		$block_id = sanitize_text_field( wp_unslash( $_POST['block_id'] ?? '' ) );

		if ( empty( $block_id ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Missing required fields.', 'wiki-blocks' ) ) );
		}

		// Validate block_id format
		if ( ! preg_match( '/^[a-zA-Z0-9\-_]+$/', $block_id ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid block ID format.', 'wiki-blocks' ) ) );
		}

		// Check if user can browse versions
		if ( ! Wilcoskywb_Wiki_Blocks_Permissions::can_browse_versions( $block_id ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to browse versions.', 'wiki-blocks' ) ) );
		}

		// Get versions
		$versions = Wilcoskywb_Wiki_Blocks_Database::get_block_versions( $block_id );

		// Add user avatars and format data
		$formatted_versions = array();
		foreach ( $versions as $version ) {
			// Decode HTML entities so content displays properly in version history
			$decoded_content = html_entity_decode( $version->content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			
			$formatted_versions[] = array(
				'id' => $version->id,
				'version_number' => $version->version_number,
				'content' => $decoded_content,
				'change_summary' => $version->change_summary,
				'is_current' => (bool) $version->is_current,
				'created_at' => $version->created_at,
				'user' => array(
					'id' => $version->user_id,
					'display_name' => $version->display_name,
					'avatar_url' => Wilcoskywb_Wiki_Blocks_Permissions::get_user_avatar_url( $version->user_id, 48 ),
				),
			);
		}

		wp_send_json_success( array(
			'versions' => $formatted_versions,
			'can_merge' => Wilcoskywb_Wiki_Blocks_Permissions::can_merge_versions( $block_id ),
		) );
	}

	/**
	 * Merge a specific version as current
	 */
	public function merge_version() {
		// Verify nonce
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wilcoskywb_wiki_blocks_merge_version' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wiki-blocks' ) );
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You must be logged in to merge versions.', 'wiki-blocks' ) ) );
		}

		// Validate required fields
		$version_id = absint( wp_unslash( $_POST['version_id'] ?? 0 ) );
		$block_id = sanitize_text_field( wp_unslash( $_POST['block_id'] ?? '' ) );

		if ( empty( $version_id ) || empty( $block_id ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Missing required fields.', 'wiki-blocks' ) ) );
		}

		// Validate block_id format
		if ( ! preg_match( '/^[a-zA-Z0-9\-_]+$/', $block_id ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid block ID format.', 'wiki-blocks' ) ) );
		}

		// Check if user can merge versions
		if ( ! Wilcoskywb_Wiki_Blocks_Permissions::can_merge_versions( $block_id ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to merge versions.', 'wiki-blocks' ) ) );
		}

		// Merge the version
		$success = Wilcoskywb_Wiki_Blocks_Database::merge_version( $version_id );

		if ( ! $success ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to merge version.', 'wiki-blocks' ) ) );
		}

		wp_send_json_success( array(
			'message' => esc_html__( 'Version merged successfully.', 'wiki-blocks' ),
		) );
	}

	/**
	 * Get current version content for block editor
	 */
	public function get_current_version() {
		// Verify nonce
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wilcoskywb_wiki_blocks_get_current_version' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wiki-blocks' ) );
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You must be logged in to access this content.', 'wiki-blocks' ) ) );
		}

		// Validate required fields
		$block_id = sanitize_text_field( wp_unslash( $_POST['block_id'] ?? '' ) );

		if ( empty( $block_id ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Missing required fields.', 'wiki-blocks' ) ) );
		}

		// Validate block_id format
		if ( ! preg_match( '/^[a-zA-Z0-9\-_]+$/', $block_id ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid block ID format.', 'wiki-blocks' ) ) );
		}

		// Get current version
		$current_version = Wilcoskywb_Wiki_Blocks_Database::get_current_version( $block_id );

		if ( ! $current_version ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No current version found.', 'wiki-blocks' ) ) );
		}

		// Decode HTML entities so content is properly formatted in editor
		$decoded_content = html_entity_decode( $current_version->content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		wp_send_json_success( array(
			'content' => $decoded_content,
		) );
	}

	/**
	 * Get block settings
	 */
	public function get_settings() {
		// Verify nonce
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wilcoskywb_wiki_blocks_get_settings' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wiki-blocks' ) );
		}

		// Validate required fields
		$block_id = sanitize_text_field( wp_unslash( $_POST['block_id'] ?? '' ) );

		if ( empty( $block_id ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Missing required fields.', 'wiki-blocks' ) ) );
		}

		// Get settings
		$settings = Wilcoskywb_Wiki_Blocks_Database::get_block_settings( $block_id );

		wp_send_json_success( array(
			'settings' => $settings,
		) );
	}

	/**
	 * Save block settings
	 */
	public function save_settings() {
		// Verify nonce
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wilcoskywb_wiki_blocks_save_settings' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wiki-blocks' ) );
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You must be logged in to save settings.', 'wiki-blocks' ) ) );
		}

		// Validate required fields
		$block_id = sanitize_text_field( wp_unslash( $_POST['block_id'] ?? '' ) );
		$settings_json = sanitize_textarea_field( wp_unslash( $_POST['settings'] ?? '{}' ) );

		if ( empty( $block_id ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Missing required fields.', 'wiki-blocks' ) ) );
		}

		// Decode JSON settings
		$settings = json_decode( $settings_json, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid settings format.', 'wiki-blocks' ) ) );
		}

		// Sanitize the decoded settings data
		$sanitized_settings = array();
		if ( is_array( $settings ) ) {
			// Sanitize merge_permissions array
			if ( isset( $settings['merge_permissions'] ) && is_array( $settings['merge_permissions'] ) ) {
				$sanitized_settings['merge_permissions'] = array_map( 'sanitize_text_field', $settings['merge_permissions'] );
			}
			
			// Sanitize browse_permissions array
			if ( isset( $settings['browse_permissions'] ) && is_array( $settings['browse_permissions'] ) ) {
				$sanitized_settings['browse_permissions'] = array_map( 'sanitize_text_field', $settings['browse_permissions'] );
			}
			
			// Sanitize suggest_permissions array
			if ( isset( $settings['suggest_permissions'] ) && is_array( $settings['suggest_permissions'] ) ) {
				$sanitized_settings['suggest_permissions'] = array_map( 'sanitize_text_field', $settings['suggest_permissions'] );
			}
			
			// Sanitize require_login_browse boolean
			if ( isset( $settings['require_login_browse'] ) ) {
				$sanitized_settings['require_login_browse'] = (bool) $settings['require_login_browse'];
			}
		}

		// Save settings
		$success = Wilcoskywb_Wiki_Blocks_Database::save_block_settings( $block_id, $sanitized_settings );

		if ( ! $success ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to save settings.', 'wiki-blocks' ) ) );
		}

		wp_send_json_success( array(
			'message' => esc_html__( 'Settings saved successfully.', 'wiki-blocks' ),
		) );
	}
} 