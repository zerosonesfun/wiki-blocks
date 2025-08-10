<?php
/**
 * Permissions Handler for Wiki Blocks
 *
 * @package WikiBlocks
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Permissions handler class for Wiki Blocks
 *
 * @since 1.0.0
 */
class Wilcoskywb_Wiki_Blocks_Permissions {

	/**
	 * Check if user can merge wiki block versions
	 *
	 * @since 1.0.0
	 * @param string $block_id The block ID.
	 * @param int    $user_id Optional user ID, defaults to current user.
	 * @return bool True if user can merge, false otherwise.
	 */
	public static function can_merge_versions( $block_id, $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return false;
		}

		// Get block settings
		$settings = Wilcoskywb_Wiki_Blocks_Database::get_block_settings( $block_id );
		$merge_permissions = $settings['merge_permissions'];

		// If block has no specific permissions, fall back to global settings
		if ( empty( $merge_permissions ) || ! is_array( $merge_permissions ) ) {
			$merge_permissions = get_option( 'wilcoskywb_wiki_blocks_merge_permissions', array( 'administrator' ) );
		}

		// Check if user has any of the required roles
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		$user_roles = $user->roles;

		foreach ( $merge_permissions as $role ) {
			if ( in_array( $role, $user_roles, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if user can browse wiki block versions
	 *
	 * @since 1.0.0
	 * @param string $block_id The block ID.
	 * @param int    $user_id Optional user ID, defaults to current user.
	 * @return bool True if user can browse, false otherwise.
	 */
	public static function can_browse_versions( $block_id, $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		// Get block settings
		$settings = Wilcoskywb_Wiki_Blocks_Database::get_block_settings( $block_id );
		$require_login_browse = $settings['require_login_browse'];
		$browse_permissions = $settings['browse_permissions'];

		// If block has no specific permissions, fall back to global settings
		if ( empty( $browse_permissions ) || ! is_array( $browse_permissions ) ) {
			$browse_permissions = get_option( 'wilcoskywb_wiki_blocks_browse_permissions', array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ) );
		}

		// If block has no specific login requirement, fall back to global settings
		if ( null === $require_login_browse ) {
			$require_login_browse = get_option( 'wilcoskywb_wiki_blocks_require_login_browse', false );
		}

		// If login is required but user is not logged in
		if ( $require_login_browse && ! $user_id ) {
			return false;
		}

		// If no login required, anyone can browse
		if ( ! $require_login_browse ) {
			return true;
		}

		// Check if logged-in user has required roles
		$user = get_userdata( $user_id );
		
		if ( ! $user ) {
			return false;
		}

		$user_roles = $user->roles;

		foreach ( $browse_permissions as $role ) {
			if ( in_array( $role, $user_roles, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if user can suggest changes to wiki blocks
	 *
	 * @since 1.0.0
	 * @param string $block_id The block ID.
	 * @param int $user_id Optional user ID, defaults to current user.
	 * @return bool True if user can suggest changes, false otherwise.
	 */
	public static function can_suggest_changes( $block_id, $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return false;
		}

		// Get block settings
		$settings = Wilcoskywb_Wiki_Blocks_Database::get_block_settings( $block_id );
		$suggest_permissions = $settings['suggest_permissions'];

		// If block has no specific permissions, fall back to global settings
		if ( empty( $suggest_permissions ) || ! is_array( $suggest_permissions ) ) {
			$suggest_permissions = get_option( 'wilcoskywb_wiki_blocks_suggest_permissions', array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ) );
		}

		// Check if user has any of the required roles
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		$user_roles = $user->roles;

		foreach ( $suggest_permissions as $role ) {
			if ( in_array( $role, $user_roles, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get available WordPress roles
	 *
	 * @since 1.0.0
	 * @return array Array of role names.
	 */
	public static function get_available_roles() {
		$roles = wp_roles();
		$role_names = array();

		foreach ( $roles->get_names() as $role_key => $role_name ) {
			$role_names[ $role_key ] = $role_name;
		}

		return $role_names;
	}

	/**
	 * Get available WordPress roles for editor
	 *
	 * @since 1.0.0
	 * @return array Array of role objects for editor.
	 */
	public static function get_available_roles_for_editor() {
		$roles = wp_roles();
		$role_objects = array();

		foreach ( $roles->get_names() as $role_key => $role_name ) {
			$role_objects[] = array(
				'value' => $role_key,
				'label' => $role_name,
			);
		}

		return $role_objects;
	}

	/**
	 * Get current user's roles
	 *
	 * @since 1.0.0
	 * @param int $user_id Optional user ID, defaults to current user.
	 * @return array Array of role names.
	 */
	public static function get_user_roles( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return array();
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return array();
		}

		return $user->roles;
	}

	/**
	 * Check if user is an administrator
	 *
	 * @since 1.0.0
	 * @param int $user_id Optional user ID, defaults to current user.
	 * @return bool True if user is administrator, false otherwise.
	 */
	public static function is_administrator( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		return in_array( 'administrator', $user->roles, true );
	}

	/**
	 * Get user display name
	 *
	 * @since 1.0.0
	 * @param int $user_id The user ID.
	 * @return string User display name or empty string if not found.
	 */
	public static function get_user_display_name( $user_id ) {
		$user = get_userdata( $user_id );
		return $user ? $user->display_name : '';
	}

	/**
	 * Get user avatar URL
	 *
	 * @since 1.0.0
	 * @param int $user_id The user ID.
	 * @param int $size Avatar size in pixels.
	 * @return string Avatar URL or empty string if not found.
	 */
	public static function get_user_avatar_url( $user_id, $size = 96 ) {
		$avatar = get_avatar_url( $user_id, $size );
		return $avatar ? $avatar : '';
	}

	/**
	 * Check if current user can edit the post
	 *
	 * @since 1.0.0
	 * @param int $post_id The post ID.
	 * @param int $user_id Optional user ID, defaults to current user.
	 * @return bool True if user can edit post, false otherwise.
	 */
	public static function can_edit_post( $post_id, $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return false;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Check if current user can view the post
	 *
	 * @since 1.0.0
	 * @param int $post_id The post ID.
	 * @param int $user_id Optional user ID, defaults to current user.
	 * @return bool True if user can view post, false otherwise.
	 */
	public static function can_view_post( $post_id, $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		// If user is logged in, check if they can read the post
		if ( $user_id ) {
			return current_user_can( 'read_post', $post_id );
		}

		// For non-logged in users, check if post is published
		return 'publish' === $post->post_status;
	}

	/**
	 * Verify nonce for security
	 *
	 * @since 1.0.0
	 * @param string $nonce The nonce to verify.
	 * @param string $action The nonce action.
	 * @return bool True if nonce is valid, false otherwise.
	 */
	public static function verify_nonce( $nonce, $action ) {
		return wp_verify_nonce( $nonce, $action );
	}

	/**
	 * Create nonce for security
	 *
	 * @since 1.0.0
	 * @param string $action The nonce action.
	 * @return string The nonce value.
	 */
	public static function create_nonce( $action ) {
		return wp_create_nonce( $action );
	}
} 