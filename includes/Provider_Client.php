<?php
/**
 * Minimal third-party provider client for Toolbox actions.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use Npcink\LocalAutomationRuntime\NightlyInspection\Cloud_Batch_Result_Merger;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Provider_Client extends Provider_Client_Support {

	private Provider_Nightly_Inspection_Service $nightly;

	private Provider_Ai_Image_Service $ai_image;

	private Provider_Web_Search_Service $web_search;

	private Provider_Media_Alt_Caption_Service $media_alt;

	private Provider_Hosted_AI_Service $hosted_ai;

	private Provider_Site_Knowledge_Service $site_knowledge;

	private Provider_Content_Collector_Service $collectors;

	private Provider_Discoverability_Service $discoverability;

	private Provider_Workflow_Plans_Service $plans;

	/**
	 * Delegates to the agent_feedback_service service.
	 */
	public function submit_agent_feedback( array $input ) {
		return $this->agent_feedback_service->submit_agent_feedback( ...func_get_args() );
	}

	/**
	 * Delegates to the agent_feedback_service service.
	 */
	public function get_agent_feedback_summary( array $input ) {
		return $this->agent_feedback_service->get_agent_feedback_summary( ...func_get_args() );
	}

	/**
	 * Delegates to the site_ops_cloud_service service.
	 */
	public function run_site_ops_cloud_analysis( array $cloud_request ) {
		return $this->site_ops_cloud_service->run_site_ops_cloud_analysis( ...func_get_args() );
	}

	/**
	 * Delegates to the article_audio_service service.
	 */
	public function run_audio_generation( array $input ) {
		return $this->article_audio_service->run_audio_generation( ...func_get_args() );
	}

	/**
	 * Delegates to the image_source_service service.
	 */
	public function image_candidates( string $query, array $options = array() ) {
		return $this->image_source_service->image_candidates( ...func_get_args() );
	}
	/**
	 * Delegates to the ai_image service for image-source composition.
	 */
	public function should_include_ai_generated_images( array $options ): bool {
		return $this->ai_image->should_include_ai_generated_images( $options );
	}

	/**
	 * Delegates to the ai_image service for image-source composition.
	 */
	public function search_ai_generated_images( string $query, array $options = array() ) {
		return $this->ai_image->search_ai_generated_images( $query, $options );
	}

	/**
	 * Delegates to the ai_image service for image-source composition.
	 */
	public function normalize_image_candidate_contract( array $image ): array {
		return $this->ai_image->normalize_image_candidate_contract( $image );
	}


	/**
	 * Delegates to the media_recognition_service service.
	 */
	public function request_image_context_evidence( array $request ) {
		return $this->media_recognition_service->request_image_context_evidence( ...func_get_args() );
	}

	/**
	 * Delegates to the media_recognition_service service.
	 */
	public function resolve_media_image_context_evidence( array $request, bool $sync_fresh_projection = false, string $upload_scope = '', bool $allow_recognition = true ) {
		return $this->media_recognition_service->resolve_media_image_context_evidence( ...func_get_args() );
	}

	/**
	 * Delegates to the media_recognition_service service.
	 */
	public function media_visual_evidence_reuse_policy( int $attachment_id, string $current_fingerprint, string $evidence_fingerprint, array $visual ): string {
		return $this->media_recognition_service->media_visual_evidence_reuse_policy( ...func_get_args() );
	}
	private const MEDIA_FINGERPRINT_SCAN_LOOKBACK_DAYS = 7;

	public function __construct( Settings $settings ) {
		parent::__construct( $settings );

		$this->media_recognition_service = new Provider_Media_Recognition_Service( $settings, $this );

		$this->image_source_service = new Provider_Image_Source_Service( $settings, $this );

		$this->article_audio_service = new Provider_Article_Audio_Service( $settings, $this );

		$this->site_ops_cloud_service = new Provider_Site_Ops_Cloud_Service( $settings );

		$this->agent_feedback_service = new Provider_Agent_Feedback_Service( $settings, $this );

		$this->nightly = new Provider_Nightly_Inspection_Service( $settings );

		$this->ai_image = new Provider_Ai_Image_Service( $settings );

		$this->web_search = new Provider_Web_Search_Service( $settings, $this );

		$this->media_alt = new Provider_Media_Alt_Caption_Service( $settings, $this );

		$this->hosted_ai = new Provider_Hosted_AI_Service( $settings, $this );

		$this->site_knowledge = new Provider_Site_Knowledge_Service( $settings, $this );

		$this->collectors = new Provider_Content_Collector_Service( $settings, $this );

		$this->discoverability = new Provider_Discoverability_Service( $settings );

		$this->plans = new Provider_Workflow_Plans_Service( $settings, $this );
	}

	/**
	 * Delegates to the nightly inspection service.
	 */
	public function submit_nightly_inspection_cloud_batch( array $snapshot, array $options = array() ) {
		return $this->nightly->submit_nightly_inspection_cloud_batch( $snapshot, $options );
	}

	/**
	 * Delegates to the nightly inspection service.
	 */
	public function get_nightly_inspection_cloud_recent_runs( int $limit = 5 ) {
		return $this->nightly->get_nightly_inspection_cloud_recent_runs( $limit );
	}

	/**
	 * Delegates to the nightly inspection service.
	 */
	public function get_nightly_inspection_cloud_batch_status( string $run_id ) {
		return $this->nightly->get_nightly_inspection_cloud_batch_status( $run_id );
	}

	/**
	 * Delegates to the nightly inspection service.
	 */
	public function get_nightly_inspection_cloud_batch_result( string $run_id, array $morning_brief = array() ) {
		return $this->nightly->get_nightly_inspection_cloud_batch_result( $run_id, $morning_brief );
	}

	/**
	 * Delegates to the nightly inspection service.
	 */
	public function retry_nightly_inspection_cloud_batch( string $run_id, array $snapshot, array $options = array() ) {
		return $this->nightly->retry_nightly_inspection_cloud_batch( $run_id, $snapshot, $options );
	}

	/**
	 * Delegates to the nightly inspection service.
	 */
	public function get_nightly_inspection_cloud_runtime_entitlement() {
		return $this->nightly->get_nightly_inspection_cloud_runtime_entitlement();
	}
	/**
	 * Delegates to the AI image service.
	 */
	public function run_ai_image_generation( array $input ) {
		return $this->ai_image->run_ai_image_generation( $input );
	}

	/**
	 * Delegates to the Cloud web search service.
	 */
	public function test_cloud_web_search( array $input ) {
		return $this->web_search->test_cloud_web_search( $input );
	}

	/**
	 * Delegates to the Cloud web search service.
	 */
	public function diagnose_automatic_web_search( array $input ) {
		return $this->web_search->diagnose_automatic_web_search( $input );
	}
	/**
	 * Delegates to the hosted AI service.
	 */
	public function run_hosted_ai_content_support( array $input ) {
		return $this->hosted_ai->run_hosted_ai_content_support( $input );
	}

	/**
	 * Delegates to the hosted AI service.
	 */
	public function run_hosted_ai_site_helper( array $input ) {
		return $this->hosted_ai->run_hosted_ai_site_helper( $input );
	}

	/**
	 * Delegates to the hosted AI service.
	 */
	public function hosted_ai_site_helper_quality_contract( string $intent ) : array {
		return $this->hosted_ai->hosted_ai_site_helper_quality_contract( $intent );
	}
	/**
	 * Delegates to the Site Knowledge service.
	 */
	public function search_site_knowledge( array $input ) {
		return $this->site_knowledge->search_site_knowledge( $input );
	}

	/**
	 * Delegates to the Site Knowledge service.
	 */
	public function get_site_knowledge_status( array $input ) {
		return $this->site_knowledge->get_site_knowledge_status( $input );
	}

	/**
	 * Delegates to the Site Knowledge service.
	 */
	public function request_site_knowledge_sync( array $input ) {
		return $this->site_knowledge->request_site_knowledge_sync( $input );
	}
	/**
	 * Delegates to the content collector service.
	 */
	public function collect_hosted_ai_post_context( int $post_id ) : array {
		return $this->collectors->collect_hosted_ai_post_context( $post_id );
	}

	/**
	 * Delegates to the content collector service.
	 */
	public function collect_hosted_ai_site_snapshot() : array {
		return $this->collectors->collect_hosted_ai_site_snapshot();
	}

	/**
	 * Delegates to the content collector service.
	 */
	public function collect_hosted_ai_media_alt_snapshot( int $limit, string $filter = 'missing_or_weak_alt' ) : array {
		return $this->collectors->collect_hosted_ai_media_alt_snapshot( $limit, $filter );
	}

	/**
	 * Delegates to the content collector service.
	 */
	public function collect_hosted_ai_current_article_media_alt_snapshot( int $post_id, int $limit ) : array {
		return $this->collectors->collect_hosted_ai_current_article_media_alt_snapshot( $post_id, $limit );
	}

	/**
	 * Delegates to the content collector service.
	 */
	public function collect_hosted_ai_selected_media_alt_snapshot( array $attachment_ids, int $limit, string $filter = 'missing_or_weak_alt' ) : array {
		return $this->collectors->collect_hosted_ai_selected_media_alt_snapshot( $attachment_ids, $limit, $filter );
	}

	/**
	 * Delegates to the content collector service.
	 */
	public function collect_site_knowledge_documents( array $post_ids, int $max_posts ) : array {
		return $this->collectors->collect_site_knowledge_documents( $post_ids, $max_posts );
	}

	/**
	 * Delegates to the content collector service.
	 */
	public function site_knowledge_post_types(  ) : array {
		return $this->collectors->site_knowledge_post_types(  );
	}

	/**
	 * Delegates to the hosted AI service.
	 */
	public function hosted_ai_media_alt_snapshot_item( int $attachment_id, string $source ) : array {
		return $this->hosted_ai->hosted_ai_media_alt_snapshot_item( $attachment_id, $source );
	}

	/**
	 * Delegates to the hosted AI service.
	 */
	public function hosted_ai_content_image_attachment_ids( string $content ): array {
		return $this->hosted_ai->hosted_ai_content_image_attachment_ids( $content );
	}

	/**
	 * Delegates to the media ALT/caption service.
	 */
	public function media_alt_caption_candidate_is_too_short( string $value ) : bool {
		return $this->media_alt->media_alt_caption_candidate_is_too_short( $value );
	}

	/**
	 * Delegates to the media ALT/caption service.
	 */
	public function media_alt_caption_is_filename_like( string $value, array $item ) : bool {
		return $this->media_alt->media_alt_caption_is_filename_like( $value, $item );
	}
	/**
	 * Delegates to the workflow plans service.
	 */
	public function build_article_write_plan( array $input ) {
		return $this->plans->build_article_write_plan( $input );
	}

	/**
	 * Delegates to the workflow plans service.
	 */
	public function build_article_batch_write_plan( array $input ) {
		return $this->plans->build_article_batch_write_plan( $input );
	}

	/**
	 * Delegates to the workflow plans service.
	 */
	public function build_article_media_batch_write_plan( array $input ) {
		return $this->plans->build_article_media_batch_write_plan( $input );
	}

	/**
	 * Delegates to the workflow plans service.
	 */
	public function build_image_candidate_adoption_plan( array $input ) {
		return $this->plans->build_image_candidate_adoption_plan( $input );
	}

	/**
	 * Delegates to the workflow plans service.
	 */
	public function build_article_audio_adoption_plan( array $input ) {
		return $this->plans->build_article_audio_adoption_plan( $input );
	}

	/**
	 * Delegates to the workflow plans service.
	 */
	public function build_site_knowledge_review_plan( array $input ) {
		return $this->plans->build_site_knowledge_review_plan( $input );
	}

	/**
	 * Delegates to the workflow plans service.
	 */
	public function build_nightly_inspection_review_plan( array $input ) {
		return $this->plans->build_nightly_inspection_review_plan( $input );
	}

	/**
	 * Delegates to the workflow plans service.
	 */
	public function build_content_metadata_apply_plan( array $input ) {
		return $this->plans->build_content_metadata_apply_plan( $input );
	}

	/**
	 * Delegates to the workflow plans service.
	 */
	public function build_media_alt_caption_review_plan( array $input ) : array {
		return $this->plans->build_media_alt_caption_review_plan( $input );
	}

	/**
	 * Delegates to the workflow plans service.
	 */
	public function build_content_discoverability_brief( array $input ) {
		return $this->plans->build_content_discoverability_brief( $input );
	}

	/**
	 * Delegates to the workflow plans service.
	 */
	public function build_ai_article_writing_pack( array $input ) {
		return $this->plans->build_ai_article_writing_pack( $input );
	}

	/**
	 * Delegates to the workflow plans service.
	 */
	public function build_media_brief( string $post_context, array $options = array() ) {
		return $this->plans->build_media_brief( $post_context, $options );
	}

	/**
	 * Delegates to the workflow plans service.
	 */
	public function build_media_derivative_handoff( array $input ) {
		return $this->plans->build_media_derivative_handoff( $input );
	}

	/**
	 * Delegates to the Cloud web search service.
	 */
	public function cloud_web_search_for_content( string $query, string $intent = 'writing_context', int $max_results = 3 ) : array {
		return $this->web_search->cloud_web_search_for_content( $query, $intent, $max_results );
	}

	/**
	 * Delegates to the Cloud web search service.
	 */
	public function cloud_web_search_notice(  ) : array {
		return $this->web_search->cloud_web_search_notice(  );
	}

	/**
	 * Delegates to the Cloud web search service.
	 */
	public function cloud_web_search_evidence( array $research ) : array {
		return $this->web_search->cloud_web_search_evidence( $research );
	}

	/**
	 * Delegates to the media ALT/caption service.
	 */
	public function media_alt_caption_clean_candidate( string $value ) : string {
		return $this->media_alt->media_alt_caption_clean_candidate( $value );
	}

	/**
	 * Delegates to the media ALT/caption service.
	 */
	public function media_alt_caption_candidate_rejection_reason( string $candidate, array $item, string $target_field ) : string {
		return $this->media_alt->media_alt_caption_candidate_rejection_reason( $candidate, $item, $target_field );
	}

	/**
	 * Delegates to the media ALT/caption service.
	 */
	public function media_alt_caption_candidate_needs_context_confirmation( string $candidate ) : bool {
		return $this->media_alt->media_alt_caption_candidate_needs_context_confirmation( $candidate );
	}

	/**
	 * Delegates to the discoverability service.
	 */
	public function resolve_discoverability_source( array $input ) {
		return $this->discoverability->resolve_discoverability_source( $input );
	}

	/**
	 * Delegates to the discoverability service.
	 */
	public function content_discoverability_field_instruction( string $field ) : string {
		return $this->discoverability->content_discoverability_field_instruction( $field );
	}

	/**
	 * Delegates to the discoverability service.
	 */
	public function content_discoverability_field_group( string $field ) : string {
		return $this->discoverability->content_discoverability_field_group( $field );
	}

	/**
	 * Delegates to the discoverability service.
	 */
	public function content_discoverability_candidate( string $field, array $source, array $context ) {
		return $this->discoverability->content_discoverability_candidate( $field, $source, $context );
	}
	/**
	 * Delegates to the media ALT/caption service.
	 */
	public function build_media_alt_caption_review_set( array $media_snapshot, int $max_items, array $image_context_evidence = array() ) : array {
		return $this->media_alt->build_media_alt_caption_review_set( $media_snapshot, $max_items, $image_context_evidence );
	}

	/**
	 * Delegates to the media ALT/caption service.
	 */
	public function maybe_request_media_alt_caption_image_context_evidence( array $review_set ) : array {
		return $this->media_alt->maybe_request_media_alt_caption_image_context_evidence( $review_set );
	}

	/**
	 * Delegates to the media ALT/caption service.
	 */
	public function local_media_alt_caption_review_response( array $runtime_payload, array $review_set, string $cloud_status = 'optional_not_requested' ) : array {
		return $this->media_alt->local_media_alt_caption_review_response( $runtime_payload, $review_set, $cloud_status );
	}
	/**
	 * @return array{path:string,filename:string,mime_type:string,media_fingerprint:string}|array{}
	 */

	public function refresh_site_media_index_batch( array $input ) {
		$page = max( 1, absint( $input['page'] ?? 1 ) );
		$per_page = max( 1, min( 10, absint( $input['per_page'] ?? 10 ) ) );
		$target_attachment_ids = array_slice( $this->sanitize_absint_list( $input['attachment_ids'] ?? array() ), 0, $per_page );
		$uses_targeted_ids = ! empty( $target_attachment_ids );
		$uses_stable_cursor = ! $uses_targeted_ids && array_key_exists( 'after_id', $input );
		$after_id = absint( $input['after_id'] ?? 0 );
		$upload_scope = preg_replace( '/[^A-Za-z0-9._:-]/', '', (string) ( $input['upload_scope'] ?? '' ) );
		$upload_scope = is_string( $upload_scope ) ? substr( $upload_scope, 0, 96 ) : '';
		$inventory = $uses_targeted_ids
			? $this->toolkit_media_inventory(
				array(
					'mime_type'     => 'image',
					'attachment_ids' => $target_attachment_ids,
					'page'          => 1,
					'per_page'      => $per_page,
					'stable_order'  => 'id_asc',
				)
			)
			: ( $uses_stable_cursor
			? $this->toolkit_media_inventory_after_id( $after_id, $per_page )
			: $this->toolkit_media_inventory(
				array(
					'mime_type'   => 'image',
					'page'        => $page,
					'per_page'    => $per_page,
					'stable_order' => 'id_asc',
				)
			) );
		if ( is_wp_error( $inventory ) ) {
			return $inventory;
		}

		$items = is_array( $inventory['items'] ?? null ) ? $inventory['items'] : array();
		$has_more = $uses_targeted_ids ? false : ( $uses_stable_cursor
			? ! empty( $inventory['continuation_has_more'] )
			: $page * $per_page < absint( $inventory['total'] ?? count( $items ) ) );
		$next_after_id = $uses_stable_cursor ? absint( $inventory['continuation_after_id'] ?? $after_id ) : 0;
		if ( empty( $items ) ) {
			return array(
				'artifact_type'          => 'site_media_index_batch.v1',
				'contract_version'       => 'site_media_index_batch.v1',
				'status'                 => 'empty',
				'page'                   => $page,
				'per_page'               => $per_page,
				'total'                  => absint( $inventory['total'] ?? 0 ),
				'indexed_items'          => 0,
				'has_more'               => $has_more,
				'next_cursor'            => array( 'after_id' => $next_after_id ),
				'direct_wordpress_write' => false,
			);
		}

		$evidence_request = array(
			'contract_version'           => 'image_context_evidence_request.v1',
			'artifact_type'              => 'image_context_evidence_request',
			'runtime_owner'              => 'cloud_or_host_runtime',
			'locale'                     => get_locale(),
			'items'                      => array(),
			'write_posture'              => 'suggestion_only',
			'direct_wordpress_write'     => false,
			'proposal_created'           => false,
			'execution_created'          => false,
			'no_local_model'             => true,
			'no_media_write'             => true,
			'source_policy'              => 'bounded_media_urls_for_visual_context_only',
			'expected_response_contract' => 'image_context_evidence.v1',
			'idempotency_scope'          => 'site_media_semantic_index',
		);
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || empty( $item['attachment_id'] ) || empty( $item['url'] ) ) {
				continue;
			}
			$mime_type = sanitize_text_field( (string) ( $item['mime_type'] ?? '' ) );
			if ( ! in_array( $mime_type, array( 'image/avif', 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
				continue;
			}
			$evidence_request['items'][] = array(
				'attachment_id'   => (string) absint( $item['attachment_id'] ),
				'title'           => sanitize_text_field( (string) ( $item['title'] ?? '' ) ),
				'filename'        => sanitize_file_name( wp_basename( (string) $item['url'] ) ),
				'mime_type'       => $mime_type,
				'url'             => $this->runtime_safe_media_url( (string) $item['url'] ),
				'attachment_url'  => $this->runtime_safe_media_url( (string) $item['url'] ),
				'media_fingerprint' => (string) ( $item['media_fingerprint'] ?? '' ),
				'candidate_quality_flags' => array( 'semantic_index_refresh' ),
			);
		}
		$evidence_request['requested_count'] = count( $evidence_request['items'] );
		$evidence_request['max_items']       = $per_page;
		$evidence_request['dispatch_mode']  = 'background_completion';
		$evidence_requested = ! empty( $evidence_request['items'] );
		$provided_evidence = is_array( $input['image_context_evidence'] ?? null ) ? $input['image_context_evidence'] : array();
		if ( ! empty( $provided_evidence ) ) {
			if (
				'image_context_evidence.v1' !== (string) ( $provided_evidence['contract_version'] ?? '' )
				|| 'image_context_evidence' !== (string) ( $provided_evidence['artifact_type'] ?? '' )
				|| 'suggestion_only' !== (string) ( $provided_evidence['write_posture'] ?? '' )
				|| false !== ( $provided_evidence['direct_wordpress_write'] ?? null )
			) {
				return new WP_Error( 'media_recognition_result_contract_invalid', 'Cloud returned an incompatible media recognition result.' );
			}
			$evidence = array(
				'contract_version'       => 'image_context_evidence.v1',
				'items'                  => $this->sanitize_payload( $provided_evidence['items'] ?? array() ),
				'requested_count'        => count( $evidence_request['items'] ),
				'submitted_count'        => 0,
				'reused_count'           => 0,
				'recognized_count'       => count( (array) ( $provided_evidence['items'] ?? array() ) ),
				'write_posture'          => 'suggestion_only',
				'direct_wordpress_write' => false,
			);
		} else {
			$evidence = $evidence_requested
				? $this->resolve_media_image_context_evidence( $evidence_request, false, $upload_scope )
				: array();
		}
		if ( is_wp_error( $evidence ) ) {
			return $evidence;
		}
		$evidence_run_id = is_array( $evidence ) ? sanitize_text_field( (string) ( $evidence['run_id'] ?? '' ) ) : '';
		if ( '' !== $evidence_run_id ) {
			$total = absint( $inventory['total'] ?? count( $items ) );
			return array(
				'artifact_type'                    => 'site_media_index_batch.v1',
				'contract_version'                 => 'site_media_index_batch.v1',
				'status'                           => 'processing',
				'page'                             => $page,
				'per_page'                         => $per_page,
				'total'                            => $total,
				'indexed_items'                    => count( $items ),
				'visual_evidence_items'            => 0,
				'visual_evidence_reused_items'     => absint( $evidence['reused_count'] ?? 0 ),
				'visual_evidence_submitted_items'  => absint( $evidence['submitted_count'] ?? 0 ),
				'screened_items'                   => max( 0, count( $items ) - count( $evidence_request['items'] ) ),
				'visual_evidence_recognized_items' => 0,
				'visual_evidence_status'           => 'processing',
				'visual_evidence_error_code'       => '',
				'visual_evidence_run_id'           => $evidence_run_id,
				'has_more'                         => $has_more,
				'next_cursor'                      => array( 'after_id' => $next_after_id ),
				'write_posture'                    => 'suggestion_only',
				'direct_wordpress_write'           => false,
			);
		}
		$evidence_by_id = array();
		foreach ( (array) ( $evidence['items'] ?? array() ) as $evidence_item ) {
			if ( is_array( $evidence_item ) ) {
				$evidence_by_id[ absint( $evidence_item['attachment_id'] ?? 0 ) ] = $evidence_item;
			}
		}

		$media_items = array();
		foreach ( $items as $item ) {
			$attachment_id = absint( is_array( $item ) ? ( $item['attachment_id'] ?? 0 ) : 0 );
			if ( $attachment_id <= 0 ) {
				continue;
			}
			$visual = is_array( $evidence_by_id[ $attachment_id ] ?? null ) ? $evidence_by_id[ $attachment_id ] : array();
			$visual_source = $this->local_media_visual_source( $attachment_id );
			$media_fingerprint = sanitize_text_field(
				(string) (
					$visual['media_fingerprint']
					?? $visual_source['media_fingerprint']
					?? $this->runtime_safe_media_fingerprint( (string) ( $item['media_fingerprint'] ?? '' ) )
				)
			);
			$media_items[] = array(
				'attachment_id'    => $attachment_id,
				'mime_type'        => sanitize_text_field( (string) ( $item['mime_type'] ?? '' ) ),
				'title'            => sanitize_text_field( (string) ( $item['title'] ?? '' ) ),
				'url'              => $this->runtime_safe_media_url( (string) ( $item['url'] ?? '' ) ),
				'modified_gmt'     => sanitize_text_field( (string) ( $item['modified_gmt'] ?? '' ) ),
				'media_fingerprint' => $media_fingerprint,
				'alt'              => sanitize_text_field( (string) ( $item['alt'] ?? '' ) ),
				'caption'          => sanitize_textarea_field( (string) ( $item['caption'] ?? '' ) ),
				'description'      => sanitize_textarea_field( (string) ( $item['description'] ?? '' ) ),
				'visual_summary'   => sanitize_textarea_field( (string) ( $visual['visual_summary'] ?? '' ) ),
				'visible_text'     => $this->sanitize_string_list( $visual['visible_text'] ?? array() ),
				'subject_tags'     => $this->sanitize_string_list( $visual['subject_tags'] ?? array() ),
				'alt_text_basis'   => sanitize_textarea_field( (string) ( $visual['alt_text_basis'] ?? '' ) ),
				'vision_contract_version' => sanitize_text_field( (string) ( $visual['contract_version'] ?? '' ) ),
				'vision_source'    => sanitize_key( (string) ( $visual['source'] ?? '' ) ),
				'vision_model_id'  => sanitize_text_field( (string) ( $visual['model_id'] ?? '' ) ),
				'vision_run_id'    => sanitize_text_field( (string) ( $visual['run_id'] ?? '' ) ),
				'confidence'       => (float) ( $visual['confidence'] ?? 0 ),
				'uncertainty_flags' => $this->sanitize_string_list( $visual['uncertainty_flags'] ?? array() ),
			);
		}

		$sync = $this->execute_site_knowledge_cloud_request(
			'npcink-cloud/site-knowledge-sync',
			'site_knowledge_sync.v1',
			'whole_run_offload',
			array(
				'contract_version'       => 'site_knowledge_sync.v1',
				'sync_mode'              => 'refresh',
				'post_ids'               => array_column( $media_items, 'attachment_id' ),
				'media_items'            => $media_items,
				'write_posture'          => 'suggestion_only',
				'direct_wordpress_write' => false,
			),
			'site_media_index_batch.v1',
			'site_media_index_projection'
		);
		if ( is_wp_error( $sync ) ) {
			return $sync;
		}

		$sync['page']                  = $page;
		$sync['per_page']              = $per_page;
		$sync['total']                 = absint( $inventory['total'] ?? count( $items ) );
		$sync['indexed_items']         = count( $media_items );
		$sync['visual_evidence_items'] = count( $evidence_by_id );
		$sync['visual_evidence_reused_items'] = absint( $evidence['reused_count'] ?? 0 );
		$sync['visual_evidence_recognized_items'] = absint( $evidence['recognized_count'] ?? 0 );
		$sync['screened_items']        = max( 0, count( $items ) - count( $evidence_request['items'] ) );
		$sync['visual_evidence_status'] = ! $evidence_requested
			? 'not_requested'
			: (
				empty( $evidence_by_id )
					? 'metadata_only_fallback'
					: ( count( $evidence_by_id ) < count( $media_items ) ? 'partial' : 'ready' )
			);
		$sync['visual_evidence_error_code'] = $evidence_requested && empty( $evidence_by_id )
			? 'visual_evidence_unavailable'
			: ( count( $evidence_by_id ) < count( $media_items ) ? 'visual_evidence_partial' : '' );
		$sync['has_more']              = $has_more;
		$sync['next_cursor']           = array( 'after_id' => $next_after_id );
		$sync['visual_evidence_run_id'] = '';
		return $sync;
	}

	/**
	 * Compares a bounded recent media sample with the current Cloud projection.
	 * The existing Toolkit media-version hook remains the only invalidation lane.
	 *
	 * @return array<int,array{attachment_id:int,media_fingerprint:string}>
	 */
	public function scan_media_fingerprint_changes( int $limit = 100 ): array {
		$ids = $this->media_fingerprint_scan_candidate_ids( $limit );
		if ( empty( $ids ) ) {
			return array();
		}

		$status = $this->site_knowledge->get_site_knowledge_status( array( 'media_attachment_ids' => $ids ) );
		$known  = array();
		foreach ( (array) ( is_array( $status ) ? ( $status['media_evidence_items'] ?? array() ) : array() ) as $item ) {
			if ( is_array( $item ) && absint( $item['attachment_id'] ?? 0 ) > 0 ) {
				$known[ absint( $item['attachment_id'] ) ] = $this->runtime_safe_media_fingerprint( (string) ( $item['media_fingerprint'] ?? '' ) );
			}
		}

		$changes = array();
		foreach ( $ids as $attachment_id ) {
			$source  = $this->local_media_visual_source( $attachment_id );
			$current = $this->runtime_safe_media_fingerprint( (string) ( $source['media_fingerprint'] ?? '' ) );
			if ( '' !== $current && isset( $known[ $attachment_id ] ) && $current !== $known[ $attachment_id ] ) {
				$changes[] = array( 'attachment_id' => $attachment_id, 'media_fingerprint' => $current );
			}
		}
		return $changes;
	}

	/** @return array<int,int> */
	private function media_fingerprint_scan_candidate_ids( int $limit ): array {
		$limit       = max( 1, min( 100, $limit ) );
		$lookback_at = gmdate( 'Y-m-d H:i:s', time() - ( self::MEDIA_FINGERPRINT_SCAN_LOOKBACK_DAYS * DAY_IN_SECONDS ) );
		$ids         = array();
		$append_ids  = static function ( array &$target, $values ): void {
			foreach ( (array) $values as $value ) {
				$id = absint( $value );
				if ( $id > 0 && ! in_array( $id, $target, true ) ) {
					$target[] = $id;
				}
			}
		};

		$recent_attachments = get_posts(
			array(
				'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image',
				'posts_per_page' => $limit, 'fields' => 'ids', 'orderby' => 'post_modified_gmt', 'order' => 'DESC',
				'date_query' => array( array( 'column' => 'post_modified_gmt', 'after' => $lookback_at ) ),
			)
		);
		$append_ids( $ids, $recent_attachments );

		$recent_posts = get_posts(
			array(
				'post_type' => array( 'post', 'page' ), 'post_status' => array( 'publish', 'private', 'draft', 'pending', 'future' ),
				'posts_per_page' => min( 100, max( 20, $limit ) ), 'fields' => 'ids', 'orderby' => 'post_modified_gmt', 'order' => 'DESC',
				'date_query' => array( array( 'column' => 'post_modified_gmt', 'after' => $lookback_at ) ),
			)
		);
		foreach ( (array) $recent_posts as $post_id ) {
			$content = function_exists( 'get_post_field' ) ? (string) get_post_field( 'post_content', absint( $post_id ) ) : '';
			$blocks  = function_exists( 'parse_blocks' ) ? parse_blocks( $content ) : array();
			$collect = function ( $nested ) use ( &$collect, &$ids, $append_ids ): void {
				foreach ( (array) $nested as $block ) {
					if ( ! is_array( $block ) ) {
						continue;
					}
					$name  = (string) ( $block['blockName'] ?? '' );
					$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
					if ( in_array( $name, array( 'core/image', 'core/cover' ), true ) ) {
						$append_ids( $ids, array( $attrs['id'] ?? 0, $attrs['mediaId'] ?? 0, $attrs['media_id'] ?? 0 ) );
					}
					if ( 'core/gallery' === $name ) {
						foreach ( (array) ( $attrs['images'] ?? array() ) as $image ) {
							$append_ids( $ids, array( is_array( $image ) ? ( $image['id'] ?? $image['mediaId'] ?? 0 ) : 0 ) );
						}
					}
					$collect( $block['innerBlocks'] ?? array() );
				}
			};
			$collect( $blocks );
			if ( count( $ids ) >= $limit ) {
				break;
			}
		}

		$evidence_ids = apply_filters( 'npcink_toolbox_media_fingerprint_scan_evidence_attachment_ids', array(), $limit );
		$prioritized  = array();
		$append_ids( $prioritized, $evidence_ids );
		$append_ids( $prioritized, $ids );
		return array_slice( $prioritized, 0, $limit );
	}



	public function toolkit_media_inventory( array $input ) {
		$ability_id = 'npcink-abilities-toolkit/get-media-inventory-health';
		if ( ! function_exists( 'npcink_abilities_toolkit_get_registered' ) ) {
			return new WP_Error(
				'npcink_toolbox_site_media_toolkit_unavailable',
				__( 'Npcink Abilities Toolkit is required to read and revalidate the local media library.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}
		$registered = npcink_abilities_toolkit_get_registered();
		$ability = is_array( $registered ) ? ( $registered[ $ability_id ] ?? null ) : null;
		$callback = is_array( $ability ) ? ( $ability['execute_callback'] ?? null ) : null;
		if ( ! is_callable( $callback ) ) {
			return new WP_Error(
				'npcink_toolbox_site_media_ability_unavailable',
				__( 'The local media inventory ability is not callable.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}
		$result = call_user_func( $callback, $input );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! is_array( $result ) || false === (bool) ( $result['success'] ?? false ) ) {
			return new WP_Error(
				'npcink_toolbox_site_media_inventory_invalid',
				__( 'The local media inventory ability returned an invalid response.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}
		return is_array( $result['data'] ?? null ) ? $result['data'] : array();
	}

	/** Reads one stable ID cursor, then delegates media row shaping to Toolkit. */
	private function toolkit_media_inventory_after_id( int $after_id, int $per_page ) {
		global $wpdb;
		$limit = max( 1, min( 10, $per_page ) ) + 1;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A bounded ID-only cursor cannot use WP_Query without page drift.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE ID > %d AND post_type = %s AND post_status = %s AND post_mime_type LIKE %s ORDER BY ID ASC LIMIT %d",
				$after_id,
				'attachment',
				'inherit',
				'image/%',
				$limit
			)
		);
		$ids      = array_values( array_filter( array_map( 'absint', is_array( $ids ) ? $ids : array() ) ) );
		$has_more = count( $ids ) > $per_page;
		$ids      = array_slice( $ids, 0, $per_page );
		if ( empty( $ids ) ) {
			return array( 'items' => array(), 'total' => 0, 'continuation_has_more' => false, 'continuation_after_id' => $after_id );
		}

		$inventory = $this->toolkit_media_inventory(
			array( 'mime_type' => 'image', 'attachment_ids' => $ids, 'page' => 1, 'per_page' => $per_page )
		);
		if ( is_wp_error( $inventory ) ) {
			return $inventory;
		}
		$inventory['continuation_has_more'] = $has_more;
		$inventory['continuation_after_id'] = (int) end( $ids );
		return $inventory;
	}


	public function execute_site_knowledge_cloud_request( string $ability_name, string $contract_version, string $execution_pattern, array $input, string $artifact_type, string $composition_role ) {
		$runtime_payload = array(
			'ability_name'        => $ability_name,
			'contract_version'    => $contract_version,
			'execution_pattern'   => $execution_pattern,
			'input'               => $this->sanitize_payload( $input ),
			'data_classification' => 'public_site_content',
			'storage_mode'        => 'result_only',
			'retention_ttl'       => 86400,
			'timeout_seconds'     => 'whole_run_offload' === $execution_pattern ? 60 : 20,
			'retry_max'           => 'whole_run_offload' === $execution_pattern ? 1 : 0,
			'policy'              => array(
				'allow_fallback' => true,
			),
		);

		$runtime_payload = apply_filters( 'npcink_toolbox_site_knowledge_runtime_payload', $runtime_payload, $ability_name, $contract_version );
		if ( ! is_array( $runtime_payload ) ) {
			return new WP_Error(
				'npcink_toolbox_invalid_site_knowledge_runtime_payload',
				__( 'The site knowledge runtime payload was not valid.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}

		$handled = apply_filters( 'npcink_toolbox_site_knowledge_cloud_request', null, $runtime_payload, $ability_name, $contract_version );
		if ( is_wp_error( $handled ) ) {
			return $handled;
		}
		if ( is_array( $handled ) ) {
			return $this->site_knowledge->normalize_site_knowledge_cloud_response( $handled, $artifact_type, $composition_role, $runtime_payload );
		}

		if ( ! function_exists( 'npcink_cloud_addon_dispatch_site_knowledge_runtime' ) ) {
			return new WP_Error(
				'npcink_toolbox_site_knowledge_cloud_unavailable',
				__( 'Connect Npcink Cloud before using site knowledge abilities.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$trace_id        = $this->trace_id( 'site_knowledge' );
		$idempotency_key = $this->trace_id( str_replace( '.', '_', $contract_version ) );
		$response        = npcink_cloud_addon_dispatch_site_knowledge_runtime( $runtime_payload, $ability_name, $contract_version );
		if ( is_wp_error( $response ) ) {
			if ( $this->is_cloud_concurrency_error( $response ) ) {
				return $this->site_knowledge->site_knowledge_active_run_response( $artifact_type, $composition_role, $runtime_payload );
			}
			return $response;
		}

		return $this->site_knowledge->normalize_site_knowledge_cloud_response( is_array( $response ) ? $response : array(), $artifact_type, $composition_role, $runtime_payload );
	}



}
