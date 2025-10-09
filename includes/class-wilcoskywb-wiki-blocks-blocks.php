<?php
/**
 * Gutenberg Blocks Handler for Wiki Blocks
 *
 * @package WikiBlocks
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gutenberg blocks handler class for Wiki Blocks
 *
 * @since 1.0.0
 */
class Wilcoskywb_Wiki_Blocks_Blocks {

	/**
	 * Instance of this class
	 *
	 * @var Wilcoskywb_Wiki_Blocks_Blocks
	 */
	private static $instance = null;

	/**
	 * Cache for decoded content during REST API processing
	 *
	 * @var array
	 */
	private $decoded_content_cache = array();

	/**
	 * Get instance of this class
	 *
	 * @return Wilcoskywb_Wiki_Blocks_Blocks
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
		add_action( 'init', array( $this, 'register_blocks' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_filter( 'block_categories_all', array( $this, 'add_block_category' ), 10, 2 );
		
		// Enhance wiki blocks on frontend with controls and modals
		add_filter( 'the_content', array( $this, 'enhance_wiki_blocks' ) );
		
		// Hook into post save to update wiki block database when edited in backend
		// Use higher priority to ensure we process before other plugins
		add_action( 'save_post', array( $this, 'handle_post_save' ), 5, 2 );
		
		// Hook into REST API to process blocks during save (only for database updates)
		add_filter( 'rest_pre_insert_post', array( $this, 'process_rest_post_data' ), 10, 2 );
		
		// Hook into post deletion to clean up wiki block data
		add_action( 'before_delete_post', array( $this, 'cleanup_post_wiki_blocks' ) );
	}

	/**
	 * Register Gutenberg blocks
	 */
	public function register_blocks() {
		// Register the wiki block
		register_block_type(
			'wilcoskywb/wiki-block',
			array(
				'editor_script' => 'wilcoskywb-wiki-blocks-editor',
				'editor_style'  => 'wilcoskywb-wiki-blocks-editor-style',
				'script'        => 'wilcoskywb-wiki-blocks-frontend',
				'style'         => 'wilcoskywb-wiki-blocks-frontend-style',
				'render_callback' => array( $this, 'render_wiki_block' ),
				'attributes'    => array(
					'content' => array(
						'type' => 'string',
						'default' => '',
					),
					'blockId' => array(
						'type' => 'string',
						'default' => '',
					),
					'align' => array(
						'type' => 'string',
						'default' => '',
					),
					'backgroundColor' => array(
						'type' => 'string',
						'default' => '',
					),
					'textColor' => array(
						'type' => 'string',
						'default' => '',
					),
					'fontSize' => array(
						'type' => 'string',
						'default' => '',
					),
				),
			)
		);
	}

