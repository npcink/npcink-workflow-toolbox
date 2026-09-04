<?php
/**
 * Main plugin coordinator.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use Npcink\LocalAutomationRuntime\NightlyInspection\Basic_WP_Cron_Dry_Run;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	public const OPTION_NAME         = 'npcink_toolbox_settings';
	public const CONTEXT_OPTION_NAME = 'npcink_toolbox_content_context';
	public const MEDIA_OPTION_NAME   = 'npcink_toolbox_media_optimization_settings';
	public const WATERMARK_OPTION_NAME = 'npcink_toolbox_watermark_templates';
	public const REST_NAMESPACE      = 'npcink-toolbox/v1';

	private static ?Plugin $instance = null;

	private Settings $settings;
	private Provider_Client $client;
	private Publish_Preflight_Service $publish_preflight;
	private Rest_Controller $rest_controller;
	private Admin_Page $admin_page;
	private Dashboard_Widget $dashboard_widget;
	private Editor_Content_Support $editor_content_support;
	private Article_Audio_Playback $article_audio_playback;
	private Site_Knowledge_Auto_Sync $site_knowledge_auto_sync;
	private Basic_WP_Cron_Dry_Run $nightly_inspection_cron;
	private Media_Fingerprint_Scan $media_fingerprint_scan;
	private Media_Recognition_Continuation $media_recognition_continuation;
	private Abilities $abilities;

	private function __construct() {
		$this->settings          = new Settings();
		$this->client            = new Provider_Client( $this->settings );
		$this->publish_preflight = new Publish_Preflight_Service();
		$this->rest_controller   = new Rest_Controller( $this->settings, $this->client, $this->publish_preflight );
		$this->admin_page        = new Admin_Page( $this->settings );
		$this->dashboard_widget = new Dashboard_Widget( $this->client );
		$this->editor_content_support = new Editor_Content_Support( $this->settings );
		$this->article_audio_playback = new Article_Audio_Playback();
		$this->site_knowledge_auto_sync = new Site_Knowledge_Auto_Sync( $this->client );
		$this->nightly_inspection_cron = new Basic_WP_Cron_Dry_Run( $this->settings );
		$this->media_fingerprint_scan = new Media_Fingerprint_Scan( $this->client );
		$this->media_recognition_continuation = new Media_Recognition_Continuation( $this->client );
		$this->abilities       = new Abilities( $this->settings, $this->client );
	}

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function register_hooks(): void {
		add_action( 'admin_init', array( $this->settings, 'register' ) );
		add_action( 'admin_menu', array( $this->admin_page, 'register_navigation' ), 5 );
		add_action( 'admin_menu', array( $this->admin_page, 'register_menu' ), 45 );
		add_action( 'admin_enqueue_scripts', array( $this->admin_page, 'enqueue' ) );
		add_action( 'admin_post_npcink_toolbox_download_scheduled_review_dry_run', array( $this->admin_page, 'download_scheduled_review_dry_run' ) );
		add_filter( 'attachment_fields_to_edit', array( $this->admin_page, 'add_media_library_attachment_actions' ), 10, 2 );
		add_filter( 'media_row_actions', array( $this->admin_page, 'filter_media_library_row_actions' ), 10, 3 );
		add_filter( 'bulk_actions-upload', array( $this->admin_page, 'filter_media_library_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-upload', array( $this->admin_page, 'handle_media_library_bulk_action' ), 10, 3 );
		$this->editor_content_support->register_hooks();
		$this->article_audio_playback->register_hooks();
		add_filter( 'plugin_action_links_' . plugin_basename( NPCINK_TOOLBOX_FILE ), array( $this, 'filter_plugin_action_links' ) );
		$this->dashboard_widget->register_hooks();
		$this->site_knowledge_auto_sync->register_hooks();
		$this->nightly_inspection_cron->register_hooks();
		$this->media_fingerprint_scan->register_hooks();
		$this->media_recognition_continuation->register_hooks();
		add_action( 'admin_post_npcink_toolbox_resume_media_recognition', array( $this->admin_page, 'handle_resume_media_recognition' ) );
		add_action( 'rest_api_init', array( $this->rest_controller, 'register_routes' ) );
		add_action( 'wp_abilities_api_categories_init', array( $this->abilities, 'register_with_npcink_abilities_toolkit' ), 1 );
		add_action( 'wp_abilities_api_categories_init', array( $this->abilities, 'register_native_category' ) );
		add_action( 'wp_abilities_api_init', array( $this->abilities, 'register_native_abilities' ) );
		add_filter( 'npcink_abilities_toolkit_media_backup_retention_days', array( $this, 'media_backup_retention_days' ) );
		add_filter( 'npcink_abilities_toolkit_media_backup_cleanup_policy', array( $this, 'media_backup_cleanup_policy' ), 10, 2 );
		add_filter( 'npcink_toolbox_refresh_site_media_index_batch', array( $this, 'refresh_site_media_index_batch' ), 10, 2 );
		add_filter( 'npcink_toolbox_media_recognition_start', array( $this, 'start_media_recognition' ), 10, 2 );
		add_filter( 'npcink_toolbox_media_recognition_status', array( $this, 'media_recognition_status' ) );
		add_filter( 'npcink_toolbox_media_recognition_resume', array( $this, 'resume_media_recognition' ) );
	}

	/**
	 * Provides the existing bounded media-index operation to local admin bridges.
	 *
	 * @param mixed $value Default filter value.
	 * @param mixed $input Batch arguments.
	 * @return mixed
	 */
	public function refresh_site_media_index_batch( $value, $input = array() ) {
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		return $this->client->refresh_site_media_index_batch( $input );
	}

	/** Starts one bounded local continuation through the existing bridge. */
	public function start_media_recognition( $value, $input = array() ): array {
		return $this->media_recognition_continuation->start( is_array( $input ) ? $input : array() );
	}

	/** Returns the bounded local continuation projection. */
	public function media_recognition_status( $value = array() ): array {
		return $this->media_recognition_continuation->status();
	}

	/** Resumes only an already-paused continuation. */
	public function resume_media_recognition( $value = array() ): array {
		return $this->media_recognition_continuation->resume();
	}

	/**
	 * Projects the local image backup retention choice to the Toolkit cleanup hook.
	 *
	 * @param mixed $days Toolkit default retention.
	 * @return int
	 */
	public function media_backup_retention_days( $days ): int {
		return 30;
	}

	/**
	 * Projects the administrator's cleanup mode into each newly-created Toolkit backup record.
	 * Existing history remains frozen and is not rewritten.
	 *
	 * @param mixed $policy Toolkit default policy.
	 * @param mixed $input Replacement input.
	 * @return string
	 */
	public function media_backup_cleanup_policy( $policy, $input = array() ): string {
		$settings = $this->settings->get_media_optimization_settings();
		return 'automatic' === (string) ( $settings['backup_cleanup_mode'] ?? 'manual' )
			? 'automatic_after_retention'
			: 'manual_confirmation_required';
	}

	/**
	 * Adds a settings shortcut on the WordPress plugins screen.
	 *
	 * @param array<int|string,string> $links Existing plugin action links.
	 * @return array<int|string,string>
	 */
	public function filter_plugin_action_links( array $links ): array {
		array_unshift(
			$links,
			sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $this->plugin_settings_url() ),
				esc_html__( 'Settings', 'npcink-workflow-toolbox' )
			)
		);

		return $links;
	}

	private function plugin_settings_url(): string {
		if ( function_exists( 'menu_page_url' ) ) {
			$url = menu_page_url( 'npcink-toolbox', false );
			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}

		return admin_url( $this->has_npcink_parent_menu() ? 'admin.php?page=npcink-toolbox' : 'tools.php?page=npcink-toolbox' );
	}

	private function has_npcink_parent_menu(): bool {
		global $menu;

		foreach ( (array) $menu as $item ) {
			if ( isset( $item[2] ) && 'npcink-ai' === $item[2] ) {
				return true;
			}
		}

		return false;
	}

}
