<?php
/**
 * Provider_Media_Recognition_Service extracted from Provider_Client.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Provider_Media_Recognition_Service extends Provider_Client_Support {

	private Provider_Client $client;

	public function __construct( Settings $settings, Provider_Client $client ) {
		parent::__construct( $settings );
		$this->client = $client;
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
		$status       = $this->client->get_site_knowledge_status(
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

	public function media_visual_evidence_reuse_policy( int $attachment_id, string $current_fingerprint, string $evidence_fingerprint, array $visual ): string {
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
		$result = $this->client->execute_site_knowledge_cloud_request(
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
}
