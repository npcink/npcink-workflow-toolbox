<?php
/**
 * REST endpoints for Toolbox admin actions and future clients.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class Rest_Controller extends Rest_Controller_Support {

	private Settings $settings;
	private Provider_Client $client;
	private Publish_Preflight_Service $publish_preflight;
	private Rest_Nightly_Inspection_Bridges $nightly_bridges;
	private Rest_Web_Search_Bridges $web_search_bridges;
	private Rest_Site_Knowledge_Bridges $site_knowledge_bridges;
	private Rest_Media_Derivative_Previews $media_derivative_previews;
	private Rest_Flow_Plan_Bridges $flow_plan_bridges;
	private Rest_Media_Optimization_Bridges $media_optimization_bridges;
	private Rest_Surface_Bridges $surface_bridges;
	private Rest_Local_Admin_Consent $local_admin_consent;
	private Rest_Editor_Content_Support $editor_content_support_service;

	/** ADR-018 scoped default capabilities; every unlisted scope and the fallback stay manage_options. */
	private const SCOPED_DEFAULT_CAPABILITIES = array(
		'cap.toolbox.editor_suggest' => 'edit_posts',
		'cap.toolbox.image_source'   => 'edit_posts',
		'cap.toolbox.feedback.write' => 'edit_posts',
	);

	public function __construct( Settings $settings, Provider_Client $client, Publish_Preflight_Service $publish_preflight ) {
		$this->settings                       = $settings;
		$this->client                         = $client;
		$this->publish_preflight              = $publish_preflight;
		$this->nightly_bridges                = new Rest_Nightly_Inspection_Bridges( $settings, $client );
		$this->web_search_bridges             = new Rest_Web_Search_Bridges( $client );
		$this->site_knowledge_bridges         = new Rest_Site_Knowledge_Bridges( $client );
		$this->media_derivative_previews      = new Rest_Media_Derivative_Previews( $client );
		$this->flow_plan_bridges              = new Rest_Flow_Plan_Bridges( $client );
		$this->media_optimization_bridges     = new Rest_Media_Optimization_Bridges();
		$this->surface_bridges                = new Rest_Surface_Bridges( $settings, $client );
		$this->local_admin_consent            = new Rest_Local_Admin_Consent();
		$this->editor_content_support_service = new Rest_Editor_Content_Support( $client, $publish_preflight );
	}

	public function register_routes(): void {
		$this->post( '/image-candidates', 'image_candidates' );
		$this->post( '/web-search/test', 'web_search_test' );
		$this->post( '/web-search/diagnostics', 'web_search_diagnostics' );
		$this->post( '/site-knowledge/search', 'site_knowledge_search' );
		$this->post( '/site-knowledge/sync', 'site_knowledge_sync' );
		$this->post( '/site-media/index-batch', 'site_media_index_batch' );
		$this->post( '/agent-feedback', 'agent_feedback' );
		$this->post( '/agent-feedback/summary', 'agent_feedback_summary' );
		$this->post( '/ai/content-support', 'hosted_ai_content_support' );
		$this->post( '/ai/site-helpers', 'hosted_ai_site_helper' );
		$this->post( '/ai/image-generation', 'ai_image_generation' );
		$this->post( '/flows/article-plan', 'article_plan' );
		$this->post( '/flows/image-candidate-adoption-plan', 'image_candidate_adoption_plan' );
		$this->post( '/flows/article-audio-adoption-plan', 'article_audio_adoption_plan' );
		$this->post( '/local-admin-consent/featured-image', 'local_admin_consent_featured_image' );
		$this->post( '/media-optimization-manifest', 'media_optimization_manifest' );
		$this->get( '/media-optimization-health', 'media_optimization_health' );
		$this->post( '/media-optimization-batches', 'media_optimization_batch_create' );
		$this->get( '/media-optimization-batches', 'media_optimization_batches' );
		$this->get( '/media-optimization-batches/current', 'media_optimization_batch_current' );
		$this->get( '/media-backup-cleanup/preview', 'media_backup_cleanup_preview' );
		$this->post( '/media-backup-cleanup/confirm', 'media_backup_cleanup_confirm' );
		$this->post( '/media-optimization-batches/(?P<batch_id>media_opt_[A-Za-z0-9]+)/confirm', 'media_optimization_batch_confirm' );
		$this->post( '/media-optimization-batches/(?P<batch_id>media_opt_[A-Za-z0-9]+)/items/(?P<attachment_id>[0-9]+)/complete', 'media_optimization_batch_complete_item' );
		$this->post( '/media-optimization-batches/(?P<batch_id>media_opt_[A-Za-z0-9]+)/items/(?P<attachment_id>[0-9]+)/restore', 'media_optimization_batch_restore_item' );
		$this->post( '/flows/site-knowledge-review-plan', 'site_knowledge_review_plan' );
		$this->post( '/flows/nightly-inspection-review-plan', 'nightly_inspection_review_plan' );
		$this->post( '/flows/content-metadata-apply-plan', 'content_metadata_apply_plan' );
		$this->post( '/flows/media-alt-caption-review-plan', 'media_alt_caption_review_plan' );
		$this->post( '/flows/media-brief', 'media_brief' );
		$this->post( '/editor/content-support', 'editor_content_support' );
		$this->post( '/media-derivative-handoff', 'media_derivative_handoff' );
		$this->post( '/media-derivative-preview', 'create_media_derivative_preview' );
		$this->get( '/media-derivative-preview/(?P<run_id>[A-Za-z0-9._:-]+)', 'get_media_derivative_preview' );
		$this->get( '/media-derivative-preview/(?P<run_id>[A-Za-z0-9._:-]+)/result', 'get_media_derivative_preview_result' );
		$this->post( '/media-derivative-optimization-payload', 'build_media_derivative_optimization_payload' );
		$this->post( '/nightly-inspection/cloud-batch', 'nightly_inspection_cloud_batch' );
		$this->get( '/nightly-inspection/cloud-runtime-entitlement', 'nightly_inspection_cloud_runtime_entitlement' );
		$this->get( '/nightly-inspection/cloud-batch/recent', 'nightly_inspection_cloud_batch_recent' );
		$this->get( '/nightly-inspection/cloud-batch/(?P<run_id>[A-Za-z0-9._:-]+)', 'nightly_inspection_cloud_batch_status' );
		$this->get( '/nightly-inspection/cloud-batch/(?P<run_id>[A-Za-z0-9._:-]+)/result', 'nightly_inspection_cloud_batch_result' );
		$this->post( '/nightly-inspection/cloud-batch/(?P<run_id>[A-Za-z0-9._:-]+)/result', 'nightly_inspection_cloud_batch_result' );
		$this->post( '/nightly-inspection/cloud-batch/(?P<run_id>[A-Za-z0-9._:-]+)/retry', 'nightly_inspection_cloud_batch_retry' );
		$this->post( '/review-tally/mark', 'review_tally_mark' );
		$this->get( '/review-tally/summary', 'review_tally_summary' );

		register_rest_route(
			Plugin::REST_NAMESPACE,
			'/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'status' ),
				'permission_callback' => array( $this, 'permission' ),
			)
		);

		register_rest_route(
			Plugin::REST_NAMESPACE,
			'/site-knowledge/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'site_knowledge_status' ),
				'permission_callback' => array( $this, 'permission' ),
			)
		);

		register_rest_route(
			Plugin::REST_NAMESPACE,
			'/media-derivative-local-review/(?P<artifact_id>art_[0-9a-f]{32})',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'serve_media_derivative_local_review' ),
				'permission_callback' => array( $this, 'permission' ),
				'args'                => $this->media_derivative_local_review_route_args(),
			)
		);
	}

	public function permission( $request = null ): bool {
		$route          = $request instanceof WP_REST_Request ? $this->normalize_route_for_scope( (string) $request->get_route() ) : '';
		$required_scope = $this->rest_route_scope( $route );
		if ( $request instanceof WP_REST_Request && $this->requires_present_admin_ui( $route, $request->get_method() ) && ! $this->is_present_admin_ui_request( $request ) ) {
			return false;
		}

		$default_capability = self::default_capability_for_scope( $required_scope );
		return $this->filtered_rest_permission( current_user_can( $default_capability ), $request, $required_scope, $route );
	}

	public static function default_capability_for_scope( string $scope ): string {
		return self::SCOPED_DEFAULT_CAPABILITIES[ $scope ] ?? 'manage_options';
	}

	public static function user_can_use_editor_support(): bool {
		return current_user_can( self::default_capability_for_scope( 'cap.toolbox.editor_suggest' ) );
	}

	private function requires_present_admin_ui( string $route, string $method ): bool {
		if ( 'POST' !== strtoupper( $method ) ) {
			return false;
		}

		if ( in_array( $route, array( '/local-admin-consent/featured-image', '/media-optimization-manifest', '/media-optimization-batches', '/media-backup-cleanup/confirm' ), true ) ) {
			return true;
		}

		return 1 === preg_match( '#^/media-optimization-batches/media_opt_[A-Za-z0-9]+(?:/confirm|/items/[0-9]+/(?:complete|restore))$#', $route );
	}

	private function is_present_admin_ui_request( WP_REST_Request $request ): bool {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return false;
		}

		if ( ! defined( 'LOGGED_IN_COOKIE' ) || empty( $_COOKIE[ LOGGED_IN_COOKIE ] ) ) {
			return false;
		}

		$source = (string) ( $request->get_header( 'Origin' ) ?: $request->get_header( 'Referer' ) );
		if ( '' === $source ) {
			return false;
		}

		$source_origin = $this->url_origin( $source );
		return '' !== $source_origin && in_array( $source_origin, array( $this->url_origin( home_url( '/' ) ), $this->url_origin( admin_url( '/' ) ) ), true );
	}

	private function url_origin( string $url ): string {
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		$host   = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$port   = absint( wp_parse_url( $url, PHP_URL_PORT ) );
		if ( '' === $scheme || '' === $host || ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return '';
		}
		if ( 0 === $port ) {
			$port = 'https' === $scheme ? 443 : 80;
		}

		return $scheme . '://' . $host . ':' . $port;
	}

	private function filtered_rest_permission( bool $allowed, $request, string $required_scope, string $route ): bool {
		return (bool) apply_filters( 'npcink_toolbox_rest_permission', $allowed, $request, $required_scope, $route );
	}

	private function normalize_route_for_scope( string $route ): string {
		$prefix = '/' . Plugin::REST_NAMESPACE;
		if ( str_starts_with( $route, $prefix ) ) {
			$route = substr( $route, strlen( $prefix ) );
		}

		return '' === $route ? '/' : $route;
	}

	private function rest_route_scope( string $route ): string {
		if ( preg_match( '#^/media-derivative-preview/[A-Za-z0-9._:-]+(?:/result)?$#', $route ) ) {
			return 'cap.toolbox.workflow_suggest';
		}
		if ( preg_match( '#^/nightly-inspection/cloud-batch/[A-Za-z0-9._:-]+(?:/result|/retry)?$#', $route ) ) {
			return 'cap.toolbox.nightly_inspection';
		}
		if ( preg_match( '#^/media-derivative-local-review/art_[0-9a-f]{32}$#', $route ) ) {
			return 'cap.toolbox.workflow_suggest';
		}
		if ( preg_match( '#^/media-optimization-batches(?:/current|/media_opt_[A-Za-z0-9]+(?:/confirm|/items/[0-9]+/(?:complete|restore))?)?$#', $route ) ) {
			return 'cap.toolbox.image_adoption';
		}
		if ( in_array( $route, array( '/media-backup-cleanup/preview', '/media-backup-cleanup/confirm' ), true ) ) {
			return 'cap.toolbox.image_adoption';
		}

		$scopes = array(
			'/status'                                  => 'cap.toolbox.status.read',
			'/image-candidates'                        => 'cap.toolbox.image_source',
			'/web-search/test'                         => 'cap.toolbox.web_search',
			'/web-search/diagnostics'                  => 'cap.toolbox.web_search',
			'/site-knowledge/status'                   => 'cap.toolbox.knowledge.read',
			'/site-knowledge/search'                   => 'cap.toolbox.knowledge.search',
			'/site-knowledge/sync'                     => 'cap.toolbox.knowledge.sync',
			'/site-media/index-batch'                  => 'cap.toolbox.knowledge.sync',
			'/agent-feedback'                          => 'cap.toolbox.feedback.write',
			'/agent-feedback/summary'                  => 'cap.toolbox.feedback.read',
			'/ai/content-support'                      => 'cap.toolbox.workflow_suggest',
			'/ai/site-helpers'                         => 'cap.toolbox.workflow_suggest',
			'/review-tally/mark'                       => 'cap.toolbox.workflow_suggest',
			'/review-tally/summary'                    => 'cap.toolbox.workflow_suggest',
			'/ai/image-generation'                     => 'cap.toolbox.image_source',
			'/flows/article-plan'                      => 'cap.toolbox.workflow_suggest',
			'/flows/image-candidate-adoption-plan'     => 'cap.toolbox.workflow_suggest',
			'/flows/article-audio-adoption-plan'       => 'cap.toolbox.workflow_suggest',
			'/local-admin-consent' . '/featured-image' => 'cap.toolbox.local_admin_consent',
			'/media-optimization-manifest'             => 'cap.toolbox.image_adoption',
			'/media-optimization-health'               => 'cap.toolbox.image_adoption',
			'/flows/site-knowledge-review-plan'        => 'cap.toolbox.workflow_suggest',
			'/flows/nightly-inspection-review-plan'    => 'cap.toolbox.workflow_suggest',
			'/flows/content-metadata-apply-plan'       => 'cap.toolbox.workflow_suggest',
			'/flows/media-alt-caption-review-plan'     => 'cap.toolbox.workflow_suggest',
			'/flows/media-brief'                       => 'cap.toolbox.workflow_suggest',
			'/editor/content-support'                  => 'cap.toolbox.editor_suggest',
			'/media-derivative-handoff'                => 'cap.toolbox.workflow_suggest',
			'/media-derivative-preview'                => 'cap.toolbox.workflow_suggest',
			'/media-derivative-optimization-payload'   => 'cap.toolbox.workflow_suggest',
			'/nightly-inspection/cloud-runtime-entitlement' => 'cap.toolbox.nightly_inspection',
			'/nightly-inspection/cloud-batch'          => 'cap.toolbox.nightly_inspection',
			'/nightly-inspection/cloud-batch/recent'   => 'cap.toolbox.nightly_inspection',
		);

		return $scopes[ $route ] ?? 'cap.toolbox.admin';
	}

	public function status(): WP_REST_Response {
		return $this->surface_bridges->status();
	}

	public function media_optimization_health(): WP_REST_Response {
		return $this->media_optimization_bridges->media_optimization_health();
	}

	public function image_candidates( WP_REST_Request $request ) {
		return $this->surface_bridges->image_candidates( $request );
	}

	public function site_media_index_batch( WP_REST_Request $request ) {
		return $this->surface_bridges->site_media_index_batch( $request );
	}

	public function site_knowledge_status( WP_REST_Request $request ) {
		return $this->site_knowledge_bridges->site_knowledge_status( $request );
	}

	public function web_search_test( WP_REST_Request $request ) {
		return $this->web_search_bridges->web_search_test( $request );
	}

	public function web_search_diagnostics( WP_REST_Request $request ) {
		return $this->web_search_bridges->web_search_diagnostics( $request );
	}

	public function site_knowledge_sync( WP_REST_Request $request ) {
		return $this->site_knowledge_bridges->site_knowledge_sync( $request );
	}

	public function site_knowledge_search( WP_REST_Request $request ) {
		return $this->site_knowledge_bridges->site_knowledge_search( $request );
	}

	public function hosted_ai_content_support( WP_REST_Request $request ) {
		$params = method_exists( $request, 'get_params' ) ? $request->get_params() : array();
		return rest_ensure_response( $this->client->run_hosted_ai_content_support( is_array( $params ) ? $params : array() ) );
	}

	public function hosted_ai_site_helper( WP_REST_Request $request ) {
		$params = method_exists( $request, 'get_params' ) ? $request->get_params() : array();
		return rest_ensure_response( $this->client->run_hosted_ai_site_helper( is_array( $params ) ? $params : array() ) );
	}

	public function ai_image_generation( WP_REST_Request $request ) {
		$params = method_exists( $request, 'get_params' ) ? $request->get_params() : array();
		return rest_ensure_response( $this->client->run_ai_image_generation( is_array( $params ) ? $params : array() ) );
	}

	public function nightly_inspection_cloud_batch( WP_REST_Request $request ) {
		return $this->nightly_bridges->nightly_inspection_cloud_batch( $request );
	}

	public function nightly_inspection_cloud_batch_status( WP_REST_Request $request ) {
		return $this->nightly_bridges->nightly_inspection_cloud_batch_status( $request );
	}

	public function nightly_inspection_cloud_batch_recent( WP_REST_Request $request ) {
		return $this->nightly_bridges->nightly_inspection_cloud_batch_recent( $request );
	}

	public function nightly_inspection_cloud_runtime_entitlement() {
		return $this->nightly_bridges->nightly_inspection_cloud_runtime_entitlement();
	}

	public function nightly_inspection_cloud_batch_result( WP_REST_Request $request ) {
		return $this->nightly_bridges->nightly_inspection_cloud_batch_result( $request );
	}

	public function review_tally_mark( WP_REST_Request $request ) {
		$review_set = sanitize_key( (string) $request->get_param( 'review_set' ) );
		$decision   = sanitize_key( (string) $request->get_param( 'decision' ) );
		if ( ! array_key_exists( $review_set, Settings::REVIEW_TALLY_SETS ) ) {
			return new WP_Error(
				'npcink_toolbox_review_tally_unknown_set',
				__( 'A supported review set is required.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}
		if ( ! in_array( $decision, array( 'accepted', 'ignored' ), true ) ) {
			return new WP_Error(
				'npcink_toolbox_review_tally_invalid_decision',
				__( 'The tally decision must be accepted or ignored.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}
		$this->settings->record_review_tally_mark( $review_set, $decision );
		return rest_ensure_response( $this->settings->review_tally_summary() );
	}

	public function review_tally_summary( WP_REST_Request $request ) {
		$requested_days = absint( $request->get_param( 'days' ) );
		$days           = $requested_days ? max( 1, min( 90, $requested_days ) ) : 7;
		return rest_ensure_response( $this->settings->review_tally_summary( $days ) );
	}

	public function nightly_inspection_cloud_batch_retry( WP_REST_Request $request ) {
		return $this->nightly_bridges->nightly_inspection_cloud_batch_retry( $request );
	}

	public function agent_feedback( WP_REST_Request $request ) {
		return $this->surface_bridges->agent_feedback( $request );
	}

	public function agent_feedback_summary( WP_REST_Request $request ) {
		return $this->surface_bridges->agent_feedback_summary( $request );
	}

	public function article_plan( WP_REST_Request $request ) {
		return $this->flow_plan_bridges->article_plan( $request );
	}

	public function image_candidate_adoption_plan( WP_REST_Request $request ) {
		return $this->flow_plan_bridges->image_candidate_adoption_plan( $request );
	}

	public function article_audio_adoption_plan( WP_REST_Request $request ) {
		return $this->flow_plan_bridges->article_audio_adoption_plan( $request );
	}

	public function local_admin_consent_featured_image( WP_REST_Request $request ) {
		return $this->local_admin_consent->local_admin_consent_featured_image( $request );
	}

	public function media_optimization_batch_create( WP_REST_Request $request ) {
		return $this->media_optimization_bridges->media_optimization_batch_create( $request );
	}

	public function media_optimization_manifest( WP_REST_Request $request ) {
		return $this->media_optimization_bridges->media_optimization_manifest( $request );
	}

	public function media_optimization_batches() {
		return $this->media_optimization_bridges->media_optimization_batches();
	}

	public function media_optimization_batch_current() {
		return $this->media_optimization_bridges->media_optimization_batch_current();
	}

	public function media_optimization_batch_confirm( WP_REST_Request $request ) {
		return $this->media_optimization_bridges->media_optimization_batch_confirm( $request );
	}

	public function media_optimization_batch_complete_item( WP_REST_Request $request ) {
		return $this->media_optimization_bridges->media_optimization_batch_complete_item( $request );
	}

	public function media_optimization_batch_restore_item( WP_REST_Request $request ) {
		return $this->media_optimization_bridges->media_optimization_batch_restore_item( $request );
	}

	public function media_backup_cleanup_preview() {
		return $this->media_optimization_bridges->media_backup_cleanup_preview();
	}

	public function media_backup_cleanup_confirm( WP_REST_Request $request ) {
		return $this->media_optimization_bridges->media_backup_cleanup_confirm( $request );
	}

	public function site_knowledge_review_plan( WP_REST_Request $request ) {
		return $this->site_knowledge_bridges->site_knowledge_review_plan( $request );
	}
	public function nightly_inspection_review_plan( WP_REST_Request $request ) {
		return $this->flow_plan_bridges->nightly_inspection_review_plan( $request );
	}

	public function content_metadata_apply_plan( WP_REST_Request $request ) {
		return $this->flow_plan_bridges->content_metadata_apply_plan( $request );
	}

	public function media_alt_caption_review_plan( WP_REST_Request $request ) {
		return $this->flow_plan_bridges->media_alt_caption_review_plan( $request );
	}

	public function media_brief( WP_REST_Request $request ) {
		return $this->editor_content_support_service->media_brief( $request );
	}

	public function editor_content_support( WP_REST_Request $request ) {
		return $this->editor_content_support_service->editor_content_support( $request );
	}

	public function media_derivative_handoff( WP_REST_Request $request ) {
		return $this->media_derivative_previews->media_derivative_handoff( $request );
	}

	public function create_media_derivative_preview( WP_REST_Request $request ) {
		return $this->media_derivative_previews->create_media_derivative_preview( $request );
	}

	public function get_media_derivative_preview( WP_REST_Request $request ) {
		return $this->media_derivative_previews->get_media_derivative_preview( $request );
	}

	public function get_media_derivative_preview_result( WP_REST_Request $request ) {
		return $this->media_derivative_previews->get_media_derivative_preview_result( $request );
	}

	public function build_media_derivative_optimization_payload( WP_REST_Request $request ) {
		return $this->media_derivative_previews->build_media_derivative_optimization_payload( $request );
	}

	public function serve_media_derivative_local_review( WP_REST_Request $request ) {
		return $this->media_derivative_previews->serve_media_derivative_local_review( $request );
	}

	private function post( string $route, string $method, array $args = array() ): void {
		register_rest_route(
			Plugin::REST_NAMESPACE,
			$route,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, $method ),
				'permission_callback' => array( $this, 'permission' ),
				'args'                => $args,
			)
		);
	}

	private function get( string $route, string $method ): void {
		register_rest_route(
			Plugin::REST_NAMESPACE,
			$route,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, $method ),
				'permission_callback' => array( $this, 'permission' ),
			)
		);
	}

	private function media_derivative_local_review_route_args(): array {
		return array(
			'artifact_id' => array(
				'required'          => true,
				'type'              => 'string',
				'validate_callback' => static fn( $value ): bool => is_string( $value ) && 1 === preg_match( '/^art_[0-9a-f]{32}$/', $value ),
			),
			'artifact'    => array(
				'required'             => true,
				'type'                 => 'object',
				'additionalProperties' => false,
				'validate_callback'    => 'rest_validate_request_arg',
				'properties'           => array(
					'artifact_id'         => array(
						'required' => true,
						'type'     => 'string',
						'pattern'  => '^art_[0-9a-f]{32}$',
					),
					'expires_at'          => array(
						'required' => true,
						'type'     => 'string',
					),
					'mime_type'           => array(
						'required' => true,
						'type'     => 'string',
						'enum'     => array( 'image/avif', 'image/jpeg', 'image/png', 'image/webp' ),
					),
					'format'              => array(
						'required' => true,
						'type'     => 'string',
						'enum'     => array( 'avif', 'jpeg', 'png', 'webp' ),
					),
					'width'               => array(
						'required' => true,
						'type'     => 'integer',
						'minimum'  => 1,
						'maximum'  => 8192,
					),
					'height'              => array(
						'required' => true,
						'type'     => 'integer',
						'minimum'  => 1,
						'maximum'  => 8192,
					),
					'filesize_bytes'      => array(
						'required' => true,
						'type'     => 'integer',
						'minimum'  => 1,
						'maximum'  => 26214400,
					),
					'sha256'              => array(
						'required' => true,
						'type'     => 'string',
						'pattern'  => '^[0-9a-f]{64}$',
					),
					'suggested_filename'  => array(
						'required'  => true,
						'type'      => 'string',
						'minLength' => 1,
						'maxLength' => 120,
					),
					'filename_basis'      => array(
						'required' => true,
						'type'     => 'object',
						'anyOf'    => array(
							array(
								'type'                 => 'object',
								'additionalProperties' => false,
								'required'             => array( 'owner', 'strategy', 'final_sanitize_unique_required' ),
								'properties'           => array(
									'owner'    => array(
										'type' => 'string',
										'enum' => array( 'wordpress_write_ability_final' ),
									),
									'strategy' => array(
										'type' => 'string',
										'enum' => array( 'format_checksum' ),
									),
									'final_sanitize_unique_required' => array(
										'type' => 'boolean',
										'enum' => array( true ),
									),
								),
							),
						),
					),
					'processing_warnings' => array(
						'required' => true,
						'type'     => 'array',
						'maxItems' => 20,
						'items'    => array(
							'type'      => 'string',
							'maxLength' => 200,
						),
					),
					'transform_facts'     => array(
						'required'             => true,
						'type'                 => 'object',
						'additionalProperties' => true,
					),
				),
			),
		);
	}
}
