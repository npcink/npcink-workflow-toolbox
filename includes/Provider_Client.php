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
	private const AUDIO_GENERATION_TEXT_CHARS = 5000;
	private const ARTICLE_PLAN_CONTENT_CHARS = 60000;
	private const ARTICLE_PLAN_NOTES_CHARS = 12000;
	private const SITE_MEDIA_VISUAL_MAX_UPLOAD_BYTES = 262144;
	private const MEDIA_FINGERPRINT_SCAN_LOOKBACK_DAYS = 7;

	public function __construct( Settings $settings ) {
		parent::__construct( $settings );

		$this->nightly = new Provider_Nightly_Inspection_Service( $settings );

		$this->ai_image = new Provider_Ai_Image_Service( $settings );

		$this->web_search = new Provider_Web_Search_Service( $settings, $this );

		$this->media_alt = new Provider_Media_Alt_Caption_Service( $settings, $this );

		$this->hosted_ai = new Provider_Hosted_AI_Service( $settings, $this );

		$this->site_knowledge = new Provider_Site_Knowledge_Service( $settings, $this );

		$this->collectors = new Provider_Content_Collector_Service( $settings, $this );
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
		return $this->nightly->get_nightly_inspection_cloud_runtime_entitlement(  );
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
	public function collect_hosted_ai_site_snapshot(  ) : array {
		return $this->collectors->collect_hosted_ai_site_snapshot(  );
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
	 * Requests bounded Cloud-owned visual evidence without exposing the runtime
	 * client or adding another Toolbox route.
	 *
	 * @param array<string,mixed> $request Image context evidence request.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function request_image_context_evidence( array $request ) {
		$items = is_array( $request['items'] ?? null ) ? $request['items'] : array();
		if (
			empty( $items )
			|| 'image_context_evidence_request.v1' !== (string) ( $request['contract_version'] ?? '' )
			|| 'suggestion_only' !== (string) ( $request['write_posture'] ?? '' )
			|| false !== (bool) ( $request['direct_wordpress_write'] ?? true )
		) {
			return array();
		}

		if ( ! function_exists( 'npcink_cloud_addon_request_image_context_evidence' ) ) {
			return array();
		}

		$idempotency_key = $this->trace_id( 'image_context_evidence_request' );
		if ( 'site_media_semantic_index' === (string) ( $request['idempotency_scope'] ?? '' ) ) {
			$revision_parts = array();
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) || empty( $item['attachment_id'] ) || empty( $item['media_fingerprint'] ) ) {
					$revision_parts = array();
					break;
				}
				$revision_parts[] = absint( $item['attachment_id'] )
					. ':' . sanitize_text_field( (string) $item['media_fingerprint'] )
					. ':' . sanitize_text_field( (string) ( $item['source_artifact_id'] ?? '' ) );
			}
			if ( ! empty( $revision_parts ) ) {
				$idempotency_key = 'site_media_vision_v1_' . substr( hash( 'sha256', implode( '|', $revision_parts ) ), 0, 32 );
			}
		}

		$result = npcink_cloud_addon_request_image_context_evidence(
			$request,
			$this->trace_id( 'image_context_evidence' ),
			$idempotency_key
		);
		if ( is_wp_error( $result ) ) {
			return 'background_completion' === (string) ( $request['dispatch_mode'] ?? '' )
				? $result
				: array();
		}
		if (
			! is_array( $result )
			|| 'image_context_evidence.v1' !== (string) ( $result['contract_version'] ?? '' )
			|| 'suggestion_only' !== (string) ( $result['write_posture'] ?? '' )
			|| false !== (bool) ( $result['direct_wordpress_write'] ?? true )
		) {
			return array();
		}

		return $this->sanitize_payload( $result );
	}

	/**
	 * Reuses current Site Knowledge visual evidence and recognizes only misses.
	 *
	 * @param array<string,mixed> $request Image context evidence request.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function resolve_media_image_context_evidence( array $request, bool $sync_fresh_projection = false, string $upload_scope = '', bool $allow_recognition = true ) {
		$requested_items = is_array( $request['items'] ?? null ) ? $request['items'] : array();
		$prepared_items  = array();
		$fingerprints    = array();
		$local_sources   = array();
		foreach ( $requested_items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$attachment_id = absint( $item['attachment_id'] ?? 0 );
			if ( 0 >= $attachment_id ) {
				continue;
			}
			$source = $this->local_media_visual_source( $attachment_id );
			if ( ! empty( $source ) ) {
				$local_sources[ $attachment_id ] = $source;
				$item['filename']          = $source['filename'];
				$item['mime_type']         = $source['mime_type'];
				$item['media_fingerprint'] = $source['media_fingerprint'];
			} else {
				$local_path = function_exists( 'get_attached_file' ) ? get_attached_file( $attachment_id ) : '';
				if ( is_string( $local_path ) && is_file( $local_path ) && is_readable( $local_path ) ) {
					$local_sources[ $attachment_id ] = array( 'uploadable' => false );
				}
				$item['media_fingerprint'] = $this->runtime_safe_media_fingerprint( (string) ( $item['media_fingerprint'] ?? '' ) );
			}
			$prepared_items[ $attachment_id ] = $item;
			$fingerprints[ $attachment_id ]   = sanitize_text_field( (string) ( $item['media_fingerprint'] ?? '' ) );
		}
		if ( empty( $prepared_items ) ) {
			return array();
		}

		$cached_by_id = array();
		$status       = $this->site_knowledge->get_site_knowledge_status(
			array(
				'media_attachment_ids' => array_keys( $prepared_items ),
			)
		);
		if ( is_array( $status ) ) {
			foreach ( (array) ( $status['media_evidence_items'] ?? array() ) as $cached_item ) {
				if ( ! is_array( $cached_item ) ) {
					continue;
				}
				$attachment_id = absint( $cached_item['attachment_id'] ?? 0 );
				$visual        = is_array( $cached_item['visual_evidence'] ?? null ) ? $cached_item['visual_evidence'] : array();
				$current_fingerprint = (string) ( $fingerprints[ $attachment_id ] ?? '' );
				$evidence_fingerprint = sanitize_text_field( (string) ( $cached_item['media_fingerprint'] ?? '' ) );
				$visual_reuse_policy = $this->media_visual_evidence_reuse_policy( $attachment_id, $current_fingerprint, $evidence_fingerprint, $visual );
				if (
					0 >= $attachment_id
					|| 'ready' !== sanitize_key( (string) ( $visual['status'] ?? '' ) )
					|| '' === $visual_reuse_policy
				) {
					continue;
				}
				$cached_by_id[ $attachment_id ] = array_merge(
					$visual,
					array(
						'attachment_id'          => $attachment_id,
						'media_fingerprint'       => $fingerprints[ $attachment_id ],
						'evidence_reuse'          => 'site_knowledge_projection',
						'visual_reuse_policy'     => $visual_reuse_policy,
						'write_posture'           => 'suggestion_only',
						'direct_wordpress_write'  => false,
					)
				);
			}
		}

		$miss_items = array();
		foreach ( $prepared_items as $attachment_id => $item ) {
			if ( isset( $cached_by_id[ $attachment_id ] ) ) {
				continue;
			}
			if ( ! $allow_recognition ) {
				$miss_items[] = $item;
				continue;
			}
			$artifact = $this->upload_local_media_visual_artifact(
				$attachment_id,
				$fingerprints[ $attachment_id ] ?? '',
				$local_sources[ $attachment_id ] ?? array(),
				$upload_scope
			);
			if ( is_array( $artifact ) && ! empty( $artifact['artifact_id'] ) ) {
				$item['source_artifact_id'] = sanitize_text_field( (string) $artifact['artifact_id'] );
				unset( $item['url'], $item['thumbnail_url'] );
			} elseif ( isset( $local_sources[ $attachment_id ] ) ) {
				continue;
			}
			$miss_items[] = $item;
		}

		$fresh_by_id = array();
		$fresh_run_id = '';
		$fresh_status = '';
		if ( $allow_recognition && ! empty( $miss_items ) ) {
			$miss_request                    = $request;
			$miss_request['items']           = $miss_items;
			$miss_request['requested_count'] = count( $miss_items );
			$miss_request['idempotency_scope'] = 'site_media_semantic_index';
			$fresh                           = $this->request_image_context_evidence( $miss_request );
			if ( is_wp_error( $fresh ) ) {
				return $fresh;
			}
			$fresh_run_id                    = sanitize_text_field( (string) ( $fresh['run_id'] ?? '' ) );
			$fresh_status                    = sanitize_key( (string) ( $fresh['status'] ?? '' ) );
			foreach ( (array) ( $fresh['items'] ?? array() ) as $fresh_item ) {
				if ( ! is_array( $fresh_item ) ) {
					continue;
				}
				$attachment_id = absint( $fresh_item['attachment_id'] ?? 0 );
				if ( 0 >= $attachment_id || ! isset( $prepared_items[ $attachment_id ] ) ) {
					continue;
				}
				$fresh_item['media_fingerprint']      = $fingerprints[ $attachment_id ];
				$fresh_item['evidence_reuse']         = 'new_visual_recognition';
				$fresh_item['write_posture']          = 'suggestion_only';
				$fresh_item['direct_wordpress_write'] = false;
				$fresh_by_id[ $attachment_id ]        = $fresh_item;
			}
		}
		$projection_queued = $sync_fresh_projection && ! empty( $fresh_by_id )
			? $this->queue_media_visual_evidence_projection( $prepared_items, $fresh_by_id )
			: false;

		$resolved_items = array();
		foreach ( array_keys( $prepared_items ) as $attachment_id ) {
			if ( isset( $cached_by_id[ $attachment_id ] ) ) {
				$resolved_items[] = $cached_by_id[ $attachment_id ];
			} elseif ( isset( $fresh_by_id[ $attachment_id ] ) ) {
				$resolved_items[] = $fresh_by_id[ $attachment_id ];
			}
		}

		$result = array(
			'contract_version'       => 'image_context_evidence.v1',
			'items'                  => $this->sanitize_payload( $resolved_items ),
			'requested_count'        => count( $prepared_items ),
			'submitted_count'        => $allow_recognition ? count( $miss_items ) : 0,
			'reused_count'           => count( $cached_by_id ),
			'recognized_count'       => count( $fresh_by_id ),
			'recognition_required_attachment_ids' => array_values( array_map( 'absint', array_keys( array_diff_key( $prepared_items, $cached_by_id, $fresh_by_id ) ) ) ),
			'projection_queued'      => $projection_queued,
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
		);
		if ( '' !== $fresh_run_id ) {
			$result['run_id'] = $fresh_run_id;
			$result['status'] = '' !== $fresh_status ? $fresh_status : 'processing';
		}

		return $result;
	}

	/**
	 * @return array{path:string,filename:string,mime_type:string,media_fingerprint:string}|array{}
	 */
	private function local_media_visual_source( int $attachment_id ): array {
		if ( 0 >= $attachment_id || ! function_exists( 'get_attached_file' ) || ! function_exists( 'wp_upload_dir' ) ) {
			return array();
		}
		$path        = get_attached_file( $attachment_id );
		$upload_dir  = wp_upload_dir();
		$upload_root = realpath( (string) ( $upload_dir['basedir'] ?? '' ) );
		$real_path   = is_string( $path ) ? realpath( $path ) : false;
		if (
			false === $upload_root
			|| false === $real_path
			|| ! is_file( $real_path )
			|| ! is_readable( $real_path )
			|| ( $upload_root !== $real_path && 0 !== strpos( $real_path, trailingslashit( $upload_root ) ) )
		) {
			return array();
		}
		$file_size = filesize( $real_path );
		if ( false === $file_size || 0 >= $file_size || 8 * MB_IN_BYTES < $file_size ) {
			return array();
		}
		$original_mime_type = function_exists( 'wp_get_image_mime' ) ? wp_get_image_mime( $real_path ) : '';
		if ( ! is_string( $original_mime_type ) || ! in_array( $original_mime_type, array( 'image/avif', 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
			return array();
		}
		$fingerprint = hash_file( 'sha256', $real_path );
		if ( ! is_string( $fingerprint ) || '' === $fingerprint ) {
			return array();
		}
		$source_path = $real_path;
		if ( $file_size > self::SITE_MEDIA_VISUAL_MAX_UPLOAD_BYTES && function_exists( 'wp_get_attachment_metadata' ) ) {
			$metadata = wp_get_attachment_metadata( $attachment_id );
			$sizes    = is_array( $metadata ) && is_array( $metadata['sizes'] ?? null ) ? $metadata['sizes'] : array();
			$candidates = array();
			foreach ( $sizes as $size ) {
				if ( ! is_array( $size ) || empty( $size['file'] ) ) {
					continue;
				}
				$candidate_path = realpath( dirname( $real_path ) . DIRECTORY_SEPARATOR . basename( (string) $size['file'] ) );
				if (
					false === $candidate_path
					|| ! is_file( $candidate_path )
					|| ! is_readable( $candidate_path )
					|| 0 !== strpos( $candidate_path, trailingslashit( $upload_root ) )
				) {
					continue;
				}
				$candidate_size = filesize( $candidate_path );
				if ( false === $candidate_size || 0 >= $candidate_size || self::SITE_MEDIA_VISUAL_MAX_UPLOAD_BYTES < $candidate_size ) {
					continue;
				}
				$candidates[] = array(
					'path' => $candidate_path,
					'area' => absint( $size['width'] ?? 0 ) * absint( $size['height'] ?? 0 ),
				);
			}
			usort(
				$candidates,
				static function ( array $left, array $right ): int {
					return (int) $right['area'] <=> (int) $left['area'];
				}
			);
			if ( ! empty( $candidates ) ) {
				$source_path = (string) $candidates[0]['path'];
			}
		}
		$source_size = filesize( $source_path );
		if ( false === $source_size || 0 >= $source_size || self::SITE_MEDIA_VISUAL_MAX_UPLOAD_BYTES < $source_size ) {
			return array();
		}
		$mime_type = function_exists( 'wp_get_image_mime' ) ? wp_get_image_mime( $source_path ) : $original_mime_type;
		if ( ! is_string( $mime_type ) || ! in_array( $mime_type, array( 'image/avif', 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
			return array();
		}
		$extension = array(
			'image/avif' => 'avif',
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/webp' => 'webp',
		)[ $mime_type ];

		return array(
			'path'              => $source_path,
			'filename'          => 'site-media-' . $attachment_id . '.' . $extension,
			'mime_type'         => $mime_type,
			'media_fingerprint' => 'sha256:' . strtolower( $fingerprint ),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function upload_local_media_visual_artifact( int $attachment_id, string $media_fingerprint, array $source = array(), string $upload_scope = '' ): array {
		if ( empty( $source ) ) {
			$source = $this->local_media_visual_source( $attachment_id );
		}
		if (
			empty( $source )
			|| false === (bool) ( $source['uploadable'] ?? true )
			|| ! is_string( $source['path'] ?? null )
			|| '' === $source['path']
			|| ! is_file( $source['path'] )
			|| ! is_readable( $source['path'] )
			|| ! is_string( $source['filename'] ?? null )
			|| '' === $source['filename']
			|| ! is_string( $source['mime_type'] ?? null )
			|| '' === $source['mime_type']
			|| ! function_exists( 'npcink_cloud_addon_upload_toolbox_site_media_visual_source' )
		) {
			return array();
		}
		$contents = file_get_contents( $source['path'] );
		if ( ! is_string( $contents ) || '' === $contents ) {
			return array();
		}
		$upload_revision = hash( 'sha256', $contents );
		$upload_nonce = '' !== $upload_scope ? $upload_scope : wp_generate_uuid4();
		$result = npcink_cloud_addon_upload_toolbox_site_media_visual_source(
			array(
				'contents'  => $contents,
				'filename'  => $source['filename'],
				'mime_type' => $source['mime_type'],
			),
			$this->trace_id( 'site_media_visual_upload' ),
			'site_media_visual_upload_v2_' . substr(
				hash( 'sha256', $upload_nonce . '|' . $attachment_id . '|' . $media_fingerprint . '|' . $upload_revision ),
				0,
				32
			)
		);
		unset( $contents );

		return is_wp_error( $result ) || ! is_array( $result ) ? array() : $this->sanitize_payload( $result );
	}

	/**
	 * @param array<int,array<string,mixed>> $prepared_items Prepared local items.
	 * @param array<int,array<string,mixed>> $fresh_by_id Newly recognized evidence.
	 */
	private function queue_media_visual_evidence_projection( array $prepared_items, array $fresh_by_id ): bool {
		$media_items = array();
		foreach ( $fresh_by_id as $attachment_id => $visual ) {
			$item = is_array( $prepared_items[ $attachment_id ] ?? null ) ? $prepared_items[ $attachment_id ] : array();
			$url  = $this->runtime_safe_media_url( (string) ( $item['url'] ?? $item['thumbnail_url'] ?? '' ) );
			if ( '' === $url ) {
				continue;
			}
			$media_items[] = array(
				'attachment_id'          => $attachment_id,
				'mime_type'              => sanitize_text_field( (string) ( $item['mime_type'] ?? '' ) ),
				'title'                  => sanitize_text_field( (string) ( $item['title'] ?? '' ) ),
				'url'                    => $url,
				'media_fingerprint'      => sanitize_text_field( (string) ( $item['media_fingerprint'] ?? '' ) ),
				'visual_summary'         => sanitize_textarea_field( (string) ( $visual['visual_summary'] ?? '' ) ),
				'visible_text'           => $this->sanitize_string_list( $visual['visible_text'] ?? array() ),
				'subject_tags'           => $this->sanitize_string_list( $visual['subject_tags'] ?? array() ),
				'alt_text_basis'         => sanitize_textarea_field( (string) ( $visual['alt_text_basis'] ?? '' ) ),
				'vision_contract_version' => sanitize_text_field( (string) ( $visual['contract_version'] ?? '' ) ),
				'vision_source'          => sanitize_key( (string) ( $visual['source'] ?? '' ) ),
				'vision_model_id'        => sanitize_text_field( (string) ( $visual['model_id'] ?? '' ) ),
				'vision_run_id'          => sanitize_text_field( (string) ( $visual['run_id'] ?? '' ) ),
				'confidence'             => (float) ( $visual['confidence'] ?? 0 ),
				'uncertainty_flags'      => $this->sanitize_string_list( $visual['uncertainty_flags'] ?? array() ),
			);
		}
		if ( empty( $media_items ) ) {
			return false;
		}
		$result = $this->execute_site_knowledge_cloud_request(
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
			'site_media_visual_evidence_projection.v1',
			'site_media_visual_evidence_projection'
		);

		return ! is_wp_error( $result );
	}

	public function image_candidates( string $query, array $options = array() ) {
		$provider = sanitize_key( (string) ( $options['provider'] ?? 'auto' ) );
		if ( ! in_array( $provider, array( 'auto', 'cloud', 'unsplash', 'pixabay', 'pexels', 'ai_generated', 'site_media' ), true ) ) {
			$provider = 'auto';
		}

		if ( 'site_media' === $provider ) {
			return $this->search_site_media_library( $query, $options );
		}

		if ( 'ai_generated' === $provider || $this->ai_image->should_include_ai_generated_images( $options ) ) {
			$result = $this->ai_image->search_ai_generated_images( $query, $options );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return $this->normalize_image_source_candidates_response(
				array(
					'provider'       => 'ai_generated',
					'provider_mode'  => 'ai_generated',
					'active_sources' => array( array( 'provider' => 'ai_generated', 'count' => count( (array) ( $result['images'] ?? array() ) ) ) ),
					'images'         => is_array( $result['images'] ?? null ) ? $result['images'] : array(),
					'raw'            => is_array( $result['raw'] ?? null ) ? $result['raw'] : array(),
				),
				$query,
				'ai_generated'
			);
		}

		return $this->execute_image_source_cloud_request( $query, $options, $provider );
	}

	public function run_audio_generation( array $input ) {
		$intent = sanitize_key( (string) ( $input['intent'] ?? 'article_narration' ) );
		if ( ! in_array( $intent, array( 'article_narration', 'article_audio_summary' ), true ) ) {
			return new WP_Error(
				'npcink_toolbox_invalid_audio_generation_intent',
				__( 'A supported audio generation intent is required.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$text = $this->trim_chars(
			trim(
				sanitize_textarea_field(
					wp_strip_all_tags(
						(string) ( $input['summary_text'] ?? ( $input['script'] ?? ( $input['text'] ?? '' ) ) )
					)
				)
			),
			self::AUDIO_GENERATION_TEXT_CHARS
		);
		if ( '' === $text ) {
			return new WP_Error(
				'npcink_toolbox_missing_audio_generation_text',
				__( 'Narration text or summary script is required before calling Cloud audio generation.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$voice_id = sanitize_text_field( (string) ( $input['voice_id'] ?? '' ) );
		$format   = sanitize_key( (string) ( $input['format'] ?? 'mp3' ) );
		$user_instruction = sanitize_textarea_field( (string) ( $input['user_instruction'] ?? '' ) );
		$audio_preferences = is_array( $input['audio_preferences'] ?? null ) ? $this->sanitize_payload( $input['audio_preferences'] ) : array();
		if ( ! in_array( $format, array( 'mp3', 'wav', 'pcm' ), true ) ) {
			$format = 'mp3';
		}

		$runtime_payload = array(
			'ability_name'        => 'npcink-toolbox/generate-audio',
			'contract_version'    => 'audio_generation_request.v1',
			'execution_pattern'   => 'inline',
			'execution_kind'      => 'audio_generation',
			'profile_id'          => sanitize_text_field( (string) ( $input['profile_id'] ?? 'audio.narration.default' ) ),
			'input'               => array(
				'intent'          => $intent,
				'text'            => $text,
				'summary_text'    => 'article_audio_summary' === $intent ? $text : '',
				'script'          => $text,
				'voice_id'        => $voice_id,
				'format'          => $format,
				'response_format' => 'url',
				'purpose'         => 'article_audio_summary' === $intent ? 'longform_audio_summary' : 'article_narration',
				'user_instruction' => $user_instruction,
				'audio_preferences' => $audio_preferences,
				'context'         => is_array( $input['context'] ?? null ) ? $this->sanitize_payload( $input['context'] ) : array(),
				'review'          => array(
					'script_review_required' => true,
					'write_posture'          => 'candidate_only',
					'direct_wordpress_write' => false,
				),
			),
			'data_classification' => 'public_site_content',
			'storage_mode'        => 'result_only',
			'retention_ttl'       => 3600,
			'timeout_seconds'     => 60,
			'http_timeout_seconds' => 60,
			'connect_timeout_seconds' => self::HTTP_CONNECT_TIMEOUT,
			'retry_max'           => 0,
			'policy'              => array(
				'allow_fallback' => false,
			),
		);
		$runtime_payload = $this->runtime_payload_with_data_classification( $runtime_payload, 'public_site_content', $input );

		$runtime_payload = apply_filters( 'npcink_toolbox_audio_generation_runtime_payload', $runtime_payload, $input );
		if ( ! is_array( $runtime_payload ) ) {
			return new WP_Error(
				'npcink_toolbox_invalid_audio_generation_runtime_payload',
				__( 'The audio generation runtime payload was not valid.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}
		$runtime_payload = $this->runtime_payload_with_data_classification( $runtime_payload, 'public_site_content', $input );

		$handled = apply_filters( 'npcink_toolbox_audio_generation_cloud_request', null, $runtime_payload, $input );
		if ( is_wp_error( $handled ) ) {
			return $handled;
		}
		if ( is_array( $handled ) ) {
			return $this->normalize_audio_generation_response( $handled, $runtime_payload );
		}

		$trace_id        = $this->trace_id( 'audio_generation' );
		$idempotency_key = $this->trace_id( 'audio_generation_request' );
		$request         = $this->toolbox_audio_generation_runtime_request( $runtime_payload );

		if ( function_exists( 'npcink_cloud_addon_execute_toolbox_audio_generation_runtime' ) ) {
			$response = npcink_cloud_addon_execute_toolbox_audio_generation_runtime( $request, $trace_id, $idempotency_key );
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			return $this->normalize_audio_generation_response( is_array( $response ) ? $response : array(), $runtime_payload );
		}

		return new WP_Error(
			'npcink_toolbox_audio_generation_cloud_unavailable',
			__( 'Connect Npcink Cloud before generating article audio.', 'npcink-workflow-toolbox' ),
			array( 'status' => 503 )
		);
	}

	private function toolbox_audio_generation_runtime_request( array $runtime_payload ): array {
		$input = is_array( $runtime_payload['input'] ?? null ) ? $runtime_payload['input'] : array();

		return array(
			'contract_version'  => 'audio_generation_request.v1',
			'intent'            => sanitize_key( (string) ( $input['intent'] ?? 'article_narration' ) ),
			'text'              => sanitize_textarea_field( (string) ( $input['text'] ?? '' ) ),
			'summary_text'      => sanitize_textarea_field( (string) ( $input['summary_text'] ?? '' ) ),
			'script'            => sanitize_textarea_field( (string) ( $input['script'] ?? '' ) ),
			'voice_id'          => sanitize_text_field( (string) ( $input['voice_id'] ?? '' ) ),
			'format'            => sanitize_key( (string) ( $input['format'] ?? 'mp3' ) ),
			'source_surface'    => 'toolbox_article_audio_candidates',
			'profile_id'        => sanitize_text_field( (string) ( $runtime_payload['profile_id'] ?? 'audio.narration.default' ) ),
			'timeout_seconds'   => absint( $runtime_payload['timeout_seconds'] ?? 60 ),
			'retention_ttl'     => absint( $runtime_payload['retention_ttl'] ?? 3600 ),
			'user_instruction'  => sanitize_textarea_field( (string) ( $input['user_instruction'] ?? '' ) ),
			'audio_preferences' => is_array( $input['audio_preferences'] ?? null ) ? $this->sanitize_payload( $input['audio_preferences'] ) : array(),
			'context'           => is_array( $input['context'] ?? null ) ? $this->sanitize_payload( $input['context'] ) : array(),
		);
	}

	public function submit_agent_feedback( array $input ) {
		$payload = $this->agent_feedback_payload( $input );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$handled = apply_filters( 'npcink_toolbox_agent_feedback_cloud_request', null, $payload, $input );
		if ( is_wp_error( $handled ) ) {
			return $handled;
		}
		if ( is_array( $handled ) ) {
			return $this->normalize_agent_feedback_response( $handled, $payload );
		}

		if ( ! function_exists( 'npcink_cloud_addon_send_agent_feedback_event' ) ) {
			return new WP_Error(
				'npcink_toolbox_agent_feedback_cloud_unavailable',
				__( 'Connect an updated Npcink Cloud Addon before sending Agent feedback.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$trace_id        = $this->trace_id( 'agent_feedback' );
		$idempotency_key = 'agent-feedback-' . substr( md5( (string) wp_json_encode( $payload ) ), 0, 24 );
		$response        = npcink_cloud_addon_send_agent_feedback_event( $payload, $trace_id, $idempotency_key );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->normalize_agent_feedback_response( is_array( $response ) ? $response : array(), $payload );
	}

	public function run_site_ops_cloud_analysis( array $cloud_request ) {
		$runtime_payload = array(
			'ability_name'        => 'npcink-toolbox/analyze-site-ops',
			'contract_version'    => 'site_ops_cloud_analysis_request.v1',
			'execution_pattern'   => 'whole_run_offload',
			'execution_kind'      => 'site_ops_cloud_analysis',
			'profile_id'          => 'site-ops-analysis.managed',
			'input'               => $this->sanitize_payload( $cloud_request ),
			'data_classification' => 'public_site_aggregate',
			'storage_mode'        => 'result_only',
			'retention_ttl'       => 3600,
			'timeout_seconds'     => 60,
			'http_timeout_seconds' => 60,
			'connect_timeout_seconds' => self::HTTP_CONNECT_TIMEOUT,
			'retry_max'           => 0,
			'policy'              => array(
				'allow_fallback' => false,
			),
		);

		$runtime_payload = apply_filters( 'npcink_toolbox_site_ops_cloud_analysis_runtime_payload', $runtime_payload, $cloud_request );
		if ( ! is_array( $runtime_payload ) ) {
			return new WP_Error(
				'npcink_toolbox_invalid_site_ops_cloud_analysis_runtime_payload',
					__( 'The Site Check Cloud runtime payload was not valid.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}

		$handled = apply_filters( 'npcink_toolbox_site_ops_cloud_analysis_cloud_request', null, $runtime_payload, $cloud_request );
		if ( is_wp_error( $handled ) ) {
			return $handled;
		}
		if ( is_array( $handled ) ) {
			return $this->normalize_site_ops_cloud_analysis_response( $handled, $runtime_payload );
		}

		$trace_id        = $this->trace_id( 'site_ops_cloud_analysis' );
		$idempotency_key = 'site-ops-cloud-analysis-' . substr( md5( (string) wp_json_encode( $runtime_payload['input'] ?? array() ) ), 0, 24 );
		$request         = $this->toolbox_site_ops_cloud_analysis_runtime_request( $runtime_payload );

		if ( function_exists( 'npcink_cloud_addon_execute_toolbox_site_ops_cloud_analysis_runtime' ) ) {
			$response = npcink_cloud_addon_execute_toolbox_site_ops_cloud_analysis_runtime( $request, $trace_id, $idempotency_key );
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			return $this->normalize_site_ops_cloud_analysis_response( is_array( $response ) ? $response : array(), $runtime_payload );
		}

		return new WP_Error(
			'npcink_toolbox_site_ops_cloud_analysis_unavailable',
			__( 'Connect Npcink Cloud before running Cloud Site Check detail.', 'npcink-workflow-toolbox' ),
			array( 'status' => 503 )
		);
	}

	private function toolbox_site_ops_cloud_analysis_runtime_request( array $runtime_payload ): array {
		$input = is_array( $runtime_payload['input'] ?? null ) ? $runtime_payload['input'] : array();
		if ( array() === $input ) {
			$input = array(
				'artifact_type'            => 'site_ops_cloud_analysis_request',
				'contract_version'         => 'site_ops_cloud_analysis_request.v1',
				'expected_result_contract' => 'site_ops_cloud_analysis_result.v1',
				'cloud_role'              => 'runtime_detail',
				'execution_pattern'        => 'whole_run_offload',
				'write_posture'            => 'suggestion_only',
				'direct_wordpress_write'   => false,
				'core_proposal_created'    => false,
				'input'                    => array(),
			);
		}

		$input['profile_id']        = sanitize_text_field( (string) ( $runtime_payload['profile_id'] ?? 'site-ops-analysis.managed' ) );
		$input['timeout_seconds']   = absint( $runtime_payload['timeout_seconds'] ?? 60 );
		$input['retention_ttl']     = absint( $runtime_payload['retention_ttl'] ?? 3600 );
		$input['storage_mode']      = 'result_only';
		$input['write_posture']     = 'suggestion_only';
		$input['cloud_role']        = 'runtime_detail';
		$input['execution_pattern'] = 'whole_run_offload';
		$input['direct_wordpress_write'] = false;
		$input['core_proposal_created']  = false;

		if ( empty( $input['contract_version'] ) ) {
			$input['contract_version'] = 'site_ops_cloud_analysis_request.v1';
		}
		if ( empty( $input['expected_result_contract'] ) ) {
			$input['expected_result_contract'] = 'site_ops_cloud_analysis_result.v1';
		}

		return $this->sanitize_payload( $input );
	}

	public function get_agent_feedback_summary( array $input ) {
		$window_hours = min( 168, max( 1, absint( $input['window_hours'] ?? 24 ) ) );

		$handled = apply_filters( 'npcink_toolbox_agent_feedback_summary_cloud_request', null, $window_hours, $input );
		if ( is_wp_error( $handled ) ) {
			return $handled;
		}
		if ( is_array( $handled ) ) {
			return $this->normalize_agent_feedback_summary_response( $handled, $window_hours );
		}

		if ( ! function_exists( 'npcink_cloud_addon_get_agent_feedback_summary' ) ) {
			return new WP_Error(
				'npcink_toolbox_agent_feedback_summary_cloud_unavailable',
				__( 'Connect an updated Npcink Cloud Addon before reading Agent feedback summary.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$response = npcink_cloud_addon_get_agent_feedback_summary( $window_hours, $this->trace_id( 'agent_feedback_summary' ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->normalize_agent_feedback_summary_response( is_array( $response ) ? $response : array(), $window_hours );
	}

	private function normalize_site_ops_cloud_analysis_response( array $response, array $runtime_payload = array() ): array {
		$data   = is_array( $response['data'] ?? null ) ? $response['data'] : array();
		$result = $this->extract_cloud_runtime_result( $response );
		$status = sanitize_key( (string) ( $data['status'] ?? $response['status'] ?? 'submitted' ) );
		$payload = $this->with_output_contract(
			array(
				'provider'              => 'npcink_cloud',
				'provider_mode'         => 'cloud_managed',
				'contract_version'      => 'site_ops_cloud_analysis_result.v1',
				'cloud_ability'         => sanitize_text_field( (string) ( $runtime_payload['ability_name'] ?? 'npcink-toolbox/analyze-site-ops' ) ),
				'cloud_runtime'         => 'npcink_cloud_addon',
				'status'                => '' !== $status ? $status : 'submitted',
				'runtime_owner'         => 'npcink-ai-cloud',
				'cloud_role'            => 'runtime_detail',
				'final_write_path'      => 'core_proposal_required',
				'cloud_run'             => array(
					'run_id'        => sanitize_text_field( (string) ( $data['run_id'] ?? $response['run_id'] ?? '' ) ),
					'status'        => sanitize_key( (string) ( $data['status'] ?? $response['status'] ?? '' ) ),
					'trace_id'      => sanitize_text_field( (string) ( $data['trace_id'] ?? $response['trace_id'] ?? '' ) ),
					'task_backend'  => is_array( $data['task_backend'] ?? null ) ? $this->sanitize_payload( $data['task_backend'] ) : array(),
					'run_lifecycle' => is_array( $data['run_lifecycle'] ?? null ) ? $this->sanitize_payload( $data['run_lifecycle'] ) : array(),
				),
				'cloud_error'           => array(
					'error_code'      => sanitize_key( (string) ( $data['error_code'] ?? $response['error_code'] ?? '' ) ),
					'error_message'   => sanitize_text_field( (string) ( $data['error_message'] ?? $response['message'] ?? '' ) ),
					'error_stage'     => sanitize_key( (string) ( $data['error_stage'] ?? '' ) ),
					'retryable'       => (bool) ( $data['retryable'] ?? false ),
					'retry_exhausted' => (bool) ( $data['retry_exhausted'] ?? false ),
				),
				'result'                => is_array( $result ) ? $this->sanitize_payload( $result ) : array(),
				'safety'                => array(
					'direct_wordpress_write' => false,
					'cloud_scheduler_truth'  => false,
					'core_proposal_created'  => false,
					'requires_local_review'  => true,
				),
				'cloud_request_summary' => array(
					'execution_pattern' => sanitize_key( (string) ( $runtime_payload['execution_pattern'] ?? 'whole_run_offload' ) ),
					'execution_kind'    => sanitize_key( (string) ( $runtime_payload['execution_kind'] ?? 'site_ops_cloud_analysis' ) ),
					'storage_mode'      => sanitize_key( (string) ( $runtime_payload['storage_mode'] ?? 'result_only' ) ),
					'retention_ttl'     => (int) ( $runtime_payload['retention_ttl'] ?? 0 ),
					'finding_count'     => count( (array) ( $runtime_payload['input']['input']['local_findings'] ?? array() ) ),
				),
			),
			'site_ops_cloud_analysis_runtime',
			'site_ops_cloud_analysis_result'
		);

		if ( $this->settings->raw_responses_enabled() ) {
			$payload['cloud_response'] = $this->sanitize_debug_payload( $response );
		}

		return $payload;
	}

	private function dedupe_image_candidates( array $images ): array {
		$seen = array();
		$out  = array();

		foreach ( $images as $image ) {
			if ( ! is_array( $image ) ) {
				continue;
			}

			$key = (string) ( $image['source_url'] ?? $image['html_url'] ?? $image['regular_url'] ?? $image['id'] ?? '' );
			if ( '' === $key || isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$out[]        = $image;
		}

		return $out;
	}

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

	private function runtime_safe_media_fingerprint( string $fingerprint ): string {
		$fingerprint = trim( sanitize_text_field( $fingerprint ) );
		if ( '' === $fingerprint ) {
			return '';
		}
		if ( 1 === preg_match( '/^sha256:[a-f0-9]{64}$/i', $fingerprint ) ) {
			return strtolower( $fingerprint );
		}

		if ( 1 === preg_match( '/^[a-f0-9]{64}$/i', $fingerprint ) ) {
			return 'sha256:' . strtolower( $fingerprint );
		}

		return '';
	}

	private function media_visual_evidence_reuse_policy( int $attachment_id, string $current_fingerprint, string $evidence_fingerprint, array $visual ): string {
		$current_fingerprint = $this->runtime_safe_media_fingerprint( $current_fingerprint );
		$evidence_fingerprint = $this->runtime_safe_media_fingerprint( $evidence_fingerprint );
		$evidence_policy = sanitize_key( (string) ( $visual['visual_reuse_policy'] ?? '' ) );
		if ( '' === $current_fingerprint || '' === $evidence_fingerprint || 'requires_reidentification' === $evidence_policy ) {
			return '';
		}
		if ( $current_fingerprint === $evidence_fingerprint ) {
			return 'reuse_with_human_check' === $evidence_policy ? $evidence_policy : 'reuse';
		}
		if ( ! function_exists( 'get_post_meta' ) ) {
			return '';
		}
		$history = get_post_meta( $attachment_id, '_npcink_ai_media_file_replacement_history', true );
		if ( ! is_array( $history ) || empty( $history ) ) {
			return '';
		}
		$latest = end( $history );
		if (
			! is_array( $latest )
			|| $current_fingerprint !== $this->runtime_safe_media_fingerprint( (string) ( $latest['new_media_fingerprint'] ?? '' ) )
			|| $evidence_fingerprint !== $this->runtime_safe_media_fingerprint( (string) ( $latest['derived_from_media_fingerprint'] ?? '' ) )
		) {
			return '';
		}
		$policy = sanitize_key( (string) ( $latest['visual_reuse_policy'] ?? '' ) );
		$facts = is_array( $latest['transform_facts'] ?? null ) ? $latest['transform_facts'] : array();
		return in_array( $policy, array( 'reuse', 'reuse_with_human_check' ), true ) && ! empty( $facts ) ? $policy : '';
	}

	private function runtime_safe_media_url( string $url ): string {
		$url = esc_url_raw( $url, array( 'http', 'https' ) );
		if ( '' === $url || 1 !== preg_match( '~^(https?://[^/?#]+)(.*)$~i', $url, $matches ) ) {
			return '';
		}

		$suffix = preg_replace_callback(
			'/%[0-9A-Fa-f]{2}|\d/',
			static function ( array $token ): string {
				return '%' === $token[0][0]
					? $token[0]
					: '%' . strtoupper( bin2hex( $token[0] ) );
			},
			$matches[2]
		);

		return $matches[1] . ( is_string( $suffix ) ? $suffix : '' );
	}

	private function search_site_media_library( string $query, array $options ) {
		$knowledge = $this->site_knowledge->search_site_knowledge(
			array(
				'query'              => $query,
				'intent'             => 'media_library_search',
				'max_results'        => max( 1, min( 10, absint( $options['per_page'] ?? 9 ) ) ),
				'result_granularity' => 'document',
				'filters'            => array(
					'post_types'  => array( 'attachment' ),
					'status'      => array( 'publish' ),
					'source_types' => array( 'media' ),
				),
			)
		);
		if ( is_wp_error( $knowledge ) ) {
			return $knowledge;
		}
		$results = is_array( $knowledge['results'] ?? null ) ? $knowledge['results'] : array();
		$status = sanitize_key( (string) ( $knowledge['status'] ?? 'ready' ) );
		$retrieval_readiness = is_array( $knowledge['retrieval_readiness'] ?? null ) ? $knowledge['retrieval_readiness'] : array();
		$message = '';
		if (
			'not_ready' === $status
			&& 'semantic_embedding_required' === sanitize_key( (string) ( $retrieval_readiness['status'] ?? '' ) )
		) {
			$message = __( 'Site media semantic search is not ready. Configure the development embedding service, then refresh the media index.', 'npcink-workflow-toolbox' );
		}
		$attachment_ids = array_values(
			array_unique(
				array_filter(
					array_map(
						static fn( $result ): int => absint( is_array( $result ) ? ( $result['source_id'] ?? $result['post_id'] ?? 0 ) : 0 ),
						$results
					)
				)
			)
		);
		$inventory = $this->toolkit_media_inventory(
			array(
				'mime_type'      => 'image',
				'attachment_ids' => array_slice( $attachment_ids, 0, 20 ),
				'page'           => 1,
				'per_page'       => 20,
			)
		);
		if ( is_wp_error( $inventory ) ) {
			return $inventory;
		}
		$rows = array();
		foreach ( (array) ( $inventory['items'] ?? array() ) as $item ) {
			if ( is_array( $item ) ) {
				$rows[ absint( $item['attachment_id'] ?? 0 ) ] = $item;
			}
		}
		$evidence_by_attachment_id = array();
		$status                    = $this->site_knowledge->get_site_knowledge_status(
			array(
				'media_attachment_ids' => array_slice( $attachment_ids, 0, 20 ),
			)
		);
		if ( is_array( $status ) ) {
			foreach ( (array) ( $status['media_evidence_items'] ?? array() ) as $evidence_item ) {
				if ( ! is_array( $evidence_item ) ) {
					continue;
				}
				$evidence_attachment_id = absint( $evidence_item['attachment_id'] ?? 0 );
				$visual_evidence        = is_array( $evidence_item['visual_evidence'] ?? null ) ? $evidence_item['visual_evidence'] : array();
				if ( $evidence_attachment_id <= 0 || 'ready' !== sanitize_key( (string) ( $visual_evidence['status'] ?? '' ) ) ) {
					continue;
				}
				$evidence_by_attachment_id[ $evidence_attachment_id ] = array(
					'media_fingerprint' => sanitize_text_field( (string) ( $evidence_item['media_fingerprint'] ?? '' ) ),
					'alt_text_basis'    => sanitize_text_field( (string) ( $visual_evidence['alt_text_basis'] ?? '' ) ),
					'visual_summary'    => sanitize_textarea_field( (string) ( $visual_evidence['visual_summary'] ?? '' ) ),
					'evidence_reuse'    => sanitize_key( (string) ( $visual_evidence['evidence_reuse'] ?? 'site_knowledge_projection' ) ),
					'visual_reuse_policy' => sanitize_key( (string) ( $visual_evidence['visual_reuse_policy'] ?? '' ) ),
				);
			}
		}
		$images = array();
		foreach ( $results as $result ) {
			$attachment_id = absint( is_array( $result ) ? ( $result['source_id'] ?? $result['post_id'] ?? 0 ) : 0 );
			$item = is_array( $rows[ $attachment_id ] ?? null ) ? $rows[ $attachment_id ] : array();
			if ( $attachment_id <= 0 || empty( $item['url'] ) ) {
				continue;
			}
			$format            = is_array( $item['format_inspection'] ?? null ) ? $item['format_inspection'] : array();
			$media_fingerprint = sanitize_text_field( (string) ( $item['media_fingerprint'] ?? '' ) );
			$visual_evidence   = is_array( $evidence_by_attachment_id[ $attachment_id ] ?? null ) ? $evidence_by_attachment_id[ $attachment_id ] : array();
			$visual_reuse_policy = $this->media_visual_evidence_reuse_policy( $attachment_id, $media_fingerprint, (string) ( $visual_evidence['media_fingerprint'] ?? '' ), $visual_evidence );
			if (
				'' === $visual_reuse_policy
			) {
				$visual_evidence = array();
				$visual_reuse_policy = '';
			}
			$suggested_alt = sanitize_text_field( (string) ( $visual_evidence['alt_text_basis'] ?? '' ) );
			$images[] = array(
				'id'                 => 'site-media-' . $attachment_id,
				'attachment_id'      => $attachment_id,
				'candidate_contract' => 'image_candidate.v1',
				'provider'           => 'site_media',
				'source'             => 'site_media_library',
				'source_type'        => 'owned',
				'provider_origin'    => 'wordpress_local',
				'title'              => sanitize_text_field( (string) ( $item['title'] ?? '' ) ),
				'description'        => sanitize_textarea_field( (string) ( $result['chunk'] ?? $item['description'] ?? '' ) ),
				'alt_description'    => sanitize_text_field( (string) ( $item['alt'] ?? '' ) ),
				'url'                => esc_url_raw( (string) $item['url'] ),
				'preview_url'        => esc_url_raw( (string) $item['url'] ),
				'download_url'       => esc_url_raw( (string) $item['url'] ),
				'mime_type'          => sanitize_text_field( (string) ( $item['mime_type'] ?? '' ) ),
				'width'              => absint( $format['width'] ?? 0 ),
				'height'             => absint( $format['height'] ?? 0 ),
				'match_score'        => (float) ( $result['score'] ?? 0 ),
				'match_reason'       => sanitize_text_field( (string) ( $result['reason'] ?? '' ) ),
				'media_fingerprint'  => $media_fingerprint,
				'suggested_alt'      => $suggested_alt,
				'visual_summary'     => sanitize_textarea_field( (string) ( $visual_evidence['visual_summary'] ?? '' ) ),
				'evidence_reuse'     => sanitize_key( (string) ( $visual_evidence['evidence_reuse'] ?? '' ) ),
				'visual_reuse_policy' => $visual_reuse_policy,
				'needs_human_visual_check' => 'reuse_with_human_check' === $visual_reuse_policy,
				'seo_suggestions'    => '' !== $suggested_alt ? array( 'alt' => $suggested_alt ) : array(),
				'requires_local_review' => true,
				'direct_wordpress_write' => false,
			);
		}

		return $this->normalize_image_source_candidates_response(
			array(
				'provider'       => 'site_media',
				'provider_mode'  => 'site_media',
				'active_sources' => array( array( 'provider' => 'site_media', 'count' => count( $images ) ) ),
				'images'         => $images,
				'status'         => $status,
				'message'        => $message,
				'retrieval_readiness' => $this->sanitize_payload( $retrieval_readiness ),
			),
			$query,
			'site_media',
			array( 'input' => array( 'per_page' => max( 1, min( 10, absint( $options['per_page'] ?? 9 ) ) ) ) )
		);
	}

	private function toolkit_media_inventory( array $input ) {
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

	public function build_article_write_plan( array $input ) {
		$title   = trim( sanitize_text_field( (string) ( $input['title'] ?? '' ) ) );
		$content = trim( $this->bounded_text( (string) ( $input['content_markdown'] ?? ( $input['content'] ?? '' ) ), self::ARTICLE_PLAN_CONTENT_CHARS ) );
		if ( '' === $title || '' === $content ) {
			return new WP_Error(
				'npcink_toolbox_missing_article_plan_input',
				__( 'A title and content_markdown are required to build an article write plan.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$topic   = trim( sanitize_text_field( (string) ( $input['topic'] ?? $title ) ) );
		$context = $this->settings->get_content_context_for_ability();
		$forbidden_claims = $this->sanitize_string_list( $context['claims']['forbidden'] ?? array() );
		$blocked_claims = $this->sanitize_string_list( $input['blocked_claims'] ?? array() );
		foreach ( $forbidden_claims as $claim ) {
			if ( '' !== $claim && false !== stripos( $content, $claim ) ) {
				$blocked_claims[] = $claim;
			}
		}
		$blocked_claims = array_values( array_unique( array_filter( $blocked_claims ) ) );

		$risk_level = sanitize_key( (string) ( $input['risk_level'] ?? ( empty( $blocked_claims ) ? 'low' : 'high' ) ) );
		if ( ! in_array( $risk_level, array( 'low', 'medium', 'high' ), true ) ) {
			$risk_level = 'medium';
		}
		$ready_for_proposal = empty( $blocked_claims ) && 'high' !== $risk_level;

		$goal_brief = is_array( $input['article_goal_brief'] ?? null ) ? $this->sanitize_payload( $input['article_goal_brief'] ) : array(
			'topic'           => $topic,
			'target_audience' => $this->sanitize_payload( $context['target_audience'] ?? array() ),
			'brand_voice'     => sanitize_textarea_field( (string) ( $context['brand_voice'] ?? '' ) ),
		);
		$evidence_pack = is_array( $input['research_evidence_pack'] ?? null ) ? $this->sanitize_payload( $input['research_evidence_pack'] ) : array(
			'sources' => is_array( $input['sources'] ?? null ) ? $this->sanitize_payload( $input['sources'] ) : array(),
		);
		$outline = is_array( $input['article_outline'] ?? null ) ? $this->sanitize_payload( $input['article_outline'] ) : array(
			'title'    => $title,
			'sections' => array(),
		);
		$draft_candidate = is_array( $input['article_draft_candidate'] ?? null ) ? $this->sanitize_payload( $input['article_draft_candidate'] ) : array(
			'content_markdown'  => $content,
			'used_sources'      => $this->sanitize_string_list( $input['used_sources'] ?? array() ),
			'unverified_claims' => $this->sanitize_string_list( $input['unverified_claims'] ?? array() ),
			'needs_human_input' => $this->sanitize_string_list( $input['needs_human_input'] ?? array() ),
		);
		$discoverability_pack = is_array( $input['discoverability_pack'] ?? null ) ? $this->sanitize_payload( $input['discoverability_pack'] ) : array(
			'seo_title'       => sanitize_text_field( (string) ( $input['seo_title'] ?? $title ) ),
			'seo_description' => sanitize_textarea_field( (string) ( $input['seo_description'] ?? wp_trim_words( wp_strip_all_tags( $content ), 24, '' ) ) ),
			'excerpt'         => sanitize_textarea_field( (string) ( $input['excerpt'] ?? wp_trim_words( wp_strip_all_tags( $content ), 35, '' ) ) ),
		);

		$risk_report = array(
			'risk_level'         => $risk_level,
			'blocked_claims'     => $blocked_claims,
			'needs_review'       => $this->sanitize_string_list( $input['needs_review'] ?? array() ),
			'ready_for_proposal' => $ready_for_proposal,
		);

		return array(
			'artifact_type'          => 'article_write_plan',
			'composition_role'       => 'core_article_write_plan',
			'version'                => 1,
			'source_recipe_id'       => 'article_draft_v1',
			'source_recipe_ref'      => 'npcink-abilities-toolkit/recipes/article-draft',
			'source_recipe_provider' => 'npcink-abilities-toolkit',
			'recipe_execution'       => 'local_operator_orchestration',
			'write_posture'          => 'core_proposal_handoff',
			'direct_wordpress_write' => false,
			'batch_id'               => 'article_write_' . substr( md5( $title . '|' . $content ), 0, 12 ),
			'requires_approval'      => true,
			'dry_run'                => true,
			'commit_execution'       => false,
			'proposal_mode'          => 'single',
			'article_goal_brief'     => $goal_brief,
			'research_evidence_pack' => $evidence_pack,
			'article_outline'        => $outline,
			'article_draft_candidate' => $draft_candidate,
			'discoverability_pack'   => $discoverability_pack,
			'article_risk_report'    => $risk_report,
			'write_actions'          => array(
				array(
					'action_id'         => 'create_article_draft',
					'target_ability_id' => 'npcink-abilities-toolkit/create-draft',
					'recipe_step'       => 'host_governed_create_draft',
					'input'             => array(
						'title'          => $title,
						'content'        => $content,
						'content_format' => 'markdown',
						'excerpt'        => (string) ( $discoverability_pack['excerpt'] ?? '' ),
						'status'         => 'draft',
						'dry_run'        => true,
						'commit'         => false,
					),
					'risk'              => 'medium',
					'requires_approval' => true,
					'commit_execution'  => false,
					'proposal_ready'    => $ready_for_proposal,
					'reason'            => __( 'Create a reviewed AI-assisted article draft through Core governance.', 'npcink-workflow-toolbox' ),
				),
			),
			'handoff'                => array(
				'plan_ability_id'        => 'npcink-toolbox/build-article-write-plan',
				'recipe_id'              => 'article_draft_v1',
				'recipe_ref'             => 'npcink-abilities-toolkit/recipes/article-draft',
				'core_route'             => '/wp-json/npcink-governance-core/v1/proposals/from-plan',
				'final_write_path'       => 'core_proposal_required',
				'direct_wordpress_write' => false,
			),
		);
	}

	public function build_article_batch_write_plan( array $input ) {
		$articles = is_array( $input['articles'] ?? null ) ? array_values( $input['articles'] ) : array();
		if ( count( $articles ) < 2 || count( $articles ) > 5 ) {
			return new WP_Error(
				'npcink_toolbox_article_batch_size_invalid',
				__( 'Article batch write plans require 2 to 5 reviewed draft articles.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$topic          = sanitize_text_field( (string) ( $input['topic'] ?? 'Article batch draft plan' ) );
		$blocked_claims = $this->sanitize_string_list( $input['blocked_claims'] ?? array() );
		$risk_level    = sanitize_key( (string) ( $input['risk_level'] ?? ( empty( $blocked_claims ) ? 'medium' : 'high' ) ) );
		if ( ! in_array( $risk_level, array( 'low', 'medium', 'high' ), true ) ) {
			$risk_level = 'medium';
		}
		$ready_for_proposal = empty( $blocked_claims ) && 'high' !== $risk_level;
		$article_artifacts  = array();
		$write_actions      = array();
		$preview            = array();

		foreach ( $articles as $index => $article ) {
			$article = is_array( $article ) ? $article : array();
			$title   = trim( sanitize_text_field( (string) ( $article['title'] ?? '' ) ) );
			$content = trim( $this->bounded_text( (string) ( $article['content_markdown'] ?? ( $article['content'] ?? '' ) ), self::ARTICLE_PLAN_CONTENT_CHARS ) );
			if ( '' === $title || '' === $content ) {
				return new WP_Error(
					'npcink_toolbox_article_batch_item_invalid',
					__( 'Every article batch item requires title and content_markdown.', 'npcink-workflow-toolbox' ),
					array(
						'status' => 400,
						'index'  => $index,
					)
				);
			}

			$action_id = 'create_article_draft_' . ( $index + 1 );
			$excerpt   = sanitize_textarea_field( (string) ( $article['excerpt'] ?? wp_trim_words( wp_strip_all_tags( $content ), 35, '' ) ) );
			$article_artifacts[] = array(
				'article_goal_brief'      => is_array( $article['article_goal_brief'] ?? null ) ? $this->sanitize_payload( $article['article_goal_brief'] ) : array(
					'topic' => $topic,
					'title' => $title,
				),
				'research_evidence_pack'  => is_array( $article['research_evidence_pack'] ?? null ) ? $this->sanitize_payload( $article['research_evidence_pack'] ) : array(
					'sources' => is_array( $article['sources'] ?? null ) ? $this->sanitize_payload( $article['sources'] ) : array(),
				),
				'article_outline'         => is_array( $article['article_outline'] ?? null ) ? $this->sanitize_payload( $article['article_outline'] ) : array(
					'title'    => $title,
					'sections' => array(),
				),
				'article_draft_candidate' => is_array( $article['article_draft_candidate'] ?? null ) ? $this->sanitize_payload( $article['article_draft_candidate'] ) : array(
					'content_markdown' => $content,
				),
				'discoverability_pack'    => is_array( $article['discoverability_pack'] ?? null ) ? $this->sanitize_payload( $article['discoverability_pack'] ) : array(
					'excerpt' => $excerpt,
				),
				'article_risk_report'     => is_array( $article['article_risk_report'] ?? null ) ? $this->sanitize_payload( $article['article_risk_report'] ) : array(
					'risk_level'         => $risk_level,
					'blocked_claims'     => $blocked_claims,
					'ready_for_proposal' => $ready_for_proposal,
				),
			);
			$write_actions[] = array(
				'action_id'         => $action_id,
				'target_ability_id' => 'npcink-abilities-toolkit/create-draft',
				'recipe_step'       => 'host_governed_create_draft',
			'input'             => array(
					'title'          => $title,
					'content'        => $content,
					'content_format' => sanitize_key( (string) ( $article['content_format'] ?? 'plain' ) ),
					'excerpt'        => $excerpt,
					'status'         => 'draft',
					'dry_run'        => true,
					'commit'         => false,
				),
				'risk'              => 'medium',
				'requires_approval' => true,
				'commit_execution'  => false,
				'proposal_ready'    => $ready_for_proposal,
				'reason'            => __( 'Create one reviewed AI-assisted article draft through Core governance.', 'npcink-workflow-toolbox' ),
			);
			$preview[] = array(
				'action_id' => $action_id,
				'title'     => $title,
				'status'    => 'draft',
				'excerpt'   => $excerpt,
			);
		}

		return array(
			'artifact_type'             => 'article_batch_write_plan',
			'composition_role'          => 'core_article_batch_write_plan',
			'version'                   => 1,
			'source_recipe_id'          => 'article_batch_draft_v1',
			'source_recipe_ref'         => 'npcink-toolbox/recipes/article-batch-draft',
			'source_recipe_provider'    => 'npcink-toolbox',
			'recipe_execution'          => 'local_operator_orchestration',
			'write_posture'             => 'core_proposal_handoff',
			'direct_wordpress_write'    => false,
			'batch_id'                  => 'article_batch_write_' . substr( md5( $topic . '|' . wp_json_encode( $preview ) ), 0, 12 ),
			'requires_approval'         => true,
			'dry_run'                   => true,
			'commit_execution'          => false,
			'proposal_mode'             => 'batch',
			'batch_approval'            => true,
			'publish_allowed'           => false,
			'partial_success'           => false,
			'action_count'              => count( $write_actions ),
			'articles'                  => $article_artifacts,
			'preview'                   => $preview,
			'article_batch_risk_report' => array(
				'risk_level'         => $risk_level,
				'blocked_claims'     => $blocked_claims,
				'needs_review'       => $this->sanitize_string_list( $input['needs_review'] ?? array() ),
				'ready_for_proposal' => $ready_for_proposal,
			),
			'write_actions'             => $write_actions,
			'handoff'                   => array(
				'plan_ability_id'        => 'npcink-toolbox/build-article-batch-write-plan',
				'recipe_id'              => 'article_batch_draft_v1',
				'recipe_ref'             => 'npcink-toolbox/recipes/article-batch-draft',
				'core_route'             => '/wp-json/npcink-governance-core/v1/proposals/from-plan',
				'final_write_path'       => 'core_proposal_required',
				'direct_wordpress_write' => false,
			),
		);
	}

	public function build_article_media_batch_write_plan( array $input ) {
		$articles = is_array( $input['articles'] ?? null ) ? array_values( $input['articles'] ) : array();
		if ( count( $articles ) < 1 || count( $articles ) > 5 ) {
			return new WP_Error(
				'npcink_toolbox_article_media_batch_size_invalid',
				__( 'Article media batch write plans require 1 to 5 reviewed draft articles.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$topic          = sanitize_text_field( (string) ( $input['topic'] ?? 'Article media batch draft plan' ) );
		$search_images  = true === (bool) ( $input['search_images'] ?? false );
		$image_provider = sanitize_key( (string) ( $input['image_provider'] ?? $input['provider'] ?? '' ) );
		$blocked_claims = $this->sanitize_string_list( $input['blocked_claims'] ?? array() );
		$risk_level     = sanitize_key( (string) ( $input['risk_level'] ?? ( empty( $blocked_claims ) ? 'medium' : 'high' ) ) );
		if ( ! in_array( $risk_level, array( 'low', 'medium', 'high' ), true ) ) {
			$risk_level = 'medium';
		}
		$ready_for_proposal = empty( $blocked_claims ) && 'high' !== $risk_level;
		$article_artifacts  = array();
		$write_actions      = array();
		$preview            = array();
		$media_workflow     = array();

		foreach ( $articles as $index => $article ) {
			$article = is_array( $article ) ? $article : array();
			$title   = trim( sanitize_text_field( (string) ( $article['title'] ?? '' ) ) );
			$content = trim( $this->bounded_text( (string) ( $article['content_markdown'] ?? ( $article['content'] ?? '' ) ), self::ARTICLE_PLAN_CONTENT_CHARS ) );
			if ( '' === $title || '' === $content ) {
				return new WP_Error(
					'npcink_toolbox_article_media_batch_item_invalid',
					__( 'Every article media batch item requires title and content_markdown.', 'npcink-workflow-toolbox' ),
					array(
						'status' => 400,
						'index'  => $index,
					)
				);
			}

			$candidate = $this->resolve_article_media_candidate( $article, $title, $topic, $search_images, $image_provider );
			if ( is_wp_error( $candidate ) ) {
				$candidate->add_data(
					array_merge(
						(array) $candidate->get_error_data(),
						array(
							'status' => 400,
							'index'  => $index,
						)
					)
				);
				return $candidate;
			}

			$image_url = (string) ( $candidate['regular_url'] ?? $candidate['small_url'] ?? $candidate['url'] ?? '' );
			if ( '' === $image_url ) {
				return new WP_Error(
					'npcink_toolbox_article_media_url_missing',
					__( 'Every article media batch item requires a selected image URL.', 'npcink-workflow-toolbox' ),
					array(
						'status' => 400,
						'index'  => $index,
					)
				);
			}

			$position      = $index + 1;
			$create_id     = 'create_article_draft_' . $position;
			$upload_id     = 'upload_featured_image_' . $position;
			$metadata_id   = 'update_featured_image_details_' . $position;
			$featured_id   = 'set_featured_image_' . $position;
			$excerpt       = sanitize_textarea_field( (string) ( $article['excerpt'] ?? wp_trim_words( wp_strip_all_tags( $content ), 35, '' ) ) );
			$provider      = sanitize_key( (string) ( $candidate['provider'] ?? 'external' ) );
			$candidate_source_type = sanitize_key( (string) ( $candidate['source_type'] ?? '' ) );
			if ( 'ai_generated' === $provider || 'ai_generated' === $candidate_source_type ) {
				$source_type = 'ai_generated';
			} elseif ( in_array( $provider, array( 'unsplash', 'pixabay', 'pexels' ), true ) || 'stock' === $candidate_source_type ) {
				$source_type = 'stock';
			} else {
				$source_type = 'external';
			}
			$source_url    = esc_url_raw( (string) ( $candidate['source_url'] ?? $candidate['html_url'] ?? '' ) );
			$photographer  = sanitize_text_field( (string) ( $candidate['photographer'] ?? $candidate['photographer_name'] ?? '' ) );
			$attribution   = sanitize_textarea_field( (string) ( $candidate['attribution'] ?? $candidate['attribution_text'] ?? '' ) );
			$alt           = sanitize_textarea_field( (string) ( $candidate['alt_description'] ?? $candidate['description'] ?? $title ) );
			$description   = sanitize_textarea_field( (string) ( $candidate['description'] ?? $alt ) );
			$file_name     = sanitize_file_name( (string) ( $article['file_name'] ?? $candidate['file_name'] ?? '' ) );

			$article_artifacts[] = array(
				'article_goal_brief'      => is_array( $article['article_goal_brief'] ?? null ) ? $this->sanitize_payload( $article['article_goal_brief'] ) : array(
					'topic'       => $topic,
					'title'       => $title,
					'image_query' => sanitize_text_field( (string) ( $article['image_query'] ?? $title ) ),
				),
				'research_evidence_pack'  => is_array( $article['research_evidence_pack'] ?? null ) ? $this->sanitize_payload( $article['research_evidence_pack'] ) : array(
					'sources' => is_array( $article['sources'] ?? null ) ? $this->sanitize_payload( $article['sources'] ) : array(),
				),
				'article_outline'         => is_array( $article['article_outline'] ?? null ) ? $this->sanitize_payload( $article['article_outline'] ) : array(
					'title'    => $title,
					'sections' => array(),
				),
				'article_draft_candidate' => is_array( $article['article_draft_candidate'] ?? null ) ? $this->sanitize_payload( $article['article_draft_candidate'] ) : array(
					'content_markdown' => $content,
				),
				'discoverability_pack'    => is_array( $article['discoverability_pack'] ?? null ) ? $this->sanitize_payload( $article['discoverability_pack'] ) : array(
					'excerpt' => $excerpt,
				),
				'article_risk_report'     => is_array( $article['article_risk_report'] ?? null ) ? $this->sanitize_payload( $article['article_risk_report'] ) : array(
					'risk_level'         => $risk_level,
					'blocked_claims'     => $blocked_claims,
					'ready_for_proposal' => $ready_for_proposal,
				),
				'featured_image_candidate' => $this->sanitize_payload( $candidate ),
			);

			$write_actions[] = array(
				'action_id'         => $create_id,
				'target_ability_id' => 'npcink-abilities-toolkit/create-draft',
				'recipe_step'       => 'host_governed_create_draft',
			'input'             => array(
					'title'          => $title,
					'content'        => $content,
					'content_format' => sanitize_key( (string) ( $article['content_format'] ?? 'plain' ) ),
					'excerpt'        => $excerpt,
					'status'         => 'draft',
					'dry_run'        => true,
					'commit'         => false,
					'idempotency_key' => 'article-media-draft-' . $position,
				),
				'risk'              => 'medium',
				'requires_approval' => true,
				'commit_execution'  => false,
				'proposal_ready'    => $ready_for_proposal,
				'reason'            => __( 'Create one reviewed AI-assisted article draft through Core governance.', 'npcink-workflow-toolbox' ),
			);
			$write_actions[] = array(
				'action_id'         => $upload_id,
				'target_ability_id' => 'npcink-abilities-toolkit/upload-media-from-url',
				'recipe_step'       => 'host_governed_upload_featured_image',
				'depends_on'        => array( $create_id ),
			'input'             => array(
					'url'               => $image_url,
					'title'             => $title,
					'file_name'         => $file_name,
					'alt'               => $alt,
					'caption'           => $attribution,
					'description'       => $description,
					'source_type'       => $source_type,
					'source_page_url'   => $source_url,
					'photographer_name' => $photographer,
					'attribution_text'  => $attribution,
					'copyright_notice'  => sanitize_text_field( (string) ( $candidate['copyright_notice'] ?? '' ) ),
					'attach_to_post_id' => '$outputs.' . $create_id . '.post_id',
					'dry_run'           => true,
					'commit'            => false,
					'idempotency_key'   => 'article-media-upload-' . $position,
				),
				'risk'              => 'medium',
				'requires_approval' => true,
				'commit_execution'  => false,
				'proposal_ready'    => $ready_for_proposal,
				'reason'            => __( 'Upload the reviewed image-source candidate into the media library after Core approval.', 'npcink-workflow-toolbox' ),
			);
			$write_actions[] = array(
				'action_id'         => $metadata_id,
				'target_ability_id' => 'npcink-abilities-toolkit/update-media-details',
				'recipe_step'       => 'host_governed_update_featured_image_metadata',
				'depends_on'        => array( $upload_id ),
			'input'             => array(
					'attachment_id'     => '$outputs.' . $upload_id . '.attachment_id',
					'alt'               => $alt,
					'caption'           => $attribution,
					'description'       => $description,
					'source_type'       => $source_type,
					'source_page_url'   => $source_url,
					'photographer_name' => $photographer,
					'attribution_text'  => $attribution,
					'dry_run'           => true,
					'commit'            => false,
					'idempotency_key'   => 'article-media-details-' . $position,
				),
				'risk'              => 'medium',
				'requires_approval' => true,
				'commit_execution'  => false,
				'proposal_ready'    => $ready_for_proposal,
				'reason'            => __( 'Apply reviewed image attribution and accessibility metadata after upload.', 'npcink-workflow-toolbox' ),
			);
			$write_actions[] = array(
				'action_id'         => $featured_id,
				'target_ability_id' => 'npcink-abilities-toolkit/set-post-featured-image',
				'recipe_step'       => 'host_governed_set_featured_image',
				'depends_on'        => array( $create_id, $upload_id ),
			'input'             => array(
					'post_id'        => '$outputs.' . $create_id . '.post_id',
					'attachment_id'  => '$outputs.' . $upload_id . '.attachment_id',
					'dry_run'        => true,
					'commit'         => false,
					'idempotency_key' => 'article-media-featured-' . $position,
				),
				'risk'              => 'medium',
				'requires_approval' => true,
				'commit_execution'  => false,
				'proposal_ready'    => $ready_for_proposal,
				'reason'            => __( 'Set the uploaded, reviewed media item as the draft featured image after Core approval.', 'npcink-workflow-toolbox' ),
			);

			$media_workflow[] = array(
				'article_index'      => $index,
				'title'              => $title,
				'image_query'        => sanitize_text_field( (string) ( $article['image_query'] ?? $title ) ),
				'candidate_provider' => $provider,
				'source_url'         => $source_url,
				'download_location'  => esc_url_raw( (string) ( $candidate['download_location'] ?? '' ) ),
				'attribution'        => $attribution,
				'action_ids'         => array( $create_id, $upload_id, $metadata_id, $featured_id ),
			);
			$preview[] = array(
				'action_id'         => $create_id,
				'title'             => $title,
				'status'            => 'draft',
				'excerpt'           => $excerpt,
				'featured_image_url' => $image_url,
				'attribution'       => $attribution,
			);
		}

		return array(
			'artifact_type'             => 'article_media_batch_write_plan',
			'composition_role'          => 'core_article_media_batch_write_plan',
			'version'                   => 1,
			'source_recipe_id'          => 'article_media_batch_draft_v1',
			'source_recipe_ref'         => 'npcink-toolbox/recipes/article-media-batch-draft',
			'source_recipe_provider'    => 'npcink-toolbox',
			'recipe_execution'          => 'local_operator_orchestration',
			'write_posture'             => 'core_proposal_handoff',
			'direct_wordpress_write'    => false,
			'batch_id'                  => 'article_media_batch_write_' . substr( md5( $topic . '|' . wp_json_encode( $preview ) ), 0, 12 ),
			'requires_approval'         => true,
			'dry_run'                   => true,
			'commit_execution'          => false,
			'proposal_mode'             => 'batch',
			'batch_approval'            => true,
			'publish_allowed'           => false,
			'partial_success'           => false,
			'action_count'              => count( $write_actions ),
			'articles'                  => $article_artifacts,
			'media_workflow'            => $media_workflow,
			'preview'                   => $preview,
			'article_batch_risk_report' => array(
				'risk_level'         => $risk_level,
				'blocked_claims'     => $blocked_claims,
				'needs_review'       => $this->sanitize_string_list( $input['needs_review'] ?? array() ),
				'ready_for_proposal' => $ready_for_proposal,
			),
			'write_actions'             => $write_actions,
			'handoff'                   => array(
				'plan_ability_id'        => 'npcink-toolbox/build-article-media-batch-write-plan',
				'recipe_id'              => 'article_media_batch_draft_v1',
				'recipe_ref'             => 'npcink-toolbox/recipes/article-media-batch-draft',
				'core_route'             => '/wp-json/npcink-governance-core/v1/proposals/from-plan',
				'final_write_path'       => 'core_proposal_required',
				'direct_wordpress_write' => false,
			),
		);
	}

	public function build_image_candidate_adoption_plan( array $input ) {
		if ( ! function_exists( 'npcink_abilities_toolkit_get_registered' ) ) {
			return new WP_Error(
				'npcink_toolbox_image_candidate_toolkit_unavailable',
				__( 'The Toolkit image candidate adoption-plan ability is not currently available.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$registered = npcink_abilities_toolkit_get_registered();
		$ability    = is_array( $registered ) ? ( $registered['npcink-abilities-toolkit/build-image-candidate-adoption-plan'] ?? null ) : null;
		$callback   = is_array( $ability ) ? ( $ability['execute_callback'] ?? null ) : null;
		if ( ! is_callable( $callback ) ) {
			return new WP_Error(
				'npcink_toolbox_image_candidate_toolkit_plan_unavailable',
				__( 'The Toolkit image candidate adoption-plan ability is not currently callable.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$result = call_user_func( $callback, $input );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! is_array( $result ) ) {
			return new WP_Error(
				'npcink_toolbox_image_candidate_toolkit_plan_invalid',
				__( 'The Toolkit image candidate adoption-plan ability returned an invalid response.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}

		$data = is_array( $result['data'] ?? null ) ? $result['data'] : $result;
		if ( empty( $data['artifact_type'] ) || 'image_candidate_adoption_plan' !== (string) $data['artifact_type'] ) {
			return new WP_Error(
				'npcink_toolbox_image_candidate_toolkit_plan_invalid_artifact',
				__( 'The Toolkit image candidate adoption-plan ability did not return the expected artifact.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}

		return $data;
	}

	public function build_article_audio_adoption_plan( array $input ) {
		$post_id = absint( $input['post_id'] ?? 0 );
		if ( $post_id <= 0 ) {
			return new WP_Error(
				'npcink_toolbox_article_audio_post_required',
				__( 'A post_id is required before preparing an article audio adoption plan.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$candidate = is_array( $input['audio_candidate'] ?? null ) ? $input['audio_candidate'] : array();
		$audio_url = esc_url_raw( (string) ( $candidate['url'] ?? ( $candidate['audio_url'] ?? ( $input['audio_url'] ?? '' ) ) ) );
		if ( '' === $audio_url ) {
			return new WP_Error(
				'npcink_toolbox_article_audio_url_required',
				__( 'Select an audio candidate with a playable URL before preparing Core review.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$candidate_type = sanitize_key( (string) ( $input['candidate_type'] ?? ( $candidate['candidate_type'] ?? 'article_narration' ) ) );
		if ( ! in_array( $candidate_type, array( 'article_narration', 'article_audio_summary' ), true ) ) {
			$candidate_type = 'article_narration';
		}

		$title = sanitize_text_field( (string) ( $candidate['name'] ?? ( $candidate['title'] ?? ( 'article_audio_summary' === $candidate_type ? __( 'Audio summary', 'npcink-workflow-toolbox' ) : __( 'Article narration', 'npcink-workflow-toolbox' ) ) ) ) );
		if ( '' === $title ) {
			$title = 'article_audio_summary' === $candidate_type ? __( 'Audio summary', 'npcink-workflow-toolbox' ) : __( 'Article narration', 'npcink-workflow-toolbox' );
		}

		$format = sanitize_key( (string) ( $candidate['format'] ?? ( $input['format'] ?? 'mp3' ) ) );
		if ( '' === $format ) {
			$format = 'mp3';
		}
		$mime_type = sanitize_mime_type( (string) ( $candidate['mime_type'] ?? ( $input['mime_type'] ?? '' ) ) );
		if ( '' === $mime_type ) {
			$mime_type = 'wav' === $format ? 'audio/wav' : 'audio/mpeg';
		}

		$duration_seconds = is_numeric( $candidate['duration_seconds'] ?? null ) ? (float) $candidate['duration_seconds'] : 0.0;
		if ( $duration_seconds <= 0 && is_numeric( $input['duration_seconds'] ?? null ) ) {
			$duration_seconds = (float) $input['duration_seconds'];
		}

		$script = $this->trim_chars(
			sanitize_textarea_field( (string) ( $input['script'] ?? ( $candidate['script'] ?? '' ) ) ),
			self::AUDIO_GENERATION_TEXT_CHARS
		);
		$source_audio_generation = is_array( $input['source_audio_generation'] ?? null ) ? $this->sanitize_payload( $input['source_audio_generation'] ) : array();
		$post_type               = sanitize_key( (string) ( $input['post_type'] ?? ( get_post_type( $post_id ) ?: 'post' ) ) );
		$source_content          = $this->article_audio_normalized_source_text( (string) ( $input['source_content'] ?? ( $input['source_content_text'] ?? '' ) ) );
		$source_content_hash     = sanitize_text_field( (string) ( $input['source_content_hash'] ?? '' ) );
		if ( '' === $source_content_hash && '' !== $source_content ) {
			$source_content_hash = $this->article_audio_content_hash( $source_content );
		}
		$source_word_count = absint( $input['source_word_count'] ?? 0 );
		if ( $source_word_count <= 0 && '' !== $source_content ) {
			$source_word_count = $this->article_audio_word_count( $source_content );
		}
		$source_generated_at = sanitize_text_field(
			(string) (
				$candidate['generated_at']
				?? ( $source_audio_generation['generated_at'] ?? ( $source_audio_generation['created_at'] ?? gmdate( 'c' ) ) )
			)
		);
		$voice_id                = sanitize_text_field( (string) ( $candidate['voice_id'] ?? ( $source_audio_generation['voice_id'] ?? '' ) ) );
		$model_id                = sanitize_text_field( (string) ( $candidate['model_id'] ?? ( $source_audio_generation['model_id'] ?? '' ) ) );
		$provider                = sanitize_key( (string) ( $candidate['provider'] ?? ( $source_audio_generation['provider'] ?? 'cloud_audio' ) ) );
		$trace_id                = sanitize_text_field( (string) ( $source_audio_generation['trace_id'] ?? ( $source_audio_generation['trace'] ?? '' ) ) );
		$import_media            = array_key_exists( 'import_media', $input ) ? ! empty( $input['import_media'] ) : true;
		$media_file_name         = sanitize_text_field( (string) ( $input['media_file_name'] ?? '' ) );
		$planner_id              = 'npcink-abilities-toolkit/build-article-audio-adoption-plan';
		$write_ability_id        = 'npcink-abilities-toolkit/adopt-article-audio';
		$planner_available       = $this->registered_ability_callable( $planner_id );
		$write_available         = $this->registered_ability_callable( $write_ability_id );
		$proposal_ready          = $planner_available && $write_available;
		$idempotency_key         = 'article-audio-adoption-' . substr( md5( $post_id . '|' . $candidate_type . '|' . $audio_url ), 0, 16 );
		$audio_hash              = md5( $audio_url );

		$missing_dependencies = array();
		if ( ! $planner_available ) {
			$missing_dependencies[] = array(
				'ability_id' => $planner_id,
				'status'     => 'not_registered_or_not_callable',
			);
		}
		if ( ! $write_available ) {
			$missing_dependencies[] = array(
				'ability_id' => $write_ability_id,
				'status'     => 'not_registered_or_not_callable',
			);
		}

		$meta_projection = array(
			'_npcink_toolbox_article_audio_url'              => $audio_url,
			'_npcink_toolbox_article_audio_title'            => $title,
			'_npcink_toolbox_article_audio_kind'             => $candidate_type,
			'_npcink_toolbox_article_audio_duration_seconds' => $duration_seconds,
			'_npcink_toolbox_article_audio_mime_type'        => $mime_type,
			'_npcink_toolbox_article_audio_source_content_hash' => $source_content_hash,
			'_npcink_toolbox_article_audio_source_word_count' => $source_word_count,
			'_npcink_toolbox_article_audio_source_generated_at' => $source_generated_at,
		);

		$audio_candidate = array(
			'url'              => $audio_url,
			'title'            => $title,
			'name'             => $title,
			'candidate_type'   => $candidate_type,
			'format'           => $format,
			'mime_type'        => $mime_type,
			'duration_seconds' => $duration_seconds,
			'voice_id'         => $voice_id,
			'model_id'         => $model_id,
			'provider'         => $provider,
		);

		return array(
			'artifact_type'            => 'article_audio_adoption_plan.v1',
			'composition_role'         => 'core_article_audio_adoption_plan',
			'version'                  => 1,
			'post_id'                  => $post_id,
			'post_type'                => $post_type,
			'candidate_type'           => $candidate_type,
			'write_posture'            => 'core_proposal_handoff',
			'final_write_path'         => 'core_proposal_required',
			'direct_wordpress_write'   => false,
			'proposal_ready'           => $proposal_ready,
			'requires_approval'        => true,
			'dry_run'                  => true,
			'commit_execution'         => false,
			'proposal_mode'            => 'single',
			'target_plan_ability_id'   => $planner_id,
			'target_write_ability_id'  => $write_ability_id,
			'missing_dependencies'     => $missing_dependencies,
			'audio_candidate'          => $this->sanitize_payload( $audio_candidate ),
			'script'                   => $script,
			'source_audio_generation'  => $source_audio_generation,
			'evidence_refs'            => array(
				array(
					'kind'        => 'article_audio_candidate',
					'post_id'     => $post_id,
					'audio_hash'  => $audio_hash,
					'provider'    => $provider,
					'model_id'    => $model_id,
					'voice_id'    => $voice_id,
					'trace_id'    => $trace_id,
					'url_host'    => sanitize_text_field( (string) wp_parse_url( $audio_url, PHP_URL_HOST ) ),
					'import_media' => $import_media,
					'script_hash' => '' !== $script ? md5( $script ) : '',
					'source_content_hash' => $source_content_hash,
					'source_word_count' => $source_word_count,
					'source_generated_at' => $source_generated_at,
				),
			),
			'preview'                  => array(
				array(
					'action_id'        => 'adopt_article_audio',
					'post_id'          => $post_id,
					'candidate_type'   => $candidate_type,
					'audio_title'      => $title,
					'audio_url'        => $audio_url,
					'storage_mode'     => $import_media ? 'wordpress_media_library' : 'remote_url',
					'meta_projection'  => $meta_projection,
					'audio_freshness'  => array(
						'initial_status'      => '' !== $source_content_hash ? 'current' : 'unknown',
						'source_content_hash' => $source_content_hash,
						'source_word_count'   => $source_word_count,
						'source_generated_at' => $source_generated_at,
						'policy'              => 'hash_match_current_else_word_count_delta_thresholds',
					),
					'proposal_ready'   => $proposal_ready,
					'write_owner'      => 'npcink-abilities-toolkit',
					'governance_owner' => 'npcink-governance-core',
				),
			),
			'write_actions'            => array(
				array(
					'action_id'         => 'adopt_article_audio',
					'target_ability_id' => $write_ability_id,
					'recipe_step'       => 'host_governed_article_audio_adoption',
					'input'             => array(
						'post_id'             => $post_id,
						'audio_url'           => $audio_url,
						'audio_title'         => $title,
						'audio_kind'          => $candidate_type,
						'duration_seconds'    => $duration_seconds,
						'mime_type'           => $mime_type,
						'source_content_hash' => $source_content_hash,
						'source_word_count'   => $source_word_count,
						'source_generated_at' => $source_generated_at,
						'provider'            => $provider,
						'model'               => $model_id,
						'trace_id'            => $trace_id,
						'import_media'        => $import_media,
						'media_file_name'     => $media_file_name,
						'dry_run'             => true,
						'commit'              => false,
						'idempotency_key'     => $idempotency_key,
					),
					'risk'              => 'low',
					'requires_approval' => true,
					'commit_execution'  => false,
					'proposal_ready'    => $proposal_ready,
					'reason'            => __( 'Adopting generated article audio imports the reviewed audio into the local media library when requested and writes playback metadata through Core governance before Adapter execution.', 'npcink-workflow-toolbox' ),
				),
			),
			'blocked_actions'          => array(
				'no_audio_meta_write_in_toolbox',
				'no_media_import_in_toolbox',
				'no_post_content_patch',
				'no_direct_wordpress_write',
			),
			'handoff'                  => array(
				'plan_ability_id'        => $planner_id,
				'recipe_id'              => 'article_audio_adoption_v1',
				'recipe_ref'             => 'workflow/article_audio_adoption',
				'core_route'             => '/wp-json/npcink-governance-core/v1/proposals/from-plan',
				'adapter_route'          => '/wp-json/npcink-openclaw-adapter/v1/proposals/from-plan',
				'final_write_path'       => 'core_proposal_required',
				'direct_wordpress_write' => false,
				'proposal_ready'         => $proposal_ready,
			),
		);
	}

	public function build_site_knowledge_review_plan( array $input ) {
		$proposal_input = $input['proposal_input'] ?? array();
		if ( is_string( $proposal_input ) ) {
			$decoded        = json_decode( $proposal_input, true );
			$proposal_input = is_array( $decoded ) ? $decoded : array();
		}
		$proposal_input = is_array( $proposal_input ) ? $proposal_input : array();

		$handoff = $input['handoff'] ?? array();
		if ( is_string( $handoff ) ) {
			$decoded = json_decode( $handoff, true );
			$handoff = is_array( $decoded ) ? $decoded : array();
		}
		$handoff = is_array( $handoff ) ? $handoff : array();

		$evidence_refs = is_array( $proposal_input['evidence_refs'] ?? null ) ? array_values( $proposal_input['evidence_refs'] ) : array();
		if ( empty( $evidence_refs ) && is_array( $handoff['proposal_input']['evidence_refs'] ?? null ) ) {
			$evidence_refs = array_values( $handoff['proposal_input']['evidence_refs'] );
		}
		if ( empty( $evidence_refs ) ) {
			return new WP_Error(
				'npcink_toolbox_site_knowledge_review_evidence_required',
				__( 'Site Knowledge review plans require evidence_refs from the Cloud handoff.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$blocked_outputs = is_array( $proposal_input['blocked_outputs'] ?? null ) ? array_values( $proposal_input['blocked_outputs'] ) : array();
		$workflow        = sanitize_key( (string) ( $handoff['workflow'] ?? ( $proposal_input['workflow'] ?? 'site_knowledge_review' ) ) );
		$intent          = sanitize_key( (string) ( $proposal_input['intent'] ?? $workflow ) );
		$cloud_output    = sanitize_key( (string) ( $handoff['cloud_output'] ?? ( $proposal_input['cloud_output'] ?? 'proposal_candidate' ) ) );
		$next_action     = sanitize_key( (string) ( $proposal_input['local_next_action'] ?? ( $handoff['local_next_action'] ?? 'operator_review' ) ) );
		$title_hint      = sanitize_text_field( (string) ( $proposal_input['title_hint'] ?? ( $input['title_hint'] ?? '' ) ) );
		$content_hint    = sanitize_textarea_field( (string) ( $proposal_input['content_hint'] ?? ( $input['content_hint'] ?? '' ) ) );
		if ( '' === trim( $title_hint ) ) {
			$title_hint = __( 'Site Knowledge review draft requires a human title', 'npcink-workflow-toolbox' );
		}
		if ( '' === trim( $content_hint ) ) {
			$content_hint = __( 'Human draft content is required before this Site Knowledge review proposal can proceed.', 'npcink-workflow-toolbox' );
		}
		$agent_id        = sanitize_key( (string) ( $handoff['agent_id'] ?? ( $proposal_input['agent_id'] ?? 'site_knowledge_suggestion_agent' ) ) );
		$agent_version   = sanitize_text_field( (string) ( $handoff['agent_version'] ?? ( $proposal_input['agent_version'] ?? '' ) ) );
		$evidence_status = sanitize_key( (string) ( $handoff['evidence_gate_status'] ?? ( $proposal_input['evidence_gate_status'] ?? '' ) ) );
		$evidence_count  = absint( $handoff['evidence_count'] ?? ( $proposal_input['evidence_count'] ?? count( $evidence_refs ) ) );
		$action_id       = 'review_site_knowledge_gap';

		$preview = array(
			array(
				'action_id'            => $action_id,
				'workflow'             => $workflow,
				'intent'               => $intent,
				'cloud_output'         => $cloud_output,
				'local_next_action'    => $next_action,
				'evidence_count'       => $evidence_count,
				'evidence_gate_status' => $evidence_status,
				'proposal_ready'       => false,
			),
		);

		return array(
			'artifact_type'          => 'site_knowledge_review_plan',
			'composition_role'       => 'core_site_knowledge_review_plan',
			'version'                => 1,
			'source_recipe_id'       => 'site_knowledge_review_v1',
			'source_recipe_ref'      => 'workflow/site_knowledge_review',
			'source_recipe_provider' => 'npcink-toolbox',
			'recipe_execution'       => 'local_operator_orchestration',
			'write_posture'          => 'core_proposal_handoff',
			'direct_wordpress_write' => false,
			'batch_id'               => 'site_knowledge_review_' . substr( md5( $workflow . '|' . $intent . '|' . wp_json_encode( $evidence_refs ) ), 0, 12 ),
			'requires_approval'      => true,
			'dry_run'                => true,
			'commit_execution'       => false,
			'proposal_mode'          => 'single',
			'agent_id'               => $agent_id,
			'agent_version'          => $agent_version,
			'workflow'               => $workflow,
			'intent'                 => $intent,
			'cloud_output'           => $cloud_output,
			'local_next_action'      => $next_action,
			'evidence_gate_status'   => $evidence_status,
			'evidence_count'         => $evidence_count,
			'evidence_refs'          => $this->sanitize_payload( $evidence_refs ),
			'blocked_outputs'        => $this->sanitize_payload( $blocked_outputs ),
			'proposal_input'         => $this->sanitize_payload( $proposal_input ),
			'preview'                => $preview,
			'manual_review'          => array(
				array(
					'code'   => 'human_draft_required',
					'fields' => array( 'title', 'content' ),
					'reason' => __( 'Site Knowledge evidence can justify a review proposal, but a human must decide the final draft title and content before commit preflight.', 'npcink-workflow-toolbox' ),
				),
			),
			'write_actions'          => array(
				array(
					'action_id'         => $action_id,
					'target_ability_id' => 'npcink-abilities-toolkit/create-draft',
					'recipe_step'       => 'host_governed_review_draft',
					'input'             => array(
						'title'           => $title_hint,
						'content'         => $content_hint,
						'status'          => 'draft',
						'meta'            => array(
							'site_knowledge_evidence_refs' => $this->sanitize_payload( $evidence_refs ),
							'site_knowledge_workflow'      => $workflow,
							'site_knowledge_intent'        => $intent,
						),
						'dry_run'         => true,
						'commit'          => false,
						'idempotency_key' => 'site-knowledge-review-' . substr( md5( $workflow . '|' . $intent . '|' . wp_json_encode( $evidence_refs ) ), 0, 12 ),
					),
					'risk'              => 'medium',
					'requires_approval' => true,
					'commit_execution'  => false,
					'proposal_ready'    => false,
					'requires_input'    => array( 'title', 'content' ),
					'reason'            => __( 'Create a blocked Core review proposal from evidence-backed Site Knowledge suggestions; human draft input is required before execution can be considered.', 'npcink-workflow-toolbox' ),
				),
			),
			'handoff'                => array(
				'plan_ability_id'        => 'npcink-toolbox/build-site-knowledge-review-plan',
				'recipe_id'              => 'site_knowledge_review_v1',
				'recipe_ref'             => 'workflow/site_knowledge_review',
				'core_route'             => '/wp-json/npcink-governance-core/v1/proposals/from-plan',
				'final_write_path'       => 'core_proposal_required',
				'direct_wordpress_write' => false,
				'proposal_ready'         => false,
			),
		);
	}

	public function build_nightly_inspection_review_plan( array $input ) {
		$selected_items = is_array( $input['selected_items'] ?? null ) ? array_values( $input['selected_items'] ) : array();
		if ( empty( $selected_items ) ) {
			return new WP_Error(
				'npcink_toolbox_nightly_inspection_review_items_required',
				__( 'Select at least one scheduled review item before creating a Core proposal.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$selected_items = array_slice( $selected_items, 0, 5 );
		$cloud_run_id   = sanitize_text_field( (string) ( $input['cloud_run_id'] ?? ( $input['run_id'] ?? '' ) ) );
		$agent_version  = sanitize_text_field( (string) ( $input['agent_version'] ?? 'nightly_site_inspection_cloud_runtime.v1' ) );
		$core_intake_package = is_array( $input['core_intake_package'] ?? null ) ? $this->sanitize_payload( $input['core_intake_package'] ) : array();
		$core_intake_summary = array(
			'contract_version'                 => sanitize_text_field( (string) ( $core_intake_package['contract_version'] ?? '' ) ),
			'target_route'                     => sanitize_text_field( (string) ( $core_intake_package['target_route'] ?? '' ) ),
			'target_plan_ability_id'           => sanitize_text_field( (string) ( $core_intake_package['target_plan_ability_id'] ?? '' ) ),
			'target_plan_contract'             => sanitize_text_field( (string) ( $core_intake_package['target_plan_contract'] ?? '' ) ),
			'core_review_plan_idempotency_key' => sanitize_text_field( (string) ( $core_intake_package['core_review_plan_idempotency_key'] ?? '' ) ),
			'proposal_state_owner'             => sanitize_key( (string) ( $core_intake_package['proposal_state_owner'] ?? '' ) ),
			'approval_truth'                   => sanitize_key( (string) ( $core_intake_package['approval_truth'] ?? '' ) ),
			'final_write_truth'                => sanitize_key( (string) ( $core_intake_package['final_write_truth'] ?? '' ) ),
			'receipt_expectation'              => is_array( $core_intake_package['receipt_expectation'] ?? null ) ? $this->sanitize_payload( $core_intake_package['receipt_expectation'] ) : array(),
			'direct_wordpress_write'           => false,
			'proposal_created'                 => false,
		);
		$evidence_refs  = array();
		$issue_types    = array();
		$max_score      = null;

		foreach ( $selected_items as $index => $raw_item ) {
			$item = is_array( $raw_item ) ? $raw_item : array();
			$action_id = sanitize_text_field( (string) ( $item['action_id'] ?? '' ) );
			if ( '' === $action_id ) {
				$action_id = 'morning_brief_review_' . ( $index + 1 );
			}
			$object_type  = sanitize_key( (string) ( $item['object_type'] ?? 'content' ) );
			$object_id    = sanitize_text_field( (string) ( $item['object_id'] ?? '' ) );
			$reason_codes = $this->sanitize_string_list( $item['reason_codes'] ?? array() );
			$score        = is_numeric( $item['score'] ?? null ) ? (float) $item['score'] : null;
			if ( null !== $score ) {
				$max_score = null === $max_score ? $score : max( $max_score, $score );
			}
			$issue_types = array_merge( $issue_types, $reason_codes );

			$evidence_refs[] = array(
				'action_id'               => $action_id,
				'title'                   => $this->bounded_text( sanitize_text_field( (string) ( $item['title'] ?? __( 'Scheduled review item', 'npcink-workflow-toolbox' ) ) ), 160 ),
				'object_type'             => $object_type,
				'object_id'               => $object_id,
				'post_id'                 => absint( $item['post_id'] ?? ( 'post' === $object_type ? $object_id : 0 ) ),
				'score'                   => null === $score ? null : $score,
				'severity'                => sanitize_key( (string) ( $item['severity'] ?? '' ) ),
				'reason_codes'            => $reason_codes,
				'evidence_summary'        => $this->bounded_text( sanitize_textarea_field( (string) ( $item['evidence_summary'] ?? '' ) ), 500 ),
				'recommended_next_action' => sanitize_key( (string) ( $item['recommended_next_action'] ?? 'operator_review' ) ),
				'suggested_use'           => 'morning_brief_review_evidence',
			);
		}

		$run_basis       = '' !== $cloud_run_id ? $cloud_run_id : wp_json_encode( $evidence_refs );
		$idempotency_key = 'nightly-inspection-review-' . substr( md5( (string) $run_basis ), 0, 16 );
		$issue_types     = array_values( array_unique( array_filter( $issue_types ) ) );
		if ( empty( $issue_types ) ) {
			$issue_types = array( 'nightly_site_inspection' );
		}

		return array(
			'artifact_type'          => 'nightly_site_inspection_review_plan',
			'contract_version'       => 'nightly_site_inspection_core_review_plan.v1',
			'version'                => 1,
			'batch_id'               => '' !== $cloud_run_id ? $cloud_run_id : $idempotency_key,
			'cloud_run_id'           => $cloud_run_id,
			'requires_approval'      => true,
			'dry_run'                => true,
			'commit_execution'       => false,
			'proposal_mode'          => 'single',
			'write_posture'          => 'core_proposal_handoff',
			'direct_wordpress_write' => false,
			'runtime_owner'          => 'npcink-local-automation-runtime',
			'agent_id'               => 'nightly_site_inspection_cloud_runtime',
			'agent_version'          => $agent_version,
			'workflow'               => 'nightly_site_inspection',
			'intent'                 => 'morning_review_preparation',
			'cloud_output'           => 'proposal_candidate',
			'local_next_action'      => 'operator_review',
			'evidence_gate_status'   => 'passed',
			'evidence_refs'          => $this->sanitize_payload( $evidence_refs ),
			'source_context'         => array(
				'cloud_intake_package_available' => ! empty( array_filter( $core_intake_summary ) ),
				'cloud_core_intake_package'      => $this->sanitize_payload( $core_intake_summary ),
				'direct_wordpress_write'         => false,
				'proposal_created'               => false,
				'approval_truth'                 => 'wordpress_local',
				'final_write_truth'              => 'wordpress_local',
			),
			'blocked_outputs'        => array(
				'direct_wordpress_write',
				'article_body',
				'article_write_plan',
				'final_seo_copy',
				'automatic_publish',
			),
			'issue_types'            => $this->sanitize_payload( $issue_types ),
			'risk'                   => array(
				'level'  => null !== $max_score && $max_score >= 80 ? 'high' : 'medium',
				'reason' => 'operator_review_required',
			),
			'preview'                => array(
				array(
					'action_id'          => 'review_nightly_site_inspection',
					'proposal_ready'     => false,
					'evidence_ref_count' => count( $evidence_refs ),
				),
			),
			'write_actions'          => array(
				array(
					'action_id'         => 'review_nightly_site_inspection',
					'target_ability_id' => 'npcink-abilities-toolkit/create-draft',
					'recipe_step'       => 'host_governed_review_draft',
					'input'             => array(
						'title'           => '',
						'content'         => '',
						'status'          => 'draft',
						'meta'            => array(
							'nightly_inspection_cloud_run_id' => $cloud_run_id,
							'nightly_inspection_evidence_refs' => $this->sanitize_payload( $evidence_refs ),
							'nightly_inspection_core_intake_package' => $this->sanitize_payload( $core_intake_summary ),
						),
						'dry_run'         => true,
						'commit'          => false,
						'idempotency_key' => $idempotency_key,
					),
					'risk'              => null !== $max_score && $max_score >= 80 ? 'high' : 'medium',
					'requires_approval' => true,
					'commit_execution'  => false,
					'proposal_ready'    => false,
					'requires_input'    => array( 'title', 'content' ),
					'reason'            => __( 'Scheduled review found reviewable content quality signals. Human draft title and content are required before execution can be considered.', 'npcink-workflow-toolbox' ),
				),
			),
			'handoff'                => array(
				'plan_ability_id'        => 'npcink-toolbox/build-nightly-inspection-review-plan',
				'recipe_id'              => 'nightly_inspection_review_v1',
				'recipe_ref'             => 'workflow/nightly_site_inspection_review',
				'core_route'             => '/wp-json/npcink-governance-core/v1/proposals/from-plan',
				'core_intake_package'    => $this->sanitize_payload( $core_intake_summary ),
				'final_write_path'       => 'core_proposal_required',
				'direct_wordpress_write' => false,
				'proposal_ready'         => false,
			),
		);
	}

	public function build_content_metadata_apply_plan( array $input ) {
		$ability_id = 'npcink-abilities-toolkit/build-content-metadata-apply-plan';
		if ( ! function_exists( 'npcink_abilities_toolkit_get_registered' ) ) {
			return new WP_Error(
				'npcink_toolbox_content_metadata_toolkit_unavailable',
				__( 'Npcink Abilities Toolkit is required to build a content metadata apply plan.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$registered = npcink_abilities_toolkit_get_registered();
		$definition = is_array( $registered[ $ability_id ] ?? null ) ? $registered[ $ability_id ] : array();
		$callback   = $definition['execute_callback'] ?? null;
		if ( ! is_callable( $callback ) ) {
			return new WP_Error(
				'npcink_toolbox_content_metadata_toolkit_plan_unavailable',
				__( 'The Toolkit content metadata apply-plan ability is not currently callable.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$result = call_user_func( $callback, $input );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! is_array( $result ) ) {
			return new WP_Error(
				'npcink_toolbox_content_metadata_toolkit_plan_invalid',
				__( 'The Toolkit content metadata apply-plan ability returned an invalid response.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}

		$data = is_array( $result['data'] ?? null ) ? $result['data'] : $result;
		if ( empty( $data['artifact_type'] ) || 'content_metadata_apply_plan' !== (string) $data['artifact_type'] ) {
			return new WP_Error(
				'npcink_toolbox_content_metadata_toolkit_plan_invalid_artifact',
				__( 'The Toolkit content metadata apply-plan ability did not return the expected artifact.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}

		return $this->normalize_content_metadata_apply_plan_contract( $data );
	}

	public function build_media_alt_caption_review_plan( array $input ): array {
		$selected_items = is_array( $input['selected_items'] ?? null ) ? $input['selected_items'] : array();
		$actions        = array();
		$blocked_actions = array();

		foreach ( $selected_items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$attachment_id = absint( $item['attachment_id'] ?? 0 );
			if ( 0 >= $attachment_id ) {
				continue;
			}

			$alt_candidates = is_array( $item['alt_candidates'] ?? null ) ? $item['alt_candidates'] : array();
			$raw_alt        = array_key_exists( 'accepted_alt', $item ) ? (string) $item['accepted_alt'] : (string) ( $alt_candidates[0] ?? '' );
			$proposed_alt   = $this->media_alt->media_alt_caption_clean_candidate( $raw_alt );
			$proposed_caption = $this->media_alt->media_alt_caption_clean_candidate( (string) ( $item['accepted_caption'] ?? '' ) );
			$alt_rejection = '' !== $proposed_alt ? $this->media_alt->media_alt_caption_candidate_rejection_reason( $proposed_alt, $item, 'alt' ) : '';
			if ( '' !== $alt_rejection ) {
				$blocked_actions[] = array(
					'action_id'            => 'media-alt-caption:' . $attachment_id . ':alt',
					'attachment_id'        => $attachment_id,
					'rejected_field'       => 'alt',
					'blocked_reason'       => $alt_rejection,
					'operator_next_action' => 'revise_alt_before_core_handoff',
				);
				$proposed_alt = '';
			}
			$caption_rejection = '' !== $proposed_caption ? $this->media_alt->media_alt_caption_candidate_rejection_reason( $proposed_caption, $item, 'caption' ) : '';
			if ( '' !== $caption_rejection ) {
				$blocked_actions[] = array(
					'action_id'            => 'media-alt-caption:' . $attachment_id . ':caption',
					'attachment_id'        => $attachment_id,
					'rejected_field'       => 'caption',
					'blocked_reason'       => $caption_rejection,
					'operator_next_action' => 'revise_caption_before_core_handoff',
				);
				$proposed_caption = '';
			}
			if ( '' !== $proposed_caption ) {
				$blocked_actions[] = array(
					'action_id'            => 'media-alt-caption:' . $attachment_id . ':caption',
					'attachment_id'        => $attachment_id,
					'rejected_field'       => 'caption',
					'blocked_reason'       => 'caption_requires_manual_review',
					'operator_next_action' => 'submit_alt_only_or_review_caption_manually',
				);
				$proposed_caption = '';
			}
			if ( '' === $proposed_alt ) {
				continue;
			}

			$title             = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
			$filename          = sanitize_text_field( (string) ( $item['filename'] ?? '' ) );
			$current_alt       = sanitize_text_field( (string) ( $item['current_alt'] ?? ( $item['alt'] ?? '' ) ) );
			$current_caption   = sanitize_textarea_field( (string) ( $item['current_caption'] ?? ( $item['caption'] ?? '' ) ) );
			$current_alt_status = sanitize_key( (string) ( $item['current_alt_status'] ?? '' ) );
			$candidate_basis   = $this->sanitize_string_list( $item['candidate_basis'] ?? array() );
			$candidate_flags   = $this->sanitize_string_list( $item['candidate_quality_flags'] ?? array() );
			$candidate_fact_types    = $this->sanitize_string_list( $item['candidate_fact_types'] ?? array() );
			$candidate_confidence    = sanitize_key( (string) ( $item['candidate_confidence'] ?? '' ) );
			$candidate_review_status = sanitize_key( (string) ( $item['candidate_review_status'] ?? '' ) );
			$needs_context_confirmation = ! empty( $item['needs_context_confirmation'] )
				|| in_array( 'needs_context_confirmation', $candidate_flags, true )
				|| 'needs_context_confirmation' === $candidate_review_status;
			$context_confirmed = $this->is_truthy( $item['context_confirmed'] ?? false );
			if ( $needs_context_confirmation && $this->media_alt->media_alt_caption_candidate_needs_context_confirmation( $proposed_alt ) && ! $context_confirmed ) {
				$blocked_actions[] = array(
					'action_id'                  => 'media-alt-caption:' . $attachment_id . ':alt',
					'attachment_id'              => $attachment_id,
					'rejected_field'             => 'alt',
					'blocked_reason'             => 'context_confirmation_required',
					'candidate_review_status'    => 'needs_context_confirmation',
					'needs_context_confirmation' => true,
					'operator_next_action'       => 'confirm_context_terms_or_edit_alt',
				);
				continue;
			}
			$proposal_input    = array(
				'attachment_id'   => $attachment_id,
				'alt'             => $proposed_alt,
				'dry_run'         => true,
				'commit'          => false,
				'idempotency_key' => 'toolbox-media-alt-' . $attachment_id . '-' . substr( md5( $proposed_alt ), 0, 12 ),
			);
			$proposal_preview  = array(
				'artifact_type'                 => 'media_alt_caption_review_item',
				'contract_version'             => 'media_alt_caption_review_item.v1',
				'review_set_contract'          => 'media_alt_caption_review_set.v1',
				'source'                       => array(
					'type'    => 'toolbox_media_alt_caption_review',
					'surface' => 'npcink_toolbox_batch_alt',
				),
				'attachment_id'                => $attachment_id,
				'title'                        => $title,
				'filename'                     => $filename,
				'current_alt_status'           => $current_alt_status,
				'current_alt'                  => $current_alt,
				'proposed_alt'                 => $proposed_alt,
				'candidate_basis'              => $candidate_basis,
				'candidate_quality_flags'      => $candidate_flags,
				'candidate_fact_types'         => $candidate_fact_types,
				'candidate_confidence'         => $candidate_confidence,
				'candidate_review_status'      => $needs_context_confirmation && ! $context_confirmed ? 'needs_context_confirmation' : $candidate_review_status,
				'needs_context_confirmation'   => $needs_context_confirmation,
				'context_confirmed'            => $context_confirmed,
				'operator_reviewed'            => true,
				'operator_visual_review_confirmed' => true,
				'visual_confirmation_required' => true,
				'direct_wordpress_write'       => false,
			);

			$actions[] = array(
				'action_id'                   => 'media-alt-caption:' . $attachment_id,
				'attachment_id'               => $attachment_id,
				'title'                       => $title,
				'filename'                    => $filename,
				'current_alt_status'          => $current_alt_status,
				'current_caption_status'      => sanitize_key( (string) ( $item['current_caption_status'] ?? '' ) ),
				'current_alt'                 => $current_alt,
				'current_caption'             => $current_caption,
				'thumbnail_url'               => esc_url_raw( (string) ( $item['thumbnail_url'] ?? '' ) ),
				'accepted_alt'                => $proposed_alt,
				'accepted_caption'            => '',
				'needs_human_visual_check'    => true,
				'visual_confirmation_required' => true,
				'candidate_basis'             => $candidate_basis,
				'candidate_quality_flags'     => $candidate_flags,
				'candidate_fact_types'        => $candidate_fact_types,
				'candidate_confidence'        => $candidate_confidence,
				'candidate_review_status'     => $needs_context_confirmation && ! $context_confirmed ? 'needs_context_confirmation' : $candidate_review_status,
				'needs_context_confirmation'  => $needs_context_confirmation,
				'context_confirmed'           => $context_confirmed,
				'target_ability_id'           => 'npcink-abilities-toolkit/update-media-details',
					'target_write_path'           => 'core_proposal_required',
					'auto_execution_supported'    => false,
					'submission_status'           => 'preview_only_not_submitted',
					'target_contract_status'      => 'future_or_unavailable',
					'proposal_created'            => false,
					'execution_created'           => false,
					'not_submittable'             => true,
					'future_contract_preview'     => array(
						'ability_id'              => 'npcink-abilities-toolkit/update-media-details',
						'submission_status'       => 'preview_only_not_submitted',
						'target_contract_status'  => 'future_or_unavailable',
						'not_submittable'         => true,
						'proposal_created'        => false,
						'execution_created'       => false,
						'direct_wordpress_write'  => false,
						'title'                   => sprintf( 'Preview ALT update for attachment #%d', $attachment_id ),
						'summary'                 => 'Preview one reviewed ALT text suggestion for a media-library image. No proposal is created from this Toolbox preview.',
						'input'                   => $proposal_input,
						'preview'                 => $proposal_preview,
					),
				'direct_wordpress_write'      => false,
			);
		}

		$review_set = is_array( $input['review_set'] ?? null ) ? $this->sanitize_payload( $input['review_set'] ) : array();
		return array(
			'artifact_type'          => 'media_alt_caption_core_handoff_plan',
			'contract_version'      => 'media_alt_caption_core_handoff_plan.v1',
			'composition_role'       => 'core_handoff_draft',
			'write_posture'          => 'suggestion_only',
				'final_write_path'       => 'core_proposal_required',
				'direct_wordpress_write' => false,
				'proposal_created'       => false,
				'core_submission'        => 'preview_only_not_submitted',
				'workflow_runtime'       => false,
			'queue_created'          => false,
			'selected_count'         => count( $actions ),
			'selected_actions'       => $actions,
			'blocked_actions'        => $blocked_actions,
			'review_set_summary'     => array(
				'contract_version' => sanitize_text_field( (string) ( $review_set['contract_version'] ?? '' ) ),
				'source_policy'    => sanitize_key( (string) ( $review_set['source_policy'] ?? '' ) ),
				'media_scope'      => sanitize_key( (string) ( $review_set['media_scope'] ?? '' ) ),
				'selected_count'   => absint( $review_set['selected_count'] ?? count( $actions ) ),
			),
			'core_auto_approval_policy' => array(
				'request_supported'          => false,
				'toolbox_direct_apply'       => false,
					'approval_owner'             => 'npcink-governance-core',
					'execution_owner'            => 'wordpress_abilities',
					'safe_action_candidate'      => 'fill_missing_or_weak_alt_only',
					'current_stage'              => 'future_policy_only',
					'required_policy_checks'     => array(
					'operator_enabled_core_policy',
					'missing_or_weak_alt_only',
					'candidate_quality_gate_passed',
					'context_terms_confirmed_or_removed',
					'no_runtime_provenance_text',
					'no_source_attribution_text',
					'operator_visual_confirmation',
					'bounded_batch_size',
					'old_value_audit_and_rollback_evidence',
				),
			),
			'handoff'                => array(
				'plan_route'             => '/wp-json/npcink-toolbox/v1/flows/media-alt-caption-review-plan',
				'plan_surface'           => 'toolbox_rest_route',
				'target_ability_id'      => 'npcink-abilities-toolkit/update-media-details',
				'recipe_id'              => 'media_alt_caption_review_v1',
				'core_route'             => '/wp-json/npcink-governance-core/v1/proposals/from-plan',
					'proposal_ready'         => false,
					'preview_available'      => 0 < count( $actions ),
				'core_submission'        => 'preview_only_not_submitted',
				'final_write_path'       => 'core_proposal_required',
				'direct_wordpress_write' => false,
			),
			'operator_next_action'   => 0 < count( $actions )
					? 'review_handoff_preview_before_future_core_submission'
				: 'select_reviewed_media_alt_caption_items',
			'guardrails'             => array(
				'no_media_metadata_write_in_toolbox',
				'no_toolbox_auto_approval',
					'no_adapter_or_core_submission_from_preview',
					'core_policy_owns_auto_approval',
					'alt_only_auto_execution_candidate_future_only',
				'human_visual_confirmation_required',
				'core_approval_required_before_final_write',
			),
		);
	}

	/**
	 * Normalizes delegated Toolkit content metadata plans for Core from-plan intake.
	 *
	 * @param array<string,mixed> $data Toolkit plan data.
	 * @return array<string,mixed>
	 */
	private function normalize_content_metadata_apply_plan_contract( array $data ): array {
		$authorization = is_array( $data['authorization'] ?? null ) ? $data['authorization'] : array();
		$classification = sanitize_key( (string) ( $authorization['classification'] ?? Operation_Classifier::CORE_PROPOSAL_REQUIRED ) );
		if ( '' === $classification ) {
			$classification = Operation_Classifier::CORE_PROPOSAL_REQUIRED;
		}

		$reasons = $this->sanitize_string_list( $authorization['reasons'] ?? array() );
		if ( empty( $reasons ) ) {
			$reasons = array( 'excerpt_or_taxonomy_mutation', 'core_proposal_required' );
		}

		$required_evidence = $this->sanitize_string_list( $authorization['required_evidence'] ?? array() );
		if ( empty( $required_evidence ) ) {
			$required_evidence = array(
				'target_ability_id',
				'target_input_or_safe_summary',
				'before_after_or_dry_run_evidence',
				'reason_risk_required_scopes',
				'caller_source_metadata',
				'batch_item_details_when_applicable',
			);
		}

		$decision_version = sanitize_text_field(
			(string) (
				$authorization['decision_version']
				?? ( $authorization['policy_version'] ?? 'operation-classification-v1' )
			)
		);
		if ( '' === $decision_version ) {
			$decision_version = 'operation-classification-v1';
		}

		$decision_envelope = is_array( $authorization['decision_envelope'] ?? null ) ? $authorization['decision_envelope'] : array();
		$decision_envelope = array_merge(
			array(
				'decision_version'  => $decision_version,
				'classification'    => $classification,
				'reasons'           => $reasons,
				'required_evidence' => $required_evidence,
			),
			$decision_envelope
		);
		$decision_envelope['decision_version']       = $decision_version;
		$decision_envelope['classification']         = $classification;
		$decision_envelope['reasons']                = $reasons;
		$decision_envelope['required_evidence']      = $required_evidence;
		$decision_envelope['final_write_path']       = 'core_proposal_required';
		$decision_envelope['direct_wordpress_write'] = false;

		$authorization['classification']    = $classification;
		$authorization['requires_proposal'] = true;
		$authorization['requires_approval'] = true;
		$authorization['policy_version']    = $decision_version;
		$authorization['decision_version']  = $decision_version;
		$authorization['reasons']           = $reasons;
		$authorization['required_evidence'] = $required_evidence;
		$authorization['decision_envelope'] = $this->sanitize_payload( $decision_envelope );
		$data['authorization']             = $authorization;
		$data['classification_evidence']   = $authorization;
		$data['direct_wordpress_write']     = false;
		$data['requires_approval']          = true;
		$data['dry_run']                    = true;
		$data['commit_execution']           = false;

		return $data;
	}

	public function build_content_discoverability_brief( array $input ) {
		$source = $this->resolve_discoverability_source( $input );
		if ( is_wp_error( $source ) ) {
			return $source;
		}

		$context           = $this->settings->get_content_context_for_ability();
		$validation        = $this->settings->validate_content_context_for_ability();
		$allowed_fields    = $this->sanitize_string_list( $context['proposal_allowed_fields'] ?? array() );
		$exceptions        = is_array( $context['exceptions'] ?? null ) ? $this->sanitize_payload( $context['exceptions'] ) : array();
		$proposal_template = array();
		$candidates        = array();
		$include_external_search = ! array_key_exists( 'include_external_search', $input ) || ! empty( $input['include_external_search'] );
		$external_search_intent  = sanitize_key( (string) ( $input['external_search_intent'] ?? 'writing_context' ) );
		if ( ! in_array( $external_search_intent, array( 'article_background', 'fact_check', 'news', 'writing_context', 'competitor_research', 'pricing_snapshot', 'product_comparison', 'source_discovery', 'external_links' ), true ) ) {
			$external_search_intent = 'writing_context';
		}
		$external_research = $include_external_search
			? $this->web_search->cloud_web_search_for_content( sanitize_text_field( (string) ( $source['topic'] ?? $source['title'] ?? '' ) ), $external_search_intent, 3 )
			: $this->web_search->cloud_web_search_notice();
		$cloud_evidence   = $this->web_search->cloud_web_search_evidence( $external_research );
		$sections          = array(
			'seo' => array(
				'rules'              => sanitize_textarea_field( (string) ( $context['rules']['seo'] ?? '' ) ),
				'allowed_fields'     => array(),
				'proposal_template'  => array(),
				'candidate_suggestions' => array(),
			),
			'aeo' => array(
				'rules'              => sanitize_textarea_field( (string) ( $context['rules']['aeo'] ?? '' ) ),
				'allow_faq_generation' => ! empty( $context['rules']['allow_faq_generation'] ),
				'allow_answer_summary' => ! empty( $context['rules']['allow_aeo_summary'] ),
				'allowed_fields'     => array(),
				'proposal_template'  => array(),
				'candidate_suggestions' => array(),
			),
			'geo' => array(
				'rules'              => sanitize_textarea_field( (string) ( $context['rules']['geo'] ?? '' ) ),
				'allow_geo_summary'  => ! empty( $context['rules']['allow_geo_summary'] ),
				'allow_structured_data_suggestions' => ! empty( $context['rules']['allow_structured_data_suggestions'] ),
				'allowed_fields'     => array(),
				'proposal_template'  => array(),
				'candidate_suggestions' => array(),
			),
		);

		foreach ( $allowed_fields as $field ) {
			$proposal_template[ $field ] = array(
				'instruction' => $this->content_discoverability_field_instruction( $field ),
				'value'       => null,
			);
			$group = $this->content_discoverability_field_group( $field );
			$sections[ $group ]['allowed_fields'][] = $field;
			$sections[ $group ]['proposal_template'][ $field ] = $proposal_template[ $field ];

			$candidate = $this->content_discoverability_candidate( $field, $source, $context );
			if ( null !== $candidate ) {
				$candidates[ $field ] = $candidate;
				$sections[ $group ]['candidate_suggestions'][ $field ] = $candidate;
			}
		}

		return array(
			'artifact_type'          => 'content_discoverability_brief',
			'composition_role'       => 'seo_aeo_geo_brief',
			'version'                => 1,
			'primary_contract'       => true,
			'write_posture'          => 'suggestion_only',
			'final_write_path'       => 'core_proposal_required',
			'direct_wordpress_write' => false,
			'context_validation'     => $validation,
			'content_context'        => $context,
			'exceptions'             => $exceptions,
			'special_cases'          => $exceptions,
			'source'                 => $source,
			'external_research'      => $external_research,
			'cloud_evidence'         => $cloud_evidence,
			'seo'                    => $sections['seo'],
			'aeo'                    => $sections['aeo'],
			'geo'                    => $sections['geo'],
			'ai_instructions'        => array(
				'Use the content_context as the site-level rule source.',
				'Use only facts present in the supplied source, public site context, or cited evidence.',
				'Use external_research only as suggestion evidence and preserve source URLs for operator review.',
				'Do not invent customer cases, ranking guarantees, source citations, or unavailable product features.',
				'Return suggestions only for proposal_allowed_fields.',
				'Respect forbidden claims and preserve the requested brand voice.',
				'Apply exceptions and special_cases before generating FAQ, HowTo, schema, or confident product claims.',
				'Final WordPress writes must go through Core proposal approval.',
			),
			'proposal_allowed_fields' => $allowed_fields,
			'proposal_template'      => $proposal_template,
			'candidate_suggestions'  => $candidates,
			'handoff'                => array(
				'brief_ability_id'       => 'npcink-toolbox/build-content-discoverability-brief',
				'context_ability_id'     => 'npcink-toolbox/get-content-discoverability-context',
				'validation_ability_id'  => 'npcink-toolbox/validate-content-discoverability-context',
				'final_writes'           => 'core_proposal_required',
				'direct_wordpress_write' => false,
			),
		);
	}

	public function build_ai_article_writing_pack( array $input ) {
		$brief = $this->build_content_discoverability_brief( $input );
		if ( is_wp_error( $brief ) ) {
			return $brief;
		}

		$brief              = is_array( $brief ) ? $brief : array();
		$source             = is_array( $brief['source'] ?? null ) ? $brief['source'] : array();
		$context            = is_array( $brief['content_context'] ?? null ) ? $brief['content_context'] : array();
		$validation         = is_array( $brief['context_validation'] ?? null ) ? $brief['context_validation'] : array();
		$rules              = is_array( $context['rules'] ?? null ) ? $context['rules'] : array();
		$keywords           = is_array( $context['keywords'] ?? null ) ? $context['keywords'] : array();
		$claims             = is_array( $context['claims'] ?? null ) ? $context['claims'] : array();
		$topic              = sanitize_text_field( (string) ( $source['topic'] ?? ( $input['topic'] ?? '' ) ) );
		$title              = sanitize_text_field( (string) ( $source['title'] ?? ( $input['title'] ?? $topic ) ) );
		$language           = sanitize_text_field( (string) ( $input['language'] ?? 'zh-CN' ) );
		$article_type       = sanitize_key( (string) ( $input['article_type'] ?? 'practical_guide' ) );
		$target_word_count  = absint( $input['target_word_count'] ?? 1200 );
		$target_word_count  = max( 500, min( 5000, $target_word_count ) );
		$context_status     = sanitize_key( (string) ( $validation['status'] ?? 'needs_attention' ) );
		$ready_for_writing  = in_array( $context_status, array( 'ready', 'ready_with_warnings' ), true );
		$proposal_fields    = $this->sanitize_string_list( $brief['proposal_allowed_fields'] ?? array() );
		$primary_keywords   = $this->sanitize_string_list( $keywords['primary'] ?? array() );
		$long_tail_keywords = $this->sanitize_string_list( $keywords['long_tail'] ?? array() );
		$entity_keywords    = $this->sanitize_string_list( $keywords['entities'] ?? array() );
		$forbidden_claims   = $this->sanitize_string_list( $claims['forbidden'] ?? array() );
		$external_research  = is_array( $brief['external_research'] ?? null ) ? $brief['external_research'] : array();
		$cloud_evidence     = is_array( $brief['cloud_evidence'] ?? null ) ? $brief['cloud_evidence'] : $this->web_search->cloud_web_search_evidence( $external_research );

		return array(
			'artifact_type'          => 'ai_article_writing_pack',
			'composition_role'       => 'ai_article_writing_pack',
			'version'                => 1,
			'primary_contract'       => false,
			'contract_role'          => 'openclaw_natural_language_fallback',
			'write_posture'          => 'suggestion_only',
			'final_write_path'       => 'core_proposal_required',
			'direct_wordpress_write' => false,
			'provider_execution'     => 'none',
			'ready_for_writing'      => $ready_for_writing,
			'context_status'         => $context_status,
			'source'                 => $source,
			'topic'                  => $topic,
			'title'                  => $title,
			'language'               => $language,
			'article_type'           => $article_type,
			'target_word_count'      => $target_word_count,
			'content_context'        => $context,
			'context_validation'     => $validation,
			'discoverability_brief'  => $brief,
			'external_research'      => $external_research,
			'cloud_evidence'         => $cloud_evidence,
			'exceptions'             => is_array( $brief['exceptions'] ?? null ) ? $brief['exceptions'] : array(),
			'special_cases'          => is_array( $brief['special_cases'] ?? null ) ? $brief['special_cases'] : array(),
			'article_prompt_pack'    => array(
				'user_intent'      => sanitize_textarea_field( (string) ( $input['user_intent'] ?? 'Write one article from the supplied topic and site rules.' ) ),
				'writing_goal'     => sprintf(
					'Write one %1$s article in %2$s about: %3$s.',
					$article_type,
					$language,
					'' !== $topic ? $topic : $title
				),
				'style_rules'      => array_filter(
					array(
						(string) ( $context['brand_voice'] ?? '' ),
						(string) ( $rules['seo'] ?? '' ),
						(string) ( $rules['aeo'] ?? '' ),
						(string) ( $rules['geo'] ?? '' ),
					)
				),
				'keyword_targets'  => array(
					'primary'   => $primary_keywords,
					'long_tail' => $long_tail_keywords,
					'entities'  => $entity_keywords,
				),
				'proposal_fields'  => $proposal_fields,
				'forbidden_claims' => $forbidden_claims,
			),
			'suggested_article_structure' => $this->article_writing_pack_structure( $rules ),
			'ai_instructions'      => array(
				'Use this pack as the local site-context source before writing.',
				'If ready_for_writing is false, stop and ask the operator to complete Toolbox Content Context.',
				'Write from the supplied source and topic; do not invent product facts, customer cases, rankings, citations, or unavailable features.',
				'Respect forbidden claims, brand voice, SEO rules, AEO rules, and GEO rules.',
				'Return article draft text and proposal-ready SEO/AEO/GEO suggestions only.',
				'Do not write WordPress data. Final WordPress writes must go through Core proposal approval and commit preflight.',
			),
			'handoff'              => array(
				'pack_ability_id'       => 'npcink-toolbox/build-ai-article-writing-pack',
				'brief_ability_id'      => 'npcink-toolbox/build-content-discoverability-brief',
				'write_plan_ability_id' => 'npcink-toolbox/build-article-write-plan',
				'final_writes'          => 'core_proposal_required',
				'direct_wordpress_write' => false,
				'next_steps'            => array(
					'Use the pack to draft one article and SEO/AEO/GEO suggestions.',
					'After human review, convert the reviewed draft with build-article-write-plan.',
					'Send write-like outcomes through Core proposal, approval, and commit preflight.',
				),
			),
		);
	}

	public function build_media_brief( string $post_context, array $options = array() ) {
		$decoded_context = json_decode( $post_context, true );
		if ( ! is_array( $decoded_context ) ) {
			$decoded_context = array();
		}
		$refresh_variant = sanitize_text_field( (string) ( $options['refresh_variant'] ?? '' ) );
		$image_mode      = sanitize_key( (string) ( $options['image_mode'] ?? 'featured_image' ) );
		if ( ! in_array( $image_mode, array( 'featured_image', 'paragraph_image', 'inline_image', 'setting_image' ), true ) ) {
			$image_mode = 'featured_image';
		}
		$visual_context = array(
			'post_id'         => absint( $decoded_context['id'] ?? 0 ),
			'title'           => sanitize_text_field( (string) ( $decoded_context['title'] ?? '' ) ),
			'excerpt'         => sanitize_textarea_field( (string) ( $decoded_context['excerpt'] ?? '' ) ),
			'content_summary' => sanitize_textarea_field( (string) ( $decoded_context['content'] ?? '' ) ),
			'image_use'       => $image_mode,
			'refresh_variant' => $refresh_variant,
			'query_intent'    => array(
				'rewrite_abstract_terms'       => true,
				'prefer_concrete_visual_scene' => true,
				'return_alternate_queries'     => true,
				'direction_count'              => 4,
				'prompt_candidate_count'       => 4,
			),
		);
		return $this->image_candidates(
			$this->post_context_to_image_query( $post_context ),
			array(
				'per_page'                     => 8,
				'runtime_data_classification' => 'pii',
				'image_mode'                   => $image_mode,
				'refresh_variant'              => $refresh_variant,
				'visual_context'               => $visual_context,
			)
		);
	}

	public function build_media_derivative_handoff( array $input ) {
		$attachment_id = absint( $input['attachment_id'] ?? 0 );
		if ( $attachment_id <= 0 ) {
			return new WP_Error(
				'npcink_toolbox_missing_attachment_id',
				__( 'An attachment_id is required to build a media derivative handoff.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$overrides = array( 'attachment_id' => $attachment_id );
		if ( '' !== trim( (string) ( $input['target_format'] ?? '' ) ) ) {
			$overrides['target_format'] = sanitize_key( (string) $input['target_format'] );
		}
		if ( '' !== trim( (string) ( $input['max_width'] ?? '' ) ) ) {
			$overrides['max_width'] = absint( $input['max_width'] );
		}
		if ( '' !== trim( (string) ( $input['quality'] ?? '' ) ) ) {
			$overrides['quality'] = absint( $input['quality'] );
		}
		$overrides = array_merge( $overrides, $this->media_derivative_crop_overrides( $input ) );
		$overrides = array_merge( $overrides, $this->media_derivative_watermark_overrides( $input ) );

		$toolbox_policy = $this->settings->media_optimization_policy_summary();
		$ability_input  = $this->settings->build_media_derivative_ability_input( $overrides );

		$warnings = array();
		$watermark_mode = sanitize_key( (string) ( $input['watermark_mode'] ?? $input['watermark_type'] ?? 'core' ) );
		if ( ! empty( $toolbox_policy['watermark_enabled'] ) && empty( $toolbox_policy['watermark_configured'] ) && ! in_array( $watermark_mode, array( 'off', 'text' ), true ) ) {
			$warnings[] = __( 'Toolbox watermark policy is enabled but no logo attachment is configured.', 'npcink-workflow-toolbox' );
		}

		return array(
			'artifact_type'          => 'media_derivative_handoff',
			'composition_role'       => 'media_derivative_operator_handoff',
			'version'                => 1,
			'workflow_projection'    => array(
				'definition_owner'            => 'npcink-abilities-toolkit',
				'projection_role'             => 'fixed_button',
				'recipe_id'                    => 'npcink-abilities-toolkit/recipes/media-optimization',
				'recipe_alias'                 => 'media_optimization_v1',
				'contract_version'             => 'v1',
				'entrypoint_ability_id'        => 'npcink-abilities-toolkit/build-media-optimization-plan',
				'required_scope'               => 'media.read',
				'required_inputs'              => array( 'attachment_id', 'media_details_input', 'derivative_artifact' ),
				'handoff_kind'                 => 'approval_request',
				'failure_policy'               => 'fail_closed',
				'host_governed_write_boundary' => true,
				'canonical_definition_storage' => false,
			),
			'write_posture'          => 'core_proposal_handoff',
			'direct_wordpress_write' => false,
			'provider'               => 'toolbox',
			'attachment_id'          => $attachment_id,
			'toolbox_policy_available' => true,
			'toolbox_policy'         => $this->sanitize_payload( $toolbox_policy ),
			'ability_id'             => 'npcink-abilities-toolkit/build-media-derivative-cloud-request',
			'ability_input'          => $this->sanitize_payload( $ability_input ),
			'optimization_plan_ability_id' => 'npcink-abilities-toolkit/build-media-optimization-plan',
			'preferred_core_route'   => '/wp-json/npcink-openclaw-adapter/v1/proposals/from-plan',
			'required_reviewed_input' => array( 'media_details_input', 'derivative_artifact' ),
			'warnings'               => $warnings,
			'handoff'                => array(
				'final_write_path'       => 'core_proposal_required',
				'direct_wordpress_write' => false,
				'default_user_intent'    => 'optimize_this_media_item',
				'do_not_split_user_intent' => true,
				'legacy_derivative_only' => 'lower_level_review_only',
				'next_steps'             => array(
					'Run the local media derivative request ability with ability_input.',
					'Use Cloud Addon only as a verified transport when available.',
					'Add reviewed media_details_input before Core proposal submission.',
					'Submit Adapter from_plan_request to /proposals/from-plan so Core creates one media optimization proposal.',
					'If Core lacks npcink-abilities-toolkit/build-media-optimization-plan, update Core and Abilities instead of splitting the same user intent into two proposals.',
				),
			),
		);
	}

	private function media_derivative_watermark_overrides( array $input ): array {
		$mode = sanitize_key( (string) ( $input['watermark_mode'] ?? $input['watermark_type'] ?? 'core' ) );
		if ( 'off' === $mode ) {
			return array( 'watermark_enabled' => false );
		}
		if ( 'override' === $mode ) {
			$mode = 'image';
		}
		if ( ! in_array( $mode, array( 'text', 'image' ), true ) ) {
			return array();
		}

		$position = sanitize_key( (string) ( $input['watermark_position'] ?? 'bottom_right' ) );
		if ( ! in_array( $position, array( 'top_left', 'top_right', 'center', 'bottom_left', 'bottom_right' ), true ) ) {
			$position = 'bottom_right';
		}
		$opacity = '' !== trim( (string) ( $input['watermark_opacity'] ?? '' ) )
			? absint( $input['watermark_opacity'] )
			: 80;
		$margin = max( 0, min( 1000, absint( $input['watermark_margin'] ?? 24 ) ) );

		if ( 'text' === $mode ) {
			$text = trim( sanitize_text_field( (string) ( $input['watermark_text'] ?? 'AI' ) ) );
			if ( '' === $text ) {
				$text = 'AI';
			}
			$text = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 64 ) : substr( $text, 0, 64 );

			return array(
				'watermark_enabled' => true,
				'watermark'         => array(
					'type'       => 'text',
					'text'       => $text,
					'position'   => $position,
					'opacity'    => round( max( 0, min( 100, $opacity ) ) / 100, 3 ),
					'font_size'  => max( 8, min( 256, absint( $input['watermark_font_size'] ?? 48 ) ) ),
					'color'      => $this->sanitize_media_derivative_watermark_color( $input['watermark_color'] ?? '#FFFFFF', '#FFFFFF' ),
					'background' => $this->sanitize_media_derivative_watermark_color( $input['watermark_background'] ?? 'rgba(0,0,0,0.35)', 'rgba(0,0,0,0.35)' ),
					'margin_px'  => $margin,
				),
			);
		}

		return array(
			'watermark_enabled' => true,
			'watermark'         => array(
				'type'          => 'image',
				'position'      => $position,
				'opacity'       => round( max( 0, min( 100, $opacity ) ) / 100, 3 ),
				'scale_percent' => max( 1, min( 100, absint( $input['watermark_scale'] ?? 20 ) ) ),
				'margin_px'     => $margin,
			),
		);
	}

	private function media_derivative_crop_overrides( array $input ): array {
		$aspect_ratio = trim( sanitize_text_field( (string) ( $input['crop_aspect_ratio'] ?? '' ) ) );
		if ( '' === $aspect_ratio ) {
			return array();
		}
		if ( 1 !== preg_match( '/^([1-9][0-9]{0,2}):([1-9][0-9]{0,2})$/', $aspect_ratio, $matches ) || (int) $matches[1] > 100 || (int) $matches[2] > 100 ) {
			$aspect_ratio = '16:9';
		}

		$position = sanitize_key( (string) ( $input['crop_position'] ?? 'center' ) );
		if ( ! in_array( $position, array( 'top_left', 'top', 'top_right', 'left', 'center', 'right', 'bottom_left', 'bottom', 'bottom_right' ), true ) ) {
			$position = 'center';
		}

		return array(
			'crop' => array(
				'type'         => 'aspect_ratio',
				'aspect_ratio' => $aspect_ratio,
				'position'     => $position,
			),
		);
	}

	private function sanitize_media_derivative_watermark_color( $value, string $default ): string {
		$color = trim( sanitize_text_field( (string) $value ) );
		if ( 'transparent' === strtolower( $color ) ) {
			return 'transparent';
		}
		if ( 1 === preg_match( '/^#[0-9A-Fa-f]{3}([0-9A-Fa-f]{3})?$/', $color ) ) {
			return strtoupper( $color );
		}
		if ( 1 === preg_match( '/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*(0|1|0?\.\d+))?\s*\)$/', $color, $matches ) ) {
			$r     = max( 0, min( 255, (int) $matches[1] ) );
			$g     = max( 0, min( 255, (int) $matches[2] ) );
			$b     = max( 0, min( 255, (int) $matches[3] ) );
			$alpha = isset( $matches[4] ) && '' !== $matches[4] ? max( 0, min( 1, (float) $matches[4] ) ) : null;

			return null === $alpha
				? sprintf( 'rgb(%d,%d,%d)', $r, $g, $b )
				: sprintf( 'rgba(%d,%d,%d,%s)', $r, $g, $b, rtrim( rtrim( sprintf( '%.3F', $alpha ), '0' ), '.' ) );
		}

		return $default;
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

	private function image_source_latency_mode( array $options ): string {
		$mode = sanitize_key( (string) ( $options['latency_mode'] ?? $options['image_latency_mode'] ?? '' ) );
		return 'fast_first' === $mode ? 'fast_first' : 'complete';
	}

	private function execute_image_source_cloud_request( string $query, array $options, string $provider ) {
		$per_page     = max( 1, min( 30, (int) ( $options['per_page'] ?? 9 ) ) );
		$latency_mode = $this->image_source_latency_mode( $options );
		$fast_first   = 'fast_first' === $latency_mode;
		$input        = array(
			'query'              => $query,
			'provider'           => $provider,
			'provider_origin'    => 'cloud',
			'per_page'           => $per_page,
			'latency_mode'       => $latency_mode,
			'latency_budget_seconds' => $fast_first ? 5 : 60,
			'enhancement_mode'   => $fast_first ? 'deferred' : 'inline',
			'orientation'        => sanitize_key( (string) ( $options['orientation'] ?? '' ) ),
			'color'              => sanitize_key( (string) ( $options['color'] ?? '' ) ),
			'purpose'            => sanitize_key( (string) ( $options['purpose'] ?? 'image_reference_candidate' ) ),
			'candidate_contract' => 'image_candidate.v1',
		);
		$refresh_variant = sanitize_text_field( (string) ( $options['refresh_variant'] ?? '' ) );
		if ( '' !== $refresh_variant ) {
			$input['refresh_variant'] = $refresh_variant;
		}
		if ( $fast_first ) {
			$input['deferred_cloud_ai_steps'] = array(
				'site_context_vectors',
				'candidate_rerank',
				'media_seo_suggestions',
			);
		}
		$visual_context = $this->image_visual_context_input( $query, $options, $per_page );
		if ( array() !== $visual_context ) {
			$input['visual_context'] = $visual_context;
		}
		$data_classification = $this->runtime_payload_data_classification( $input, 'public_reference_media', $options );
		$runtime_payload = array(
			'ability_name'        => 'npcink-toolbox/search-image-source',
			'contract_version'    => 'image_source_cloud_request.v1',
			'execution_pattern'   => 'inline',
			'execution_kind'      => 'image_source',
			'profile_id'          => 'image-source.managed',
			'input'               => $this->sanitize_payload( $input ),
			'data_classification' => $data_classification,
			'storage_mode'        => $this->runtime_payload_storage_mode( $data_classification ),
			'retention_ttl'       => 3600,
			'timeout_seconds'     => $fast_first ? 5 : 60,
			'http_timeout_seconds' => $fast_first ? 5 : 60,
			'connect_timeout_seconds' => self::HTTP_CONNECT_TIMEOUT,
			'retry_max'           => 0,
			'policy'              => array(
				'allow_fallback' => true,
			),
		);

		$runtime_payload = apply_filters( 'npcink_toolbox_image_source_runtime_payload', $runtime_payload, $query, $options );
		if ( ! is_array( $runtime_payload ) ) {
			return new WP_Error(
				'npcink_toolbox_invalid_image_source_runtime_payload',
				__( 'The image-source runtime payload was not valid.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}
		$runtime_payload = $this->runtime_payload_with_data_classification( $runtime_payload, 'public_reference_media', $options );

		$handled = apply_filters( 'npcink_toolbox_image_source_cloud_request', null, $runtime_payload, $query, $options );
		if ( is_wp_error( $handled ) ) {
			return $handled;
		}
		if ( is_array( $handled ) ) {
			return $this->normalize_image_source_candidates_response( $handled, $query, $provider, $runtime_payload );
		}

		$trace_id        = $this->trace_id( 'image_source' );
		$idempotency_key = $this->trace_id( 'image_source_cloud_request' );
		$request         = $this->toolbox_image_source_runtime_request( $runtime_payload );

		if ( function_exists( 'npcink_cloud_addon_execute_toolbox_image_source_runtime' ) ) {
			$response = npcink_cloud_addon_execute_toolbox_image_source_runtime( $request, $trace_id, $idempotency_key );
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			return $this->normalize_image_source_candidates_response( is_array( $response ) ? $response : array(), $query, $provider, $runtime_payload );
		}

		return new WP_Error(
			'npcink_toolbox_image_source_cloud_unavailable',
			__( 'Connect Npcink Cloud before searching managed image-source candidates. Reviewed image URLs can still be adopted from the editor image sidebar.', 'npcink-workflow-toolbox' ),
			array( 'status' => 503 )
		);
	}

	private function toolbox_image_source_runtime_request( array $runtime_payload ): array {
		$input = is_array( $runtime_payload['input'] ?? null ) ? $runtime_payload['input'] : array();

		$input['contract_version']       = 'image_source_cloud_request.v1';
		$input['profile_id']             = sanitize_text_field( (string) ( $runtime_payload['profile_id'] ?? 'image-source.managed' ) );
		$input['timeout_seconds']        = absint( $runtime_payload['timeout_seconds'] ?? 60 );
		$input['retention_ttl']          = absint( $runtime_payload['retention_ttl'] ?? 3600 );
		$input['storage_mode']           = sanitize_key( (string) ( $runtime_payload['storage_mode'] ?? 'result_only' ) );
		$input['data_classification']    = sanitize_key( (string) ( $runtime_payload['data_classification'] ?? 'public_reference_media' ) );
		$input['write_posture']          = 'suggestion_only';
		$input['direct_wordpress_write'] = false;
		$input['allow_fallback']         = ! empty( $runtime_payload['policy']['allow_fallback'] );

		return $this->sanitize_payload( $input );
	}

	private function image_visual_context_input( string $query, array $options, int $per_page ): array {
		$context = is_array( $options['visual_context'] ?? null ) ? $options['visual_context'] : array();
		if ( array() === $context && ! empty( $options['post_context'] ) && is_array( $options['post_context'] ) ) {
			$context = $options['post_context'];
		}
		$latency_mode = $this->image_source_latency_mode(
			array_merge(
				$options,
				array(
					'latency_mode' => $context['latency_mode'] ?? ( $options['latency_mode'] ?? '' ),
				)
			)
		);
		$fast_first   = 'fast_first' === $latency_mode;

		$selection = trim( sanitize_textarea_field( (string) ( $context['selected_text'] ?? $context['selected_block_text'] ?? '' ) ) );
		$title     = trim( sanitize_text_field( (string) ( $context['title'] ?? '' ) ) );
		$excerpt   = trim( sanitize_textarea_field( (string) ( $context['excerpt'] ?? '' ) ) );
		$content   = trim( sanitize_textarea_field( (string) ( $context['content_summary'] ?? $context['content_text'] ?? $context['content'] ?? '' ) ) );
		$post_id   = max( 0, absint( $context['post_id'] ?? $options['post_id'] ?? 0 ) );
		$mode      = sanitize_key( (string) ( $context['image_mode'] ?? $context['image_use'] ?? $options['image_mode'] ?? 'featured_image' ) );
		if ( ! in_array( $mode, array( 'featured_image', 'paragraph_image', 'inline_image', 'setting_image' ), true ) ) {
			$mode = 'featured_image';
		}

		$visual_context = array(
			'contract_version'       => 'image_visual_brief_request.v1',
			'locale'                 => function_exists( 'determine_locale' ) ? determine_locale() : get_locale(),
			'image_use'              => $mode,
			'latency_mode'           => $latency_mode,
			'latency_budget_seconds' => $fast_first ? 5 : 60,
			'manual_query'           => sanitize_text_field( (string) ( $context['manual_query'] ?? $options['manual_query'] ?? '' ) ),
			'fallback_query'         => sanitize_text_field( $query ),
			'refresh_variant'        => sanitize_text_field( (string) ( $context['refresh_variant'] ?? $options['refresh_variant'] ?? '' ) ),
			'post_id'                => $post_id,
			'title'                  => wp_trim_words( $title, 18, '' ),
			'excerpt'                => wp_trim_words( $excerpt, 36, '' ),
			'selected_text'          => wp_trim_words( $selection, 80, '' ),
			'content_summary'        => wp_trim_words( $content, 80, '' ),
			'selected_block_name'    => sanitize_key( (string) ( $context['selected_block_name'] ?? '' ) ),
			'query_intent'           => array(
				'rewrite_abstract_terms'       => ! empty( $context['query_intent']['rewrite_abstract_terms'] ),
				'prefer_concrete_visual_scene' => ! empty( $context['query_intent']['prefer_concrete_visual_scene'] ),
				'return_alternate_queries'     => ! empty( $context['query_intent']['return_alternate_queries'] ),
				'direction_count'              => max( 1, min( 4, absint( $context['query_intent']['direction_count'] ?? $options['direction_count'] ?? 3 ) ) ),
				'prompt_candidate_count'       => max( 1, min( 4, absint( $context['query_intent']['prompt_candidate_count'] ?? $options['prompt_candidate_count'] ?? 3 ) ) ),
			),
			'constraints'            => array(
				'avoid_brand_logos'     => ! empty( $context['avoid_brand_logos'] ),
				'prefer_editorial_safe' => true,
				'write_posture'         => 'suggestion_only',
			),
			'cloud_ai_steps'         => $fast_first
				? array( 'visual_brief' )
				: array(
					'visual_brief',
					'site_context_vectors',
					'candidate_rerank',
					'media_seo_suggestions',
				),
			'deferred_cloud_ai_steps' => $fast_first
				? array(
					'site_context_vectors',
					'candidate_rerank',
					'media_seo_suggestions',
				)
				: array(),
			'quality_filters'        => array(
				'dedupe_similar_images'       => true,
				'avoid_visible_watermarks'     => true,
				'avoid_brand_logos'            => ! empty( $context['avoid_brand_logos'] ),
				'minimum_width'                => 1200,
				'minimum_height'               => 675,
				'prefer_editorial_over_stock'  => true,
			),
			'rights_requirements'    => array(
				'preserve_attribution'         => true,
				'preserve_source_url'          => true,
				'preserve_download_location'   => true,
				'return_license_review_status' => true,
			),
			'ui_contract'            => array(
				'return_match_reason'           => ! $fast_first,
				'return_quality_tags'           => true,
				'return_risk_flags'             => true,
				'return_empty_query_suggestions' => true,
			),
			'candidate_limits'       => array(
				'returned_candidates'      => $per_page,
				'max_source_candidates'    => $fast_first ? max( $per_page, min( 12, max( 8, $per_page * 2 ) ) ) : max( $per_page, min( 30, max( 20, $per_page * 3 ) ) ),
				'max_site_context_results' => $fast_first ? 0 : 4,
			),
			'fallback_policy'        => array(
				'plain_image_search' => true,
				'defer_rerank'       => $fast_first,
				'keep_candidate_order_when_rerank_unavailable' => true,
			),
			'data_minimization'      => array(
				'full_post_content_sent' => false,
				'content_truncated'      => true,
			),
		);

		if ( '' === $visual_context['title'] && '' === $visual_context['excerpt'] && '' === $visual_context['selected_text'] && '' === $visual_context['content_summary'] && '' === $visual_context['manual_query'] ) {
			return array();
		}

		return $this->sanitize_payload( $visual_context );
	}

	private function normalize_image_visual_brief( array $result, array $runtime_payload ): array {
		$input = is_array( $runtime_payload['input'] ?? null ) ? $runtime_payload['input'] : array();
		$brief = array();
		foreach ( array( 'visual_brief', 'search_brief', 'image_brief' ) as $key ) {
			if ( is_array( $result[ $key ] ?? null ) ) {
				$brief = $result[ $key ];
				break;
			}
		}

		$primary_query = sanitize_text_field( (string) ( $brief['primary_query'] ?? $result['primary_query'] ?? $result['optimized_query'] ?? $input['query'] ?? '' ) );
		$visual_intent = sanitize_textarea_field( (string) ( $brief['visual_intent'] ?? $result['visual_intent'] ?? '' ) );
		$style = sanitize_text_field( (string) ( $brief['style'] ?? $result['style'] ?? '' ) );
		$orientation = sanitize_key( (string) ( $brief['preferred_orientation'] ?? $input['orientation'] ?? '' ) );

		return array(
			'status'                => sanitize_key( (string) ( $result['visual_brief_status'] ?? $result['brief_status'] ?? ( array() !== $brief ? 'ready' : 'fallback' ) ) ),
			'primary_query'         => $primary_query,
			'visual_intent'         => $visual_intent,
			'query_suggestions'     => $this->sanitize_image_query_suggestions( $brief['query_suggestions'] ?? $result['query_suggestions'] ?? $result['empty_query_suggestions'] ?? array() ),
			'negative_terms'        => array_slice( $this->sanitize_string_list( $brief['negative_terms'] ?? $result['negative_terms'] ?? array() ), 0, 8 ),
			'preferred_orientation' => $orientation,
			'style'                 => $style,
			'match_criteria'        => array_slice( $this->sanitize_string_list( $brief['match_criteria'] ?? $result['match_criteria'] ?? array() ), 0, 8 ),
			'site_context_status'   => sanitize_key( (string) ( $result['site_context_status'] ?? $result['vector_context_status'] ?? '' ) ),
			'rerank_status'         => sanitize_key( (string) ( $result['rerank_status'] ?? $result['candidate_rerank_status'] ?? '' ) ),
			'cloud_ai_steps'        => $this->sanitize_string_list( $input['visual_context']['cloud_ai_steps'] ?? array() ),
		);
	}

	private function sanitize_image_query_suggestions( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$suggestions = array();
		foreach ( array_slice( $value, 0, 5 ) as $item ) {
			if ( is_array( $item ) ) {
				$label = sanitize_text_field( html_entity_decode( (string) ( $item['display_label'] ?? $item['label'] ?? $item['query'] ?? '' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
				$query = sanitize_text_field( html_entity_decode( (string) ( $item['search_query'] ?? $item['query'] ?? $item['display_label'] ?? '' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
				if ( '' !== $label && '' !== $query ) {
					$suggestions[] = array(
						'display_label' => $label,
						'search_query'  => $query,
					);
				}
				continue;
			}
			$query = sanitize_text_field( html_entity_decode( (string) $item, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			if ( '' !== $query ) {
				$suggestions[] = array(
					'display_label' => $query,
					'search_query'  => $query,
				);
			}
		}
		return $suggestions;
	}

	private function normalize_audio_generation_response( array $response, array $runtime_payload ): array {
		$result = $this->extract_cloud_runtime_result( $response );
		$data   = is_array( $response['data'] ?? null ) ? $response['data'] : array();
		$input  = is_array( $runtime_payload['input'] ?? null ) ? $runtime_payload['input'] : array();
		$audios = array();

		foreach ( array( 'audios', 'audio_candidates', 'candidates', 'items' ) as $key ) {
			if ( is_array( $result[ $key ] ?? null ) ) {
				$audios = $result[ $key ];
				break;
			}
		}
		if ( array() === $audios && ( ! empty( $result['url'] ) || ! empty( $result['audio_url'] ) ) ) {
			$audios = array( $result );
		}

		$items = array();
		foreach ( array_slice( array_values( array_filter( $audios, 'is_array' ) ), 0, 4 ) as $index => $audio ) {
			$url = esc_url_raw( (string) ( $audio['url'] ?? ( $audio['audio_url'] ?? '' ) ) );
			$b64 = sanitize_textarea_field( (string) ( $audio['b64_json'] ?? '' ) );
			if ( '' === $url && '' === $b64 ) {
				continue;
			}
			$items[] = array(
				'id'               => sanitize_key( (string) ( $audio['id'] ?? 'audio_' . ( $index + 1 ) ) ),
				'name'             => sanitize_text_field( (string) ( $audio['name'] ?? ( 'article_audio_summary' === (string) ( $input['intent'] ?? '' ) ? __( 'Audio summary candidate', 'npcink-workflow-toolbox' ) : __( 'Narration candidate', 'npcink-workflow-toolbox' ) ) ) ),
				'url'              => $url,
				'b64_json'         => $b64,
				'format'           => sanitize_key( (string) ( $audio['format'] ?? ( $input['format'] ?? 'mp3' ) ) ),
				'duration_seconds' => is_numeric( $audio['duration_seconds'] ?? null ) ? (float) $audio['duration_seconds'] : null,
				'size_bytes'       => absint( $audio['size_bytes'] ?? 0 ),
				'voice_id'         => sanitize_text_field( (string) ( $audio['voice_id'] ?? ( $result['voice_id'] ?? ( $input['voice_id'] ?? '' ) ) ) ),
				'model_id'         => sanitize_text_field( (string) ( $audio['model_id'] ?? ( $result['model_id'] ?? '' ) ) ),
				'provider'         => sanitize_key( (string) ( $audio['provider'] ?? ( $result['provider'] ?? 'npcink_cloud' ) ) ),
				'action_policy'    => 'operator_review_only_no_media_import',
				'quality_status'   => 'review',
			);
		}

		return $this->with_output_contract(
			array(
				'provider'                 => 'npcink_cloud',
				'cloud_runtime'            => 'npcink_cloud_addon',
				'cloud_ability'            => sanitize_text_field( (string) ( $runtime_payload['ability_name'] ?? 'npcink-toolbox/generate-audio' ) ),
				'contract_version'         => sanitize_text_field( (string) ( $runtime_payload['contract_version'] ?? 'audio_generation_request.v1' ) ),
				'hosted_profile'           => sanitize_text_field( (string) ( $runtime_payload['profile_id'] ?? 'audio.narration.default' ) ),
				'model_id'                 => sanitize_text_field( (string) ( $result['model_id'] ?? '' ) ),
				'intent'                   => sanitize_key( (string) ( $input['intent'] ?? '' ) ),
				'status'                   => sanitize_key( (string) ( $result['status'] ?? ( $response['status'] ?? 'ready' ) ) ),
				'run_id'                   => sanitize_text_field( (string) ( $response['run_id'] ?? ( $result['run_id'] ?? '' ) ) ),
				'cloud_run_id'             => sanitize_text_field( (string) ( $data['run_id'] ?? $response['run_id'] ?? '' ) ),
				'provider_response_format' => sanitize_key( (string) ( $result['provider_response_format'] ?? 'url' ) ),
				'user_instruction'         => sanitize_textarea_field( (string) ( $input['user_instruction'] ?? '' ) ),
				'audio_preferences'        => is_array( $input['audio_preferences'] ?? null ) ? $this->sanitize_payload( $input['audio_preferences'] ) : array(),
				'items'                    => $this->sanitize_payload( $items ),
				'audios'                   => $this->sanitize_payload( $items ),
				'script_preview'           => $this->trim_chars( sanitize_textarea_field( (string) ( $input['script'] ?? ( $input['text'] ?? '' ) ) ), 1200 ),
				'result'                   => $this->sanitize_payload( $result ),
				'candidate_count'          => count( $items ),
				'write_posture'            => 'suggestion_only',
				'final_write_path'         => 'operator_review_only_no_media_import',
				'direct_wordpress_write'   => false,
				'handoff'                  => array(
					'final_writes'           => 'operator_review_only_no_media_import',
					'direct_wordpress_write' => false,
					'blocked_actions'        => array(
						'no_media_import_in_toolbox',
						'no_post_content_patch',
						'no_direct_wordpress_write',
					),
				),
			),
			'audio_generation_candidates',
			'article_audio_support'
		);
	}

	private function normalize_image_source_candidates_response( array $response, string $query, string $provider_mode, array $runtime_payload = array() ): array {
		$result = $this->extract_cloud_runtime_result( $response );

		$images = $this->extract_image_source_candidate_items( $result );

		$contract_images = array();
		foreach ( array_slice( $this->dedupe_image_candidates( $images ), 0, max( 1, min( 30, (int) ( $runtime_payload['input']['per_page'] ?? 8 ) ) ) ) as $image ) {
			if ( is_array( $image ) ) {
				$image['provider_origin'] = $image['provider_origin'] ?? 'cloud';
				$contract_images[]        = $this->ai_image->normalize_image_candidate_contract( $image );
			}
		}

		$active_sources = is_array( $result['active_sources'] ?? null ) ? $this->sanitize_payload( $result['active_sources'] ) : array();
		if ( array() === $active_sources && $provider_mode ) {
			$active_sources[] = array(
				'provider' => 'cloud' === $provider_mode || 'auto' === $provider_mode ? 'cloud_image_sources' : $provider_mode,
				'count'    => count( $contract_images ),
			);
		}
		$resolved_provider = sanitize_key( (string) ( $result['resolved_provider'] ?? $result['provider_mode'] ?? '' ) );
		if ( '' === $resolved_provider && is_array( $active_sources[0] ?? null ) ) {
			$resolved_provider = sanitize_key( (string) ( $active_sources[0]['provider'] ?? '' ) );
		}
		$visual_brief = $this->normalize_image_visual_brief( $result, $runtime_payload );
		$prompt_candidates = is_array( $result['prompt_candidates'] ?? null ) ? $this->sanitize_payload( $result['prompt_candidates'] ) : array();
		$ai_generation_handoff = is_array( $result['ai_generation_handoff'] ?? null ) ? $this->sanitize_payload( $result['ai_generation_handoff'] ) : array();
		$result_handoff        = is_array( $result['handoff'] ?? null ) ? $this->sanitize_payload( $result['handoff'] ) : array();
		if ( array() !== $ai_generation_handoff ) {
			$result_handoff['ai_generation_handoff'] = $ai_generation_handoff;
			$actions = is_array( $result_handoff['available_actions'] ?? null ) ? $result_handoff['available_actions'] : array();
			if ( ! in_array( 'ai_generation_handoff', $actions, true ) ) {
				$actions[] = 'ai_generation_handoff';
			}
			$result_handoff['available_actions'] = array_values( $actions );
		}

		$payload = $this->with_output_contract(
			array(
				'provider'                   => 'npcink_cloud',
				'provider_mode'              => $provider_mode,
				'requested_provider_mode'    => sanitize_key( (string) ( $result['requested_provider_mode'] ?? $provider_mode ) ),
				'resolved_provider'          => $resolved_provider,
				'auto_strategy'              => sanitize_key( (string) ( $result['auto_strategy'] ?? '' ) ),
				'candidate_contract_version' => 'image_candidate.v1',
				'cloud_ability'              => sanitize_text_field( (string) ( $runtime_payload['ability_name'] ?? 'npcink-toolbox/search-image-source' ) ),
				'cloud_runtime'              => 'npcink_cloud_addon',
				'status'                     => sanitize_key( (string) ( $result['status'] ?? $response['status'] ?? 'unknown' ) ),
				'message'                    => sanitize_text_field( (string) ( $result['message'] ?? $result['error_message'] ?? $response['message'] ?? '' ) ),
				'retrieval_readiness'        => is_array( $result['retrieval_readiness'] ?? null ) ? $this->sanitize_payload( $result['retrieval_readiness'] ) : array(),
				'candidate_source_count'     => count( $images ),
				'result_count'               => count( $contract_images ),
				'active_sources'             => $active_sources,
					'provider_errors'            => is_array( $result['provider_errors'] ?? null ) ? $this->sanitize_payload( $result['provider_errors'] ) : array(),
					'query'                      => $query,
					'visual_brief'               => $visual_brief,
					'prompt_candidates'          => $prompt_candidates,
					'optimized_query'            => sanitize_text_field( (string) ( $result['optimized_query'] ?? $visual_brief['primary_query'] ?? $query ) ),
					'query_suggestions'          => $visual_brief['query_suggestions'],
					'rerank_status'              => $visual_brief['rerank_status'],
					'site_context_status'        => $visual_brief['site_context_status'],
				'images'                     => $contract_images,
				'handoff'                    => array(
					'candidate_contract'    => 'image_candidate.v1',
					'final_writes'          => 'core_proposal_required',
					'direct_wordpress_write' => false,
				) + $result_handoff,
				'ai_generation_handoff'      => $ai_generation_handoff,
			),
			'image_source_candidates',
			'image_source_candidates'
		);

		return $this->with_optional_raw( $payload, is_array( $response['raw'] ?? null ) ? $response['raw'] : $response );
	}

	private function extract_image_source_candidate_items( array $result ): array {
		if ( $this->is_list( $result ) ) {
			return array_values( array_filter( $result, 'is_array' ) );
		}

		foreach ( array( 'images', 'image_source_candidates', 'source_candidates', 'media_candidates', 'assets', 'candidates', 'image_candidates', 'results', 'items', 'photos' ) as $key ) {
			if ( ! is_array( $result[ $key ] ?? null ) ) {
				continue;
			}

			$value = $result[ $key ];
			if ( $this->is_list( $value ) ) {
				return array_values( array_filter( $value, 'is_array' ) );
			}

			$nested = $this->extract_image_source_candidate_items( $value );
			if ( array() !== $nested ) {
				return $nested;
			}
		}

		foreach ( array( 'payload', 'data', 'result', 'output', 'response' ) as $key ) {
			if ( is_array( $result[ $key ] ?? null ) ) {
				$nested = $this->extract_image_source_candidate_items( $result[ $key ] );
				if ( array() !== $nested ) {
					return $nested;
				}
			}
		}

		return array();
	}



	private function agent_feedback_payload( array $input ) {
		$handoff        = is_array( $input['handoff'] ?? null ) ? $input['handoff'] : array();
		$proposal_input = is_array( $handoff['proposal_input'] ?? null ) ? $handoff['proposal_input'] : array();
		$outcome        = sanitize_key( (string) ( $input['local_outcome'] ?? '' ) );
		$allowed_outcomes = array(
			'accepted',
			'rejected',
			'edited_before_accept',
			'ignored',
			'expired',
			'blocked_by_policy',
			'blocked_by_missing_input',
		);

		if ( ! in_array( $outcome, $allowed_outcomes, true ) ) {
			return new WP_Error(
				'npcink_toolbox_agent_feedback_outcome_invalid',
				__( 'Choose a supported Agent feedback outcome.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$agent_id      = sanitize_key( (string) ( $input['agent_id'] ?? ( $handoff['agent_id'] ?? 'site_knowledge_suggestion_agent' ) ) );
		$handoff_type  = sanitize_key( (string) ( $input['handoff_type'] ?? ( $handoff['handoff_type'] ?? 'proposal_input' ) ) );
		$source_runtime = sanitize_key( (string) ( $input['source_runtime'] ?? 'site_knowledge' ) );
		if ( '' === $agent_id ) {
			$agent_id = 'site_knowledge_suggestion_agent';
		}
		if ( '' === $handoff_type ) {
			$handoff_type = 'proposal_input';
		}
		if ( '' === $source_runtime ) {
			$source_runtime = 'site_knowledge';
		}

		$handoff_id = sanitize_text_field( (string) ( $input['handoff_id'] ?? ( $handoff['handoff_id'] ?? '' ) ) );
		if ( '' === $handoff_id ) {
			$handoff_id = 'site_knowledge_handoff_' . substr( md5( $agent_id . '|' . wp_json_encode( $proposal_input ) ), 0, 16 );
		}
		$created_at = sanitize_text_field( (string) ( $input['created_at'] ?? '' ) );
		if ( '' === $created_at ) {
			$created_at = gmdate( 'c' );
		}

		return array(
			'contract_version' => 'cloud_agent_feedback.v1',
			'agent_id'         => $agent_id,
			'agent_version'    => sanitize_text_field( (string) ( $input['agent_version'] ?? ( $handoff['agent_version'] ?? '' ) ) ),
			'source_runtime'   => $source_runtime,
			'source_run_id'    => sanitize_text_field( (string) ( $input['source_run_id'] ?? ( $handoff['source_run_id'] ?? '' ) ) ),
			'handoff_id'       => $handoff_id,
			'handoff_type'     => $handoff_type,
			'local_surface'    => sanitize_key( (string) ( $input['local_surface'] ?? 'toolbox_site_knowledge' ) ),
			'local_outcome'    => $outcome,
			'feedback_labels'  => $this->sanitize_agent_feedback_labels( $input['feedback_labels'] ?? array() ),
			'operator_note'    => substr( sanitize_textarea_field( (string) ( $input['operator_note'] ?? '' ) ), 0, 500 ),
			'local_proposal_id' => sanitize_text_field( (string) ( $input['local_proposal_id'] ?? '' ) ),
			'evidence_ref_ids' => $this->agent_feedback_evidence_ref_ids( $input, $proposal_input ),
			'source_action_id' => substr( sanitize_text_field( (string) ( $input['source_action_id'] ?? '' ) ), 0, 191 ),
			'source_object_type' => sanitize_key( (string) ( $input['source_object_type'] ?? '' ) ),
			'source_object_id' => substr( sanitize_text_field( (string) ( $input['source_object_id'] ?? '' ) ), 0, 191 ),
			'source_reason_codes' => $this->sanitize_string_list( $input['source_reason_codes'] ?? array(), 12 ),
			'source_score'     => isset( $input['source_score'] ) ? max( 0, min( 100, (int) $input['source_score'] ) ) : null,
			'source_severity'  => sanitize_key( (string) ( $input['source_severity'] ?? '' ) ),
			'redaction_status' => 'metadata_only',
			'retention_class'  => 'quality_eval',
			'created_at'       => $created_at,
		);
	}

	private function sanitize_agent_feedback_labels( $labels ): array {
		$allowed = array(
			'evidence_useful',
			'evidence_weak',
			'wrong_intent',
			'wrong_next_step',
			'missing_context',
			'wrong_priority',
			'already_handled',
			'unsafe_or_overreaching',
			'too_generic',
			'duplicate_suggestion',
			'good_but_needs_human_draft',
			'not_relevant_to_site',
			'source_or_license_risk',
				'visual_quality_low',
				'operator_confidence_high',
				'operator_confidence_low',
				'media_search_has_results',
				'media_search_no_results',
				'media_search_runtime_error',
				'media_candidate_adopted',
				'alt_suggestion_applied',
				'alt_saved_unchanged',
				'alt_saved_edited',
				'alt_saved_decorative',
				'alt_saved_cleared',
				'alt_suggestion_not_saved',
			);
		$items = is_array( $labels ) ? $labels : array();
		$normalized = array();
		foreach ( $items as $label ) {
			$value = sanitize_key( (string) $label );
			if ( in_array( $value, $allowed, true ) && ! in_array( $value, $normalized, true ) ) {
				$normalized[] = $value;
			}
		}

		return array_slice( $normalized, 0, 12 );
	}

	private function agent_feedback_evidence_ref_ids( array $input, array $proposal_input ): array {
		$ids = array();
		if ( is_array( $input['evidence_ref_ids'] ?? null ) ) {
			foreach ( $input['evidence_ref_ids'] as $ref_id ) {
				$value = substr( sanitize_text_field( (string) $ref_id ), 0, 191 );
				if ( '' !== $value && ! in_array( $value, $ids, true ) ) {
					$ids[] = $value;
				}
			}
		}

		$refs = is_array( $proposal_input['evidence_refs'] ?? null ) ? $proposal_input['evidence_refs'] : array();
		foreach ( $refs as $index => $ref ) {
			if ( ! is_array( $ref ) ) {
				continue;
			}
			$value = sanitize_text_field( (string) ( $ref['id'] ?? ( $ref['ref_id'] ?? '' ) ) );
			if ( '' === $value ) {
				$source = sanitize_key( (string) ( $ref['source_type'] ?? 'evidence' ) );
				$source_id = sanitize_text_field( (string) ( $ref['source_id'] ?? ( $ref['post_id'] ?? ( $ref['url'] ?? ( $index + 1 ) ) ) ) );
				$value = $source . ':' . $source_id;
			}
			$value = substr( $value, 0, 191 );
			if ( '' !== $value && ! in_array( $value, $ids, true ) ) {
				$ids[] = $value;
			}
		}

		return array_slice( $ids, 0, 24 );
	}

	private function normalize_agent_feedback_response( array $response, array $payload ): array {
		$data = is_array( $response['data'] ?? null ) ? $response['data'] : $response;

		return array(
			'artifact_type'             => 'site_knowledge_agent_feedback_receipt',
			'contract_version'         => 'cloud_agent_feedback.v1',
			'status'                   => sanitize_key( (string) ( $response['status'] ?? 'ok' ) ),
			'cloud_submission'         => 'submitted_for_eval',
			'accepted_for_eval'        => ! array_key_exists( 'accepted_for_eval', $data ) || ! empty( $data['accepted_for_eval'] ),
			'quality_rollup_candidate' => ! empty( $data['quality_rollup_candidate'] ),
			'production_mutation'      => false,
			'approval_truth'           => 'wordpress_local',
			'preflight_truth'          => 'wordpress_local',
			'final_write_truth'        => 'wordpress_local',
			'feedback_event_id'        => sanitize_text_field( (string) ( $data['feedback_event_id'] ?? '' ) ),
			'local_outcome'            => sanitize_key( (string) ( $payload['local_outcome'] ?? '' ) ),
			'feedback_labels'          => $this->sanitize_agent_feedback_labels( $payload['feedback_labels'] ?? array() ),
		);
	}

	private function normalize_agent_feedback_summary_response( array $response, int $window_hours ): array {
		$data = is_array( $response['data'] ?? null ) ? $response['data'] : $response;

		return array(
			'artifact_type'        => 'site_knowledge_agent_feedback_summary',
			'contract_version'    => 'cloud_agent_feedback.v1',
			'window_hours'        => $window_hours,
			'events_total'        => absint( $data['events_total'] ?? 0 ),
			'outcomes'            => is_array( $data['outcomes'] ?? null ) ? $this->sanitize_payload( $data['outcomes'] ) : array(),
			'labels'              => is_array( $data['labels'] ?? null ) ? $this->sanitize_payload( $data['labels'] ) : array(),
			'rates'               => is_array( $data['rates'] ?? null ) ? $this->sanitize_payload( $data['rates'] ) : array(),
			'source_runtimes'     => is_array( $data['source_runtimes'] ?? null ) ? $this->sanitize_payload( $data['source_runtimes'] ) : array(),
			'local_surfaces'      => is_array( $data['local_surfaces'] ?? null ) ? $this->sanitize_payload( $data['local_surfaces'] ) : array(),
			'scenarios'           => is_array( $data['scenarios'] ?? null ) ? $this->sanitize_payload( $data['scenarios'] ) : array(),
			'quality_trend'       => is_array( $data['quality_trend'] ?? null ) ? $this->sanitize_payload( $data['quality_trend'] ) : array(),
			'low_quality_labels'  => is_array( $data['low_quality_labels'] ?? null ) ? $this->sanitize_payload( $data['low_quality_labels'] ) : array(),
			'rejection_reasons'   => is_array( $data['rejection_reasons'] ?? null ) ? $this->sanitize_payload( $data['rejection_reasons'] ) : array(),
			'nightly_inspection'  => is_array( $data['nightly_inspection'] ?? null ) ? $this->sanitize_payload( $data['nightly_inspection'] ) : array(),
			'production_mutation' => false,
			'approval_truth'      => 'wordpress_local',
			'preflight_truth'     => 'wordpress_local',
			'final_write_truth'   => 'wordpress_local',
		);
	}

	private function article_writing_pack_structure( array $rules ): array {
		$structure = array(
			array(
				'section' => 'title',
				'purpose' => 'Use a clear article title aligned with the primary keyword and source topic.',
			),
			array(
				'section' => 'direct_answer',
				'purpose' => 'Open with a concise answer or definition that an answer engine can extract.',
			),
			array(
				'section' => 'context',
				'purpose' => 'Explain why the topic matters to the target audience using only supported facts.',
			),
			array(
				'section' => 'main_body',
				'purpose' => 'Use practical headings, steps, examples, comparisons, or checklists where the source supports them.',
			),
			array(
				'section' => 'geo_summary',
				'purpose' => 'Include a fact-dense summary suitable for generated search citation.',
			),
			array(
				'section' => 'conclusion',
				'purpose' => 'Close with a practical next step without claiming guaranteed ranking or outcomes.',
			),
		);

		if ( ! empty( $rules['allow_faq_generation'] ) ) {
			$structure[] = array(
				'section' => 'faq',
				'purpose' => 'Add 3 to 5 grounded FAQ items only when the brief allows FAQ suggestions.',
			);
		}

		return $structure;
	}

	private function resolve_article_media_candidate( array $article, string $title, string $topic, bool $search_images, string $image_provider ) {
		$candidate = array();
		foreach ( array( 'image_candidate', 'featured_image', 'featured_image_candidate' ) as $key ) {
			if ( is_array( $article[ $key ] ?? null ) ) {
				$candidate = $article[ $key ];
				break;
			}
		}

		if ( empty( $candidate ) && ! empty( $article['image_url'] ) ) {
			$candidate = array(
				'url'             => esc_url_raw( (string) $article['image_url'] ),
				'regular_url'     => esc_url_raw( (string) $article['image_url'] ),
				'description'     => sanitize_textarea_field( (string) ( $article['image_alt'] ?? $title ) ),
				'alt_description' => sanitize_textarea_field( (string) ( $article['image_alt'] ?? $title ) ),
				'provider'        => sanitize_key( (string) ( $article['image_provider'] ?? 'external' ) ),
				'source_url'      => esc_url_raw( (string) ( $article['image_source_url'] ?? '' ) ),
				'photographer'    => sanitize_text_field( (string) ( $article['photographer_name'] ?? '' ) ),
				'attribution'     => sanitize_textarea_field( (string) ( $article['attribution_text'] ?? '' ) ),
			);
		}

		if ( empty( $candidate ) && $search_images ) {
			$query  = trim( sanitize_text_field( (string) ( $article['image_query'] ?? $title . ' ' . $topic ) ) );
			$result = $this->image_candidates(
				$query,
				array(
					'provider' => $image_provider,
					'per_page' => 1,
				)
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$images = is_array( $result['images'] ?? null ) ? array_values( $result['images'] ) : array();
			if ( empty( $images ) || ! is_array( $images[0] ?? null ) ) {
				return new WP_Error(
					'npcink_toolbox_article_media_candidate_missing',
					__( 'Image-source search did not return a usable candidate for an article media batch item.', 'npcink-workflow-toolbox' ),
					array( 'status' => 502 )
				);
			}
			$candidate = $images[0];
		}

		if ( empty( $candidate ) ) {
			return new WP_Error(
				'npcink_toolbox_article_media_candidate_required',
				__( 'Every article media batch item requires image_candidate, featured_image, image_url, or search_images=true.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		return $this->sanitize_payload( $candidate );
	}

	private function registered_ability_callable( string $ability_id ): bool {
		if ( ! function_exists( 'npcink_abilities_toolkit_get_registered' ) ) {
			return false;
		}

		$registered = npcink_abilities_toolkit_get_registered();
		if ( ! is_array( $registered ) ) {
			return false;
		}

		$definition = is_array( $registered[ $ability_id ] ?? null ) ? $registered[ $ability_id ] : array();

		return is_callable( $definition['execute_callback'] ?? null );
	}

	private function article_audio_normalized_source_text( string $content ): string {
		$content = trim( wp_strip_all_tags( $content ) );
		$content = preg_replace( '/\s+/u', ' ', $content );

		return is_string( $content ) ? trim( $content ) : '';
	}

	private function article_audio_content_hash( string $content ): string {
		$content = $this->article_audio_normalized_source_text( $content );

		return '' === $content ? '' : hash( 'sha256', $content );
	}

	private function article_audio_word_count( string $content ): int {
		$content = $this->article_audio_normalized_source_text( $content );
		if ( '' === $content ) {
			return 0;
		}

		$word_count = str_word_count( $content );
		if ( $word_count > 0 ) {
			return $word_count;
		}

		return function_exists( 'mb_strlen' ) ? mb_strlen( $content, 'UTF-8' ) : strlen( $content );
	}

	private function resolve_discoverability_source( array $input ) {
		$post_id = absint( $input['post_id'] ?? 0 );
		$title   = trim( sanitize_text_field( (string) ( $input['title'] ?? '' ) ) );
		$topic   = trim( sanitize_text_field( (string) ( $input['topic'] ?? '' ) ) );
		$content = trim( $this->bounded_text( (string) ( $input['content'] ?? ( $input['content_markdown'] ?? '' ) ), self::ARTICLE_PLAN_CONTENT_CHARS ) );
		$excerpt = trim( sanitize_textarea_field( (string) ( $input['excerpt'] ?? '' ) ) );

		if ( 0 < $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				return new WP_Error(
					'npcink_toolbox_post_not_found',
					__( 'The requested post was not found.', 'npcink-workflow-toolbox' ),
					array( 'status' => 404 )
				);
			}

			$title   = '' !== $title ? $title : get_the_title( $post );
			$content = '' !== $content ? $content : wp_strip_all_tags( (string) $post->post_content );
			$excerpt = '' !== $excerpt ? $excerpt : wp_strip_all_tags( get_the_excerpt( $post ) );
			$topic   = '' !== $topic ? $topic : $title;

			return array(
				'input_type'      => 'post',
				'post_id'         => $post_id,
				'post_type'       => get_post_type( $post ),
				'post_status'     => get_post_status( $post ),
				'title'           => sanitize_text_field( (string) $title ),
				'topic'           => sanitize_text_field( (string) $topic ),
				'excerpt'         => sanitize_textarea_field( (string) $excerpt ),
				'content_excerpt' => wp_trim_words( wp_strip_all_tags( $content ), 180, '' ),
			);
		}

		if ( '' === $title && '' === $topic ) {
			return new WP_Error(
				'npcink_toolbox_missing_discoverability_source',
				__( 'A post_id, topic, or title is required to build a content discoverability brief.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		if ( '' === $title ) {
			$title = $topic;
		}
		if ( '' === $topic ) {
			$topic = $title;
		}

		return array(
			'input_type'      => 'supplied_context',
			'post_id'         => 0,
			'post_type'       => sanitize_key( (string) ( $input['post_type'] ?? 'post' ) ),
			'post_status'     => sanitize_key( (string) ( $input['post_status'] ?? 'draft' ) ),
			'title'           => $title,
			'topic'           => $topic,
			'excerpt'         => $excerpt,
			'content_excerpt' => wp_trim_words( wp_strip_all_tags( $content ), 180, '' ),
		);
	}

	private function content_discoverability_field_instruction( string $field ): string {
		$instructions = array(
			'seo_title'             => __( 'Suggest a concise search title based on the source topic and primary keywords. Avoid clickbait and unsupported claims.', 'npcink-workflow-toolbox' ),
			'seo_description'       => __( 'Suggest a meta description that summarizes the reader problem, topic, and value using verified source facts only.', 'npcink-workflow-toolbox' ),
			'slug'                  => __( 'Suggest a short, readable URL slug from the title or topic.', 'npcink-workflow-toolbox' ),
			'excerpt'               => __( 'Suggest an editorial excerpt grounded in the supplied content.', 'npcink-workflow-toolbox' ),
			'faq'                   => __( 'Suggest FAQ question and answer pairs only when the context allows FAQ generation and the source supports the answers.', 'npcink-workflow-toolbox' ),
			'answer_summary'        => __( 'Suggest a direct one-sentence AEO answer summary grounded in the supplied source.', 'npcink-workflow-toolbox' ),
			'geo_summary'           => __( 'Suggest a standalone GEO summary that is easy for AI systems to quote without adding unsupported facts.', 'npcink-workflow-toolbox' ),
			'structured_data_hints' => __( 'Suggest schema hints only when the source supports them; do not claim schema has been applied.', 'npcink-workflow-toolbox' ),
		);

		return $instructions[ $field ] ?? __( 'Suggest a reviewable content improvement grounded in the supplied source.', 'npcink-workflow-toolbox' );
	}

	private function content_discoverability_field_group( string $field ): string {
		if ( in_array( $field, array( 'faq', 'answer_summary' ), true ) ) {
			return 'aeo';
		}

		if ( in_array( $field, array( 'geo_summary', 'structured_data_hints' ), true ) ) {
			return 'geo';
		}

		return 'seo';
	}

	private function content_discoverability_candidate( string $field, array $source, array $context ) {
		$title   = sanitize_text_field( (string) ( $source['title'] ?? $source['topic'] ?? '' ) );
		$topic   = sanitize_text_field( (string) ( $source['topic'] ?? $title ) );
		$content = sanitize_textarea_field( (string) ( $source['content_excerpt'] ?? '' ) );
		$excerpt = sanitize_textarea_field( (string) ( $source['excerpt'] ?? '' ) );
		$text    = '' !== $excerpt ? $excerpt : $content;

		if ( '' === $text ) {
			$text = $topic;
		}

		if ( 'seo_title' === $field ) {
			return wp_trim_words( $title, 12, '' );
		}
		if ( 'seo_description' === $field ) {
			return wp_trim_words( wp_strip_all_tags( $text ), 26, '' );
		}
		if ( 'slug' === $field ) {
			return $this->content_discoverability_slug_candidate( $title, $topic, $text );
		}
		if ( 'excerpt' === $field ) {
			return wp_trim_words( wp_strip_all_tags( $text ), 36, '' );
		}
		if ( 'answer_summary' === $field && ! empty( $context['rules']['allow_aeo_summary'] ) ) {
			return wp_trim_words( wp_strip_all_tags( $text ), 28, '' );
		}
		if ( 'geo_summary' === $field && ! empty( $context['rules']['allow_geo_summary'] ) ) {
			return wp_trim_words( wp_strip_all_tags( $text ), 42, '' );
		}
		if ( 'faq' === $field && ! empty( $context['rules']['allow_faq_generation'] ) ) {
			return array(
				array(
					'question' => sprintf(
						/* translators: %s: topic. */
						__( 'What should readers know about %s?', 'npcink-workflow-toolbox' ),
						$topic
					),
					'answer_guidance' => __( 'Answer only with facts supported by the supplied source and site context.', 'npcink-workflow-toolbox' ),
				),
				array(
					'question' => sprintf(
						/* translators: %s: topic. */
						__( 'How does %s affect the target audience?', 'npcink-workflow-toolbox' ),
						$topic
					),
					'answer_guidance' => __( 'Connect the answer to target audience needs without inventing outcomes or guarantees.', 'npcink-workflow-toolbox' ),
				),
			);
		}
		if ( 'structured_data_hints' === $field && ! empty( $context['rules']['allow_structured_data_suggestions'] ) ) {
			return array(
				'Article',
				! empty( $context['rules']['allow_faq_generation'] ) ? 'FAQPage candidate if final FAQ answers are verified' : 'FAQPage disabled by context',
			);
		}

		return null;
	}

	private function content_discoverability_slug_candidate( string $title, string $topic, string $text ): string {
		$source = remove_accents( $title . ' ' . $topic . ' ' . wp_trim_words( wp_strip_all_tags( $text ), 12, '' ) );
		preg_match_all( '/[a-z0-9]+/i', strtolower( $source ), $matches );
		$tokens = array();
		foreach ( $matches[0] ?? array() as $token ) {
			$token = sanitize_key( $token );
			if ( '' === $token || in_array( $token, array( 'the', 'and', 'for', 'with', 'about' ), true ) ) {
				continue;
			}
			if ( ! in_array( $token, $tokens, true ) ) {
				$tokens[] = $token;
			}
			if ( 6 <= count( $tokens ) ) {
				break;
			}
		}
		if ( ! empty( $tokens ) ) {
			return implode( '-', $tokens );
		}

		return sanitize_title( $title );
	}

	private function post_context_to_image_query( string $post_context ): string {
		$decoded = json_decode( $post_context, true );
		if ( is_array( $decoded ) ) {
			$title = trim( sanitize_text_field( (string) ( $decoded['title'] ?? '' ) ) );
			if ( '' !== $title ) {
				return $title;
			}

			$excerpt = trim( sanitize_textarea_field( (string) ( $decoded['excerpt'] ?? '' ) ) );
			if ( '' !== $excerpt ) {
				return wp_trim_words( $excerpt, 12, '' );
			}
		}

		return wp_trim_words( wp_strip_all_tags( $post_context ), 12, '' );
	}
}
