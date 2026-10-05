<?php
/**
 * ADR-003 local admin consent bridge: the historical existing-attachment
 * featured-image exception, moved verbatim from Rest_Controller behind a
 * one-line facade delegate.
 *
 * The boundary is unchanged: one current post, one existing attachment,
 * Core audit records, no proposal, no media import.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

final class Rest_Local_Admin_Consent {

	public function local_admin_consent_featured_image( WP_REST_Request $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'npcink_toolbox_local_featured_image_admin_required',
				__( 'Local admin consent requires an administrator session.', 'npcink-workflow-toolbox' ),
				array( 'status' => 403 )
			);
		}

		$post_id       = absint( $request->get_param( 'post_id' ) );
		$attachment_id = absint( $request->get_param( 'attachment_id' ) );
		if ( $post_id <= 0 || $attachment_id <= 0 ) {
			return new WP_Error(
				'npcink_toolbox_local_featured_image_target_required',
				__( 'A post_id and existing attachment_id are required.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$post = get_post( $post_id );
		$attachment = get_post( $attachment_id );
		if ( ! $post || ! $attachment || 'attachment' !== get_post_type( $attachment ) ) {
			return new WP_Error(
				'npcink_toolbox_local_featured_image_target_not_found',
				__( 'The target post or media attachment was not found.', 'npcink-workflow-toolbox' ),
				array( 'status' => 404 )
			);
		}

		if ( ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
			return new WP_Error(
				'npcink_toolbox_local_featured_image_permission_denied',
				__( 'You do not have permission to update this featured image.', 'npcink-workflow-toolbox' ),
				array( 'status' => 403 )
			);
		}

		if ( function_exists( 'wp_attachment_is_image' ) && ! wp_attachment_is_image( $attachment_id ) ) {
			return new WP_Error(
				'npcink_toolbox_local_featured_image_attachment_not_image',
				__( 'Local admin consent can set only existing image attachments as featured images.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$classification = ( new Operation_Classifier() )->classify(
			array(
				'request_source'          => Operation_Classifier::SOURCE_WP_ADMIN_UI,
				'actor_presence'         => Operation_Classifier::ACTOR_PRESENT_CLICK,
				'preview_completeness'    => Operation_Classifier::PREVIEW_EXACT_FINAL,
				'scope'                   => Operation_Classifier::SCOPE_ONE_OBJECT,
				'reversibility'           => Operation_Classifier::REVERSIBILITY_EASY_UNDO,
				'operation_kind'          => Operation_Classifier::KIND_SET_FEATURED_IMAGE,
				'writes_wordpress_state'  => true,
			)
		);
		if ( Operation_Classifier::LOCAL_ADMIN_CONSENT !== (string) ( $classification['classification'] ?? '' ) ) {
			return new WP_Error(
				'npcink_toolbox_local_featured_image_classification_rejected',
				__( 'This featured image action is not eligible for local admin consent.', 'npcink-workflow-toolbox' ),
				array(
					'status'         => 422,
					'classification' => $classification,
				)
			);
		}

		$before_attachment_id = absint( get_post_thumbnail_id( $post_id ) );
		$audit_base           = $this->local_featured_image_audit_metadata( $request, $post_id, $attachment_id, $before_attachment_id, $classification );
		$requested_audit      = $this->record_core_local_admin_consent_audit( 'local_admin_consent.requested', $audit_base );
		if ( is_wp_error( $requested_audit ) ) {
			return $requested_audit;
		}

		$set_result = set_post_thumbnail( $post_id, $attachment_id );
		$after_attachment_id = absint( get_post_thumbnail_id( $post_id ) );
		if ( $after_attachment_id !== $attachment_id || ( false === $set_result && $before_attachment_id !== $attachment_id ) ) {
			$this->record_core_local_admin_consent_audit(
				'local_admin_consent.failed',
				array_merge(
					$audit_base,
					array(
						'failure_code'        => 'set_post_thumbnail_failed',
						'after_attachment_id' => $after_attachment_id,
					)
				)
			);

			return new WP_Error(
				'npcink_toolbox_local_featured_image_write_failed',
				__( 'WordPress did not accept the featured image update.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}

		$completed_audit = $this->record_core_local_admin_consent_audit(
			'local_admin_consent.completed',
			array_merge(
				$audit_base,
				array(
					'after_attachment_id' => $after_attachment_id,
				)
			)
		);
		if ( is_wp_error( $completed_audit ) ) {
			if ( $before_attachment_id > 0 ) {
				set_post_thumbnail( $post_id, $before_attachment_id );
			} else {
				delete_post_thumbnail( $post_id );
			}

			return new WP_Error(
				'npcink_toolbox_local_featured_image_completion_audit_failed',
				__( 'The featured image update could not be fully audited and was rolled back.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'artifact_type'          => 'local_admin_consent_featured_image_result',
				'status'                 => 'completed',
				'operation_kind'         => Operation_Classifier::KIND_SET_FEATURED_IMAGE,
				'classification'         => $classification,
				'post_id'                => $post_id,
				'attachment_id'          => $attachment_id,
				'featured_media'         => $attachment_id,
				'previous_attachment_id' => $before_attachment_id,
				'proposal_created'       => false,
				'core_proposal_required' => false,
				'direct_wordpress_write' => true,
				'write_owner'            => 'toolbox_local_admin_consent',
				'audit_owner'            => 'npcink-governance-core',
				'audit'                  => array(
					'requested' => $requested_audit,
					'completed' => $completed_audit,
				),
			)
		);
	}

	/**
	 * @param WP_REST_Request    $request Request.
	 * @param int                $post_id Post id.
	 * @param int                 $attachment_id Attachment id.
	 * @param int                 $before_attachment_id Previous thumbnail id.
	 * @param array<string,mixed> $classification Classification result.
	 * @return array<string,mixed>
	 */

	private function local_featured_image_audit_metadata( WP_REST_Request $request, int $post_id, int $attachment_id, int $before_attachment_id, array $classification ): array {
		$candidate = $request->get_param( 'candidate' );
		$candidate = is_array( $candidate ) ? $candidate : array();
		$title     = sanitize_text_field( (string) ( $candidate['title'] ?? ( $candidate['name'] ?? get_the_title( $attachment_id ) ) ) );
		$source    = sanitize_text_field( (string) ( $candidate['source'] ?? ( $candidate['provider'] ?? 'media_library' ) ) );
		$image_url = esc_url_raw( (string) ( $candidate['url'] ?? ( $candidate['image_url'] ?? wp_get_attachment_url( $attachment_id ) ) ) );

		return array(
			'source_module'          => 'npcink-toolbox',
			'surface'                => 'editor_image_source_modal',
			'operation_kind'         => Operation_Classifier::KIND_SET_FEATURED_IMAGE,
			'classification'         => sanitize_key( (string) ( $classification['classification'] ?? '' ) ),
			'policy_version'         => sanitize_text_field( (string) ( $classification['policy_version'] ?? Operation_Classifier::POLICY_VERSION ) ),
			'reasons'                => array_values( array_map( 'sanitize_key', (array) ( $classification['reasons'] ?? array() ) ) ),
			'required_evidence'      => array_values( array_map( 'sanitize_key', (array) ( $classification['required_evidence'] ?? array() ) ) ),
			'operation_classification' => $classification,
			'actor_user_id'          => get_current_user_id(),
			'target_object_type'     => 'post',
			'target_object_id'       => $post_id,
			'post_id'                => $post_id,
			'attachment_id'          => $attachment_id,
			'before_attachment_id'   => $before_attachment_id,
			'ai_suggestion_summary'  => '' !== $title ? $title : __( 'Set one reviewed existing media image as the featured image.', 'npcink-workflow-toolbox' ),
			'image_source'           => $source,
			'image_url'              => $image_url,
			'preview_completeness'   => Operation_Classifier::PREVIEW_EXACT_FINAL,
			'actor_presence'         => Operation_Classifier::ACTOR_PRESENT_CLICK,
			'reversibility'          => Operation_Classifier::REVERSIBILITY_EASY_UNDO,
			'core_proposal_created'  => false,
			'request_or_correlation_id' => sanitize_text_field( (string) ( $request->get_header( 'x-request-id' ) ?: wp_generate_uuid4() ) ),
		);
	}

	/**
	 * Records a local-admin-consent event through Governance Core.
	 *
	 * @param string              $event_name Event name.
	 * @param array<string,mixed> $metadata Event metadata.
	 * @return array<string,mixed>|WP_Error
	 */

	private function record_core_local_admin_consent_audit( string $event_name, array $metadata ) {
		$result = apply_filters( 'npcink_governance_core_record_local_admin_consent', null, $event_name, $metadata );
		if ( null === $result ) {
			return new WP_Error(
				'npcink_toolbox_local_consent_core_audit_unavailable',
				__( 'Governance Core local consent audit is unavailable.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return is_array( $result ) ? $result : array( 'event_id' => sanitize_text_field( (string) $result ) );
	}

}