	/**
	 * Enqueue editor assets
	 */
	public function enqueue_editor_assets() {
		wp_enqueue_script(
			'wilcoskywb-wiki-blocks-editor',
			Wilcoskywb_Wiki_Blocks_Assets::get_asset_url( 'assets/js/editor.js' ),
			array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n', 'wp-data' ),
			Wilcoskywb_Wiki_Blocks_Assets::get_asset_version( 'assets/js/editor.js' ),
			true
		);

		wp_enqueue_style(
			'wilcoskywb-wiki-blocks-editor-style',
			Wilcoskywb_Wiki_Blocks_Assets::get_asset_url( 'assets/css/editor.css' ),
			array(),
			Wilcoskywb_Wiki_Blocks_Assets::get_asset_version( 'assets/css/editor.css' )
		);

		// Localize script with data
		wp_localize_script(
			'wilcoskywb-wiki-blocks-editor',
			'wilcoskywbWikiBlocks',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonces' => array(
				'getSettings' => wp_create_nonce( 'wilcoskywb_wiki_blocks_get_settings' ),
				'saveSettings' => wp_create_nonce( 'wilcoskywb_wiki_blocks_save_settings' ),
				'getCurrentVersion' => wp_create_nonce( 'wilcoskywb_wiki_blocks_get_current_version' ),
			),
				'roles' => Wilcoskywb_Wiki_Blocks_Permissions::get_available_roles_for_editor(),
				'strings' => array(
					'blockTitle' => __( 'Wiki Block', 'wiki-blocks' ),
					'blockDescription' => __( 'A collaborative wiki block with version control.', 'wiki-blocks' ),
					'contentPlaceholder' => __( 'Enter your wiki content here...', 'wiki-blocks' ),
					'settingsTitle' => __( 'Wiki Block Settings', 'wiki-blocks' ),
					'mergePermissions' => __( 'Merge Permissions', 'wiki-blocks' ),
					'browsePermissions' => __( 'Browse Permissions', 'wiki-blocks' ),
					'requireLoginBrowse' => __( 'Require Login to Browse Versions', 'wiki-blocks' ),
					'saveSettings' => __( 'Save Settings', 'wiki-blocks' ),
					'settingsSaved' => __( 'Settings saved successfully!', 'wiki-blocks' ),
					'errorSaving' => __( 'Error saving settings.', 'wiki-blocks' ),
				),
			)
		);
	}

	/**
	 * Add block category
	 *
	 * @param array $categories Block categories.
	 * @param object $post Post object.
	 * @return array Modified block categories.
	 */
	public function add_block_category( $categories, $post ) {
		return array_merge(
			$categories,
			array(
				array(
					'slug' => 'wilcoskywb-wiki-blocks',
					'title' => __( 'Wiki Blocks', 'wiki-blocks' ),
					'icon' => 'admin-page',
				),
			)
		);
	}



	/**
	 * Enhance wiki blocks on frontend with controls and modals
	 *
	 * @param string $content Post content.
	 * @return string Enhanced content.
	 */
	public function enhance_wiki_blocks( $content ) {
		// Only enhance on frontend, not in admin
		if ( is_admin() ) {
			return $content;
		}

		// Find wiki blocks and enhance them
		$content = preg_replace_callback(
			'/<div[^>]*class="[^"]*wilcoskywb-wiki-block[^"]*"[^>]*data-block-id="([^"]*)"[^>]*>(.*?)<\/div>/s',
			array( $this, 'enhance_wiki_block_match' ),
			$content
		);

		return $content;
	}

	/**
	 * Enhance a single wiki block match
	 *
	 * @param array $matches Regex matches.
	 * @return string Enhanced block HTML.
	 */
	private function enhance_wiki_block_match( $matches ) {
		$block_id = $matches[1];
		$original_html = $matches[0];
		$content_html = $matches[2];

		// Get current version content from database
		$current_version = Wilcoskywb_Wiki_Blocks_Database::get_current_version( $block_id );
		
		if ( $current_version ) {
			// Use content from database
			$display_content = $current_version->content;
		} else {
			// Extract content from saved HTML and save as initial version
			// The saved HTML structure is: <div class="wilcoskywb-wiki-content">content</div>
			if ( preg_match( '/<div[^>]*class="[^"]*wilcoskywb-wiki-content[^"]*"[^>]*>(.*?)<\/div>/s', $original_html, $content_matches ) ) {
				$display_content = $content_matches[1];
				
				// Save as initial version only if we have content, a user, and valid post context
				$user_id = get_current_user_id();
				$post_id = get_the_ID();
				
				// Only save if we have all required data and content is meaningful
				if ( $user_id && $post_id && ! empty( trim( wp_strip_all_tags( $display_content ) ) ) ) {
					// Wrap in try-catch to prevent 500 errors if database insert fails
					try {
						Wilcoskywb_Wiki_Blocks_Database::insert_version( $block_id, $display_content, $user_id, __( 'Initial version', 'wiki-blocks' ), $post_id );
					} catch ( Exception $e ) {
						// Log error but don't break page rendering
						error_log( 'Wiki Blocks: Failed to create initial version for block ' . $block_id . ': ' . $e->getMessage() );
					}
				}
			} else {
				$display_content = '';
			}
		}



		// Get block settings
		$settings = Wilcoskywb_Wiki_Blocks_Database::get_block_settings( $block_id );

		// Check permissions
		$can_suggest_changes = Wilcoskywb_Wiki_Blocks_Permissions::can_suggest_changes( $block_id );
		$can_browse_versions = Wilcoskywb_Wiki_Blocks_Permissions::can_browse_versions( $block_id );
		$can_merge_versions = Wilcoskywb_Wiki_Blocks_Permissions::can_merge_versions( $block_id );

		// Build enhanced HTML
		$enhanced_html = '<div class="wilcoskywb-wiki-block" data-block-id="' . esc_attr( $block_id ) . '">';

		// Wiki content
		$enhanced_html .= '<div class="wilcoskywb-wiki-content">';
		// Decode HTML entities so tags are rendered, then sanitize
		$decoded_content = html_entity_decode( $display_content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$enhanced_html .= wp_kses_post( $decoded_content );
		$enhanced_html .= '</div>';

		// Wiki controls
		$enhanced_html .= '<div class="wilcoskywb-wiki-controls">';

		// Suggest changes button (for logged-in users)
		if ( $can_suggest_changes ) {
			$enhanced_html .= '<button type="button" class="wilcoskywb-wiki-suggest-btn" data-action="suggest-change" title="' . esc_attr__( 'Suggest Change', 'wiki-blocks' ) . '">';
			$enhanced_html .= '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>';
			$enhanced_html .= '</button>';
		}

		// Browse versions button
		if ( $can_browse_versions ) {
			$enhanced_html .= '<button type="button" class="wilcoskywb-wiki-browse-btn" data-action="browse-versions" title="' . esc_attr__( 'View History', 'wiki-blocks' ) . '">';
			$enhanced_html .= '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>';
			$enhanced_html .= '</button>';
		}

		$enhanced_html .= '</div>';

		// Wiki modal (hidden by default)
		$enhanced_html .= '<div class="wilcoskywb-wiki-modal" style="display: none;">';
		$enhanced_html .= '<div class="wilcoskywb-wiki-modal-overlay"></div>';
		$enhanced_html .= '<div class="wilcoskywb-wiki-modal-content">';
		$enhanced_html .= '<div class="wilcoskywb-wiki-modal-header">';
		$enhanced_html .= '<h3>' . esc_html__( 'History', 'wiki-blocks' ) . '</h3>';
		$enhanced_html .= '<button type="button" class="wilcoskywb-wiki-modal-close">&times;</button>';
		$enhanced_html .= '</div>';
		$enhanced_html .= '<div class="wilcoskywb-wiki-modal-body">';
		$enhanced_html .= '<div class="wilcoskywb-wiki-versions-list"></div>';
		$enhanced_html .= '</div>';
		$enhanced_html .= '</div>';
		$enhanced_html .= '</div>';

		// Suggest change modal (hidden by default)
		if ( $can_suggest_changes ) {
			$enhanced_html .= '<div class="wilcoskywb-wiki-suggest-modal" style="display: none;">';
			$enhanced_html .= '<div class="wilcoskywb-wiki-modal-overlay"></div>';
			$enhanced_html .= '<div class="wilcoskywb-wiki-modal-content">';
			$enhanced_html .= '<div class="wilcoskywb-wiki-modal-header">';
			$enhanced_html .= '<h3>' . esc_html__( 'Suggest a Change', 'wiki-blocks' ) . '</h3>';
			$enhanced_html .= '<button type="button" class="wilcoskywb-wiki-modal-close">&times;</button>';
			$enhanced_html .= '</div>';
			$enhanced_html .= '<div class="wilcoskywb-wiki-modal-body">';
			$enhanced_html .= '<form class="wilcoskywb-wiki-suggest-form">';
			$enhanced_html .= '<div class="wilcoskywb-wiki-form-group">';
			$enhanced_html .= '<label for="wilcoskywb-wiki-change-summary">' . esc_html__( 'Change Summary', 'wiki-blocks' ) . '</label>';
			$enhanced_html .= '<input type="text" id="wilcoskywb-wiki-change-summary" name="change_summary" placeholder="' . esc_attr__( 'Brief description of your changes...', 'wiki-blocks' ) . '" required>';
			$enhanced_html .= '</div>';
			$enhanced_html .= '<div class="wilcoskywb-wiki-form-group">';
			$enhanced_html .= '<label for="wilcoskywb-wiki-content">' . esc_html__( 'Content', 'wiki-blocks' ) . '</label>';
			// Decode HTML entities for editing so user sees actual HTML tags, not entities
			$decoded_for_edit = html_entity_decode( $display_content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$enhanced_html .= '<textarea id="wilcoskywb-wiki-content" name="content" rows="10" required>' . esc_textarea( $decoded_for_edit ) . '</textarea>';
			$enhanced_html .= '</div>';
			$enhanced_html .= '<div class="wilcoskywb-wiki-form-actions">';
			$enhanced_html .= '<button type="submit" class="wilcoskywb-wiki-submit-btn">' . esc_html__( 'Submit Suggestion', 'wiki-blocks' ) . '</button>';
			$enhanced_html .= '<button type="button" class="wilcoskywb-wiki-cancel-btn">' . esc_html__( 'Cancel', 'wiki-blocks' ) . '</button>';
			$enhanced_html .= '</div>';
			$enhanced_html .= '</form>';
			$enhanced_html .= '</div>';
			$enhanced_html .= '</div>';
			$enhanced_html .= '</div>';
		}

		$enhanced_html .= '</div>';

		return $enhanced_html;
	}

	/**
	 * Render wiki block on frontend
	 *
	 * @param array $attributes Block attributes.
	 * @param string $content Block content.
	 * @return string Rendered block HTML.
	 */
	public function render_wiki_block( $attributes, $content ) {
		// Generate unique block ID if not set
		$block_id = $attributes['blockId'] ?? '';
		if ( empty( $block_id ) ) {
			$block_id = 'wiki-block-' . uniqid();
		}

		// Get current version content
		$current_version = Wilcoskywb_Wiki_Blocks_Database::get_current_version( $block_id );
		$display_content = $current_version ? $current_version->content : ( $attributes['content'] ?? '' );

		// If no current version exists but we have content, save it as the initial version
		if ( ! $current_version && ! empty( $attributes['content'] ) ) {
			$user_id = get_current_user_id();
			$post_id = get_the_ID();
			
			// Only save if we have all required data
			if ( $user_id && $post_id && ! empty( trim( wp_strip_all_tags( $attributes['content'] ) ) ) ) {
				// Wrap in try-catch to prevent 500 errors if database insert fails
				try {
					Wilcoskywb_Wiki_Blocks_Database::insert_version( $block_id, $attributes['content'], $user_id, __( 'Initial version', 'wiki-blocks' ), $post_id );
					$display_content = $attributes['content'];
				} catch ( Exception $e ) {
					// Log error but don't break page rendering
					error_log( 'Wiki Blocks: Failed to create initial version for block ' . $block_id . ': ' . $e->getMessage() );
					// Still use the content even if version creation failed
					$display_content = $attributes['content'];
				}
			}
		}

		// Get block settings
		$settings = Wilcoskywb_Wiki_Blocks_Database::get_block_settings( $block_id );

		// Check permissions
		$can_suggest_changes = Wilcoskywb_Wiki_Blocks_Permissions::can_suggest_changes( $block_id );
		$can_browse_versions = Wilcoskywb_Wiki_Blocks_Permissions::can_browse_versions( $block_id );
		$can_merge_versions = Wilcoskywb_Wiki_Blocks_Permissions::can_merge_versions( $block_id );

		// Build CSS classes
		$classes = array( 'wp-block-wilcoskywb-wiki-block', 'wilcoskywb-wiki-block' );
		if ( ! empty( $attributes['align'] ) ) {
			$classes[] = 'align' . $attributes['align'];
		}
		if ( ! empty( $attributes['backgroundColor'] ) ) {
			$classes[] = 'has-background';
			$classes[] = 'has-' . $attributes['backgroundColor'] . '-background-color';
		}
		if ( ! empty( $attributes['textColor'] ) ) {
			$classes[] = 'has-text-color';
			$classes[] = 'has-' . $attributes['textColor'] . '-color';
		}
		if ( ! empty( $attributes['fontSize'] ) ) {
			$classes[] = 'has-' . $attributes['fontSize'] . '-font-size';
		}

		// Build inline styles
		$style = '';
		if ( ! empty( $attributes['backgroundColor'] ) && strpos( $attributes['backgroundColor'], '#' ) !== false ) {
			$style .= 'background-color: ' . esc_attr( $attributes['backgroundColor'] ) . ';';
		}
		if ( ! empty( $attributes['textColor'] ) && strpos( $attributes['textColor'], '#' ) !== false ) {
			$style .= 'color: ' . esc_attr( $attributes['textColor'] ) . ';';
		}

		// Start building HTML
		$html = '<div class="' . esc_attr( implode( ' ', $classes ) ) . '"';
		if ( ! empty( $style ) ) {
			$html .= ' style="' . esc_attr( $style ) . '"';
		}
		$html .= ' data-block-id="' . esc_attr( $block_id ) . '">';

		// Wiki content
		$html .= '<div class="wilcoskywb-wiki-content">';
		// Decode HTML entities so tags are rendered, then sanitize
		$decoded_content = html_entity_decode( $display_content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$html .= wp_kses_post( $decoded_content );
		$html .= '</div>';

		// Wiki controls
		$html .= '<div class="wilcoskywb-wiki-controls">';

		// Suggest changes button (for logged-in users)
		if ( $can_suggest_changes ) {
			$html .= '<button type="button" class="wilcoskywb-wiki-suggest-btn" data-action="suggest-change" title="' . esc_attr__( 'Suggest Change', 'wiki-blocks' ) . '">';
			$html .= '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>';
			$html .= '</button>';
		}

		// Browse versions button
		if ( $can_browse_versions ) {
			$html .= '<button type="button" class="wilcoskywb-wiki-browse-btn" data-action="browse-versions" title="' . esc_attr__( 'View History', 'wiki-blocks' ) . '">';
			$html .= '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>';
			$html .= '</button>';
		}

		$html .= '</div>';

		// Wiki modal (hidden by default)
		$html .= '<div class="wilcoskywb-wiki-modal" style="display: none;">';
		$html .= '<div class="wilcoskywb-wiki-modal-overlay"></div>';
		$html .= '<div class="wilcoskywb-wiki-modal-content">';
		$html .= '<div class="wilcoskywb-wiki-modal-header">';
		$html .= '<h3>' . esc_html__( 'History', 'wiki-blocks' ) . '</h3>';
		$html .= '<button type="button" class="wilcoskywb-wiki-modal-close">&times;</button>';
		$html .= '</div>';
		$html .= '<div class="wilcoskywb-wiki-modal-body">';
		$html .= '<div class="wilcoskywb-wiki-versions-list"></div>';
		$html .= '</div>';
		$html .= '</div>';
		$html .= '</div>';

		// Suggest change modal (hidden by default)
		if ( $can_suggest_changes ) {
			$html .= '<div class="wilcoskywb-wiki-suggest-modal" style="display: none;">';
			$html .= '<div class="wilcoskywb-wiki-modal-overlay"></div>';
			$html .= '<div class="wilcoskywb-wiki-modal-content">';
			$html .= '<div class="wilcoskywb-wiki-modal-header">';
			$html .= '<h3>' . esc_html__( 'Suggest a Change', 'wiki-blocks' ) . '</h3>';
			$html .= '<button type="button" class="wilcoskywb-wiki-modal-close">&times;</button>';
			$html .= '</div>';
			$html .= '<div class="wilcoskywb-wiki-modal-body">';
			$html .= '<form class="wilcoskywb-wiki-suggest-form">';
			$html .= '<div class="wilcoskywb-wiki-form-group">';
			$html .= '<label for="wilcoskywb-wiki-change-summary">' . esc_html__( 'Change Summary', 'wiki-blocks' ) . '</label>';
			$html .= '<input type="text" id="wilcoskywb-wiki-change-summary" name="change_summary" placeholder="' . esc_attr__( 'Brief description of your changes...', 'wiki-blocks' ) . '" required>';
			$html .= '</div>';
			$html .= '<div class="wilcoskywb-wiki-form-group">';
			$html .= '<label for="wilcoskywb-wiki-content">' . esc_html__( 'Content', 'wiki-blocks' ) . '</label>';
			// Decode HTML entities for editing so user sees actual HTML tags, not entities
			$decoded_for_edit = html_entity_decode( $display_content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$html .= '<textarea id="wilcoskywb-wiki-content" name="content" rows="10" required>' . esc_textarea( $decoded_for_edit ) . '</textarea>';
			$html .= '</div>';
			$html .= '<div class="wilcoskywb-wiki-form-actions">';
			$html .= '<button type="submit" class="wilcoskywb-wiki-submit-btn">' . esc_html__( 'Submit Suggestion', 'wiki-blocks' ) . '</button>';
			$html .= '<button type="button" class="wilcoskywb-wiki-cancel-btn">' . esc_html__( 'Cancel', 'wiki-blocks' ) . '</button>';
			$html .= '</div>';
			$html .= '</form>';
			$html .= '</div>';
			$html .= '</div>';
			$html .= '</div>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Handle post save to update wiki block database when edited in backend
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function handle_post_save( $post_id, $post ) {
		// Skip if this is an autosave or revision
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Skip if user doesn't have permission to edit this post
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Determine if this is a backend edit that should create versions
		// Check multiple indicators to be more reliable
		$is_backend_edit = false;
		
		// Method 1: Check for the transient we set in REST API hook
		if ( get_transient( 'wilcoskywb_backend_edit_' . $post_id ) ) {
			$is_backend_edit = true;
			delete_transient( 'wilcoskywb_backend_edit_' . $post_id );
		}
		
		// Method 2: Check if we're in a REST request (Gutenberg saves via REST)
		if ( ! $is_backend_edit && defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			// It's a REST request, check if it's not an autosave
			$is_backend_edit = true;
		}
		
		// Method 3: Check if current user is in admin and making changes
		if ( ! $is_backend_edit && is_admin() && current_user_can( 'edit_posts' ) ) {
			// Check if this looks like an intentional edit (not a quick edit or bulk action)
			// Quick edits and bulk actions typically don't update post_content
			if ( isset( $_POST['content'] ) || isset( $_POST['post_content'] ) ) {
				$is_backend_edit = true;
			}
		}
		
		// Only process if we've determined this is a legitimate backend edit
		if ( $is_backend_edit ) {
			// Parse blocks from post content
			$blocks = parse_blocks( $post->post_content );

			// Process each wiki block
			foreach ( $blocks as $block ) {
				if ( $block['blockName'] === 'wilcoskywb/wiki-block' ) {
					$this->update_wiki_block_from_save( $block, $post_id );
				}
			}
		}
	}

	/**
	 * Process REST API post data to fix encoding issues (for database updates only)
	 *
	 * @param WP_Post         $post    The post object.
	 * @param WP_REST_Request $request The request object.
	 * @return WP_Post Modified post object.
	 */
	public function process_rest_post_data( $post, $request ) {
		// Only process if content is being updated
		if ( ! $request->get_param( 'content' ) ) {
			return $post;
		}

		// Check if this is an autosave by looking at the request route
		// Autosaves use /wp/v2/posts/{id}/autosaves endpoint
		$route = $request->get_route();
		$is_autosave = strpos( $route, '/autosaves' ) !== false;
		
		// Set a flag to indicate this is a backend edit (not frontend AJAX)
		// We use a transient that expires quickly to handle the save_post hook
		if ( ! $is_autosave ) {
			// Use post ID from request since $post->ID might not be set yet
			$post_id = $request->get_param( 'id' ) ?: ( isset( $post->ID ) ? $post->ID : 0 );
			if ( $post_id ) {
				set_transient( 'wilcoskywb_backend_edit_' . $post_id, true, 30 );
			}
		}

		// Store the decoded content for our database processing
		// We don't modify the request content to avoid breaking block structure
		$content = $request->get_param( 'content' );
		$blocks = parse_blocks( $content );
		
		foreach ( $blocks as $block ) {
			if ( $block['blockName'] === 'wilcoskywb/wiki-block' ) {
				$attributes = $block['attrs'] ?? array();
				$block_content = $attributes['content'] ?? '';
				
				if ( ! empty( $block_content ) ) {
					$decoded_content = $this->decode_block_content( $block_content );
					
					if ( $decoded_content !== $block_content ) {
						// Store the decoded content for later processing with block ID as key
						$block_id = $attributes['blockId'] ?? '';
						if ( $block_id ) {
							$this->decoded_content_cache[ $block_id ] = $decoded_content;
						}
					}
				}
			}
		}
		
		return $post;
	}

	/**
	 * Decode block content using multiple methods
	 *
	 * @param string $content Original content.
	 * @return string Decoded content.
	 */
	private function decode_block_content( $content ) {
		$original_content = $content;
		
		// Method 1: Try JSON decode
		$decoded_content = json_decode( $content, true );
		if ( json_last_error() === JSON_ERROR_NONE && is_string( $decoded_content ) ) {
			$content = $decoded_content;
		}
		
		// Method 2: Decode Unicode escape sequences (u003c, u003e, etc.)
		if ( preg_match( '/\\\\u[0-9a-fA-F]{4}/', $content ) ) {
			$content = preg_replace_callback( '/\\\\u([0-9a-fA-F]{4})/', function( $matches ) {
				return mb_convert_encoding( pack( 'H*', $matches[1] ), 'UTF-8', 'UCS-2BE' );
			}, $content );
		}
		
		// Method 3: Decode without backslashes (u003c instead of \u003c)
		if ( preg_match( '/u[0-9a-fA-F]{4}/', $content ) ) {
			$content = preg_replace_callback( '/u([0-9a-fA-F]{4})/', function( $matches ) {
				return mb_convert_encoding( pack( 'H*', $matches[1] ), 'UTF-8', 'UCS-2BE' );
			}, $content );
		}
		
		// Method 4: Decode HTML entities if they exist
		$content = html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		
		return $content;
	}


	/**
	 * Update wiki block database when block is saved from backend editor
	 *
	 * @param array $block   Block data.
	 * @param int   $post_id Post ID.
	 */
	private function update_wiki_block_from_save( $block, $post_id ) {
		$attributes = $block['attrs'] ?? array();

		// Extract block ID
		$block_id = $attributes['blockId'] ?? '';
		if ( empty( $block_id ) ) {
			return; // Skip blocks without proper ID
		}

		// Get content from block attributes (this is what the editor saves)
		$content = $attributes['content'] ?? '';
		if ( empty( trim( $content ) ) ) {
			return; // Skip empty blocks
		}

		// Check if we have cached decoded content from REST API processing
		if ( isset( $this->decoded_content_cache[ $block_id ] ) ) {
			$content = $this->decoded_content_cache[ $block_id ];
			// Clear the cache after use
			unset( $this->decoded_content_cache[ $block_id ] );
		} else {
			// Fallback: decode the content using our method
			$content = $this->decode_block_content( $content );
		}

		// Get current user ID
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		// Get current version from database
		$current_version = Wilcoskywb_Wiki_Blocks_Database::get_current_version( $block_id );

		// Normalize content for comparison (strip extra whitespace, normalize line endings)
		$normalized_new_content = $this->normalize_content_for_comparison( $content );
		$normalized_current_content = $current_version ? $this->normalize_content_for_comparison( $current_version->content ) : '';

		// Only update if content has actually changed (after normalization)
		if ( $current_version && $normalized_current_content === $normalized_new_content ) {
			return; // No meaningful change, skip update
		}

		// IMPORTANT: Check if this is truly an admin edit or if we're about to overwrite
		// a recently merged suggestion. If the current version was created/updated within
		// the last 5 minutes and by a different user, we should be cautious
		if ( $current_version ) {
			$version_time = strtotime( $current_version->created_at );
			$time_diff = time() - $version_time;
			
			// If current version is less than 5 minutes old and created by a different user,
			// this might be a merged suggestion that we shouldn't overwrite
			if ( $time_diff < 300 && $current_version->user_id != $user_id ) {
				// Check if the admin actually made changes to the content
				// If the block attribute content matches an older version, this might be stale data
				$all_versions = Wilcoskywb_Wiki_Blocks_Database::get_block_versions( $block_id );
				foreach ( $all_versions as $version ) {
					$normalized_version_content = $this->normalize_content_for_comparison( $version->content );
					if ( $normalized_version_content === $normalized_new_content && $version->id != $current_version->id ) {
						// The "new" content matches an older version, so this is likely stale data
						// Don't create a new version
						return;
					}
				}
			}
		}

		// Create a new version with the updated content
		$change_summary = __( 'Edited in backend editor', 'wiki-blocks' );
		$version_id = Wilcoskywb_Wiki_Blocks_Database::insert_version( 
			$block_id, 
			$content, 
			$user_id, 
			$change_summary, 
			$post_id 
		);

		// If this created a new version, make it the current version
		if ( $version_id && $current_version ) {
			Wilcoskywb_Wiki_Blocks_Database::merge_version( $version_id );
		}
	}

	/**
	 * Normalize content for comparison to avoid false positives
	 *
	 * @param string $content Content to normalize.
	 * @return string Normalized content.
	 */
	private function normalize_content_for_comparison( $content ) {
		// First decode HTML entities - this handles &lt;br&gt; becoming <br>
		$content = html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		
		// Normalize line endings
		$content = str_replace( array( "\r\n", "\r" ), "\n", $content );
		
		// Trim whitespace from beginning and end
		$content = trim( $content );
		
		// Normalize whitespace around HTML tags for consistent comparison
		$content = preg_replace( '/\s*(<[^>]+>)\s*/', '$1', $content );
		
		// Normalize multiple spaces to single space (but preserve intentional formatting)
		$content = preg_replace( '/[ \t]+/', ' ', $content );
		
		// Remove zero-width spaces and other invisible characters
		$content = preg_replace( '/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $content );
		
		// Normalize self-closing tags (e.g., <br> vs <br />)
		$content = preg_replace( '/<br\s*\/?>/', '<br>', $content );
		
		return $content;
	}

	/**
	 * Clean up wiki block data when a post is deleted
	 *
	 * @since 1.1.0
	 * @param int $post_id The post ID being deleted.
	 */
	public function cleanup_post_wiki_blocks( $post_id ) {
		// Check if cleanup is enabled
		$cleanup_on_delete = get_option( 'wilcoskywb_wiki_blocks_cleanup_on_delete', true );
		
		if ( ! $cleanup_on_delete ) {
			return;
		}

		// Get all wiki block versions associated with this post
		global $wpdb;
		$table_name = $wpdb->prefix . 'wilcoskywb_wiki_block_versions';
		
		// Find all block IDs associated with this post
		$block_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT block_id FROM $table_name WHERE post_id = %d",
				$post_id
			)
		);

		// Delete all versions for these blocks
		foreach ( $block_ids as $block_id ) {
			// Delete all versions for this block
			$wpdb->delete(
				$table_name,
				array( 'block_id' => $block_id ),
				array( '%s' )
			);

			// Delete block settings
			$settings_table = $wpdb->prefix . 'wilcoskywb_wiki_block_settings';
			$wpdb->delete(
				$settings_table,
				array( 'block_id' => $block_id ),
				array( '%s' )
			);
		}

		// Also delete any versions that might be associated with this post directly
		$wpdb->delete(
			$table_name,
			array( 'post_id' => $post_id ),
			array( '%d' )
		);
	}
}