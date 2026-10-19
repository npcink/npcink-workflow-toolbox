<?php
/**
 * Status, image-source, media-recognition, and agent-feedback REST bridge
 * cluster, moved verbatim from Rest_Controller behind one-line facade
 * delegates.
 *
 * Read-only status projection and feedback transports: no WordPress
 * writes, no provider credentials, no new runtime ownership.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class Rest_Surface_Bridges extends Rest_Controller_Support {

	private Settings $settings;
	private Provider_Client $client;

	public function __construct( Settings $settings, Provider_Client $client ) {
		$this->settings = $settings;
		$this->client   = $client;
	}

	public function status(): WP_REST_Response {
		$cloud_runtime = $this->settings->cloud_runtime_status();
		$cloud_ready   = (bool) $cloud_runtime['available'];

		return rest_ensure_response(
			array(
				'image_provider'                 => 'cloud_image_sources',
				'image_source_providers'         => $this->settings->configured_image_source_providers(),
				'vector_provider'                => 'cloud_site_knowledge',
				'web_search_owner'               => 'cloud_runtime',
				'cloud_image_sources_configured' => $this->settings->has_image_source_provider(),
				'raw_responses_enabled'          => $this->settings->raw_responses_enabled(),
				'image_source_enabled'           => (bool) $this->settings->get( 'enable_image_source' ),
				'image_source_available'         => $cloud_ready && (bool) $this->settings->get( 'enable_image_source' ),
				'vector_search_registered'       => true,
				'vector_search_enabled'          => $cloud_ready,
				'web_search_registered'          => true,
				'web_search_enabled'             => $cloud_ready,
				'image_source_owner'             => 'cloud_runtime',
				'ai_image_generation'            => array(
					'registered'             => true,
					'available'              => $cloud_ready,
					'hosted_profile'         => 'grok-imagine-image-quality',
					'entry_surface'          => 'image_source_ai_generation_handoff',
					'posture'                => 'candidate_only_core_approval_required',
					'direct_wordpress_write' => false,
				),
				'vector_owner'                   => 'cloud_runtime',
				'cloud_runtime'                  => $cloud_runtime,
				'hosted_ai'                      => array(
					'entry_surface'           => 'toolbox_content_support',
					'hosted_profile'          => 'text.ai',
					'registered'              => true,
					'site_helpers_registered' => true,
					'available'               => $cloud_ready,
					'posture'                 => 'suggestion_only_core_approval_required',
				),
				'content_operations'             => $this->content_operations_projection( $cloud_ready ),
				'pro_nightly_inspection'         => array(
					'registered'             => true,
					'available'              => $cloud_ready && ! Settings::cloud_feature_paused( 'cloud_inspection' ),
					'availability_state'     => Settings::cloud_feature_paused( 'cloud_inspection' ) ? 'paused' : 'available',
					'entry_surface'          => 'nightly_inspection_cloud_batch',
					'runtime_owner'          => 'npcink-local-automation-runtime',
					'cloud_role'             => 'runtime_detail',
					'posture'                => 'review_only_core_proposal_required',
					'direct_wordpress_write' => false,
					'polling_registered'     => true,
					'entitlement_route'      => '/nightly-inspection/cloud-runtime-entitlement',
					'recent_route'           => '/nightly-inspection/cloud-batch/recent',
					'retry_registered'       => ! Settings::cloud_feature_paused( 'cloud_inspection' ),
				),
				'boundary'                       => 'Toolbox returns Cloud-managed image-source and Cloud-managed site-knowledge suggestions only. Cloud owns web search execution and provider configuration. WordPress writes should be handed to Abilities/Core governance.',
			)
		);
	}

	/**
	 * Performs a live, read-only Cloud readiness check before media processing.
	 *
	 * @return WP_REST_Response
	 */

	public function image_candidates( WP_REST_Request $request ) {
		if ( ! $this->settings->get( 'enable_image_source' ) ) {
			return $this->disabled_error( 'image source search' );
		}

		$query = $this->required_text( $request, 'query' );
		if ( is_wp_error( $query ) ) {
			return $query;
		}

		$args = array(
			'orientation'          => sanitize_key( (string) $request->get_param( 'orientation' ) ),
			'color'                => sanitize_key( (string) $request->get_param( 'color' ) ),
			'provider'             => sanitize_key( (string) $request->get_param( 'provider' ) ),
			'per_page'             => (int) ( $request->get_param( 'per_page' ) ?: 8 ),
			'latency_mode'         => sanitize_key( (string) $request->get_param( 'latency_mode' ) ),
			'include_ai_generated' => ! empty( $request->get_param( 'include_ai_generated' ) ),
			'generation_prompt'    => sanitize_textarea_field( (string) $request->get_param( 'generation_prompt' ) ),
			'generated_image_url'  => esc_url_raw( (string) $request->get_param( 'generated_image_url' ) ),
			'model'                => sanitize_text_field( (string) $request->get_param( 'model' ) ),
			'manual_query'         => $query,
			'refresh_variant'      => sanitize_text_field( (string) $request->get_param( 'refresh_variant' ) ),
			'visual_context'       => $this->image_visual_context_from_request( $request, $query ),
		);

		// Identical author/contributor searches coalesce behind a short
		// per-user transient: an explicit refresh variant skips the read
		// but overwrites the entry, an unencodable payload skips
		// coalescing, and oversized payloads skip the database write.
		$cache_key = '';
		$key_args  = $args;
		$refresh   = is_scalar( $key_args['refresh_variant'] ?? '' ) && '' !== trim( (string) $key_args['refresh_variant'] );
		unset( $key_args['refresh_variant'] );
		if ( isset( $key_args['visual_context']['refresh_variant'] ) ) {
			// image_visual_context_from_request() mirrors the refresh token
			// into the visual context; the key must hash it out too, or a
			// refresh writes a one-time dead key instead of overwriting.
			unset( $key_args['visual_context']['refresh_variant'] );
		}
		$encoded = wp_json_encode( $key_args );
		if ( is_string( $encoded ) ) {
			$cache_key = 'npcink_toolbox_provider_image_source_' . md5( (string) get_current_user_id() . $encoded );
			if ( ! $refresh ) {
				$cached = get_transient( $cache_key );
				if ( is_array( $cached ) && isset( $cached['__provider_coalesced'] ) ) {
					unset( $cached['__provider_coalesced'] );
					return rest_ensure_response( $cached );
				}
			}
		}

		$result = $this->client->image_candidates( $query, $args );
		if ( is_wp_error( $result ) ) {
			if ( $refresh && '' !== $cache_key ) {
				delete_transient( $cache_key );
			}
			return rest_ensure_response( self::sanitize_provider_error_data( $result ) );
		}
		if ( '' !== $cache_key ) {
			$should_cache = is_array( $result ) && ! isset( $result['code'] ) && ! empty( $result['images'] );
			if ( $should_cache ) {
				$cache_copy                         = $result;
				$cache_copy['__provider_coalesced'] = true;
				$encoded_result                     = wp_json_encode( $cache_copy );
				if ( is_string( $encoded_result ) && strlen( $encoded_result ) <= 102400 ) {
					set_transient( $cache_key, $cache_copy, 60 );
					return rest_ensure_response( $result );
				}
			}
			if ( $refresh ) {
				delete_transient( $cache_key );
			}
		}

		return rest_ensure_response( $result );
	}

	public function site_media_index_batch( WP_REST_Request $request ) {
		$continuation = apply_filters(
			'npcink_toolbox_media_recognition_start',
			array(),
			array( 'per_page' => max( 1, min( 10, (int) ( $request->get_param( 'per_page' ) ?: 10 ) ) ) )
		);
		if ( is_array( $continuation ) && '' !== (string) ( $continuation['plan_id'] ?? '' ) ) {
			return rest_ensure_response( $continuation );
		}

		return new WP_Error( 'npcink_toolbox_media_recognition_unavailable', __( 'Media recognition continuation is unavailable.', 'npcink-workflow-toolbox' ), array( 'status' => 503 ) );
	}

	public function agent_feedback( WP_REST_Request $request ) {
		$params = method_exists( $request, 'get_json_params' ) ? $request->get_json_params() : array();
		if ( ! is_array( $params ) ) {
			$params = method_exists( $request, 'get_params' ) ? $request->get_params() : array();
		}

		return rest_ensure_response( $this->client->submit_agent_feedback( is_array( $params ) ? $params : array() ) );
	}

	public function agent_feedback_summary( WP_REST_Request $request ) {
		$params = method_exists( $request, 'get_json_params' ) ? $request->get_json_params() : array();
		if ( ! is_array( $params ) ) {
			$params = method_exists( $request, 'get_params' ) ? $request->get_params() : array();
		}

		return rest_ensure_response( $this->client->get_agent_feedback_summary( is_array( $params ) ? $params : array() ) );
	}

	private function content_operations_projection( bool $cloud_ready ): array {
		$projection = array(
			'contract_version'       => 'toolbox_content_operations_projection.v1',
			'registered'             => true,
			'available'              => $cloud_ready,
			'write_posture'          => 'suggestion_only',
			'final_write_path'       => 'core_proposal_required',
			'approval_truth'         => 'wordpress_local',
			'final_write_truth'      => 'wordpress_local',
			'direct_wordpress_write' => false,
			'projection_role'        => 'single_toolbox_status_projection',
			'surfaces'               => array(
				'editor_content_support' => array(
					'route'          => '/editor/content-support',
					'artifact_type'  => 'editor_content_support_flow',
					'source_layers'  => array( 'local_editor_context', 'cloud_site_knowledge', 'cloud_web_search', 'hosted_ai' ),
					'intents'        => array( 'progressive_recommendations', 'writing_support', 'zhihu_research', 'zhihu_hot_topics', 'article_checkup', 'title_suggestions', 'article_outline', 'polish_notes', 'summary_suggestions', 'category_suggestions', 'tag_suggestions', 'summary_terms_optimization', 'taxonomy_tags', 'internal_links', 'image_candidates', 'image_alt_suggestions', 'comment_reply_suggestion', 'publish_preflight', 'discoverability' ),
					'feedback_scope' => 'editor_content_support',
				),
				'nightly_inspection'     => array(
					'route'          => '/nightly-inspection/cloud-batch',
					'contracts'      => array( 'nightly_site_inspection_morning_brief.v2', 'nightly_site_inspection_core_intake_package.v1' ),
					'source_layers'  => array( 'local_site_snapshot', 'cloud_batch_runtime' ),
					'feedback_scope' => 'nightly_site_inspection',
				),
				'site_knowledge'         => array(
					'route'          => '/site-knowledge/search',
					'intents'        => array( 'site_search', 'related_content', 'writing_context', 'internal_links', 'refresh_suggestions', 'image_context', 'faq_candidates', 'content_gap_analysis', 'duplicate_check', 'writing_support_plan' ),
					'source_layers'  => array( 'cloud_site_knowledge' ),
					'feedback_scope' => 'site_knowledge',
				),
				'media_site_helpers'     => array(
					'route'          => '/ai/site-helpers',
					'contracts'      => array( 'media_alt_caption_review_set.v1', 'current_article_image_alt_suggestions.v1' ),
					'source_layers'  => array( 'media_library_metadata_only_no_pixel_vision', 'hosted_ai' ),
					'feedback_scope' => 'media_alt_caption',
				),
			),
			'gap_contracts'          => array(
				'seo_metadata_suggestion.v1'      => array(
					'state'                  => 'covered_by_existing_projection',
					'current_artifacts'      => array( 'seo_meta_handoff_preview.v1', 'content_metadata_delta' ),
					'route'                  => '/editor/content-support',
					'final_write_path'       => 'core_proposal_required',
					'target_ability_id'      => 'npcink-abilities-toolkit/set-post-seo-meta',
					'direct_wordpress_write' => false,
					'feedback_scope'         => 'seo_metadata',
				),
				'media_alt_caption_suggestion.v1' => array(
					'state'                  => 'covered_by_existing_projection',
					'current_artifacts'      => array( 'media_alt_caption_review_set.v1', 'current_article_image_alt_suggestions.v1' ),
					'route'                  => '/ai/site-helpers',
					'evidence_policy'        => 'media_library_metadata_only_no_pixel_vision',
					'direct_wordpress_write' => false,
					'feedback_scope'         => 'media_alt_caption',
				),
			),
			'feedback'               => array(
				'route'                  => '/agent-feedback',
				'summary_route'          => '/agent-feedback/summary',
				'contract_version'       => 'cloud_agent_feedback.v1',
				'quality_owner'          => 'cloud_eval_only',
				'mutation_scope'         => 'none',
				'source_runtimes'        => array( 'editor_content_support', 'image_candidates', 'nightly_site_inspection', 'site_knowledge', 'seo_metadata', 'media_alt_caption', 'comment_reply' ),
				'direct_wordpress_write' => false,
			),
		);
		if ( Settings::is_discoverability_paused() ) {
			$projection['surfaces']['editor_content_support']['intents']                    = array_values( array_diff( $projection['surfaces']['editor_content_support']['intents'], array( 'discoverability' ) ) );
			$projection['gap_contracts']['seo_metadata_suggestion.v1']['state']             = 'paused';
			$projection['gap_contracts']['seo_metadata_suggestion.v1']['available']         = false;
			$projection['gap_contracts']['seo_metadata_suggestion.v1']['current_artifacts'] = array();
			unset( $projection['gap_contracts']['seo_metadata_suggestion.v1']['target_ability_id'] );
		}
		if ( Settings::cloud_feature_paused( 'cloud_inspection' ) ) {
			$projection['surfaces']['nightly_inspection']['available']          = false;
			$projection['surfaces']['nightly_inspection']['availability_state'] = 'paused';
		}
		return $projection;
	}

	private function image_visual_context_from_request( WP_REST_Request $request, string $query ): array {
		$context = $request->get_param( 'visual_context' );
		if ( is_array( $context ) ) {
			$context['manual_query']    = $context['manual_query'] ?? $query;
			$context['latency_mode']    = $context['latency_mode'] ?? (string) $request->get_param( 'latency_mode' );
			$context['refresh_variant'] = $context['refresh_variant'] ?? (string) $request->get_param( 'refresh_variant' );
			return $this->sanitize_image_visual_context( $context );
		}

		$content = trim( wp_strip_all_tags( (string) $request->get_param( 'content' ) ) );
		return $this->sanitize_image_visual_context(
			array(
				'manual_query'        => $query,
				'title'               => (string) $request->get_param( 'title' ),
				'excerpt'             => (string) $request->get_param( 'excerpt' ),
				'content_summary'     => wp_trim_words( $content, 80, '' ),
				'selected_text'       => (string) $request->get_param( 'selected_text' ),
				'selected_block_text' => (string) $request->get_param( 'selected_block_text' ),
				'selected_block_name' => (string) $request->get_param( 'selected_block_name' ),
				'image_mode'          => (string) $request->get_param( 'image_mode' ),
				'latency_mode'        => (string) $request->get_param( 'latency_mode' ),
				'refresh_variant'     => (string) $request->get_param( 'refresh_variant' ),
			)
		);
	}

	private function disabled_error( string $label ): WP_Error {
		return new WP_Error(
			'npcink_toolbox_disabled',
			sprintf(
				/* translators: %s: feature label. */
				__( 'Enable %s in Npcink Workflow Toolbox settings before running this tool.', 'npcink-workflow-toolbox' ),
				$label
			),
			array( 'status' => 403 )
		);
	}
}
