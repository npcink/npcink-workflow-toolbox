<?php
/**
 * Comment moderation review-set service for the provider client.
 *
 * Builds the suggestion-only comment_moderation_review_set.v1 artifact from a
 * bounded pending-comment sample and optional Cloud classifications. The
 * sample contains only approved-would-be-public fields and never collects or
 * forwards comment author email, IP address, or user agent. The review set
 * never changes comment status; moderation truth, comment workflow
 * governance, queues, and audit stores stay outside Toolbox.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

final class Provider_Comment_Moderation_Service extends Provider_Client_Support {
	private Provider_Client $client;

	private const CLASSIFICATION_VALUES  = array( 'spam', 'legitimate', 'uncertain' );
	private const SUGGESTED_ACTION_VALUES = array( 'open_in_wordpress_moderation_queue', 'review_manually' );

	public function __construct( Settings $settings, Provider_Client $client ) {
		parent::__construct( $settings );
		$this->client = $client;
	}

	public function local_comment_moderation_review_response( array $runtime_payload, array $review_set, string $cloud_status = 'cloud_required' ): array {
		$quality_contract = $this->client->hosted_ai_site_helper_quality_contract( 'comment_moderation_suggestions' );

		return $this->with_output_contract(
			array(
				'provider'                       => 'local_pending_sample_review',
				'cloud_runtime'                  => 'required',
				'cloud_enrichment_status'        => sanitize_key( $cloud_status ),
				'cloud_ability'                  => sanitize_text_field( (string) ( $runtime_payload['ability_name'] ?? 'npcink-toolbox/ai-site-helper' ) ),
				'contract_version'               => sanitize_text_field( (string) ( $runtime_payload['contract_version'] ?? 'hosted_ai_site_helper.v1' ) ),
				'intent'                         => 'comment_moderation_suggestions',
				'status'                         => 'cloud_required',
				'output_text'                    => '',
				'result'                         => array(),
				'quality_contract'               => $this->sanitize_payload( $quality_contract ),
				'output_shape'                   => $this->sanitize_payload( $quality_contract['output_shape'] ?? array() ),
				'review_checklist'               => $this->sanitize_string_list( $quality_contract['review_checklist'] ?? array() ),
				'reject_if'                      => $this->sanitize_string_list( $quality_contract['reject_if'] ?? array() ),
				'comment_moderation_review_set'  => $this->sanitize_payload( $review_set ),
				'write_posture'                  => 'suggestion_only',
				'final_write_path'               => 'core_proposal_required',
				'direct_wordpress_write'         => false,
				'handoff'                        => array(
					'final_writes'           => 'core_proposal_required',
					'direct_wordpress_write' => false,
				),
			),
			'hosted_ai_site_helper',
			'hosted_ai_site_helper'
		);
	}

	public function build_comment_moderation_review_set( array $sample, array $classifications = array(), string $cloud_status = 'cloud_required' ): array {
		$items            = is_array( $sample['items'] ?? null ) ? $sample['items'] : array();
		$cloud_ready      = 'ready' === sanitize_key( $cloud_status );
		$indexed          = $cloud_ready ? $this->index_comment_classifications( $classifications ) : array();
		$selected         = array();
		$blocked          = array();

		foreach ( $items as $item ) {
			$comment_id = absint( $item['comment_id'] ?? 0 );
			if ( 0 >= $comment_id ) {
				continue;
			}

			if ( ! $cloud_ready ) {
				$blocked[] = array(
					'comment_id'       => $comment_id,
					'blocked_reason'   => 'cloud_classification_unavailable',
					'suggested_action' => 'review_manually',
				);
				continue;
			}

			$classification = $indexed[ $comment_id ] ?? null;
			if ( null === $classification ) {
				$blocked[] = array(
					'comment_id'       => $comment_id,
					'blocked_reason'   => 'classification_missing',
					'suggested_action' => 'review_manually',
				);
				continue;
			}

			$selected[] = array(
				'comment_id'       => $comment_id,
				'post_id'          => absint( $item['post_id'] ?? 0 ),
				'post_title'       => sanitize_text_field( (string) ( $item['post_title'] ?? '' ) ),
				'author_name'      => sanitize_text_field( (string) ( $item['author_name'] ?? '' ) ),
				'author_url'       => esc_url_raw( (string) ( $item['author_url'] ?? '' ) ),
				'content'          => sanitize_textarea_field( (string) ( $item['content'] ?? '' ) ),
				'date_gmt'         => sanitize_text_field( (string) ( $item['date_gmt'] ?? '' ) ),
				'classification'   => $classification['classification'],
				'confidence'       => $classification['confidence'],
				'reasons'          => $classification['reasons'],
				'suggested_action' => $classification['suggested_action'],
			);
		}

		$pending_total = absint( $sample['pending_total'] ?? count( $items ) );

		return array(
			'contract_version'         => 'comment_moderation_review_set.v1',
			'write_posture'            => 'suggestion_only',
			'comment_status_unchanged' => true,
			'direct_wordpress_write'   => false,
			'proposal_created'         => false,
			'execution_created'        => false,
			'data_classification'      => 'pii',
			'cloud_status'             => $cloud_ready ? 'ready' : 'cloud_required',
			'snapshot_policy'          => sanitize_key( (string) ( $sample['snapshot_policy'] ?? 'pending_hold_approved_would_be_public_fields_only' ) ),
			'eligibility_summary'      => array(
				'pending_total'  => $pending_total,
				'sampled_count'  => count( $items ),
				'selected_count' => count( $selected ),
				'blocked_count'  => count( $blocked ),
				'sampled_status' => 'hold',
				'sample_limit'   => max( 1, min( 50, absint( $sample['limit'] ?? 50 ) ) ),
			),
			'selected_items'           => $selected,
			'blocked_items'            => $blocked,
			'operator_next_action'     => $cloud_ready
				? 'Review spam and uncertain rows, then handle them in the native WordPress moderation queue.'
				: 'Connect the Cloud runtime, then rerun the comment moderation review.',
			'retryable'                => ! $cloud_ready,
			'retry_guidance'           => $cloud_ready
				? 'Rerun the review after moderating to refresh the pending sample.'
				: 'Cloud classification is required; no local fallback classification exists. Retry after the Cloud Addon runtime is available.',
		);
	}

	private function index_comment_classifications( array $classifications ): array {
		$indexed = array();
		foreach ( $classifications as $classification ) {
			if ( ! is_array( $classification ) ) {
				continue;
			}
			$comment_id = absint( $classification['comment_id'] ?? 0 );
			if ( 0 >= $comment_id || isset( $indexed[ $comment_id ] ) ) {
				continue;
			}

			$value = sanitize_key( (string) ( $classification['classification'] ?? '' ) );
			if ( ! in_array( $value, self::CLASSIFICATION_VALUES, true ) ) {
				$value = 'uncertain';
			}
			$action = sanitize_key( (string) ( $classification['suggested_action'] ?? '' ) );
			if ( ! in_array( $action, self::SUGGESTED_ACTION_VALUES, true ) ) {
				$action = 'review_manually';
			}
			$confidence = (float) ( $classification['confidence'] ?? 0 );
			if ( 0 > $confidence || 1 < $confidence ) {
				$confidence = 0;
			}

			$indexed[ $comment_id ] = array(
				'classification'   => $value,
				'confidence'       => $confidence,
				'reasons'          => $this->sanitize_string_list( $classification['reasons'] ?? array() ),
				'suggested_action' => $action,
			);
		}

		return $indexed;
	}
}
