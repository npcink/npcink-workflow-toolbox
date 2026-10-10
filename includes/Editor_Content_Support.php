<?php
/**
 * Post editor entrypoint for fixed content-support flows.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

final class Editor_Content_Support {
	private Settings $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	public function register_hooks(): void {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
	}

	public function enqueue(): void {
		if ( ! Rest_Controller::user_can_use_editor_support() ) {
			return;
		}
		if ( ! $this->is_supported_editor_post_type() ) {
			return;
		}

		$style_path  = $this->asset_path( 'assets/editor-content-support.css' );
		$script_path = $this->asset_path( 'assets/editor-content-support.js' );

		wp_enqueue_style(
			'npcink-toolbox-editor-content-support',
			NPCINK_TOOLBOX_URL . $style_path,
			array(),
			$this->asset_version( $style_path )
		);

		wp_enqueue_script(
			'npcink-toolbox-editor-content-format',
			NPCINK_TOOLBOX_URL . $this->asset_path( 'assets/editor-content-format.js' ),
			array( 'wp-api-fetch', 'wp-blocks', 'wp-components', 'wp-data', 'wp-editor', 'wp-element', 'wp-block-editor', 'wp-i18n' ),
			$this->asset_version( $this->asset_path( 'assets/editor-content-format.js' ) ),
			true
		);
		wp_set_script_translations(
			'npcink-toolbox-editor-content-format',
			'npcink-workflow-toolbox',
			NPCINK_TOOLBOX_DIR . 'languages'
		);

		wp_enqueue_script(
			'npcink-toolbox-editor-content-support-text-utils',
			NPCINK_TOOLBOX_URL . $this->asset_path( 'assets/editor-content-support/text-utils.js' ),
			array(),
			$this->asset_version( $this->asset_path( 'assets/editor-content-support/text-utils.js' ) ),
			true
		);

		wp_enqueue_script(
			'npcink-toolbox-editor-content-support-internal-links',
			NPCINK_TOOLBOX_URL . $this->asset_path( 'assets/editor-content-support/internal-links.js' ),
			array( 'npcink-toolbox-editor-content-support-text-utils' ),
			$this->asset_version( $this->asset_path( 'assets/editor-content-support/internal-links.js' ) ),
			true
		);

		wp_enqueue_script(
			'npcink-toolbox-editor-content-support-audio',
			NPCINK_TOOLBOX_URL . $this->asset_path( 'assets/editor-content-support/audio-preferences.js' ),
			array( 'wp-i18n', 'wp-element', 'wp-components' ),
			$this->asset_version( $this->asset_path( 'assets/editor-content-support/audio-preferences.js' ) ),
			true
		);
		wp_set_script_translations(
			'npcink-toolbox-editor-content-support-audio',
			'npcink-workflow-toolbox',
			NPCINK_TOOLBOX_DIR . 'languages'
		);

		wp_enqueue_script(
			'npcink-toolbox-editor-content-support',
			NPCINK_TOOLBOX_URL . $script_path,
			array( 'npcink-toolbox-editor-content-format', 'npcink-toolbox-editor-content-support-internal-links', 'npcink-toolbox-editor-content-support-audio', 'wp-api-fetch', 'wp-block-editor', 'wp-blocks', 'wp-components', 'wp-core-data', 'wp-data', 'wp-edit-post', 'wp-editor', 'wp-element', 'wp-hooks', 'wp-i18n', 'wp-plugins', 'wp-rich-text' ),
			$this->asset_version( $script_path ),
			true
		);
		wp_set_script_translations(
			'npcink-toolbox-editor-content-support',
			'npcink-workflow-toolbox',
			NPCINK_TOOLBOX_DIR . 'languages'
		);

		wp_localize_script(
			'npcink-toolbox-editor-content-support',
			'NpcinkToolboxEditorSupport',
			array(
				'restUrl'                    => esc_url_raw( rest_url( Plugin::REST_NAMESPACE ) ),
				'coreRestUrl'                => esc_url_raw( rest_url( 'npcink-governance-core/v1' ) ),
				'adapterRestUrl'             => esc_url_raw( rest_url( 'npcink-openclaw-adapter/v1' ) ),
				'nonce'                      => wp_create_nonce( 'wp_rest' ),
				'adminUrl'                   => esc_url_raw( admin_url( 'admin.php?page=npcink-toolbox&toolbox_tab=tools' ) ),
				'cloudAddonSiteKnowledgeUrl' => esc_url_raw( admin_url( 'admin.php?page=npcink-cloud-addon&tab=site_knowledge' ) ),
				'coreAdminUrl'               => esc_url_raw( admin_url( 'admin.php?page=npcink-governance-core' ) ),
				'showRuntimeDiagnostics'     => $this->show_runtime_diagnostics(),
				'locale'                     => function_exists( 'determine_locale' ) ? determine_locale() : get_locale(),
			)
		);
	}

	private function show_runtime_diagnostics(): bool {
		return $this->settings->raw_responses_enabled();
	}

	/**
	 * The content-support sidebar ships for article-editing post types only;
	 * hosts can extend the allowlist (for example custom article post types)
	 * through this filter. Screens without a post type (widgets, site editor)
	 * never load the bundle.
	 */
	private function is_supported_editor_post_type(): bool {
		$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$post_type = ( $screen && ! empty( $screen->post_type ) ) ? sanitize_key( (string) $screen->post_type ) : '';
		if ( '' === $post_type ) {
			return false;
		}

		$supported = apply_filters( 'npcink_toolbox_editor_supported_post_types', array( 'post', 'page' ) );
		return in_array( $post_type, (array) $supported, true );
	}

	private function asset_path( string $relative_path ): string {
		$min_path = (string) preg_replace( '/\.(js|css)$/i', '.min.$1', $relative_path );
		if ( ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) || ! file_exists( NPCINK_TOOLBOX_DIR . $min_path ) ) {
			return $relative_path;
		}

		return $min_path;
	}

	private function asset_version( string $relative_path ): string {
		$path     = NPCINK_TOOLBOX_DIR . ltrim( $relative_path, '/' );
		$modified = file_exists( $path ) ? filemtime( $path ) : false;
		return NPCINK_TOOLBOX_VERSION . ( $modified ? '-' . (string) $modified : '' );
	}
}
