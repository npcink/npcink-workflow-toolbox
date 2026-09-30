<?php
/**
 * Provider_Site_Ops_Cloud_Service extracted from Provider_Client.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Provider_Site_Ops_Cloud_Service extends Provider_Client_Support {

	public function __construct( Settings $settings ) {
		parent::__construct( $settings );
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
}
