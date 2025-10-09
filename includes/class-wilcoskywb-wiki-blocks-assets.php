<?php
/**
 * Assets Handler for Wiki Blocks
 *
 * @package WikiBlocks
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assets handler class for Wiki Blocks
 *
 * @since 1.0.0
 */
class Wilcoskywb_Wiki_Blocks_Assets {

	/**
	 * Instance of this class
	 *
	 * @var Wilcoskywb_Wiki_Blocks_Assets
	 */
	private static $instance = null;

	/**
	 * Get instance of this class
	 *
	 * @return Wilcoskywb_Wiki_Blocks_Assets
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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_dashicons' ) );
	}

	/**
	 * Enqueue frontend assets
	 */
	public function enqueue_frontend_assets() {
		// Only enqueue on pages that might have wiki blocks
		if ( ! is_singular() && ! is_home() && ! is_archive() ) {
			return;
		}

		// Enqueue WordPress media library for frontend image insertion
		wp_enqueue_media();

		wp_enqueue_script(
			'wilcoskywb-wiki-blocks-frontend',
			self::get_asset_url( 'assets/js/frontend.js' ),
			array( 'jquery', 'media-upload', 'thickbox' ),
			self::get_asset_version( 'assets/js/frontend.js' ),
			true
		);

		wp_enqueue_style(
			'wilcoskywb-wiki-blocks-frontend-style',
			self::get_asset_url( 'assets/css/frontend.css' ),
			array(),
			self::get_asset_version( 'assets/css/frontend.css' )
		);

		// Localize script with data
		wp_localize_script(
			'wilcoskywb-wiki-blocks-frontend',
			'wilcoskywbWikiBlocksFrontend',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'postId' => get_the_ID(),
			'nonces' => array(
				'getVersions' => wp_create_nonce( 'wilcoskywb_wiki_blocks_get_versions' ),
				'suggestChange' => wp_create_nonce( 'wilcoskywb_wiki_blocks_suggest_change' ),
				'mergeVersion' => wp_create_nonce( 'wilcoskywb_wiki_blocks_merge_version' ),
			),
				'strings' => array(
					'loading' => __( 'Loading...', 'wiki-blocks' ),
					'error' => __( 'An error occurred. Please try again.', 'wiki-blocks' ),
					'noVersions' => __( 'No versions found.', 'wiki-blocks' ),
					'noVersionsHint' => __( 'Be the first to suggest a change!', 'wiki-blocks' ),
					'mergeConfirm' => __( 'Are you sure you want to make this version the current version?', 'wiki-blocks' ),
					'mergeSuccess' => __( 'Version merged successfully!', 'wiki-blocks' ),
					'suggestSuccess' => __( 'Your suggestion has been submitted successfully!', 'wiki-blocks' ),
					'close' => __( 'Close', 'wiki-blocks' ),
					'merge' => __( 'Merge', 'wiki-blocks' ),
					'submit' => __( 'Submit', 'wiki-blocks' ),
					'cancel' => __( 'Cancel', 'wiki-blocks' ),
					'confirm' => __( 'OK', 'wiki-blocks' ),
					'current' => __( 'Current', 'wiki-blocks' ),
					'on' => __( 'on', 'wiki-blocks' ),
					'by' => __( 'by', 'wiki-blocks' ),
					'readMore' => __( 'Read Full Version', 'wiki-blocks' ),
					'readLess' => __( 'Show Less', 'wiki-blocks' ),
				),
			)
		);
	}

	/**
	 * Enqueue Dashicons for consistent iconography
	 */
	public function enqueue_dashicons() {
		wp_enqueue_style( 'dashicons' );
	}

	/**
	 * Get asset URL with version
	 *
	 * @param string $path Asset path relative to plugin directory.
	 * @return string Asset URL with version.
	 */
	public static function get_asset_url( $path ) {
		$url = WILCOSKYWB_WIKI_BLOCKS_PLUGIN_URL . $path;
		$version = self::get_asset_version( $path );
		
		if ( $version ) {
			$url = add_query_arg( 'ver', $version, $url );
		}
		
		return $url;
	}

	/**
	 * Get asset version for cache busting
	 *
	 * @param string $path Asset path relative to plugin directory.
	 * @return string Asset version.
	 */
	public static function get_asset_version( $path ) {
		$file_path = WILCOSKYWB_WIKI_BLOCKS_PLUGIN_DIR . $path;
		
		if ( file_exists( $file_path ) ) {
			return WILCOSKYWB_WIKI_BLOCKS_VERSION . '.' . filemtime( $file_path );
		}
		
		return WILCOSKYWB_WIKI_BLOCKS_VERSION;
	}

	/**
	 * Check if asset exists
	 *
	 * @param string $path Asset path relative to plugin directory.
	 * @return bool True if asset exists, false otherwise.
	 */
	public static function asset_exists( $path ) {
		$file_path = WILCOSKYWB_WIKI_BLOCKS_PLUGIN_DIR . $path;
		return file_exists( $file_path );
	}

	/**
	 * Get asset contents
	 *
	 * @param string $path Asset path relative to plugin directory.
	 * @return string|false Asset contents or false if file doesn't exist.
	 */
	public static function get_asset_contents( $path ) {
		$file_path = WILCOSKYWB_WIKI_BLOCKS_PLUGIN_DIR . $path;
		
		if ( file_exists( $file_path ) ) {
			return file_get_contents( $file_path );
		}
		
		return false;
	}

	/**
	 * Minify CSS content
	 *
	 * @param string $css CSS content to minify.
	 * @return string Minified CSS.
	 */
	public static function minify_css( $css ) {
		// Remove comments
		$css = preg_replace( '!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css );
		
		// Remove unnecessary whitespace
		$css = preg_replace( '/\s+/', ' ', $css );
		$css = preg_replace( '/;\s*/', ';', $css );
		$css = preg_replace( '/:\s*/', ':', $css );
		$css = preg_replace( '/\s*{\s*/', '{', $css );
		$css = preg_replace( '/\s*}\s*/', '}', $css );
		$css = preg_replace( '/;\s*}/', '}', $css );
		
		// Remove leading/trailing whitespace
		$css = trim( $css );
		
		return $css;
	}

	/**
	 * Minify JavaScript content
	 *
	 * @param string $js JavaScript content to minify.
	 * @return string Minified JavaScript.
	 */
	public static function minify_js( $js ) {
		// Remove single-line comments (but preserve URLs)
		$js = preg_replace( '/(?<!:)\/\/.*$/m', '', $js );
		
		// Remove multi-line comments
		$js = preg_replace( '/\/\*[\s\S]*?\*\//', '', $js );
		
		// Remove unnecessary whitespace
		$js = preg_replace( '/\s+/', ' ', $js );
		$js = preg_replace( '/;\s*/', ';', $js );
		$js = preg_replace( '/,\s*/', ',', $js );
		$js = preg_replace( '/:\s*/', ':', $js );
		$js = preg_replace( '/\s*{\s*/', '{', $js );
		$js = preg_replace( '/\s*}\s*/', '}', $js );
		$js = preg_replace( '/;\s*}/', '}', $js );
		
		// Remove leading/trailing whitespace
		$js = trim( $js );
		
		return $js;
	}

	/**
	 * Inline critical CSS
	 *
	 * @param string $handle Script handle.
	 * @param string $css CSS content to inline.
	 */
	public static function inline_critical_css( $handle, $css ) {
		wp_add_inline_style( $handle, $css );
	}

	/**
	 * Inline critical JavaScript
	 *
	 * @param string $handle Script handle.
	 * @param string $js JavaScript content to inline.
	 */
	public static function inline_critical_js( $handle, $js ) {
		wp_add_inline_script( $handle, $js );
	}

	/**
	 * Defer non-critical JavaScript
	 *
	 * @param string $tag Script tag.
	 * @param string $handle Script handle.
	 * @param string $src Script source.
	 * @return string Modified script tag.
	 */
	public static function defer_js( $tag, $handle, $src ) {
		$defer_handles = array(
			'wilcoskywb-wiki-blocks-frontend',
		);
		
		if ( in_array( $handle, $defer_handles, true ) ) {
			return str_replace( '<script ', '<script defer ', $tag );
		}
		
		return $tag;
	}

	/**
	 * Preload critical assets
	 */
	public static function preload_critical_assets() {
		$critical_css = self::get_asset_contents( 'assets/css/frontend.css' );
		
		if ( $critical_css ) {
			$minified_css = self::minify_css( $critical_css );
			echo '<style id="wilcoskywb-wiki-blocks-critical-css">' . esc_html( $minified_css ) . '</style>';
		}
	}
} 