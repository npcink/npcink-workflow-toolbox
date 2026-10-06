<?php
/**
 * Provider_Agent_Feedback_Service extracted from Provider_Client.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Provider_Agent_Feedback_Service extends Provider_Client_Support {

	private Provider_Client $client;

	public function __construct( Settings $settings, Provider_Client $client ) {
		parent::__construct( $settings );
		$this->client = $client;
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

	private function agent_feedback_payload( array $input ) {
		$handoff          = is_array( $input['handoff'] ?? null ) ? $input['handoff'] : array();
		$proposal_input   = is_array( $handoff['proposal_input'] ?? null ) ? $handoff['proposal_input'] : array();
		$outcome          = sanitize_key( (string) ( $input['local_outcome'] ?? '' ) );
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

		$agent_id       = sanitize_key( (string) ( $input['agent_id'] ?? ( $handoff['agent_id'] ?? 'site_knowledge_suggestion_agent' ) ) );
		$handoff_type   = sanitize_key( (string) ( $input['handoff_type'] ?? ( $handoff['handoff_type'] ?? 'proposal_input' ) ) );
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
			'contract_version'    => 'cloud_agent_feedback.v1',
			'agent_id'            => $agent_id,
			'agent_version'       => sanitize_text_field( (string) ( $input['agent_version'] ?? ( $handoff['agent_version'] ?? '' ) ) ),
			'source_runtime'      => $source_runtime,
			'source_run_id'       => sanitize_text_field( (string) ( $input['source_run_id'] ?? ( $handoff['source_run_id'] ?? '' ) ) ),
			'handoff_id'          => $handoff_id,
			'handoff_type'        => $handoff_type,
			'local_surface'       => sanitize_key( (string) ( $input['local_surface'] ?? 'toolbox_site_knowledge' ) ),
			'local_outcome'       => $outcome,
			'feedback_labels'     => $this->sanitize_agent_feedback_labels( $input['feedback_labels'] ?? array() ),
			'operator_note'       => substr( sanitize_textarea_field( (string) ( $input['operator_note'] ?? '' ) ), 0, 500 ),
			'local_proposal_id'   => sanitize_text_field( (string) ( $input['local_proposal_id'] ?? '' ) ),
			'evidence_ref_ids'    => $this->agent_feedback_evidence_ref_ids( $input, $proposal_input ),
			'source_action_id'    => substr( sanitize_text_field( (string) ( $input['source_action_id'] ?? '' ) ), 0, 191 ),
			'source_object_type'  => sanitize_key( (string) ( $input['source_object_type'] ?? '' ) ),
			'source_object_id'    => substr( sanitize_text_field( (string) ( $input['source_object_id'] ?? '' ) ), 0, 191 ),
			'source_reason_codes' => $this->sanitize_string_list( $input['source_reason_codes'] ?? array(), 12 ),
			'source_score'        => isset( $input['source_score'] ) ? max( 0, min( 100, (int) $input['source_score'] ) ) : null,
			'source_severity'     => sanitize_key( (string) ( $input['source_severity'] ?? '' ) ),
			'redaction_status'    => 'metadata_only',
			'retention_class'     => 'quality_eval',
			'created_at'          => $created_at,
		);
	}

	private function sanitize_agent_feedback_labels( $labels ): array {
		$allowed    = array(
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
		$items      = is_array( $labels ) ? $labels : array();
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
				$source    = sanitize_key( (string) ( $ref['source_type'] ?? 'evidence' ) );
				$source_id = sanitize_text_field( (string) ( $ref['source_id'] ?? ( $ref['post_id'] ?? ( $ref['url'] ?? ( $index + 1 ) ) ) ) );
				$value     = $source . ':' . $source_id;
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
			'artifact_type'            => 'site_knowledge_agent_feedback_receipt',
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
			'artifact_type'       => 'site_knowledge_agent_feedback_summary',
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
}
