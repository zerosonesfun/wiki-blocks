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
			
			// Save as initial version only if we have content and a user
			$user_id = get_current_user_id();
			if ( $user_id && ! empty( trim( $display_content ) ) ) {
				$post_id = get_the_ID();
				Wilcoskywb_Wiki_Blocks_Database::insert_version( $block_id, $display_content, $user_id, __( 'Initial version', 'wiki-blocks' ), $post_id );
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
		$enhanced_html .= wp_kses_post( $display_content );
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
			$enhanced_html .= '<textarea id="wilcoskywb-wiki-content" name="content" rows="10" required>' . esc_textarea( $display_content ) . '</textarea>';
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
			if ( $user_id ) {
				$post_id = get_the_ID();
				Wilcoskywb_Wiki_Blocks_Database::insert_version( $block_id, $attributes['content'], $user_id, __( 'Initial version', 'wiki-blocks' ), $post_id );
				$display_content = $attributes['content'];
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
		$html .= wp_kses_post( $display_content );
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
			$html .= '<textarea id="wilcoskywb-wiki-content" name="content" rows="10" required>' . esc_textarea( $display_content ) . '</textarea>';
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
} 