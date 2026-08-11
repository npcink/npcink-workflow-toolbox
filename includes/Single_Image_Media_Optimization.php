<?php
/**
 * Strong-local-confirmation replacement for one Media Library image.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

final class Single_Image_Media_Optimization {
	public const CONTRACT_VERSION = 'single_image_media_optimization_result.v1';
	public const ACTION_REPLACE_CURRENT = 'replace_current';
	private const ABILITY_ID = 'npcink-abilities-toolkit/adopt-cloud-media-derivative';

	/**
	 * Executes one exact, visually confirmed media replacement.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function execute( WP_REST_Request $request ) {
		$json = method_exists( $request, 'get_json_params' ) ? $request->get_json_params() : array();
		$json = is_array( $json ) ? $json : array();
		$allowed_top = array( 'action', 'confirmed_action', 'confirmed_artifact_id', 'preview_verified', 'input' );
		if ( count( $json ) !== count( $allowed_top ) || array_diff( array_keys( $json ), $allowed_top ) || array_diff( $allowed_top, array_keys( $json ) ) ) {
			return $this->invalid_request();
		}

		$action = sanitize_key( (string) ( $json['action'] ?? '' ) );
		if ( self::ACTION_REPLACE_CURRENT !== $action || $action !== sanitize_key( (string) ( $json['confirmed_action'] ?? '' ) ) ) {
			return new WP_Error( 'npcink_toolbox_single_image_action_unconfirmed', __( 'Review and confirm the exact replace-current action before continuing.', 'npcink-workflow-toolbox' ), array( 'status' => 409 ) );
		}
		if ( true !== ( $json['preview_verified'] ?? null ) ) {
			return new WP_Error( 'npcink_toolbox_single_image_preview_unverified', __( 'The exact same-origin preview must be visibly verified before replacement.', 'npcink-workflow-toolbox' ), array( 'status' => 409 ) );
		}

		$input = is_array( $json['input'] ?? null ) ? $json['input'] : array();
		$allowed_input = array( 'attachment_id', 'derivative_artifact', 'expected_derivative_mime_type', 'file_name' );
		if ( array_diff( array_keys( $input ), $allowed_input ) || ! isset( $input['attachment_id'], $input['derivative_artifact'], $input['expected_derivative_mime_type'] ) ) {
			return $this->invalid_request();
		}

		$attachment_id = absint( $input['attachment_id'] );
		if ( $attachment_id <= 0 || 'attachment' !== get_post_type( $attachment_id ) || ! wp_attachment_is_image( $attachment_id ) ) {
			return new WP_Error( 'npcink_toolbox_single_image_attachment_invalid', __( 'Choose one valid Media Library image.', 'npcink-workflow-toolbox' ), array( 'status' => 400 ) );
		}
		if ( ! current_user_can( 'upload_files' ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
			return new WP_Error( 'npcink_toolbox_single_image_permission_denied', __( 'You do not have permission to replace this Media Library image.', 'npcink-workflow-toolbox' ), array( 'status' => 403 ) );
		}

		$artifact = is_array( $input['derivative_artifact'] ) ? $input['derivative_artifact'] : array();
		$artifact_id = sanitize_text_field( (string) ( $artifact['artifact_id'] ?? '' ) );
		if ( ! preg_match( '/^art_[0-9a-f]{32}$/', $artifact_id ) || ! hash_equals( $artifact_id, sanitize_text_field( (string) $json['confirmed_artifact_id'] ) ) ) {
			return new WP_Error( 'npcink_toolbox_single_image_artifact_unconfirmed', __( 'The confirmed artifact does not match the previewed image.', 'npcink-workflow-toolbox' ), array( 'status' => 409 ) );
		}

		$classification = ( new Operation_Classifier() )->classify(
			array(
				'request_source'          => Operation_Classifier::SOURCE_WP_ADMIN_UI,
				'actor_presence'         => Operation_Classifier::ACTOR_PRESENT_CLICK,
				'preview_completeness'   => Operation_Classifier::PREVIEW_EXACT_FINAL,
				'scope'                  => Operation_Classifier::SCOPE_ONE_OBJECT,
				'reversibility'          => Operation_Classifier::REVERSIBILITY_BACKUP_RESTORE,
				'operation_kind'         => Operation_Classifier::KIND_REPLACE_FILE,
				'writes_wordpress_state' => true,
			)
		);
		if ( Operation_Classifier::STRONG_LOCAL_CONFIRMATION !== (string) ( $classification['classification'] ?? '' ) ) {
			return new WP_Error( 'npcink_toolbox_single_image_classification_rejected', __( 'This replacement is outside the strong local confirmation boundary.', 'npcink-workflow-toolbox' ), array( 'status' => 422, 'classification' => $classification ) );
		}

		$ability_input = array(
			'attachment_id'                 => $attachment_id,
			'derivative_artifact'            => $artifact,
			'expected_derivative_mime_type' => sanitize_text_field( (string) $input['expected_derivative_mime_type'] ),
			'backup_suffix'                  => 'npcink-toolbox-local-backup',
			'idempotency_key'                => 'toolbox-local-' . $artifact_id,
			'dry_run'                       => true,
			'commit'                        => false,
		);
		if ( isset( $input['file_name'] ) && '' !== trim( (string) $input['file_name'] ) ) {
			$ability_input['file_name'] = sanitize_file_name( (string) $input['file_name'] );
		}

		$preview = $this->run_ability( $ability_input );
		if ( is_wp_error( $preview ) ) {
			return $preview;
		}

		$authorize = static function ( bool $allowed, string $ability_id ): bool {
			return self::ABILITY_ID === $ability_id ? true : $allowed;
		};
		add_filter( 'npcink_abilities_toolkit_write_commit_allowed', $authorize, 10, 2 );
		try {
			$ability_input['dry_run'] = false;
			$ability_input['commit']  = true;
			$result = $this->run_ability( $ability_input );
		} finally {
			remove_filter( 'npcink_abilities_toolkit_write_commit_allowed', $authorize, 10 );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'contract_version'      => self::CONTRACT_VERSION,
			'status'                => 'completed',
			'action'                => self::ACTION_REPLACE_CURRENT,
			'attachment_id'         => $attachment_id,
			'artifact_id'           => $artifact_id,
			'classification'        => $classification,
			'validation_preview'     => $preview,
			'replacement'            => $result,
			'backup'                 => is_array( $result['backup'] ?? null ) ? $result['backup'] : array(),
			'verification'           => is_array( $result['verification'] ?? null ) ? $result['verification'] : array(),
			'proposal_created'       => false,
			'core_proposal_required' => false,
			'direct_wordpress_write' => true,
			'write_owner'            => 'npcink-abilities-toolkit',
			'confirmation_receipt'   => array(
				'confirmed_at_gmt' => gmdate( 'c' ),
				'user_id'          => get_current_user_id(),
				'preview_verified' => true,
				'backup_required'  => true,
			),
		);
	}

	/** @return array<string,mixed>|WP_Error */
	private function run_ability( array $input ) {
		if ( ! function_exists( 'npcink_abilities_toolkit_get_registered' ) ) {
			return new WP_Error( 'npcink_toolbox_single_image_toolkit_unavailable', __( 'Npcink Abilities Toolkit is required for local image replacement.', 'npcink-workflow-toolbox' ), array( 'status' => 503 ) );
		}
		$registered = npcink_abilities_toolkit_get_registered();
		$definition = is_array( $registered[ self::ABILITY_ID ] ?? null ) ? $registered[ self::ABILITY_ID ] : array();
		$callback = $definition['execute_callback'] ?? null;
		if ( ! is_callable( $callback ) ) {
			return new WP_Error( 'npcink_toolbox_single_image_ability_unavailable', __( 'The Toolkit image replacement ability is unavailable.', 'npcink-workflow-toolbox' ), array( 'status' => 503 ) );
		}
		$result = call_user_func( $callback, $input );
		return is_array( $result ) || is_wp_error( $result ) ? $result : new WP_Error( 'npcink_toolbox_single_image_ability_invalid', __( 'The Toolkit image replacement ability returned an invalid response.', 'npcink-workflow-toolbox' ), array( 'status' => 502 ) );
	}

	private function invalid_request(): WP_Error {
		return new WP_Error( 'npcink_toolbox_single_image_request_invalid', __( 'The request contains missing or unsupported single-image confirmation fields.', 'npcink-workflow-toolbox' ), array( 'status' => 400 ) );
	}
}
