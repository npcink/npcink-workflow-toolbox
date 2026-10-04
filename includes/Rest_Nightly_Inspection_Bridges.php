<?php
/**
 * Nightly Inspection Cloud Batch REST bridge cluster, moved verbatim from
 * Rest_Controller behind one-line facade delegates.
 *
 * These are read-only compatibility bridges to Cloud-owned runtime runs:
 * Toolbox stores no server-side run history, owns no retry policy, exposes
 * no recovery workspace, and performs no WordPress writes from here.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use Npcink\LocalAutomationRuntime\NightlyInspection\Snapshot_Collector;
use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

final class Rest_Nightly_Inspection_Bridges {

	private Settings $settings;
	private Provider_Client $client;

	public function __construct( Settings $settings, Provider_Client $client ) {
		$this->settings = $settings;
		$this->client   = $client;
	}

	public function nightly_inspection_cloud_batch( WP_REST_Request $request ) {
		if ( ! $this->settings->cloud_runtime_available() ) {
			return new WP_Error(
				'npcink_toolbox_nightly_inspection_cloud_batch_unavailable',
				__( 'Connect Npcink Cloud before submitting Pro Nightly Inspection batches.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$post_limit      = max( 1, min( 50, (int) ( $request->get_param( 'post_limit' ) ?: 12 ) ) );
		$media_limit     = max( 1, min( 50, (int) ( $request->get_param( 'media_limit' ) ?: 8 ) ) );
		$idempotency_key = sanitize_text_field( (string) $request->get_param( 'idempotency_key' ) );
		$config          = $this->settings->get_nightly_inspection_settings();
		$snapshot        = ( new Snapshot_Collector() )->collect( $post_limit, $media_limit );

		return rest_ensure_response(
			$this->client->submit_nightly_inspection_cloud_batch(
				$snapshot,
				array(
					'idempotency_key' => $idempotency_key,
					'payload_mode'    => (string) ( $request->get_param( 'payload_mode' ) ?: $config['cloud_payload_mode'] ),
					'retention_ttl'   => (int) ( $request->get_param( 'retention_ttl' ) ?: $config['cloud_retention_ttl'] ),
					'source'          => 'toolbox_rest',
				)
			)
		);
	}

	public function nightly_inspection_cloud_batch_status( WP_REST_Request $request ) {
		if ( ! $this->settings->cloud_runtime_available() ) {
			return new WP_Error(
				'npcink_toolbox_nightly_inspection_cloud_batch_unavailable',
				__( 'Connect Npcink Cloud before reading Pro Nightly Inspection batches.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		return rest_ensure_response(
			$this->client->get_nightly_inspection_cloud_batch_status(
				sanitize_text_field( (string) $request->get_param( 'run_id' ) )
			)
		);
	}

	public function nightly_inspection_cloud_batch_recent( WP_REST_Request $request ) {
		if ( ! $this->settings->cloud_runtime_available() ) {
			return new WP_Error(
				'npcink_toolbox_nightly_inspection_cloud_batch_unavailable',
				__( 'Connect Npcink Cloud before reading recent Pro Nightly Inspection runs.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		return rest_ensure_response(
			$this->client->get_nightly_inspection_cloud_recent_runs(
				max( 1, min( 50, (int) ( $request->get_param( 'limit' ) ?: 5 ) ) )
			)
		);
	}

	public function nightly_inspection_cloud_runtime_entitlement() {
		if ( ! $this->settings->cloud_runtime_available() ) {
			return new WP_Error(
				'npcink_toolbox_nightly_inspection_entitlement_unavailable',
				__( 'Connect Npcink Cloud before reading Pro Cloud Runtime entitlement.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		return rest_ensure_response( $this->client->get_nightly_inspection_cloud_runtime_entitlement() );
	}

	public function nightly_inspection_cloud_batch_result( WP_REST_Request $request ) {
		if ( ! $this->settings->cloud_runtime_available() ) {
			return new WP_Error(
				'npcink_toolbox_nightly_inspection_cloud_batch_unavailable',
				__( 'Connect Npcink Cloud before reading Pro Nightly Inspection batches.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$params        = method_exists( $request, 'get_json_params' ) ? $request->get_json_params() : array();
		$morning_brief = is_array( $params ) && is_array( $params['morning_brief'] ?? null ) ? $params['morning_brief'] : array();

		return rest_ensure_response(
			$this->client->get_nightly_inspection_cloud_batch_result(
				sanitize_text_field( (string) $request->get_param( 'run_id' ) ),
				$morning_brief
			)
		);
	}

	public function nightly_inspection_cloud_batch_retry( WP_REST_Request $request ) {
		if ( ! $this->settings->cloud_runtime_available() ) {
			return new WP_Error(
				'npcink_toolbox_nightly_inspection_cloud_batch_unavailable',
				__( 'Connect Npcink Cloud before retrying Pro Nightly Inspection runs.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$params          = method_exists( $request, 'get_json_params' ) ? $request->get_json_params() : array();
		$params          = is_array( $params ) ? $params : array();
		$config          = $this->settings->get_nightly_inspection_settings();
		$post_limit_raw  = $params['post_limit'] ?? $request->get_param( 'post_limit' );
		$media_limit_raw = $params['media_limit'] ?? $request->get_param( 'media_limit' );
		$post_limit      = max( 1, min( 50, (int) ( $post_limit_raw ?: 12 ) ) );
		$media_limit     = max( 1, min( 50, (int) ( $media_limit_raw ?: 8 ) ) );
		$idempotency_key = sanitize_text_field( (string) ( $params['idempotency_key'] ?? $request->get_param( 'idempotency_key' ) ) );
		$snapshot        = ( new Snapshot_Collector() )->collect( $post_limit, $media_limit );
		$payload_mode    = (string) ( $params['payload_mode'] ?? $request->get_param( 'payload_mode' ) );
		$retention_ttl   = $params['retention_ttl'] ?? $request->get_param( 'retention_ttl' );

		return rest_ensure_response(
			$this->client->retry_nightly_inspection_cloud_batch(
				sanitize_text_field( (string) $request->get_param( 'run_id' ) ),
				$snapshot,
				array(
					'idempotency_key' => $idempotency_key,
					'payload_mode'    => '' !== $payload_mode ? $payload_mode : (string) $config['cloud_payload_mode'],
					'retention_ttl'   => (int) ( $retention_ttl ?: $config['cloud_retention_ttl'] ),
					'source'          => 'toolbox_rest_retry',
				)
			)
		);
	}

}
