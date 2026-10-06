<?php
/**
 * Media ALT/caption review-set service for the provider client.
 *
 * Builds the metadata-only media_alt_caption_review_set.v1 artifacts for
 * editor current-article review and explicit backend review sets, including
 * candidate quality assessment, rejection screening, and bounded Cloud
 * visual-evidence reuse. Suggestion-only: no media metadata write, import,
 * approval, or queue ownership lives here.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

final class Provider_Media_Alt_Caption_Service extends Provider_Client_Support {
	private Provider_Client $client;

	public function __construct( Settings $settings, Provider_Client $client ) {
		parent::__construct( $settings );
		$this->client = $client;
	}

	public function local_media_alt_caption_review_response( array $runtime_payload, array $review_set, string $cloud_status = 'optional_not_requested' ): array {
		$quality_contract = $this->client->hosted_ai_site_helper_quality_contract( 'media_alt_suggestions' );

		return $this->with_output_contract(
			array(
				'provider'                     => 'local_metadata_review',
				'cloud_runtime'                => 'optional',
				'cloud_enrichment_status'      => sanitize_key( $cloud_status ),
				'cloud_ability'                => sanitize_text_field( (string) ( $runtime_payload['ability_name'] ?? 'npcink-toolbox/ai-site-helper' ) ),
				'contract_version'             => sanitize_text_field( (string) ( $runtime_payload['contract_version'] ?? 'hosted_ai_site_helper.v1' ) ),
				'intent'                       => 'media_alt_suggestions',
				'status'                       => 'ready_local',
				'output_text'                  => '',
				'result'                       => array(),
				'quality_contract'             => $this->sanitize_payload( $quality_contract ),
				'output_shape'                 => $this->sanitize_payload( $quality_contract['output_shape'] ?? array() ),
				'review_checklist'             => $this->sanitize_string_list( $quality_contract['review_checklist'] ?? array() ),
				'reject_if'                    => $this->sanitize_string_list( $quality_contract['reject_if'] ?? array() ),
				'media_alt_caption_review_set' => $this->sanitize_payload( $review_set ),
				'write_posture'                => 'suggestion_only',
				'final_write_path'             => 'future_core_contract_required',
				'direct_wordpress_write'       => false,
				'handoff'                      => array(
					'core_submission'        => 'not_available_from_preview',
					'direct_wordpress_write' => false,
				),
			),
			'hosted_ai_site_helper',
			'hosted_ai_site_helper'
		);
	}


	public function build_media_alt_caption_review_set( array $media_snapshot, int $max_items, array $image_context_evidence = array() ): array {
		$toolkit_review_set = $this->build_media_alt_caption_review_set_from_toolkit( $media_snapshot, $max_items, $image_context_evidence );
		if ( is_array( $toolkit_review_set ) ) {
			return $toolkit_review_set;
		}

		$items                        = is_array( $media_snapshot['items'] ?? null ) ? $media_snapshot['items'] : array();
		$image_context_evidence_by_id = $this->media_alt_caption_index_image_context_evidence( $image_context_evidence );
		$source_policy                = $this->media_alt_caption_review_source_policy( $media_snapshot );
		$media_scope                  = sanitize_key( (string) ( $media_snapshot['media_scope'] ?? ( 'current_article_media_metadata_only' === (string) ( $media_snapshot['snapshot_policy'] ?? '' ) ? 'current_article_used_images' : 'media_library_sample' ) ) );
		$post_context                 = is_array( $media_snapshot['post_context'] ?? null ) ? $this->sanitize_payload( $media_snapshot['post_context'] ) : array();
		$selected                     = array();
		$blocked                      = array();
		$scanned                      = 0;

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			++$scanned;
			$attachment_id = absint( $item['attachment_id'] ?? 0 );
			if ( 0 >= $attachment_id ) {
				$blocked[] = array(
					'attachment_id'        => 0,
					'status'               => 'blocked',
					'blocked_reason'       => 'missing_attachment_id',
					'operator_next_action' => 'skip_or_adjust_media_snapshot',
				);
				continue;
			}

			$item_evidence = $image_context_evidence_by_id[ $attachment_id ] ?? array();
			if ( ! empty( $item_evidence ) ) {
				$item = $this->media_alt_caption_apply_image_context_evidence( $item, $item_evidence );
			}
			$item_status = $this->media_alt_caption_item_status( $item );
			if ( empty( $item_status['review_reasons'] ) ) {
				$blocked[] = array(
					'attachment_id'          => $attachment_id,
					'status'                 => 'blocked',
					'blocked_reason'         => 'metadata_complete_for_p0',
					'current_alt_status'     => $item_status['current_alt_status'],
					'current_caption_status' => $item_status['current_caption_status'],
					'operator_next_action'   => 'skip_or_adjust_filters',
				);
				continue;
			}

			$candidate_quality = $this->media_alt_caption_candidate_quality( $item, $item_status );
			if ( empty( $candidate_quality['alt_candidates'] ) && '' === (string) ( $candidate_quality['caption_candidate'] ?? '' ) ) {
				$blocked[] = array(
					'attachment_id'              => $attachment_id,
					'status'                     => 'blocked',
					'blocked_reason'             => 'candidate_quality_insufficient',
					'current_alt_status'         => $item_status['current_alt_status'],
					'current_caption_status'     => $item_status['current_caption_status'],
					'review_reasons'             => $item_status['review_reasons'],
					'title'                      => sanitize_text_field( (string) ( $item['title'] ?? '' ) ),
					'filename'                   => sanitize_file_name( (string) ( $item['filename'] ?? '' ) ),
					'thumbnail_url'              => esc_url_raw( (string) ( $item['thumbnail_url'] ?? '' ) ),
					'url'                        => esc_url_raw( (string) ( $item['url'] ?? '' ) ),
					'mime_type'                  => sanitize_text_field( (string) ( $item['mime_type'] ?? '' ) ),
					'candidate_quality_flags'    => $candidate_quality['candidate_quality_flags'],
					'filtered_candidate_notes'   => $candidate_quality['filtered_candidate_notes'],
					'candidate_fact_types'       => $candidate_quality['candidate_fact_types'],
					'candidate_confidence'       => $candidate_quality['candidate_confidence'],
					'candidate_review_status'    => $candidate_quality['candidate_review_status'],
					'needs_context_confirmation' => $candidate_quality['needs_context_confirmation'],
					'candidate_quality'          => $candidate_quality['candidate_quality'],
					'candidate_quality_score'    => $candidate_quality['candidate_quality_score'],
					'candidate_quality_tier'     => $candidate_quality['candidate_quality_tier'],
					'automation_recommendation'  => $candidate_quality['automation_recommendation'],
					'visual_evidence_required'   => $candidate_quality['visual_evidence_required'],
					'operator_next_action'       => 'request_ai_vision_evidence_or_skip',
				);
				continue;
			}

			if ( count( $selected ) >= $max_items ) {
				$blocked[] = array(
					'attachment_id'          => $attachment_id,
					'status'                 => 'blocked',
					'blocked_reason'         => 'selection_limit_reached',
					'current_alt_status'     => $item_status['current_alt_status'],
					'current_caption_status' => $item_status['current_caption_status'],
					'operator_next_action'   => 'review_current_selection_then_rebuild',
				);
				continue;
			}

			$selected[] = array_merge(
				array(
					'id'                         => 'media-alt-caption:' . $attachment_id,
					'attachment_id'              => $attachment_id,
					'object_type'                => 'attachment',
					'status'                     => 'selected',
					'result_ref'                 => 'attachment:' . $attachment_id,
					'title'                      => sanitize_text_field( (string) ( $item['title'] ?? '' ) ),
					'filename'                   => sanitize_file_name( (string) ( $item['filename'] ?? '' ) ),
					'thumbnail_url'              => esc_url_raw( (string) ( $item['thumbnail_url'] ?? '' ) ),
					'url'                        => esc_url_raw( (string) ( $item['url'] ?? '' ) ),
					'current_alt'                => sanitize_text_field( (string) ( $item['alt'] ?? '' ) ),
					'current_caption'            => sanitize_textarea_field( (string) ( $item['caption'] ?? '' ) ),
					'alt_candidates'             => $candidate_quality['alt_candidates'],
					'caption_candidate'          => $candidate_quality['caption_candidate'],
					'candidate_basis'            => $candidate_quality['candidate_basis'],
					'candidate_quality_flags'    => $candidate_quality['candidate_quality_flags'],
					'filtered_candidate_notes'   => $candidate_quality['filtered_candidate_notes'],
					'candidate_fact_types'       => $candidate_quality['candidate_fact_types'],
					'candidate_confidence'       => $candidate_quality['candidate_confidence'],
					'candidate_review_status'    => $candidate_quality['candidate_review_status'],
					'needs_context_confirmation' => $candidate_quality['needs_context_confirmation'],
					'candidate_quality'          => $candidate_quality['candidate_quality'],
					'candidate_quality_score'    => $candidate_quality['candidate_quality_score'],
					'candidate_quality_tier'     => $candidate_quality['candidate_quality_tier'],
					'automation_recommendation'  => $candidate_quality['automation_recommendation'],
					'visual_evidence_required'   => $candidate_quality['visual_evidence_required'],
					'image_context_evidence'     => ! empty( $item_evidence ) ? $this->media_alt_caption_public_image_context_evidence( $item_evidence ) : array(),
					'needs_human_visual_check'   => true,
					'target_write_path'          => 'core_proposal_required',
					'direct_wordpress_write'     => false,
					'operator_next_action'       => $candidate_quality['operator_next_action'],
				),
				$item_status
			);
		}

		$quality_summary = $this->media_alt_caption_review_quality_summary( $selected, $blocked );
		return array(
			'contract_version'               => 'media_alt_caption_review_set.v1',
			'artifact_type'                  => 'media_alt_caption_review_set',
			'mode'                           => 'governed_review_set',
			'runtime_owner'                  => 'toolbox',
			'write_posture'                  => 'suggestion_only',
			'final_write_path'               => 'core_proposal_required',
			'direct_wordpress_write'         => false,
			'proposal_created'               => false,
			'execution_created'              => false,
			'source_policy'                  => $source_policy,
			'media_scope'                    => $media_scope,
			'post_context'                   => $post_context,
			'eligibility_summary'            => array(
				'scanned_count'  => $scanned,
				'eligible_count' => count( $selected ) + count(
					array_filter(
						$blocked,
						static function ( array $item ): bool {
							return 'selection_limit_reached' === ( $item['blocked_reason'] ?? '' );
						}
					)
				),
				'selected_count' => count( $selected ),
				'blocked_count'  => count( $blocked ),
				'max_items'      => $max_items,
			) + $quality_summary,
			'selected_items'                 => $selected,
			'blocked_items'                  => $blocked,
			'image_context_evidence_request' => $this->media_alt_caption_image_context_evidence_request( $blocked, $max_items ),
			'operator_next_action'           => 'review_selected_alt_caption_suggestions',
			'retryable'                      => true,
			'retry_guidance'                 => array(
				'retryable'            => true,
				'reason'               => 'review_set_can_be_rebuilt',
				'operator_next_action' => 'adjust_focus_or_media_filters_then_rebuild',
			),
			'safety'                         => array(
				'local_queue_created'          => false,
				'core_proposal_created'        => false,
				'direct_wordpress_write'       => false,
				'media_derivative_run_created' => false,
				'requires_human_visual_check'  => true,
			),
			'handoff'                        => array(
				'current_stage'               => 'review_only',
				'future_apply_path'           => 'Core proposal only after a media metadata WordPress ability contract exists.',
				'blocked_direct_apply_reason' => 'Toolbox does not own media metadata writes.',
			),
		);
	}


	private function build_media_alt_caption_review_set_from_toolkit( array $media_snapshot, int $max_items, array $image_context_evidence ) {
		$ability_id = 'npcink-abilities-toolkit/build-media-alt-caption-review-set';
		if ( ! function_exists( 'npcink_abilities_toolkit_get_registered' ) ) {
			return null;
		}

		$registered = npcink_abilities_toolkit_get_registered();
		$ability    = is_array( $registered ) ? ( $registered[ $ability_id ] ?? null ) : null;
		$callback   = is_array( $ability ) ? ( $ability['execute_callback'] ?? null ) : null;
		if ( ! is_callable( $callback ) ) {
			return null;
		}

		$result = call_user_func(
			$callback,
			array(
				'media_snapshot'         => $media_snapshot,
				'review_set_limit'       => $max_items,
				'image_context_evidence' => $image_context_evidence,
			)
		);
		if ( is_wp_error( $result ) || ! is_array( $result ) ) {
			return null;
		}

		$data = is_array( $result['data'] ?? null ) ? $result['data'] : $result;
		if (
			'media_alt_caption_review_set.v1' !== (string) ( $data['contract_version'] ?? '' )
			|| 'media_alt_caption_review_set' !== (string) ( $data['artifact_type'] ?? '' )
			|| false !== (bool) ( $data['direct_wordpress_write'] ?? true )
		) {
			return null;
		}

		return $data;
	}


	private function media_alt_caption_review_source_policy( array $media_snapshot ): string {
		$snapshot_policy = sanitize_key( (string) ( $media_snapshot['snapshot_policy'] ?? '' ) );
		if ( 'current_article_media_metadata_only' === $snapshot_policy ) {
			return 'current_article_media_metadata_only_no_pixel_vision';
		}
		if ( 'operator_supplied_media_metadata_only' === $snapshot_policy ) {
			return 'operator_supplied_media_metadata_only_no_pixel_vision';
		}

		return 'media_library_metadata_only_no_pixel_vision';
	}


	public function maybe_request_media_alt_caption_image_context_evidence( array $review_set ): array {
		$request = is_array( $review_set['image_context_evidence_request'] ?? null ) ? $review_set['image_context_evidence_request'] : array();
		return $this->client->resolve_media_image_context_evidence( $request, true );
	}


	private function media_alt_caption_index_image_context_evidence( array $image_context_evidence ): array {
		if (
			'image_context_evidence.v1' !== (string) ( $image_context_evidence['contract_version'] ?? '' )
			|| 'suggestion_only' !== (string) ( $image_context_evidence['write_posture'] ?? '' )
			|| false !== (bool) ( $image_context_evidence['direct_wordpress_write'] ?? true )
		) {
			return array();
		}

		$items   = is_array( $image_context_evidence['items'] ?? null ) ? $image_context_evidence['items'] : array();
		$indexed = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$attachment_id = absint( $item['attachment_id'] ?? 0 );
			if ( 0 >= $attachment_id ) {
				continue;
			}
			$item['contract_version']         = 'image_context_evidence.v1';
			$item['source']                   = sanitize_key( (string) ( $item['source'] ?? 'cloud_or_host_runtime' ) );
			$item['write_posture']            = 'suggestion_only';
			$item['direct_wordpress_write']   = false;
			$item['needs_human_visual_check'] = true;
			$indexed[ $attachment_id ]        = $this->sanitize_payload( $item );
		}

		return $indexed;
	}


	private function media_alt_caption_apply_image_context_evidence( array $item, array $evidence ): array {
		$summary = $this->media_alt_caption_clean_candidate( (string) ( $evidence['visual_summary'] ?? ( $evidence['alt_text_basis'] ?? '' ) ) );
		$scene   = $this->media_alt_caption_clean_candidate( (string) ( $evidence['scene'] ?? '' ) );
		$objects = $this->sanitize_string_list( $evidence['objects'] ?? ( $evidence['subject_tags'] ?? array() ) );
		$text    = $this->sanitize_string_list( $evidence['text_seen'] ?? ( $evidence['visible_text'] ?? array() ) );

		if ( '' !== $summary ) {
			$item['image_context_visual_summary'] = $summary;
		}
		if ( '' !== $scene ) {
			$item['image_context_scene'] = $scene;
		}
		if ( ! empty( $objects ) ) {
			$item['image_context_objects_summary'] = implode( ', ', array_slice( $objects, 0, 8 ) );
		}
		if ( ! empty( $text ) ) {
			$item['image_context_text_seen'] = implode( ', ', array_slice( $text, 0, 5 ) );
		}

		return $item;
	}


	private function media_alt_caption_public_image_context_evidence( array $evidence ): array {
		return array(
			'contract_version'         => 'image_context_evidence.v1',
			'source'                   => sanitize_key( (string) ( $evidence['source'] ?? 'cloud_or_host_runtime' ) ),
			'visual_summary'           => $this->trim_chars( $this->media_alt_caption_clean_candidate( (string) ( $evidence['visual_summary'] ?? ( $evidence['alt_text_basis'] ?? '' ) ) ), 180 ),
			'scene'                    => $this->trim_chars( $this->media_alt_caption_clean_candidate( (string) ( $evidence['scene'] ?? '' ) ), 120 ),
			'objects'                  => array_slice( $this->sanitize_string_list( $evidence['objects'] ?? ( $evidence['subject_tags'] ?? array() ) ), 0, 8 ),
			'text_seen'                => array_slice( $this->sanitize_string_list( $evidence['text_seen'] ?? ( $evidence['visible_text'] ?? array() ) ), 0, 5 ),
			'confidence'               => sanitize_text_field( (string) ( $evidence['confidence'] ?? '' ) ),
			'write_posture'            => 'suggestion_only',
			'direct_wordpress_write'   => false,
			'needs_human_visual_check' => true,
		);
	}


	private function media_alt_caption_image_context_evidence_request( array $blocked, int $max_items ): array {
		$items = array();
		foreach ( $blocked as $item ) {
			if ( ! is_array( $item ) || 'candidate_quality_insufficient' !== (string) ( $item['blocked_reason'] ?? '' ) ) {
				continue;
			}
			$attachment_id = absint( $item['attachment_id'] ?? 0 );
			if ( 0 >= $attachment_id ) {
				continue;
			}
			$items[] = array(
				'attachment_id'            => $attachment_id,
				'title'                    => sanitize_text_field( (string) ( $item['title'] ?? '' ) ),
				'filename'                 => sanitize_file_name( (string) ( $item['filename'] ?? '' ) ),
				'thumbnail_url'            => esc_url_raw( (string) ( $item['thumbnail_url'] ?? '' ) ),
				'url'                      => esc_url_raw( (string) ( $item['url'] ?? '' ) ),
				'mime_type'                => sanitize_text_field( (string) ( $item['mime_type'] ?? '' ) ),
				'current_alt_status'       => sanitize_key( (string) ( $item['current_alt_status'] ?? '' ) ),
				'current_caption_status'   => sanitize_key( (string) ( $item['current_caption_status'] ?? '' ) ),
				'candidate_quality_flags'  => $this->sanitize_string_list( $item['candidate_quality_flags'] ?? array() ),
				'filtered_candidate_notes' => $this->sanitize_string_list( $item['filtered_candidate_notes'] ?? array() ),
			);
			if ( count( $items ) >= min( 10, max( 1, $max_items ) ) ) {
				break;
			}
		}

		if ( empty( $items ) ) {
			return array();
		}

		return array(
			'contract_version'           => 'image_context_evidence_request.v1',
			'artifact_type'              => 'image_context_evidence_request',
			'runtime_owner'              => 'cloud_or_host_runtime',
			'write_posture'              => 'suggestion_only',
			'direct_wordpress_write'     => false,
			'proposal_created'           => false,
			'execution_created'          => false,
			'no_local_model'             => true,
			'no_media_write'             => true,
			'source_policy'              => 'bounded_media_urls_for_visual_context_only',
			'expected_response_contract' => 'image_context_evidence.v1',
			'requested_count'            => count( $items ),
			'max_items'                  => min( 10, max( 1, $max_items ) ),
			'items'                      => $items,
			'operator_next_action'       => 'request_cloud_image_context_evidence',
		);
	}


	private function media_alt_caption_item_status( array $item ): array {
		$alt     = trim( sanitize_text_field( (string) ( $item['alt'] ?? '' ) ) );
		$caption = trim( sanitize_textarea_field( (string) ( $item['caption'] ?? '' ) ) );
		$title   = trim( sanitize_text_field( (string) ( $item['title'] ?? '' ) ) );

		$current_alt_status = 'present';
		$review_reasons     = array();
		if ( '' === $alt ) {
			$current_alt_status = 'missing';
			$review_reasons[]   = 'missing_alt';
		} elseif ( $this->media_alt_caption_candidate_is_too_short( $alt ) || $this->media_alt_caption_is_filename_like( $alt, $item ) ) {
			$current_alt_status = 'weak';
			$review_reasons[]   = 'weak_alt';
		}

		$current_caption_status = '' === $caption ? 'missing' : 'present';
		if ( '' === $caption ) {
			$review_reasons[] = 'missing_caption';
		}
		if ( '' !== $title && $this->media_alt_caption_is_filename_like( $title, $item ) ) {
			$review_reasons[] = 'filename_like_title';
		}

		return array(
			'current_alt_status'     => $current_alt_status,
			'current_caption_status' => $current_caption_status,
			'review_reasons'         => array_values( array_unique( $review_reasons ) ),
		);
	}


	private function media_alt_caption_candidate_quality( array $item, array $item_status ): array {
		$flags                      = array();
		$notes                      = array();
		$basis                      = array();
		$fact_types                 = array();
		$alt_candidates             = array();
		$caption_candidate          = '';
		$candidate_confidence       = 'low';
		$needs_context_confirmation = false;

		if ( 'present' !== (string) ( $item_status['current_alt_status'] ?? '' ) ) {
			foreach ( array( 'image_context_visual_summary', 'image_context_scene', 'image_context_objects_summary', 'description', 'caption', 'title', 'filename' ) as $field ) {
				$value     = 'filename' === $field
					? $this->media_alt_caption_filename_descriptor( (string) ( $item['filename'] ?? '' ) )
					: (string) ( $item[ $field ] ?? '' );
				$candidate = $this->media_alt_caption_clean_candidate( $value );
				if ( '' === $candidate ) {
					continue;
				}
				$rejection = $this->media_alt_caption_candidate_rejection_reason( $candidate, $item, 'alt' );
				if ( '' !== $rejection ) {
					$flags[] = $rejection;
					$notes[] = 'filtered_alt_' . $field . ':' . $rejection;
					continue;
				}
				$context_profile            = $this->media_alt_caption_candidate_context_profile( $candidate, $item, $field );
				$flags                      = array_merge( $flags, $context_profile['candidate_quality_flags'] );
				$notes                      = array_merge( $notes, $context_profile['filtered_candidate_notes'] );
				$fact_types                 = array_merge( $fact_types, $context_profile['candidate_fact_types'] );
				$candidate_confidence       = $this->media_alt_caption_merge_candidate_confidence( $candidate_confidence, (string) $context_profile['candidate_confidence'] );
				$needs_context_confirmation = $needs_context_confirmation || (bool) $context_profile['needs_context_confirmation'];
				$alt_candidates[]           = $this->trim_chars( $candidate, 140 );
				$basis[]                    = 'alt:' . $field;
			}
		}

		if ( 'missing' === (string) ( $item_status['current_caption_status'] ?? '' ) ) {
			foreach ( array( 'image_context_visual_summary', 'image_context_scene', 'description', 'alt', 'title' ) as $field ) {
				$candidate = $this->media_alt_caption_clean_candidate( (string) ( $item[ $field ] ?? '' ) );
				if ( '' === $candidate ) {
					continue;
				}
				$rejection = $this->media_alt_caption_candidate_rejection_reason( $candidate, $item, 'caption' );
				if ( '' !== $rejection ) {
					$flags[] = $rejection;
					$notes[] = 'filtered_caption_' . $field . ':' . $rejection;
					continue;
				}
				$context_profile            = $this->media_alt_caption_candidate_context_profile( $candidate, $item, $field );
				$flags                      = array_merge( $flags, $context_profile['candidate_quality_flags'] );
				$notes                      = array_merge( $notes, $context_profile['filtered_candidate_notes'] );
				$fact_types                 = array_merge( $fact_types, $context_profile['candidate_fact_types'] );
				$candidate_confidence       = $this->media_alt_caption_merge_candidate_confidence( $candidate_confidence, (string) $context_profile['candidate_confidence'] );
				$needs_context_confirmation = $needs_context_confirmation || (bool) $context_profile['needs_context_confirmation'];
				$caption_candidate          = $this->trim_chars( $this->media_alt_caption_sentence( $candidate ), 180 );
				$basis[]                    = 'caption:' . $field;
				break;
			}
		} else {
			$flags[] = 'caption_redundant';
			$notes[] = 'filtered_caption_existing:caption_redundant';
		}

		if ( empty( $alt_candidates ) && '' === $caption_candidate ) {
			$flags[] = 'metadata_insufficient';
		}

		$alt_candidates          = array_slice( array_values( array_unique( array_filter( $alt_candidates ) ) ), 0, 2 );
		$candidate_review_status = $needs_context_confirmation ? 'needs_context_confirmation' : 'ready_for_review';
		$operator_next_action    = $needs_context_confirmation ? 'confirm_context_terms_or_edit_alt' : 'visually_review_alt_caption';
		if ( empty( $alt_candidates ) && '' !== $caption_candidate ) {
			$candidate_review_status = 'caption_review_only';
			$operator_next_action    = 'review_caption_manually_or_skip_alt_handoff';
		}

		$assessment = $this->media_alt_caption_candidate_quality_assessment(
			$alt_candidates,
			$caption_candidate,
			$flags,
			$fact_types,
			$candidate_confidence,
			$candidate_review_status,
			$needs_context_confirmation
		);

		return array(
			'alt_candidates'             => $alt_candidates,
			'caption_candidate'          => $caption_candidate,
			'candidate_basis'            => array_values( array_unique( $basis ) ),
			'candidate_quality_flags'    => array_values( array_unique( array_filter( $flags ) ) ),
			'filtered_candidate_notes'   => array_values( array_unique( array_filter( $notes ) ) ),
			'candidate_fact_types'       => array_values( array_unique( array_filter( $fact_types ) ) ),
			'candidate_confidence'       => $needs_context_confirmation ? 'context_required' : $candidate_confidence,
			'candidate_review_status'    => $candidate_review_status,
			'needs_context_confirmation' => $needs_context_confirmation,
			'candidate_quality'          => $assessment,
			'candidate_quality_score'    => $assessment['score'],
			'candidate_quality_tier'     => $assessment['tier'],
			'automation_recommendation'  => $assessment['automation_recommendation'],
			'visual_evidence_required'   => $assessment['visual_evidence_required'],
			'operator_next_action'       => $operator_next_action,
		);
	}


	private function media_alt_caption_candidate_quality_assessment( array $alt_candidates, string $caption_candidate, array $flags, array $fact_types, string $confidence, string $candidate_review_status, bool $needs_context_confirmation ): array {
		$has_alt     = ! empty( $alt_candidates );
		$has_caption = '' !== $caption_candidate;
		$fact_types  = array_values( array_unique( array_filter( $fact_types ) ) );
		$flags       = array_values( array_unique( array_filter( $flags ) ) );

		$score                     = 60;
		$tier                      = 'review';
		$basis_summary             = 'context_only';
		$visual_evidence_required  = false;
		$automation_recommendation = 'visually_review_alt_caption';

		if ( ! $has_alt && ! $has_caption ) {
			$score                     = 0;
			$tier                      = 'insufficient';
			$basis_summary             = 'insufficient_metadata';
			$visual_evidence_required  = true;
			$automation_recommendation = 'request_visual_evidence_or_skip';
		} elseif ( 'caption_review_only' === $candidate_review_status ) {
			$score                     = 35;
			$tier                      = 'caption_only';
			$basis_summary             = 'caption_only';
			$automation_recommendation = 'review_caption_manually_or_skip_alt_handoff';
		} elseif ( $needs_context_confirmation ) {
			$score                     = 50;
			$tier                      = 'context_required';
			$basis_summary             = 'context_requires_confirmation';
			$automation_recommendation = 'confirm_context_terms_or_edit_alt';
		} elseif ( in_array( 'visual_fact', $fact_types, true ) && $has_alt ) {
			$score                     = 90;
			$tier                      = 'ready';
			$basis_summary             = 'visual_evidence';
			$automation_recommendation = 'eligible_for_local_preview_after_visual_check';
		} elseif ( in_array( 'metadata_fact', $fact_types, true ) && $has_alt ) {
			$score                     = 75;
			$tier                      = 'ready';
			$basis_summary             = 'metadata_evidence';
			$automation_recommendation = 'eligible_for_local_preview_after_visual_check';
		} elseif ( $has_alt ) {
			$score                     = 55;
			$tier                      = 'review';
			$basis_summary             = 'context_only';
			$automation_recommendation = 'visually_review_or_request_visual_evidence';
		}

		return array(
			'score'                     => $score,
			'tier'                      => $tier,
			'basis_summary'             => $basis_summary,
			'primary_alt_candidate'     => $has_alt ? (string) $alt_candidates[0] : '',
			'automation_recommendation' => $automation_recommendation,
			'visual_evidence_required'  => $visual_evidence_required,
			'confidence'                => $needs_context_confirmation ? 'context_required' : sanitize_key( $confidence ),
			'fact_types'                => $fact_types,
			'flags'                     => $flags,
		);
	}


	private function media_alt_caption_review_quality_summary( array $selected, array $blocked ): array {
			$summary = array(
				'local_preview_candidate_count' => 0,
				'context_confirmation_count'    => 0,
				'caption_review_only_count'     => 0,
				'visual_evidence_request_count' => 0,
				'insufficient_quality_count'    => 0,
			);

			foreach ( $selected as $item ) {
				$quality = is_array( $item['candidate_quality'] ?? null ) ? $item['candidate_quality'] : array();
				$tier    = sanitize_key( (string) ( $quality['tier'] ?? ( $item['candidate_quality_tier'] ?? '' ) ) );
				if ( 'ready' === $tier ) {
					++$summary['local_preview_candidate_count'];
				} elseif ( 'context_required' === $tier ) {
					++$summary['context_confirmation_count'];
				} elseif ( 'caption_only' === $tier ) {
					++$summary['caption_review_only_count'];
				}
			}

			foreach ( $blocked as $item ) {
				if ( 'candidate_quality_insufficient' !== (string) ( $item['blocked_reason'] ?? '' ) ) {
					continue;
				}
				++$summary['insufficient_quality_count'];
				$quality = is_array( $item['candidate_quality'] ?? null ) ? $item['candidate_quality'] : array();
				if ( true === (bool) ( $quality['visual_evidence_required'] ?? ( $item['visual_evidence_required'] ?? false ) ) ) {
					++$summary['visual_evidence_request_count'];
				}
			}

			return $summary;
	}


	public function media_alt_caption_candidate_rejection_reason( string $candidate, array $item, string $target_field ): string {
		$candidate = $this->media_alt_caption_clean_candidate( $candidate );
		if ( '' === $candidate ) {
			return 'metadata_insufficient';
		}
		if ( $this->media_alt_caption_is_runtime_provenance_text( $candidate ) ) {
			return 'runtime_provenance';
		}
		if ( $this->media_alt_caption_is_url_or_source_text( $candidate ) ) {
			return 'source_attribution_or_url';
		}
		if ( $this->media_alt_caption_is_camera_default( $candidate ) ) {
			return 'camera_default';
		}
		if ( $this->media_alt_caption_is_filename_like( $candidate, $item ) ) {
			return 'filename_like';
		}
		if ( $this->media_alt_caption_is_duplicate_metadata( $candidate, $item, $target_field ) ) {
			return 'caption' === $target_field ? 'caption_redundant' : 'metadata_duplicate';
		}
		if ( $this->media_alt_caption_is_too_generic_candidate( $candidate ) ) {
			return 'too_generic';
		}
		if ( $this->media_alt_caption_has_metadata_conflict( $candidate, $item ) ) {
			return 'metadata_conflict';
		}
		if ( $this->media_alt_caption_candidate_is_too_short( $candidate ) ) {
			return 'too_generic';
		}

		return '';
	}


	private function media_alt_caption_candidate_context_profile( string $candidate, array $item, string $source_field ): array {
		$source_field               = sanitize_key( $source_field );
		$is_visual_evidence         = 0 === strpos( $source_field, 'image_context_' );
		$fact_types                 = array();
		$flags                      = array();
		$notes                      = array();
		$confidence                 = 'low';
		$needs_context_confirmation = false;

		if ( $is_visual_evidence ) {
			$fact_types[] = 'visual_fact';
			$confidence   = 'high';
		} elseif ( in_array( $source_field, array( 'alt', 'caption', 'description' ), true ) ) {
			$fact_types[] = 'metadata_fact';
			$confidence   = 'medium';
		} else {
			$fact_types[] = 'context_only';
			$confidence   = 'low';
		}

		if ( ! $is_visual_evidence && $this->media_alt_caption_candidate_needs_context_confirmation( $candidate ) ) {
			$needs_context_confirmation = true;
			$fact_types[]               = 'context_only';
			$flags[]                    = 'needs_context_confirmation';
			$notes[]                    = 'context_' . $source_field . ':needs_context_confirmation';
		}

		return array(
			'candidate_fact_types'       => array_values( array_unique( $fact_types ) ),
			'candidate_quality_flags'    => $flags,
			'filtered_candidate_notes'   => $notes,
			'candidate_confidence'       => $confidence,
			'needs_context_confirmation' => $needs_context_confirmation,
		);
	}


	private function media_alt_caption_merge_candidate_confidence( string $current, string $next ): string {
		$ranks        = array(
			'low'    => 1,
			'medium' => 2,
			'high'   => 3,
		);
		$current_rank = $ranks[ $current ] ?? 0;
		$next_rank    = $ranks[ $next ] ?? 0;

		return $next_rank > $current_rank ? $next : $current;
	}


	public function media_alt_caption_candidate_needs_context_confirmation( string $candidate ): bool {
		$candidate = $this->media_alt_caption_clean_candidate( $candidate );
		if ( '' === $candidate ) {
			return false;
		}
		if ( preg_match( '/\b(in|near|at|outside of|from)\s+[A-Z][A-Za-z]+(?:\s+[A-Z][A-Za-z]+){0,3}\b/', $candidate ) ) {
			return true;
		}
		if ( preg_match( '/,\s*[A-Z][A-Za-z]+(?:\s+[A-Z][A-Za-z]+)?\b/', $candidate ) ) {
			return true;
		}

		preg_match_all( '/\b[A-Z][a-z]{2,}\b/', $candidate, $matches );
		$terms = array();
		foreach ( (array) ( $matches[0] ?? array() ) as $term ) {
			$normalized = strtolower( (string) $term );
			if ( in_array( $normalized, array( 'abstract', 'approval', 'beach', 'big', 'image', 'rocky', 'rocks', 'sea', 'visual', 'windmill', 'wordpress' ), true ) ) {
				continue;
			}
			$terms[] = $normalized;
		}

		return count( array_unique( $terms ) ) >= 2;
	}


	private function media_alt_caption_alt_candidates( array $item ): array {
		$current_alt = $this->media_alt_caption_clean_candidate( (string) ( $item['alt'] ?? '' ) );
		if ( '' !== $current_alt && $this->hosted_ai_text_length( $current_alt ) >= 18 && ! $this->media_alt_caption_is_filename_like( $current_alt, $item ) ) {
			return array( $this->trim_chars( $current_alt, 140 ) );
		}

		$descriptors = array_filter(
			array(
				$current_alt,
				$this->media_alt_caption_clean_candidate( (string) ( $item['description'] ?? '' ) ),
				$this->media_alt_caption_clean_candidate( (string) ( $item['caption'] ?? '' ) ),
				$this->media_alt_caption_clean_candidate( (string) ( $item['title'] ?? '' ) ),
				$this->media_alt_caption_clean_candidate( $this->media_alt_caption_filename_descriptor( (string) ( $item['filename'] ?? '' ) ) ),
			)
		);
		$candidates  = array();
		foreach ( $descriptors as $descriptor ) {
			if ( $this->media_alt_caption_is_filename_like( $descriptor, $item ) ) {
				continue;
			}
			$candidates[] = $this->trim_chars( $descriptor, 140 );
		}

		if ( empty( $candidates ) ) {
			$candidates[] = 'Add concise ALT text after visual review.';
		}

		return array_slice( array_values( array_unique( array_filter( $candidates ) ) ), 0, 2 );
	}


	private function media_alt_caption_caption_candidate( array $item ): string {
		$caption = trim( sanitize_textarea_field( (string) ( $item['caption'] ?? '' ) ) );
		if ( '' !== $caption ) {
			return $this->trim_chars( $caption, 180 );
		}

		$description = $this->media_alt_caption_clean_candidate( (string) ( $item['description'] ?? '' ) );
		if ( '' !== $description && ! $this->media_alt_caption_is_filename_like( $description, $item ) ) {
			return $this->trim_chars( $this->media_alt_caption_sentence( $description ), 180 );
		}

		$alt = $this->media_alt_caption_clean_candidate( (string) ( $item['alt'] ?? '' ) );
		if ( '' !== $alt && ! $this->media_alt_caption_is_filename_like( $alt, $item ) ) {
			return $this->trim_chars( $this->media_alt_caption_caption_from_alt( $alt ), 180 );
		}

		$title = $this->media_alt_caption_clean_candidate( (string) ( $item['title'] ?? '' ) );
		if ( '' !== $title && ! $this->media_alt_caption_is_filename_like( $title, $item ) ) {
			return $this->trim_chars( $this->media_alt_caption_sentence( $title ), 180 );
		}

		return 'Add a caption only if the image needs visible context beyond ALT.';
	}


	private function media_alt_caption_candidate_basis( array $item ): array {
		$basis = array();
		foreach ( array( 'image_context_visual_summary', 'image_context_scene', 'image_context_objects_summary', 'alt', 'description', 'caption', 'title', 'filename' ) as $field ) {
			$value = 'filename' === $field
				? $this->media_alt_caption_filename_descriptor( (string) ( $item['filename'] ?? '' ) )
				: (string) ( $item[ $field ] ?? '' );
			if ( '' !== $this->media_alt_caption_clean_candidate( $value ) ) {
				$basis[] = $field;
			}
		}

		return array_values( array_unique( $basis ) );
	}


	public function media_alt_caption_clean_candidate( string $value ): string {
		$value = trim( sanitize_textarea_field( wp_strip_all_tags( $value ) ) );
		$value = preg_replace( '/\s+/u', ' ', $value ) ?? $value;

		return trim( $value );
	}


	private function media_alt_caption_normalized_candidate( string $value ): string {
		$value = strtolower( $this->media_alt_caption_clean_candidate( $value ) );
		$value = preg_replace( '/https?:\/\/\S+/i', '', $value ) ?? $value;
		$value = preg_replace( '/\.[a-z0-9]{2,5}\b/i', '', $value ) ?? $value;
		$value = preg_replace( '/[^a-z0-9\x{4e00}-\x{9fff}]+/u', ' ', $value ) ?? $value;
		$value = preg_replace( '/\s+/u', ' ', $value ) ?? $value;

		return trim( $value );
	}


	private function media_alt_caption_is_duplicate_metadata( string $candidate, array $item, string $target_field ): bool {
		$normalized = $this->media_alt_caption_normalized_candidate( $candidate );
		if ( '' === $normalized ) {
			return false;
		}

		$fields = 'caption' === $target_field
			? array( 'title', 'alt', 'caption' )
			: array( 'alt', 'title' );
		foreach ( $fields as $field ) {
			$source = $this->media_alt_caption_normalized_candidate( (string) ( $item[ $field ] ?? '' ) );
			if ( '' !== $source && $normalized === $source ) {
				return true;
			}
		}

		return false;
	}


	private function media_alt_caption_is_url_or_source_text( string $value ): bool {
		$value = trim( $value );
		if ( preg_match( '/https?:\/\/|www\.|^\S+\.(com|net|org|cn|io|ai)(\/|$)/i', $value ) ) {
			return true;
		}

		return (bool) preg_match( '/\b(source|credit|credits|photo by|photograph by|image source|via|unsplash|pexels|pixabay|getty|shutterstock|istock)\b/i', $value );
	}


	private function media_alt_caption_is_runtime_provenance_text( string $value ): bool {
		$value = trim( $value );
		if ( '' === $value ) {
			return false;
		}

		$patterns = array(
			'/\b(generated|created|produced|made)\s+(by|with|using)\b/i',
			'/\b(prompt|model|provider|profile|seed|negative prompt)\s*:/i',
			'/\b(npcink cloud|cloud scene image|gpt|dall[- ]?e|midjourney|stable diffusion|flux|grok|imagen)\b.*\b(prompt|generated|created|using|model)\b/i',
			'/\busing\s+[A-Z][A-Za-z0-9 ._-]{2,80}\s+on\s+\d{4}[\/-]\d{1,2}/',
			'/^(由|通过|使用).{0,40}(生成|创建|模型|提示词)/u',
			'/(提示词|模型|由.*生成|由.*创建)\s*[:：]/u',
		);
		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $value ) ) {
				return true;
			}
		}

		return false;
	}


	private function media_alt_caption_is_camera_default( string $value ): bool {
		return (bool) preg_match( '/^(olympus digital camera|canon digital camera|nikon digital camera|dscn?\d+|img[_ -]?\d+|p\d{7}|sam_\d+|image[_ -]?\d+)$/i', trim( $value ) );
	}


	public function media_alt_caption_candidate_is_too_short( string $value ): bool {
		$normalized = $this->media_alt_caption_normalized_candidate( $value );
		if ( '' === $normalized ) {
			return true;
		}
		if ( preg_match( '/\p{Han}/u', $normalized ) ) {
			return $this->hosted_ai_text_length( $normalized ) < 4;
		}

		return $this->hosted_ai_text_length( $normalized ) < 18;
	}


	private function media_alt_caption_is_too_generic_candidate( string $value ): bool {
		$normalized = $this->media_alt_caption_normalized_candidate( $value );
		if ( '' === $normalized ) {
			return true;
		}
		if ( $this->media_alt_caption_candidate_is_too_short( $normalized ) ) {
			return true;
		}

		$generic_patterns = array(
			'/^(featured image|horizontal featured image|vertical featured image|hero image|image|photo|screenshot|visual|wallpaper)$/i',
			'/^(add|write|review|provide|create)\s+(concise\s+)?(alt|caption|description)/i',
			'/\b(add concise alt text|after visual review|needs visible context|image needs visible context)\b/i',
			'/\b(click here|read more|learn more|take this)\b/i',
			'/\blorem ipsum\b/i',
		);
		foreach ( $generic_patterns as $pattern ) {
			if ( preg_match( $pattern, $value ) ) {
				return true;
			}
		}

		return false;
	}


	private function media_alt_caption_has_metadata_conflict( string $candidate, array $item ): bool {
		$candidate = $this->media_alt_caption_normalized_candidate( $candidate );
		$evidence  = $this->media_alt_caption_normalized_candidate(
			implode(
				' ',
				array(
					(string) ( $item['title'] ?? '' ),
					(string) ( $item['alt'] ?? '' ),
					(string) ( $item['caption'] ?? '' ),
					(string) ( $item['description'] ?? '' ),
					$this->media_alt_caption_filename_descriptor( (string) ( $item['filename'] ?? '' ) ),
				)
			)
		);

		if ( '' === $candidate || '' === $evidence ) {
			return false;
		}

		$opposites = array(
			array( 'horizontal', 'vertical' ),
			array( 'portrait', 'landscape' ),
		);
		foreach ( $opposites as $pair ) {
			if ( false !== strpos( $candidate, $pair[0] ) && false !== strpos( $evidence, $pair[1] ) ) {
				return true;
			}
			if ( false !== strpos( $candidate, $pair[1] ) && false !== strpos( $evidence, $pair[0] ) ) {
				return true;
			}
		}

		return false;
	}


	private function media_alt_caption_sentence( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		if ( preg_match( '/[.!?。！？]$/u', $value ) ) {
			return $value;
		}

		return $value . '.';
	}


	private function media_alt_caption_caption_from_alt( string $alt ): string {
		$alt = preg_replace( '/\bcropped to\s+/i', '', $alt ) ?? $alt;
		$alt = preg_replace( '/\bvisual\b/i', 'image', $alt ) ?? $alt;
		$alt = preg_replace( '/\s+/', ' ', $alt ) ?? $alt;
		$alt = trim( $alt );
		if ( '' === $alt ) {
			return '';
		}
		if ( preg_match( '/\bhero image\b/i', $alt ) ) {
			return $this->media_alt_caption_sentence( $alt );
		}

		return $this->media_alt_caption_sentence( $alt );
	}


	private function media_alt_caption_filename_descriptor( string $filename ): string {
		$value = preg_replace( '/\.[a-z0-9]{2,5}$/i', '', $filename );
		$value = preg_replace( '/[-_]+/', ' ', is_string( $value ) ? $value : '' );
		$value = preg_replace( '/\b\d{2,5}x\d{2,5}\b/i', '', is_string( $value ) ? $value : '' );
		$value = preg_replace( '/\s+/', ' ', is_string( $value ) ? $value : '' );

		return sanitize_text_field( trim( (string) $value ) );
	}


	public function media_alt_caption_is_filename_like( string $value, array $item ): bool {
		$value = strtolower( trim( $value ) );
		if ( '' === $value ) {
			return false;
		}

		$filename = strtolower( $this->media_alt_caption_filename_descriptor( (string) ( $item['filename'] ?? '' ) ) );
		if ( '' !== $filename && $value === $filename ) {
			return true;
		}

		return (bool) preg_match( '/^(img|dsc|image|photo|screenshot|screen-shot)[-_ ]?\d+$/i', $value );
	}
}
