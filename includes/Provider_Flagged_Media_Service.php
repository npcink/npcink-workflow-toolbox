<?php
/**
 * Flagged media review-set service for the provider client.
 *
 * Builds the suggestion-only flagged_media_review_set.v1 artifact from a
 * bounded recent media metadata sample and optional Cloud content-safety
 * statuses read from the Cloud media projection. No media write, deletion,
 * pixel upload, local vision model, queue, or safety-truth store lives here.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

final class Provider_Flagged_Media_Service extends Provider_Client_Support {
	private Provider_Client $client;

	private const CONTENT_SAFETY_VALUES   = array( 'safe', 'flagged', 'unknown' );
	private const SUGGESTED_ACTION_VALUES = array( 'review_attachment_manually', 'open_attachment_in_wordpress' );

	public function __construct( Settings $settings, Provider_Client $client ) {
		parent::__construct( $settings );
		$this->client = $client;
	}

	public function local_flagged_media_review_response( array $runtime_payload, array $review_set, string $cloud_status = 'cloud_required' ): array {
		$quality_contract = $this->client->hosted_ai_site_helper_quality_contract( 'flagged_media_suggestions' );

		return $this->with_output_contract(
			array(
				'provider'                 => 'local_media_metadata_review',
				'cloud_runtime'            => 'required',
				'cloud_enrichment_status'  => sanitize_key( $cloud_status ),
				'cloud_ability'            => sanitize_text_field( (string) ( $runtime_payload['ability_name'] ?? 'npcink-toolbox/ai-site-helper' ) ),
				'contract_version'         => sanitize_text_field( (string) ( $runtime_payload['contract_version'] ?? 'hosted_ai_site_helper.v1' ) ),
				'intent'                   => 'flagged_media_suggestions',
				'status'                   => 'cloud_required',
				'output_text'              => '',
				'result'                   => array(),
				'quality_contract'         => $this->sanitize_payload( $quality_contract ),
				'output_shape'             => $this->sanitize_payload( $quality_contract['output_shape'] ?? array() ),
				'review_checklist'         => $this->sanitize_string_list( $quality_contract['review_checklist'] ?? array() ),
				'reject_if'                => $this->sanitize_string_list( $quality_contract['reject_if'] ?? array() ),
				'flagged_media_review_set' => $this->sanitize_payload( $review_set ),
				'write_posture'            => 'suggestion_only',
				'final_write_path'         => 'core_proposal_required',
				'direct_wordpress_write'   => false,
				'handoff'                  => array(
					'final_writes'           => 'core_proposal_required',
					'direct_wordpress_write' => false,
				),
			),
			'hosted_ai_site_helper',
			'hosted_ai_site_helper'
		);
	}

	public function build_flagged_media_review_set( array $sample, array $safety_statuses = array(), string $cloud_status = 'cloud_required' ): array {
		$items       = is_array( $sample['items'] ?? null ) ? $sample['items'] : array();
		$cloud_ready = 'ready' === sanitize_key( $cloud_status );
		$indexed     = $cloud_ready ? $this->index_content_safety_statuses( $safety_statuses ) : array();
		$selected    = array();
		$blocked     = array();
		$safe_count  = 0;

		foreach ( $items as $item ) {
			$attachment_id = absint( $item['attachment_id'] ?? 0 );
			if ( 0 >= $attachment_id ) {
				continue;
			}

			if ( ! $cloud_ready ) {
				$blocked[] = array(
					'attachment_id'    => $attachment_id,
					'blocked_reason'   => 'cloud_safety_status_unavailable',
					'suggested_action' => 'review_attachment_manually',
				);
				continue;
			}

			$status = $indexed[ $attachment_id ] ?? null;
			if ( null === $status || 'unknown' === $status['content_safety'] ) {
				$blocked[] = array(
					'attachment_id'    => $attachment_id,
					'blocked_reason'   => 'safety_status_unknown',
					'suggested_action' => 'review_attachment_manually',
				);
				continue;
			}

			if ( 'safe' === $status['content_safety'] ) {
				++$safe_count;
				continue;
			}

			$selected[] = array(
				'attachment_id'    => $attachment_id,
				'title'            => sanitize_text_field( (string) ( $item['title'] ?? '' ) ),
				'filename'         => sanitize_text_field( (string) ( $item['filename'] ?? '' ) ),
				'mime_type'        => sanitize_text_field( (string) ( $item['mime_type'] ?? '' ) ),
				'content_safety'   => 'flagged',
				'confidence'       => $status['confidence'],
				'reasons'          => $status['reasons'],
				'suggested_action' => $status['suggested_action'],
			);
		}

		return array(
			'contract_version'       => 'flagged_media_review_set.v1',
			'write_posture'          => 'suggestion_only',
			'media_unchanged'        => true,
			'direct_wordpress_write' => false,
			'proposal_created'       => false,
			'execution_created'      => false,
			'data_classification'    => 'pii',
			'cloud_status'           => $cloud_ready ? 'ready' : 'cloud_required',
			'snapshot_policy'        => 'recent_media_metadata_only_no_pixels',
			'eligibility_summary'    => array(
				'sampled_count'  => count( $items ),
				'selected_count' => count( $selected ),
				'blocked_count'  => count( $blocked ),
				'safe_count'     => $safe_count,
				'sample_limit'   => max( 1, min( 50, absint( $sample['limit'] ?? 50 ) ) ),
			),
			'selected_items'         => $selected,
			'blocked_items'          => $blocked,
			'operator_next_action'   => $cloud_ready
				? 'Review each flagged attachment, then handle it in WordPress. Deletion is not part of this stage.'
				: 'Connect the Cloud runtime, then rerun the flagged media review.',
			'retryable'              => ! $cloud_ready,
			'retry_guidance'         => $cloud_ready
				? 'Rerun the review after handling flagged items to refresh the sample.'
				: 'Cloud safety status is required; no local fallback assessment exists. Retry after the Cloud Addon runtime is available.',
		);
	}

	private function index_content_safety_statuses( array $safety_statuses ): array {
		$indexed = array();
		foreach ( $safety_statuses as $status ) {
			if ( ! is_array( $status ) ) {
				continue;
			}
			$attachment_id = absint( $status['attachment_id'] ?? 0 );
			if ( 0 >= $attachment_id || isset( $indexed[ $attachment_id ] ) ) {
				continue;
			}

			$value = sanitize_key( (string) ( $status['content_safety'] ?? '' ) );
			if ( ! in_array( $value, self::CONTENT_SAFETY_VALUES, true ) ) {
				$value = 'unknown';
			}
			$action = sanitize_key( (string) ( $status['suggested_action'] ?? '' ) );
			if ( ! in_array( $action, self::SUGGESTED_ACTION_VALUES, true ) ) {
				$action = 'review_attachment_manually';
			}
			$confidence = (float) ( $status['confidence'] ?? 0 );
			if ( 0 > $confidence || 1 < $confidence ) {
				$confidence = 0;
			}

			$indexed[ $attachment_id ] = array(
				'content_safety'   => $value,
				'confidence'       => $confidence,
				'reasons'          => $this->sanitize_string_list( $status['reasons'] ?? array() ),
				'suggested_action' => $action,
			);
		}

		return $indexed;
	}
}
