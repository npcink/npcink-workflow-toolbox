<?php
/**
 * Media Library optimization REST bridge cluster (ADR-015 exact-manifest
 * batches and the ADR-016 bounded backup cleanup), moved verbatim from
 * Rest_Controller behind one-line facade delegates.
 *
 * Transport only: every operation delegates to the Media_Optimization_Batches
 * owner class, which holds the bounded manifest, Toolkit write handoff, and
 * retention marking. No queue, scheduler, approval store, or WordPress write
 * moves into this service.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class Rest_Media_Optimization_Bridges {

	public function media_optimization_health(): WP_REST_Response {
		if ( ! function_exists( 'npcink_cloud_addon_get_manual_readiness_result' ) ) {
			return rest_ensure_response(
				array(
					'ready'             => false,
					'status'            => 'unavailable',
					'blocked_reason'    => 'cloud_addon_not_installed',
					'next_action'       => 'check_cloud_connection',
					'write_posture'     => 'read_only',
					'contract_version'  => 'toolbox_media_optimization_health.v1',
				)
			);
		}

		$readiness = npcink_cloud_addon_get_manual_readiness_result();
		$readiness = is_array( $readiness ) ? $readiness : array();
		$status    = sanitize_key( (string) ( $readiness['status'] ?? 'unavailable' ) );

		return rest_ensure_response(
			array(
				'ready'            => 'ready' === $status,
				'status'           => '' !== $status ? $status : 'unavailable',
				'blocked_reason'   => sanitize_text_field( (string) ( $readiness['blocked_reason'] ?? '' ) ),
				'next_action'      => sanitize_key( (string) ( $readiness['next_safe_action'] ?? 'check_cloud_connection' ) ),
				'write_posture'    => 'read_only',
				'contract_version' => 'toolbox_media_optimization_health.v1',
			)
		);
	}
	public function media_optimization_batch_create( WP_REST_Request $request ) {
		$params = method_exists( $request, 'get_json_params' ) ? $request->get_json_params() : array();
		$result = ( new Media_Optimization_Batches() )->create( is_array( $params ) ? $params : array() );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}
	public function media_optimization_manifest( WP_REST_Request $request ) {
		$params = method_exists( $request, 'get_json_params' ) ? $request->get_json_params() : array();
		$result = ( new Media_Optimization_Batches() )->build_manifest( is_array( $params ) ? $params : array() );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}
	public function media_optimization_batches() {
		return rest_ensure_response( ( new Media_Optimization_Batches() )->all() );
	}
	public function media_optimization_batch_current() {
		$result = ( new Media_Optimization_Batches() )->current();
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}
	public function media_optimization_batch_confirm( WP_REST_Request $request ) {
		$params = method_exists( $request, 'get_json_params' ) ? $request->get_json_params() : array();
		$result = ( new Media_Optimization_Batches() )->confirm( sanitize_text_field( (string) $request->get_param( 'batch_id' ) ), is_array( $params ) ? $params : array() );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}
	public function media_optimization_batch_complete_item( WP_REST_Request $request ) {
		$params = method_exists( $request, 'get_json_params' ) ? $request->get_json_params() : array();
		$result = ( new Media_Optimization_Batches() )->complete_item(
			sanitize_text_field( (string) $request->get_param( 'batch_id' ) ),
			absint( $request->get_param( 'attachment_id' ) ),
			is_array( $params ) ? $params : array()
		);
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}
	public function media_optimization_batch_restore_item( WP_REST_Request $request ) {
		$result = ( new Media_Optimization_Batches() )->restore_item(
			sanitize_text_field( (string) $request->get_param( 'batch_id' ) ),
			absint( $request->get_param( 'attachment_id' ) )
		);
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}
	public function media_backup_cleanup_preview() {
		$result = ( new Media_Optimization_Batches() )->preview_backup_cleanup();
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}
	public function media_backup_cleanup_confirm( WP_REST_Request $request ) {
		$params = method_exists( $request, 'get_json_params' ) ? $request->get_json_params() : array();
		$params = is_array( $params ) ? $params : array();
		$result = ( new Media_Optimization_Batches() )->cleanup_backups( $params );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

}
