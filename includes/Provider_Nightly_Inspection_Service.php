<?php
/**
 * Nightly Inspection Cloud runtime service for the provider client.
 *
 * Owns the Pro Cloud batch submit/status/result/retry/entitlement bridges
 * and their payload minimization plus response normalization. Cloud stays
 * the runtime and detail owner; this service never schedules, persists runs,
 * creates Core proposals, or writes WordPress data.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use Npcink\LocalAutomationRuntime\NightlyInspection\Cloud_Batch_Result_Merger;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Provider_Nightly_Inspection_Service extends Provider_Client_Support {

	public function submit_nightly_inspection_cloud_batch( array $snapshot, array $options = array() ) {
		$runtime_payload = $this->build_nightly_inspection_cloud_batch_runtime_payload( $snapshot, $options );
		if ( is_wp_error( $runtime_payload ) ) {
			return $runtime_payload;
		}

		$handled = apply_filters( 'npcink_toolbox_nightly_inspection_cloud_batch_cloud_request', null, $runtime_payload, $snapshot, $options );
		if ( is_wp_error( $handled ) ) {
			return $handled;
		}
		if ( is_array( $handled ) ) {
			return $this->normalize_nightly_inspection_cloud_batch_response( $handled, $runtime_payload );
		}

		if ( ! function_exists( 'npcink_cloud_addon_submit_toolbox_nightly_inspection' ) ) {
			return new WP_Error(
				'npcink_toolbox_nightly_inspection_cloud_batch_unavailable',
				__( 'Connect Npcink Cloud before submitting Pro Nightly Inspection batches.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$trace_id        = $this->trace_id( 'nightly_inspection_cloud_batch' );
		$idempotency_key = sanitize_text_field( (string) ( $options['idempotency_key'] ?? '' ) );
		if ( '' === $idempotency_key ) {
			$idempotency_key = 'nightly-inspection-cloud-batch-' . substr( md5( (string) wp_json_encode( $runtime_payload['input'] ?? array() ) ), 0, 24 );
		}

		$response = npcink_cloud_addon_submit_toolbox_nightly_inspection( $runtime_payload, $trace_id, $idempotency_key );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->normalize_nightly_inspection_cloud_batch_response( is_array( $response ) ? $response : array(), $runtime_payload );
	}


	public function get_nightly_inspection_cloud_recent_runs( int $limit = 5 ) {
		$limit = max( 1, min( 50, absint( $limit ) ) );

		$handled = apply_filters( 'npcink_toolbox_nightly_inspection_cloud_recent_runs_request', null, $limit );
		if ( is_wp_error( $handled ) ) {
			return $handled;
		}
		if ( is_array( $handled ) ) {
			return $this->normalize_nightly_inspection_cloud_recent_runs_response( $handled, $limit );
		}

		if ( ! function_exists( 'npcink_cloud_addon_get_toolbox_nightly_inspection_recent_runs' ) ) {
			return new WP_Error(
				'npcink_toolbox_nightly_inspection_cloud_recent_runs_unavailable',
				__( 'Connect an updated Npcink Cloud Addon before reading recent Nightly Inspection runs.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$response = npcink_cloud_addon_get_toolbox_nightly_inspection_recent_runs( $limit, $this->trace_id( 'nightly_inspection_cloud_recent_runs' ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->normalize_nightly_inspection_cloud_recent_runs_response( is_array( $response ) ? $response : array(), $limit );
	}


	public function get_nightly_inspection_cloud_batch_status( string $run_id ) {
		$run_id = sanitize_text_field( $run_id );
		if ( '' === $run_id ) {
			return new WP_Error(
				'npcink_toolbox_nightly_inspection_cloud_batch_run_id_required',
				__( 'A Cloud run_id is required.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$handled = apply_filters( 'npcink_toolbox_nightly_inspection_cloud_batch_status_request', null, $run_id );
		if ( is_wp_error( $handled ) ) {
			return $handled;
		}
		if ( is_array( $handled ) ) {
			return $this->normalize_nightly_inspection_cloud_batch_status_response( $handled, $run_id );
		}

		if ( ! function_exists( 'npcink_cloud_addon_get_toolbox_runtime_run' ) ) {
			return new WP_Error(
				'npcink_toolbox_nightly_inspection_cloud_batch_status_unavailable',
				__( 'Connect an updated Npcink Cloud Addon before reading Cloud Batch status.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$response = npcink_cloud_addon_get_toolbox_runtime_run( $run_id, $this->trace_id( 'nightly_inspection_cloud_batch_status' ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->normalize_nightly_inspection_cloud_batch_status_response( is_array( $response ) ? $response : array(), $run_id );
	}


	public function get_nightly_inspection_cloud_batch_result( string $run_id, array $morning_brief = array() ) {
		$run_id = sanitize_text_field( $run_id );
		if ( '' === $run_id ) {
			return new WP_Error(
				'npcink_toolbox_nightly_inspection_cloud_batch_run_id_required',
				__( 'A Cloud run_id is required.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$handled = apply_filters( 'npcink_toolbox_nightly_inspection_cloud_batch_result_request', null, $run_id, $morning_brief );
		if ( is_wp_error( $handled ) ) {
			return $handled;
		}
		if ( is_array( $handled ) ) {
			return $this->normalize_nightly_inspection_cloud_batch_response( $handled, array(), $morning_brief );
		}

		if ( ! function_exists( 'npcink_cloud_addon_get_toolbox_runtime_run_result' ) ) {
			return new WP_Error(
				'npcink_toolbox_nightly_inspection_cloud_batch_result_unavailable',
				__( 'Connect an updated Npcink Cloud Addon before reading Cloud Batch results.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$response = npcink_cloud_addon_get_toolbox_runtime_run_result( $run_id, $this->trace_id( 'nightly_inspection_cloud_batch_result' ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->normalize_nightly_inspection_cloud_batch_response( is_array( $response ) ? $response : array(), array(), $morning_brief );
	}


	public function retry_nightly_inspection_cloud_batch( string $run_id, array $snapshot, array $options = array() ) {
		$run_id = sanitize_text_field( $run_id );
		if ( '' === $run_id ) {
			return new WP_Error(
				'npcink_toolbox_nightly_inspection_cloud_batch_run_id_required',
				__( 'A Cloud run_id is required.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$runtime_payload = $this->build_nightly_inspection_cloud_batch_runtime_payload( $snapshot, $options );
		if ( is_wp_error( $runtime_payload ) ) {
			return $runtime_payload;
		}

		$handled = apply_filters( 'npcink_toolbox_nightly_inspection_cloud_batch_retry_request', null, $run_id, $runtime_payload, $snapshot, $options );
		if ( is_wp_error( $handled ) ) {
			return $handled;
		}
		if ( is_array( $handled ) ) {
			return $this->normalize_nightly_inspection_cloud_batch_retry_response( $handled, $run_id, $runtime_payload );
		}

		if ( ! function_exists( 'npcink_cloud_addon_retry_toolbox_nightly_inspection' ) ) {
			return new WP_Error(
				'npcink_toolbox_nightly_inspection_cloud_batch_retry_unavailable',
				__( 'Connect an updated Npcink Cloud Addon before retrying Pro Nightly Inspection runs.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$idempotency_key = sanitize_text_field( (string) ( $options['idempotency_key'] ?? '' ) );
		if ( '' === $idempotency_key ) {
			$idempotency_key = 'nightly-inspection-cloud-retry-' . substr( md5( $run_id . '|' . (string) wp_json_encode( $runtime_payload['input'] ?? array() ) . '|' . microtime( true ) ), 0, 24 );
		}

		$response = npcink_cloud_addon_retry_toolbox_nightly_inspection( $run_id, is_array( $runtime_payload['input'] ?? null ) ? $runtime_payload['input'] : array(), $this->trace_id( 'nightly_inspection_cloud_batch_retry' ), $idempotency_key );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->normalize_nightly_inspection_cloud_batch_retry_response( is_array( $response ) ? $response : array(), $run_id, $runtime_payload );
	}


	public function get_nightly_inspection_cloud_runtime_entitlement() {
		$handled = apply_filters( 'npcink_toolbox_nightly_inspection_cloud_runtime_entitlement_request', null );
		if ( is_wp_error( $handled ) ) {
			return $handled;
		}
		if ( is_array( $handled ) ) {
			return $this->normalize_nightly_inspection_cloud_runtime_entitlement_response( $handled );
		}

		if ( ! function_exists( 'npcink_cloud_addon_get_toolbox_runtime_entitlement' ) ) {
			return new WP_Error(
				'npcink_toolbox_nightly_inspection_entitlement_unavailable',
				__( 'Connect an updated Npcink Cloud Addon before reading Pro Cloud Runtime entitlement.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$response = npcink_cloud_addon_get_toolbox_runtime_entitlement( $this->trace_id( 'nightly_inspection_entitlement' ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->normalize_nightly_inspection_cloud_runtime_entitlement_response( is_array( $response ) ? $response : array() );
	}


	private function build_nightly_inspection_cloud_batch_runtime_payload( array $snapshot, array $options = array() ) {
		$payload_mode         = $this->nightly_inspection_cloud_payload_mode( (string) ( $options['payload_mode'] ?? 'metadata_only' ) );
		$retention_ttl        = $this->nightly_inspection_cloud_retention_ttl( $options['retention_ttl'] ?? null );
		$payload_minimization = array();
		$items                = $this->nightly_inspection_cloud_batch_items( $snapshot, $payload_mode, $payload_minimization );
		if ( array() === $items ) {
			return new WP_Error(
				'npcink_toolbox_nightly_inspection_cloud_batch_empty',
				__( 'The Nightly Inspection snapshot did not include any content items for Cloud analysis.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$runtime_input = array(
			'contract_version'       => 'cloud_batch_runtime_request.v1',
			'task_profile'           => 'nightly_site_inspection_morning_brief',
			'local_runtime_owner'    => 'npcink-local-automation-runtime',
			'snapshot_run_id'        => sanitize_text_field( (string) ( $snapshot['run_id'] ?? '' ) ),
			'snapshot_generated_at'  => sanitize_text_field( (string) ( $snapshot['generated_at'] ?? '' ) ),
			'items'                  => $items,
			'privacy'                => array(
				'payload_mode'               => $payload_mode,
				'excerpt_included'           => 'excerpt' === $payload_mode,
				'full_content_included'      => false,
				'cloud_result_retention_ttl' => $retention_ttl,
				'payload_minimization'       => $payload_minimization,
			),
			'direct_wordpress_write' => false,
		);

		$runtime_payload = array(
			'ability_name'            => 'npcink-toolbox/analyze-nightly-content-batch',
			'contract_version'        => 'cloud_batch_runtime_request.v1',
			'execution_pattern'       => 'whole_run_offload',
			'execution_kind'          => 'nightly_site_inspection',
			'profile_id'              => 'cloud-batch-runtime.managed',
			'input'                   => $this->sanitize_payload( $runtime_input ),
			'data_classification'     => 'internal',
			'storage_mode'            => 'result_only',
			'retention_ttl'           => $retention_ttl,
			'timeout_seconds'         => 60,
			'http_timeout_seconds'    => 60,
			'connect_timeout_seconds' => self::HTTP_CONNECT_TIMEOUT,
			'retry_max'               => 0,
			'policy'                  => array(
				'allow_fallback' => false,
			),
		);

		$runtime_payload = apply_filters( 'npcink_toolbox_nightly_inspection_cloud_batch_runtime_payload', $runtime_payload, $snapshot, $options );
		if ( ! is_array( $runtime_payload ) ) {
			return new WP_Error(
				'npcink_toolbox_invalid_nightly_inspection_cloud_batch_runtime_payload',
				__( 'The Nightly Inspection Cloud batch runtime payload was not valid.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}

		return $runtime_payload;
	}


	private function nightly_inspection_cloud_batch_items( array $snapshot, string $payload_mode, array &$minimization_report = array() ): array {
		$items               = array();
		$minimization_events = array();
		$posts               = is_array( $snapshot['posts'] ?? null ) ? $snapshot['posts'] : array();
		foreach ( $posts as $post ) {
			if ( ! is_array( $post ) ) {
				continue;
			}
			$content     = wp_strip_all_tags( (string) ( $post['content'] ?? '' ) );
			$modified_at = sanitize_text_field( (string) ( $post['modified_at'] ?? '' ) );
			$object_id   = absint( $post['object_id'] ?? 0 );
			$items[]     = array(
				'object_type'            => sanitize_key( (string) ( $post['object_type'] ?? 'post' ) ),
				'object_id'              => $object_id,
				'title'                  => $this->nightly_inspection_cloud_safe_text( (string) ( $post['title'] ?? '' ), 'content item metadata', 'post', $object_id, 'title', $minimization_events ),
				'meta_description'       => $this->nightly_inspection_cloud_safe_text( (string) ( $post['meta_description'] ?? '' ), '', 'post', $object_id, 'meta_description', $minimization_events ),
				'word_count'             => $this->nightly_inspection_word_count( $content ),
				'internal_link_count'    => max( 0, (int) ( $post['internal_link_count'] ?? 0 ) ),
				'image_alt_missing'      => max( 0, (int) ( $post['missing_alt_count'] ?? 0 ) ),
				'days_since_modified'    => $this->days_since_gmt( $modified_at ),
				'direct_wordpress_write' => false,
			);
			if ( 'excerpt' === $payload_mode ) {
				$items[ count( $items ) - 1 ]['excerpt'] = $this->nightly_inspection_cloud_safe_text(
					$this->trim_chars( $content, 800 ),
					'content excerpt minimized',
					'post',
					$object_id,
					'excerpt',
					$minimization_events
				);
			}
			if ( count( $items ) >= 50 ) {
				$minimization_report = $this->nightly_inspection_cloud_payload_minimization_report( $minimization_events );
				return $items;
			}
		}

		$media = is_array( $snapshot['media'] ?? null ) ? $snapshot['media'] : array();
		foreach ( $media as $media_item ) {
			if ( ! is_array( $media_item ) ) {
				continue;
			}
			$object_id = absint( $media_item['object_id'] ?? 0 );
			$title     = $this->nightly_inspection_cloud_attachment_label( $media_item, $object_id, $minimization_events );
			$items[]   = array(
				'object_type'            => 'attachment',
				'object_id'              => $object_id,
				'title'                  => $title,
				'meta_description'       => '',
				'word_count'             => 0,
				'internal_link_count'    => 0,
				'image_alt_missing'      => '' === trim( (string) ( $media_item['alt'] ?? '' ) ) ? 1 : 0,
				'days_since_modified'    => 0,
				'direct_wordpress_write' => false,
			);
			if ( 'excerpt' === $payload_mode ) {
				$items[ count( $items ) - 1 ]['excerpt'] = $title;
			}
			if ( count( $items ) >= 50 ) {
				break;
			}
		}

		$minimization_report = $this->nightly_inspection_cloud_payload_minimization_report( $minimization_events );
		return $items;
	}


	private function nightly_inspection_cloud_attachment_label( array $media_item, int $object_id, array &$events ): string {
		$title    = sanitize_text_field( (string) ( $media_item['title'] ?? '' ) );
		$filename = sanitize_text_field( (string) ( $media_item['filename'] ?? '' ) );
		if ( '' !== $title || '' !== $filename ) {
			$events[] = array(
				'object_type' => 'attachment',
				'object_id'   => $object_id,
				'field'       => '' !== $title ? 'title' : 'filename',
				'reason'      => 'attachment_free_text_minimized',
			);
		}

		return 'media attachment metadata';
	}


	private function nightly_inspection_cloud_safe_text( string $value, string $fallback, string $object_type, int $object_id, string $field, array &$events ): string {
		$text = sanitize_textarea_field( $value );
		if ( '' === trim( $text ) ) {
			return '';
		}
		if ( ! $this->nightly_inspection_cloud_text_needs_minimization( $text ) ) {
			return $text;
		}

		$events[] = array(
			'object_type' => sanitize_key( $object_type ),
			'object_id'   => $object_id,
			'field'       => sanitize_key( $field ),
			'reason'      => 'sensitive_pattern_minimized',
		);

		return sanitize_text_field( $fallback );
	}


	private function nightly_inspection_cloud_text_needs_minimization( string $value ): bool {
		$text = trim( $value );
		if ( '' === $text ) {
			return false;
		}
		if ( preg_match( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text ) ) {
			return true;
		}
		if ( preg_match( '/(?:api[_-]?key|secret|password|token|bearer\s+[A-Z0-9._\-]+)/i', $text ) ) {
			return true;
		}

		$digits = preg_replace( '/\D+/', '', $text );
		return is_string( $digits ) && strlen( $digits ) >= 8;
	}


	private function nightly_inspection_cloud_payload_minimization_report( array $events ): array {
		$fields = array();
		$items  = array();
		foreach ( $events as $event ) {
			if ( ! is_array( $event ) ) {
				continue;
			}
			$field = sanitize_key( (string) ( $event['field'] ?? '' ) );
			if ( '' !== $field ) {
				$fields[ $field ] = true;
			}
			$item_key = sanitize_key( (string) ( $event['object_type'] ?? '' ) ) . ':' . absint( $event['object_id'] ?? 0 );
			if ( ':' !== $item_key ) {
				$items[ $item_key ] = true;
			}
		}

		return array(
			'applied'                => array() !== $events,
			'modified_item_count'    => count( $items ),
			'modified_field_count'   => count( $events ),
			'modified_fields'        => array_slice( array_keys( $fields ), 0, 12 ),
			'policy'                 => 'cloud_batch_free_text_minimization',
			'raw_values_included'    => false,
			'direct_wordpress_write' => false,
		);
	}


	private function nightly_inspection_cloud_payload_mode( string $value ): string {
		$mode = sanitize_key( $value );
		return in_array( $mode, array( 'metadata_only', 'excerpt' ), true ) ? $mode : 'metadata_only';
	}


	private function nightly_inspection_cloud_retention_ttl( $value ): int {
		$day_seconds = defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400;
		$ttl         = is_numeric( $value ) ? (int) $value : 7 * $day_seconds;

		return max( $day_seconds, min( 90 * $day_seconds, $ttl ) );
	}


	private function nightly_inspection_word_count( string $content ): int {
		$content = trim( preg_replace( '/\s+/u', ' ', $content ) ?? $content );
		if ( '' === $content ) {
			return 0;
		}

		$words = preg_split( '/\s+/u', $content );
		if ( is_array( $words ) && count( $words ) > 1 ) {
			return count( array_filter( $words, static fn( $word ): bool => '' !== trim( (string) $word ) ) );
		}

		if ( function_exists( 'mb_strlen' ) ) {
			return max( 1, (int) ceil( mb_strlen( $content ) / 2 ) );
		}

		return max( 1, (int) ceil( strlen( $content ) / 5 ) );
	}


	private function normalize_nightly_inspection_cloud_recent_runs_response( array $response, int $limit ): array {
		$data = is_array( $response['data'] ?? null ) ? $response['data'] : $response;

		return $this->with_output_contract(
			array(
				'provider'         => 'npcink_cloud',
				'provider_mode'    => 'cloud_managed',
				'contract_version' => 'nightly_site_inspection_recent_runs.v1',
				'cloud_runtime'    => 'npcink_cloud_addon',
				'status'           => sanitize_key( (string) ( $response['status'] ?? 'ok' ) ),
				'limit'            => max( 1, min( 50, absint( $data['limit'] ?? $limit ) ) ),
				'items'            => is_array( $data['items'] ?? null ) ? $this->sanitize_payload( $data['items'] ) : array(),
				'latest'           => is_array( $data['latest'] ?? null ) ? $this->sanitize_payload( $data['latest'] ) : array(),
				'latest_failure'   => is_array( $data['latest_failure'] ?? null ) ? $this->sanitize_payload( $data['latest_failure'] ) : array(),
				'toolbox_guidance' => is_array( $data['toolbox_guidance'] ?? null ) ? $this->sanitize_payload( $data['toolbox_guidance'] ) : array(
					'display_surface'        => 'morning_brief_recent_runs',
					'polling_supported'      => true,
					'cloud_scheduler_truth'  => false,
					'direct_wordpress_write' => false,
				),
				'boundary'         => is_array( $data['boundary'] ?? null ) ? $this->sanitize_payload( $data['boundary'] ) : array(
					'cloud_role'             => 'runtime_detail',
					'schedule_truth'         => 'wordpress_local',
					'proposal_truth'         => 'npcink_governance_core',
					'final_write_truth'      => 'wordpress_local',
					'direct_wordpress_write' => false,
				),
				'safety'           => array(
					'direct_wordpress_write' => false,
					'cloud_scheduler_truth'  => false,
					'server_side_history'    => 'cloud_owned',
					'requires_local_review'  => true,
				),
			),
			'nightly_inspection_cloud_recent_runs',
			'morning_brief_cloud_recent_runs'
		);
	}


	private function normalize_nightly_inspection_cloud_batch_status_response( array $response, string $run_id ): array {
		$data = is_array( $response['data'] ?? null ) ? $response['data'] : $response;

		return $this->with_output_contract(
			array(
				'provider'         => 'npcink_cloud',
				'provider_mode'    => 'cloud_managed',
				'contract_version' => 'cloud_batch_runtime_status.v1',
				'cloud_runtime'    => 'npcink_cloud_addon',
				'status'           => sanitize_key( (string) ( $data['status'] ?? $response['status'] ?? 'unknown' ) ),
				'cloud_run'        => array(
					'run_id'        => sanitize_text_field( (string) ( $data['run_id'] ?? $run_id ) ),
					'status'        => sanitize_key( (string) ( $data['status'] ?? $response['status'] ?? '' ) ),
					'trace_id'      => sanitize_text_field( (string) ( $data['trace_id'] ?? $response['trace_id'] ?? '' ) ),
					'run_lifecycle' => is_array( $data['run_lifecycle'] ?? null ) ? $this->sanitize_payload( $data['run_lifecycle'] ) : array(),
				),
				'polling'          => array(
					'result_route'           => '/nightly-inspection/cloud-batch/' . rawurlencode( $run_id ) . '/result',
					'direct_wordpress_write' => false,
				),
				'safety'           => array(
					'direct_wordpress_write' => false,
					'cloud_scheduler_truth'  => false,
					'requires_local_review'  => true,
				),
			),
			'nightly_inspection_cloud_batch_status',
			'morning_brief_cloud_runtime_status'
		);
	}


	private function normalize_nightly_inspection_cloud_batch_response( array $response, array $runtime_payload = array(), array $morning_brief = array() ): array {
		$data   = is_array( $response['data'] ?? null ) ? $response['data'] : array();
		$result = $this->extract_cloud_runtime_result( $response );
		$status = sanitize_key( (string) ( $data['status'] ?? $response['status'] ?? 'submitted' ) );
		$merger = new Cloud_Batch_Result_Merger();
		$patch  = is_array( $result ) ? $merger->patch( $result ) : array();

		$payload = $this->with_output_contract(
			array(
				'provider'              => 'npcink_cloud',
				'provider_mode'         => 'cloud_managed',
				'contract_version'      => 'cloud_batch_runtime_request.v1',
				'cloud_ability'         => sanitize_text_field( (string) ( $runtime_payload['ability_name'] ?? 'npcink-toolbox/analyze-nightly-content-batch' ) ),
				'cloud_runtime'         => 'npcink_cloud_addon',
				'status'                => '' !== $status ? $status : 'submitted',
				'runtime_owner'         => 'npcink-local-automation-runtime',
				'cloud_role'            => 'runtime_detail',
				'final_write_path'      => 'core_proposal_required',
				'cloud_run'             => array(
					'run_id'        => sanitize_text_field( (string) ( $data['run_id'] ?? $response['run_id'] ?? '' ) ),
					'status'        => sanitize_key( (string) ( $data['status'] ?? $response['status'] ?? '' ) ),
					'trace_id'      => sanitize_text_field( (string) ( $data['trace_id'] ?? $response['trace_id'] ?? '' ) ),
					'task_backend'  => is_array( $data['task_backend'] ?? null ) ? $this->sanitize_payload( $data['task_backend'] ) : array(),
					'run_lifecycle' => is_array( $data['run_lifecycle'] ?? null ) ? $this->sanitize_payload( $data['run_lifecycle'] ) : array(),
				),
				'result'                => is_array( $result ) ? $this->sanitize_payload( $result ) : array(),
				'morning_brief_patch'   => $this->sanitize_payload( $patch ),
				'safety'                => array(
					'direct_wordpress_write' => false,
					'cloud_scheduler_truth'  => false,
					'core_proposal_created'  => false,
					'requires_local_review'  => true,
				),
				'cloud_request_summary' => array(
					'execution_pattern' => sanitize_key( (string) ( $runtime_payload['execution_pattern'] ?? 'whole_run_offload' ) ),
					'execution_kind'    => sanitize_key( (string) ( $runtime_payload['execution_kind'] ?? 'nightly_site_inspection' ) ),
					'storage_mode'      => sanitize_key( (string) ( $runtime_payload['storage_mode'] ?? 'result_only' ) ),
					'payload_mode'      => sanitize_key( (string) ( $runtime_payload['input']['privacy']['payload_mode'] ?? 'metadata_only' ) ),
					'retention_ttl'     => (int) ( $runtime_payload['retention_ttl'] ?? 0 ),
					'item_count'        => count( (array) ( $runtime_payload['input']['items'] ?? array() ) ),
				),
			),
			'nightly_inspection_cloud_batch_runtime',
			'morning_brief_cloud_runtime_result'
		);

		if ( array() !== $morning_brief && is_array( $result ) ) {
			$payload['merged_morning_brief'] = $this->sanitize_payload( $merger->merge( $morning_brief, $result ) );
		}

		if ( $this->settings->raw_responses_enabled() ) {
			$payload['cloud_response'] = $this->sanitize_debug_payload( $response );
		}

		return $payload;
	}


	private function normalize_nightly_inspection_cloud_batch_retry_response( array $response, string $source_run_id, array $runtime_payload = array() ): array {
		$data      = is_array( $response['data'] ?? null ) ? $response['data'] : $response;
		$retry_run = is_array( $data['retry_run'] ?? null ) ? $data['retry_run'] : array();
		$run_id    = sanitize_text_field( (string) ( $retry_run['run_id'] ?? $data['run_id'] ?? '' ) );
		$status    = sanitize_key( (string) ( $retry_run['status'] ?? $data['status'] ?? 'queued' ) );

		return $this->with_output_contract(
			array(
				'provider'              => 'npcink_cloud',
				'provider_mode'         => 'cloud_managed',
				'contract_version'      => 'cloud_batch_runtime_retry.v1',
				'cloud_ability'         => sanitize_text_field( (string) ( $runtime_payload['ability_name'] ?? 'npcink-toolbox/analyze-nightly-content-batch' ) ),
				'cloud_runtime'         => 'npcink_cloud_addon',
				'status'                => '' !== $status ? $status : 'queued',
				'runtime_owner'         => 'npcink-local-automation-runtime',
				'cloud_role'            => 'runtime_detail',
				'final_write_path'      => 'core_proposal_required',
				'source_run_id'         => sanitize_text_field( (string) ( $data['source_run_id'] ?? $source_run_id ) ),
				'cloud_run'             => array(
					'run_id'        => $run_id,
					'status'        => $status,
					'trace_id'      => sanitize_text_field( (string) ( $retry_run['trace_id'] ?? $response['trace_id'] ?? '' ) ),
					'task_backend'  => is_array( $retry_run['task_backend'] ?? null ) ? $this->sanitize_payload( $retry_run['task_backend'] ) : array(),
					'run_lifecycle' => is_array( $retry_run['run_lifecycle'] ?? null ) ? $this->sanitize_payload( $retry_run['run_lifecycle'] ) : array(),
					'run_state'     => is_array( $retry_run['run_state'] ?? null ) ? $this->sanitize_payload( $retry_run['run_state'] ) : array(),
				),
				'retry'                 => array(
					'source_run_id'          => sanitize_text_field( (string) ( $data['source_run_id'] ?? $source_run_id ) ),
					'retry_run_id'           => $run_id,
					'cloud_scheduler_truth'  => false,
					'direct_wordpress_write' => false,
				),
				'safety'                => array(
					'direct_wordpress_write' => false,
					'cloud_scheduler_truth'  => false,
					'core_proposal_created'  => false,
					'requires_local_review'  => true,
				),
				'cloud_request_summary' => array(
					'execution_pattern' => sanitize_key( (string) ( $runtime_payload['execution_pattern'] ?? 'whole_run_offload' ) ),
					'execution_kind'    => sanitize_key( (string) ( $runtime_payload['execution_kind'] ?? 'nightly_site_inspection' ) ),
					'storage_mode'      => sanitize_key( (string) ( $runtime_payload['storage_mode'] ?? 'result_only' ) ),
					'payload_mode'      => sanitize_key( (string) ( $runtime_payload['input']['privacy']['payload_mode'] ?? 'metadata_only' ) ),
					'retention_ttl'     => (int) ( $runtime_payload['retention_ttl'] ?? 0 ),
					'item_count'        => count( (array) ( $runtime_payload['input']['items'] ?? array() ) ),
				),
				'boundary'              => is_array( $data['boundary'] ?? null ) ? $this->sanitize_payload( $data['boundary'] ) : array(
					'cloud_role'             => 'runtime_detail',
					'cloud_scheduler_truth'  => false,
					'direct_wordpress_write' => false,
				),
			),
			'nightly_inspection_cloud_batch_retry',
			'morning_brief_cloud_runtime_retry'
		);
	}


	private function normalize_nightly_inspection_cloud_runtime_entitlement_response( array $response ): array {
		$data        = is_array( $response['data'] ?? null ) ? $response['data'] : $response;
		$entitlement = is_array( $data['entitlement'] ?? null ) ? $data['entitlement'] : $data;
		$runtime     = is_array( $entitlement['pro_cloud_runtime'] ?? null ) ? $entitlement['pro_cloud_runtime'] : array();
		$period      = is_array( $data['period'] ?? null ) ? $data['period'] : array();
		$local_truth = is_array( $runtime['local_truth'] ?? null ) ? $runtime['local_truth'] : array();

		$max_runs        = absint( $runtime['max_nightly_inspection_runs_per_period'] ?? 0 );
		$used            = absint( $runtime['used_nightly_inspection_runs'] ?? 0 );
		$remaining       = array_key_exists( 'remaining_nightly_inspection_runs', $runtime )
			? absint( $runtime['remaining_nightly_inspection_runs'] )
			: ( $max_runs > 0 ? max( 0, $max_runs - $used ) : 0 );
		$quota_exhausted = ! empty( $runtime['quota_exhausted'] ) || ( $max_runs > 0 && $used >= $max_runs );

		$pro_cloud_runtime = array(
			'contract_version'                       => sanitize_text_field( (string) ( $runtime['contract_version'] ?? 'pro-cloud-runtime-entitlement-v1' ) ),
			'feature_id'                             => sanitize_key( (string) ( $runtime['feature_id'] ?? 'nightly_site_inspection' ) ),
			'execution_pattern'                      => sanitize_key( (string) ( $runtime['execution_pattern'] ?? 'whole_run_offload' ) ),
			'meter_key'                              => sanitize_key( (string) ( $runtime['meter_key'] ?? 'nightly_site_inspection_runs' ) ),
			'limit_enforced'                         => ! empty( $runtime['limit_enforced'] ),
			'max_nightly_inspection_runs_per_period' => $max_runs,
			'used_nightly_inspection_runs'           => $used,
			'remaining_nightly_inspection_runs'      => $remaining,
			'quota_exhausted'                        => $quota_exhausted,
			'max_batch_items'                        => absint( $runtime['max_batch_items'] ?? 0 ),
			'result_retention_days'                  => absint( $runtime['result_retention_days'] ?? 0 ),
			'payload_modes'                          => array_slice( $this->sanitize_string_list( $runtime['payload_modes'] ?? array( 'metadata_only', 'excerpt' ) ), 0, 8 ),
			'cloud_role'                             => sanitize_key( (string) ( $runtime['cloud_role'] ?? 'runtime_detail' ) ),
			'local_truth'                            => array(
				'schedule_owner'         => sanitize_text_field( (string) ( $local_truth['schedule_owner'] ?? 'npcink-local-automation-runtime' ) ),
				'runtime_owner'          => sanitize_text_field( (string) ( $local_truth['runtime_owner'] ?? 'npcink-local-automation-runtime' ) ),
				'final_write_path'       => sanitize_key( (string) ( $local_truth['final_write_path'] ?? 'core_proposal_required' ) ),
				'direct_wordpress_write' => false,
			),
		);

		return $this->with_output_contract(
			array(
				'provider'               => 'npcink_cloud',
				'provider_mode'          => 'cloud_managed',
				'contract_version'       => 'pro_cloud_runtime_entitlement_status.v1',
				'status'                 => sanitize_key( (string) ( $data['status'] ?? $entitlement['status'] ?? '' ) ),
				'package_label'          => sanitize_text_field( (string) ( $data['package'] ?? $data['package_label'] ?? '' ) ),
				'package_tier'           => sanitize_key( (string) ( $data['package_tier'] ?? $entitlement['package_tier'] ?? '' ) ),
				'period'                 => array(
					'start_at' => sanitize_text_field( (string) ( $period['start_at'] ?? '' ) ),
					'end_at'   => sanitize_text_field( (string) ( $period['end_at'] ?? '' ) ),
				),
				'pro_cloud_runtime'      => $pro_cloud_runtime,
				'submit_allowed'         => ! $quota_exhausted,
				'direct_wordpress_write' => false,
				'final_write_path'       => 'core_proposal_required',
				'cloud_scheduler_truth'  => false,
			),
			'pro_cloud_runtime_entitlement',
			'nightly_inspection_cloud_runtime_entitlement'
		);
	}
}
