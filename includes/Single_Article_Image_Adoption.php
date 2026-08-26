<?php
/**
 * Strong-local-confirmation image adoption for one current article.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

final class Single_Article_Image_Adoption {
	public const CONTRACT_VERSION = 'single_article_image_adoption_result.v1';
	public const ACTION_IMPORT_ONLY = 'import_only';
	public const ACTION_SET_FEATURED_EXISTING = 'set_featured_existing';
	public const ACTION_IMPORT_AND_SET_FEATURED = 'import_and_set_featured';

	private const MAX_FILE_BYTES = 10485760;
	private const MAX_IMAGE_PIXELS = 40000000;
	private const DOWNLOAD_TIMEOUT_SECONDS = 30;

	/**
	 * Executes one editor-present image adoption transaction.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function execute( WP_REST_Request $request ) {
		$action  = sanitize_key( (string) $request->get_param( 'action' ) );
		$post_id = absint( $request->get_param( 'post_id' ) );
		if ( ! in_array( $action, $this->allowed_actions(), true ) ) {
			return new WP_Error(
				'npcink_toolbox_image_adoption_action_invalid',
				__( 'Choose one supported single-image adoption action.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		if ( $post_id <= 0 || ! get_post( $post_id ) ) {
			return new WP_Error(
				'npcink_toolbox_image_adoption_post_required',
				__( 'The current article is required for image adoption.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error(
				'npcink_toolbox_image_adoption_permission_denied',
				__( 'You do not have permission to edit this article.', 'npcink-workflow-toolbox' ),
				array( 'status' => 403 )
			);
		}

		$confirmed_action = sanitize_key( (string) $request->get_param( 'confirmed_action' ) );
		if ( $action !== $confirmed_action ) {
			return new WP_Error(
				'npcink_toolbox_image_adoption_confirmation_required',
				__( 'Review the exact image action and confirm it before continuing.', 'npcink-workflow-toolbox' ),
				array( 'status' => 409 )
			);
		}

		$classification = ( new Operation_Classifier() )->classify(
			array(
				'request_source'         => Operation_Classifier::SOURCE_WP_ADMIN_UI,
				'actor_presence'        => Operation_Classifier::ACTOR_PRESENT_CLICK,
				'preview_completeness'  => Operation_Classifier::PREVIEW_EXACT_FINAL,
				'scope'                 => Operation_Classifier::SCOPE_ONE_OBJECT,
				'reversibility'         => Operation_Classifier::REVERSIBILITY_BACKUP_RESTORE,
				'operation_kind'        => Operation_Classifier::KIND_ADOPT_REVIEWED_IMAGE,
				'writes_wordpress_state' => true,
			)
		);
		if ( Operation_Classifier::STRONG_LOCAL_CONFIRMATION !== (string) ( $classification['classification'] ?? '' ) ) {
			return new WP_Error(
				'npcink_toolbox_image_adoption_classification_rejected',
				__( 'This image action is outside the single-article confirmation boundary.', 'npcink-workflow-toolbox' ),
				array( 'status' => 422, 'classification' => $classification )
			);
		}

		$previous_featured_id = absint( get_post_thumbnail_id( $post_id ) );
		if ( self::ACTION_SET_FEATURED_EXISTING === $action ) {
			return $this->set_existing_featured_image( $request, $post_id, $previous_featured_id, $classification );
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error(
				'npcink_toolbox_image_adoption_upload_permission_denied',
				__( 'You do not have permission to upload images.', 'npcink-workflow-toolbox' ),
				array( 'status' => 403 )
			);
		}

		$candidate = $this->candidate_from_request( $request );
		if ( is_wp_error( $candidate ) ) {
			return $candidate;
		}

		$attachment_id = $this->import_candidate( $candidate, $post_id );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		if ( self::ACTION_IMPORT_AND_SET_FEATURED === $action ) {
			$featured_result = set_post_thumbnail( $post_id, $attachment_id );
			if ( false === $featured_result || $attachment_id !== absint( get_post_thumbnail_id( $post_id ) ) ) {
				$this->restore_featured_image( $post_id, $previous_featured_id );
				wp_delete_attachment( $attachment_id, true );

				return new WP_Error(
					'npcink_toolbox_image_adoption_featured_write_failed',
					__( 'The image was not adopted because WordPress could not set it as the featured image. The new attachment was removed.', 'npcink-workflow-toolbox' ),
					array( 'status' => 500, 'rollback_status' => 'completed' )
				);
			}
		}

		return $this->result(
			$action,
			$post_id,
			$attachment_id,
			$previous_featured_id,
			$classification,
			$candidate,
			true
		);
	}

	/**
	 * @return array<int,string>
	 */
	private function allowed_actions(): array {
		return array( self::ACTION_IMPORT_ONLY, self::ACTION_SET_FEATURED_EXISTING, self::ACTION_IMPORT_AND_SET_FEATURED );
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	private function set_existing_featured_image( WP_REST_Request $request, int $post_id, int $previous_featured_id, array $classification ) {
		$attachment_id = absint( $request->get_param( 'attachment_id' ) );
		if ( $attachment_id <= 0 || 'attachment' !== get_post_type( $attachment_id ) || ! wp_attachment_is_image( $attachment_id ) || ! current_user_can( 'read_post', $attachment_id ) ) {
			return new WP_Error(
				'npcink_toolbox_image_adoption_existing_image_required',
				__( 'Choose one existing Media Library image.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$result = set_post_thumbnail( $post_id, $attachment_id );
		$current_featured_id = absint( get_post_thumbnail_id( $post_id ) );
		if ( $attachment_id !== $current_featured_id || ( false === $result && $previous_featured_id !== $attachment_id ) ) {
			$this->restore_featured_image( $post_id, $previous_featured_id );

			return new WP_Error(
				'npcink_toolbox_image_adoption_existing_featured_failed',
				__( 'WordPress could not set the selected Media Library image as featured.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500, 'rollback_status' => 'completed' )
			);
		}

		$candidate = $this->candidate_from_request( $request, false );
		$candidate = is_wp_error( $candidate ) ? array() : $candidate;

		return $this->result( self::ACTION_SET_FEATURED_EXISTING, $post_id, $attachment_id, $previous_featured_id, $classification, $candidate, false );
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	private function candidate_from_request( WP_REST_Request $request, bool $require_url = true ) {
		$candidate = $request->get_param( 'candidate' );
		$candidate = is_array( $candidate ) ? $candidate : array();
		$cloud_artifact = is_array( $candidate['cloud_artifact'] ?? null ) ? $candidate['cloud_artifact'] : array();
		if ( array() !== $cloud_artifact ) {
			$validated_artifact = ( new Cloud_Image_Artifact_Transport() )->validate_artifact( $cloud_artifact );
			if ( is_wp_error( $validated_artifact ) ) {
				return $validated_artifact;
			}
		}
		$url       = esc_url_raw( (string) ( $candidate['download_url'] ?? $candidate['regular_url'] ?? $candidate['url'] ?? '' ) );
		if ( $require_url && array() === $cloud_artifact ) {
			$parts = wp_parse_url( $url );
			if ( '' === $url || ! is_array( $parts ) || 'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) ) || ! wp_http_validate_url( $url ) ) {
				return new WP_Error(
					'npcink_toolbox_image_adoption_safe_url_required',
					__( 'The reviewed image must provide a safe HTTPS download URL.', 'npcink-workflow-toolbox' ),
					array( 'status' => 400 )
				);
			}
		}

		$license_status = sanitize_key( (string) ( $candidate['license_review_status'] ?? 'review_required' ) );
		if ( in_array( $license_status, array( 'blocked', 'rejected', 'denied' ), true ) ) {
			return new WP_Error(
				'npcink_toolbox_image_adoption_license_blocked',
				__( 'This image candidate is blocked by its license review status.', 'npcink-workflow-toolbox' ),
				array( 'status' => 422 )
			);
		}

		return array(
			'download_url'          => $url,
			'cloud_artifact'        => $cloud_artifact,
			'source_url'            => esc_url_raw( (string) ( $candidate['source_url'] ?? $candidate['html_url'] ?? '' ) ),
			'download_location'      => esc_url_raw( (string) ( $candidate['download_location'] ?? '' ) ),
			'provider'               => sanitize_key( (string) ( $candidate['provider'] ?? 'external' ) ),
			'source_type'            => sanitize_key( (string) ( $candidate['source_type'] ?? 'external' ) ),
			'license_review_status'  => $license_status,
			'attribution'            => sanitize_text_field( (string) ( $candidate['attribution'] ?? $candidate['photographer'] ?? '' ) ),
			'suggested_filename'     => sanitize_file_name( (string) ( $candidate['suggested_filename'] ?? $candidate['file_name'] ?? '' ) ),
			'title'                  => sanitize_text_field( (string) ( $candidate['title'] ?? $candidate['name'] ?? '' ) ),
			'alt'                    => sanitize_text_field( (string) ( $candidate['alt'] ?? $candidate['suggested_alt'] ?? '' ) ),
			'caption'                => sanitize_textarea_field( (string) ( $candidate['caption'] ?? '' ) ),
			'description'            => sanitize_textarea_field( (string) ( $candidate['description'] ?? '' ) ),
		);
	}

	/**
	 * Creates a temporary file in both REST and WP-CLI execution contexts.
	 *
	 * WP-CLI preloads wp_tempnam(), while ordinary REST requests do not. The
	 * native fallback avoids adding a runtime dependency on wp-admin files.
	 *
	 * @return string|false
	 */
	private function create_temporary_file( string $filename ) {
		if ( function_exists( 'wp_tempnam' ) ) {
			return wp_tempnam( $filename );
		}

		$prefix = (string) preg_replace( '/[^A-Za-z0-9_-]/', '-', pathinfo( $filename, PATHINFO_FILENAME ) );
		$prefix = '' !== $prefix ? substr( $prefix, 0, 20 ) : 'npcink-image';

		return tempnam( get_temp_dir(), $prefix );
	}

	/**
	 * @param array<string,mixed> $candidate Candidate.
	 * @return int|WP_Error
	 */
	private function import_candidate( array $candidate, int $post_id ) {
		$filename = $this->candidate_filename( $candidate );
		$tmp_file = $this->create_temporary_file( $filename );
		if ( ! is_string( $tmp_file ) || '' === $tmp_file ) {
			return new WP_Error( 'npcink_toolbox_image_adoption_tempfile_failed', __( 'WordPress could not prepare a temporary image file.', 'npcink-workflow-toolbox' ), array( 'status' => 500 ) );
		}

		$status = 200;
		if ( is_array( $candidate['cloud_artifact'] ?? null ) && array() !== $candidate['cloud_artifact'] ) {
			$received = ( new Cloud_Image_Artifact_Transport() )->receive( $candidate['cloud_artifact'], 'image_adoption_' . wp_generate_uuid4() );
			if ( is_wp_error( $received ) ) {
				@unlink( $tmp_file );
				return $received;
			}
			if ( false === file_put_contents( $tmp_file, (string) $received['body'] ) ) {
				@unlink( $tmp_file );
				return new WP_Error( 'npcink_toolbox_image_adoption_artifact_tempfile_failed', __( 'WordPress could not prepare the verified generated image for import.', 'npcink-workflow-toolbox' ), array( 'status' => 500 ) );
			}
		} else {
			$response = wp_safe_remote_get(
				(string) $candidate['download_url'],
				array(
					'timeout'             => self::DOWNLOAD_TIMEOUT_SECONDS,
					'redirection'         => 3,
					'reject_unsafe_urls'  => true,
					'sslverify'           => true,
					'stream'              => true,
					'filename'            => $tmp_file,
					'limit_response_size' => self::MAX_FILE_BYTES,
				)
			);
			if ( is_wp_error( $response ) ) {
				@unlink( $tmp_file );
				return $response;
			}
			$status = (int) wp_remote_retrieve_response_code( $response );
		}
		$size   = is_file( $tmp_file ) ? (int) filesize( $tmp_file ) : 0;
		if ( 200 !== $status || $size <= 0 || $size > self::MAX_FILE_BYTES ) {
			@unlink( $tmp_file );
			return new WP_Error(
				'npcink_toolbox_image_adoption_download_invalid',
				__( 'The remote image download was empty, too large, or returned an unexpected status.', 'npcink-workflow-toolbox' ),
				array( 'status' => 422 )
			);
		}

		$image_info = @getimagesize( $tmp_file );
		$width      = is_array( $image_info ) ? absint( $image_info[0] ?? 0 ) : 0;
		$height     = is_array( $image_info ) ? absint( $image_info[1] ?? 0 ) : 0;
		$mime       = is_array( $image_info ) ? sanitize_text_field( (string) ( $image_info['mime'] ?? '' ) ) : '';
		if ( $width <= 0 || $height <= 0 || $width * $height > self::MAX_IMAGE_PIXELS || ! str_starts_with( $mime, 'image/' ) ) {
			@unlink( $tmp_file );
			return new WP_Error(
				'npcink_toolbox_image_adoption_image_invalid',
				__( 'The downloaded file is not a supported image or exceeds the pixel limit.', 'npcink-workflow-toolbox' ),
				array( 'status' => 422 )
			);
		}

		$extension = $this->extension_for_mime( $mime );
		if ( '' === $extension ) {
			@unlink( $tmp_file );
			return new WP_Error( 'npcink_toolbox_image_adoption_mime_unsupported', __( 'The downloaded image format is not supported.', 'npcink-workflow-toolbox' ), array( 'status' => 422 ) );
		}
		$filename = preg_replace( '/\.[a-z0-9]+$/i', '', $filename ) . '.' . $extension;

		$file_bytes = file_get_contents( $tmp_file );
		wp_delete_file( $tmp_file );
		if ( ! is_string( $file_bytes ) || '' === $file_bytes ) {
			return new WP_Error(
				'npcink_toolbox_image_adoption_tempfile_read_failed',
				__( 'WordPress could not read the verified image for import.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}

		$upload = wp_upload_bits( sanitize_file_name( $filename ), null, $file_bytes );
		unset( $file_bytes );
		if ( ! empty( $upload['error'] ) || empty( $upload['file'] ) || empty( $upload['url'] ) ) {
			return new WP_Error(
				'npcink_toolbox_image_adoption_upload_failed',
				__( 'WordPress could not store the verified image in the Media Library.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}

		$uploaded_file = (string) $upload['file'];
		$attachment    = array(
			'guid'           => esc_url_raw( (string) $upload['url'] ),
			'post_mime_type' => $mime,
			'post_title'     => '' !== (string) $candidate['title'] ? (string) $candidate['title'] : pathinfo( $filename, PATHINFO_FILENAME ),
			'post_excerpt'   => (string) $candidate['caption'],
			'post_content'   => (string) $candidate['description'],
			'post_status'    => 'inherit',
		);
		$attachment_id = wp_insert_attachment( $attachment, $uploaded_file, $post_id, true );
		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $uploaded_file );
			return $attachment_id;
		}

		$attachment_id = absint( $attachment_id );
		$metadata      = $this->attachment_metadata( $uploaded_file, $width, $height, $size );
		wp_update_attachment_metadata( $attachment_id, $metadata );
		if ( $metadata !== wp_get_attachment_metadata( $attachment_id ) ) {
			$this->delete_generated_sizes( $uploaded_file, $metadata );
			wp_delete_attachment( $attachment_id, true );

			return new WP_Error(
				'npcink_toolbox_image_adoption_metadata_failed',
				__( 'WordPress could not finish the Media Library metadata for the verified image. The uploaded file was removed.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500, 'rollback_status' => 'completed' )
			);
		}

		if ( '' !== (string) $candidate['alt'] ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', (string) $candidate['alt'] );
		}
		update_post_meta( $attachment_id, '_npcink_image_provider', (string) $candidate['provider'] );
		update_post_meta( $attachment_id, '_npcink_image_source_type', (string) $candidate['source_type'] );
		update_post_meta( $attachment_id, '_npcink_image_source_url', (string) $candidate['source_url'] );
		update_post_meta( $attachment_id, '_npcink_image_download_location', (string) $candidate['download_location'] );
		update_post_meta( $attachment_id, '_npcink_image_license_review_status', (string) $candidate['license_review_status'] );
		update_post_meta( $attachment_id, '_npcink_image_attribution', (string) $candidate['attribution'] );
		if ( is_array( $candidate['cloud_artifact'] ?? null ) && ! empty( $candidate['cloud_artifact']['artifact_id'] ) ) {
			update_post_meta( $attachment_id, '_npcink_cloud_source_artifact_id', sanitize_text_field( (string) $candidate['cloud_artifact']['artifact_id'] ) );
		}

		return $attachment_id;
	}

	/**
	 * Builds core metadata and registered image sizes for an imported image.
	 *
	 * This uses the loaded WordPress image editor and registered size contract,
	 * without loading admin-only media helpers.
	 *
	 * @return array<string,mixed>
	 */
	private function attachment_metadata( string $file, int $width, int $height, int $filesize ): array {
		$upload_dir    = wp_upload_dir();
		$normalized    = wp_normalize_path( $file );
		$base_dir      = wp_normalize_path( (string) ( $upload_dir['basedir'] ?? '' ) );
		$relative_file = '' !== $base_dir && str_starts_with( $normalized, trailingslashit( $base_dir ) )
			? ltrim( substr( $normalized, strlen( $base_dir ) ), '/' )
			: wp_basename( $file );

		$metadata = array(
			'width'    => $width,
			'height'   => $height,
			'file'     => $relative_file,
			'filesize' => $filesize,
			'sizes'    => array(),
		);

		if ( ! function_exists( 'wp_get_image_editor' ) || ! function_exists( 'wp_get_registered_image_subsizes' ) ) {
			return $metadata;
		}

		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) {
			return $metadata;
		}

		$generated_sizes = $editor->multi_resize( wp_get_registered_image_subsizes() );
		if ( is_array( $generated_sizes ) ) {
			$metadata['sizes'] = $generated_sizes;
		}

		return $metadata;
	}

	/**
	 * Removes exact generated image-size files during attachment rollback.
	 *
	 * @param array<string,mixed> $metadata Attachment metadata.
	 */
	private function delete_generated_sizes( string $file, array $metadata ): void {
		$directory = trailingslashit( dirname( $file ) );
		$sizes     = is_array( $metadata['sizes'] ?? null ) ? $metadata['sizes'] : array();

		foreach ( $sizes as $size ) {
			$generated_file = is_array( $size ) ? sanitize_file_name( (string) ( $size['file'] ?? '' ) ) : '';
			if ( '' !== $generated_file ) {
				wp_delete_file( $directory . $generated_file );
			}
		}
	}

	/**
	 * @param array<string,mixed> $candidate Candidate.
	 */
	private function candidate_filename( array $candidate ): string {
		$filename = sanitize_file_name( (string) ( $candidate['suggested_filename'] ?? '' ) );
		if ( '' !== $filename ) {
			return $filename;
		}

		if ( is_array( $candidate['cloud_artifact'] ?? null ) && ! empty( $candidate['cloud_artifact']['artifact_id'] ) ) {
			return sanitize_file_name( (string) $candidate['cloud_artifact']['artifact_id'] );
		}

		$path = (string) wp_parse_url( (string) $candidate['download_url'], PHP_URL_PATH );
		$filename = sanitize_file_name( basename( $path ) );

		return '' !== $filename ? $filename : 'npcink-reviewed-image';
	}

	private function extension_for_mime( string $mime ): string {
		$map = array(
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/gif'  => 'gif',
			'image/webp' => 'webp',
			'image/avif' => 'avif',
		);

		return $map[ $mime ] ?? '';
	}

	private function restore_featured_image( int $post_id, int $attachment_id ): void {
		if ( $attachment_id > 0 ) {
			set_post_thumbnail( $post_id, $attachment_id );
			return;
		}

		delete_post_thumbnail( $post_id );
	}

	/**
	 * @param array<string,mixed> $classification Classification.
	 * @param array<string,mixed> $candidate Candidate.
	 * @return array<string,mixed>
	 */
	private function result( string $action, int $post_id, int $attachment_id, int $previous_featured_id, array $classification, array $candidate, bool $attachment_created ): array {
		return array(
			'artifact_type'            => self::CONTRACT_VERSION,
			'status'                   => 'completed',
			'action'                   => $action,
			'classification'           => $classification,
			'post_id'                  => $post_id,
			'attachment_id'            => $attachment_id,
			'attachment_created'       => $attachment_created,
			'featured_media'           => in_array( $action, array( self::ACTION_SET_FEATURED_EXISTING, self::ACTION_IMPORT_AND_SET_FEATURED ), true ) ? $attachment_id : 0,
			'previous_attachment_id'   => $previous_featured_id,
			'url'                      => esc_url_raw( (string) wp_get_attachment_url( $attachment_id ) ),
			'thumbnail_url'            => esc_url_raw( (string) wp_get_attachment_image_url( $attachment_id, 'medium' ) ),
			'alt'                      => sanitize_text_field( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ),
			'caption'                  => sanitize_text_field( (string) wp_get_attachment_caption( $attachment_id ) ),
			'proposal_created'         => false,
			'core_proposal_required'   => false,
			'direct_wordpress_write'   => true,
			'write_owner'              => 'npcink_workflow_toolbox',
			'cloud_role'               => 'candidate_runtime_or_transport_only',
			'rollback_status'          => 'not_needed',
			'receipt'                  => array(
				'actor_user_id'        => get_current_user_id(),
				'post_id'              => $post_id,
				'attachment_id'        => $attachment_id,
				'action'               => $action,
				'provider'             => sanitize_key( (string) ( $candidate['provider'] ?? 'media_library' ) ),
				'source_type'          => sanitize_key( (string) ( $candidate['source_type'] ?? 'media_library' ) ),
				'previous_featured_id' => $previous_featured_id,
				'completed_at'         => gmdate( 'c' ),
			),
		);
	}
}
