<?php
/**
 * Hosted AI image candidate service for the provider client.
 *
 * Owns the reviewed-prompt hosted image generation runtime request, the
 * explicit AI-generated candidate extraction and normalization, and the
 * shared image_candidate.v1 contract normalization used by both hosted and
 * image-source candidate paths. Generation runtime, model routing, and
 * provider billing stay Cloud/host owned; nothing here writes WordPress data.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Provider_Ai_Image_Service extends Provider_Client_Support {
	private const AI_IMAGE_PROMPT_CHARS        = 4000;
	private const AI_IMAGE_PREVIEW_TOTAL_BYTES = 20971520;

	public function run_ai_image_generation( array $input ) {
		$prompt = $this->trim_chars(
			trim( sanitize_textarea_field( (string) ( $input['prompt'] ?? '' ) ) ),
			self::AI_IMAGE_PROMPT_CHARS
		);
		if ( '' === $prompt ) {
			return new WP_Error(
				'npcink_toolbox_missing_ai_image_prompt',
				__( 'Review and enter a hosted image prompt before calling Cloud.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$n            = max( 1, min( 4, (int) ( $input['n'] ?? 1 ) ) );
		$aspect_ratio = sanitize_text_field( (string) ( $input['aspect_ratio'] ?? '16:9' ) );
		if ( ! in_array( $aspect_ratio, array( '1:1', '4:3', '3:4', '16:9', '9:16' ), true ) ) {
			$aspect_ratio = '16:9';
		}
		$resolution = sanitize_key( (string) ( $input['resolution'] ?? 'high' ) );
		if ( ! in_array( $resolution, array( 'low', 'medium', 'high' ), true ) ) {
			$resolution = 'high';
		}
		$response_format = sanitize_key( (string) ( $input['response_format'] ?? 'url' ) );
		if ( ! in_array( $response_format, array( 'url', 'b64_json' ), true ) ) {
			$response_format = 'url';
		}
		if ( 'b64_json' === $response_format ) {
			return new WP_Error(
				'npcink_toolbox_ai_image_response_format_unsupported',
				__( 'Toolbox currently requires URL-based AI image candidates so Core can review and import the selected image.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}
		$media_context               = $this->ai_image_media_context_from_input( $input, $prompt );
		$review_input                = is_array( $input['review'] ?? null ) ? $input['review'] : array();
		$prompt_reviewed_by_operator = ! empty( $input['prompt_reviewed_by_operator'] ) || ! empty( $review_input['prompt_reviewed_by_operator'] );
		$source_prompt_locale        = sanitize_key( (string) ( $input['prompt_source_locale'] ?? $review_input['source_prompt_locale'] ?? '' ) );
		$prompt_translation_mode     = sanitize_key( (string) ( $input['prompt_translation_mode'] ?? $review_input['prompt_translation_mode'] ?? 'none' ) );
		if ( ! in_array( $prompt_translation_mode, array( 'none', 'preplanned_pair', 'required' ), true ) ) {
			$prompt_translation_mode = 'none';
		}

		$handoff      = is_array( $input['handoff'] ?? null ) ? $input['handoff'] : array();
		$template     = is_array( $handoff['runtime_request_template'] ?? null ) ? $handoff['runtime_request_template'] : array();
		$ability_name = sanitize_text_field( (string) ( $template['ability_name'] ?? 'npcink-cloud/generate-image' ) );
		if ( ! in_array( $ability_name, array( 'npcink-cloud/generate-image', 'npcink-toolbox/generate-image' ), true ) ) {
			$ability_name = 'npcink-cloud/generate-image';
		}

		$runtime_payload                        = array(
			'ability_name'            => $ability_name,
			'contract_version'        => 'image_generation_request.v1',
			'execution_pattern'       => 'inline',
			'execution_kind'          => 'image_generation',
			'profile_id'              => sanitize_text_field( (string) ( $template['profile_id'] ?? 'wp-ai.image-generation' ) ),
			'input'                   => array(
				'prompt'          => $prompt,
				'aspect_ratio'    => $aspect_ratio,
				'resolution'      => $resolution,
				'response_format' => $response_format,
				'n'               => $n,
				'purpose'         => sanitize_key( (string) ( $input['purpose'] ?? 'image_source_candidate_generation' ) ),
				'media_context'   => $media_context,
				'review'          => array(
					'prompt_reviewed_by_operator'          => $prompt_reviewed_by_operator,
					'source_prompt_reviewed_by_operator'   => $prompt_reviewed_by_operator,
					'source_prompt_locale'                 => $source_prompt_locale,
					'prompt_translation_mode'              => $prompt_translation_mode,
					'provider_prompt_reviewed_by_operator' => ! empty( $input['provider_prompt_reviewed_by_operator'] ),
					'write_posture'                        => 'candidate_only',
					'direct_wordpress_write'               => false,
				),
			),
			'data_classification'     => 'internal',
			'storage_mode'            => 'result_only',
			'retention_ttl'           => 3600,
			'timeout_seconds'         => 60,
			'http_timeout_seconds'    => 60,
			'connect_timeout_seconds' => self::HTTP_CONNECT_TIMEOUT,
			'retry_max'               => 0,
			'policy'                  => array(
				'allow_fallback' => false,
			),
		);
		$runtime_payload['data_classification'] = $this->runtime_payload_data_classification( $runtime_payload['input'], 'internal', $input );
		$runtime_payload['storage_mode']        = $this->runtime_payload_storage_mode( $runtime_payload['data_classification'] );

		if ( isset( $handoff['query_hash'] ) ) {
			$runtime_payload['input']['source_handoff'] = array(
				'action_id'  => sanitize_key( (string) ( $handoff['action_id'] ?? 'ai_generate_image' ) ),
				'query_hash' => sanitize_text_field( (string) $handoff['query_hash'] ),
			);
		}

		$runtime_payload = apply_filters( 'npcink_toolbox_ai_image_generation_runtime_payload', $runtime_payload, $input );
		if ( ! is_array( $runtime_payload ) ) {
				return new WP_Error(
					'npcink_toolbox_invalid_ai_image_generation_runtime_payload',
					__( 'The hosted image candidate runtime payload was not valid.', 'npcink-workflow-toolbox' ),
					array( 'status' => 500 )
				);
		}
		$runtime_payload = $this->runtime_payload_with_data_classification( $runtime_payload, 'internal', $input );

		$handled = apply_filters( 'npcink_toolbox_ai_image_generation_cloud_request', null, $runtime_payload, $input );
		if ( is_wp_error( $handled ) ) {
			return $handled;
		}
		if ( is_array( $handled ) ) {
			return $this->normalize_ai_image_generation_response( $handled, $runtime_payload );
		}

		$trace_id        = $this->trace_id( 'ai_image_generation' );
		$idempotency_key = $this->trace_id( 'ai_image_generation_request' );
		$request         = $this->toolbox_image_generation_runtime_request( $runtime_payload );

		if ( function_exists( 'npcink_cloud_addon_execute_toolbox_image_generation_runtime' ) ) {
			$response = npcink_cloud_addon_execute_toolbox_image_generation_runtime( $request, $trace_id, $idempotency_key );
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			return $this->normalize_ai_image_generation_response( is_array( $response ) ? $response : array(), $runtime_payload );
		}

		return new WP_Error(
			'npcink_toolbox_ai_image_generation_cloud_unavailable',
			__( 'Connect Npcink Cloud before generating AI image candidates.', 'npcink-workflow-toolbox' ),
			array( 'status' => 503 )
		);
	}


	private function toolbox_image_generation_runtime_request( array $runtime_payload ): array {
		$input = is_array( $runtime_payload['input'] ?? null ) ? $runtime_payload['input'] : array();

		return array(
			'contract_version' => 'image_generation_request.v1',
			'task'             => 'image_generation',
			'prompt'           => sanitize_textarea_field( (string) ( $input['prompt'] ?? '' ) ),
			'n'                => max( 1, min( 4, (int) ( $input['n'] ?? 1 ) ) ),
			'aspect_ratio'     => sanitize_text_field( (string) ( $input['aspect_ratio'] ?? '16:9' ) ),
			'resolution'       => sanitize_key( (string) ( $input['resolution'] ?? 'high' ) ),
			'source_surface'   => 'toolbox_featured_image',
			'timeout_seconds'  => absint( $runtime_payload['timeout_seconds'] ?? 60 ),
			'retention_ttl'    => absint( $runtime_payload['retention_ttl'] ?? 3600 ),
			'review'           => is_array( $input['review'] ?? null ) ? $input['review'] : array(),
		);
	}


	public function should_include_ai_generated_images( array $options ): bool {
		if ( ! empty( $options['include_ai_generated'] ) ) {
			return true;
		}

		foreach ( array( 'generated_image_url', 'ai_image_url', 'image_url', 'regular_url' ) as $key ) {
			if ( '' !== trim( (string) ( $options[ $key ] ?? '' ) ) ) {
				return true;
			}
		}

		return false;
	}


	public function search_ai_generated_images( string $query, array $options ) {
		$prompt        = trim( sanitize_textarea_field( (string) ( $options['generation_prompt'] ?? $options['prompt'] ?? $query ) ) );
		$media_context = $this->ai_image_media_context_from_input( $options, $prompt );
		$url           = $this->first_non_empty_url(
			array(
				$options['generated_image_url'] ?? '',
				$options['ai_image_url'] ?? '',
				$options['image_url'] ?? '',
				$options['regular_url'] ?? '',
			)
		);

		if ( '' !== $url ) {
			return array(
				'provider' => 'ai_generated',
				'images'   => array(
					$this->normalize_ai_generated_image_candidate(
						array_merge(
							$options,
							array(
								'regular_url' => $url,
								'prompt'      => $prompt,
							)
						),
						$query,
						$prompt,
						$media_context
					),
				),
				'raw'      => array(),
			);
		}

		$request = array(
			'query'            => $query,
			'prompt'           => $prompt,
			'orientation'      => sanitize_key( (string) ( $options['orientation'] ?? '' ) ),
			'color'            => sanitize_key( (string) ( $options['color'] ?? '' ) ),
			'per_page'         => max( 1, min( 4, (int) ( $options['per_page'] ?? 1 ) ) ),
			'purpose'          => sanitize_key( (string) ( $options['purpose'] ?? 'article_image_candidate' ) ),
			'contract_version' => 'legacy_filter_ai_image_generation_request.v1',
			'review'           => array(
				'prompt_reviewed_by_operator' => ! empty( $options['prompt_reviewed_by_operator'] ),
				'write_posture'               => 'candidate_only',
				'direct_wordpress_write'      => false,
			),
		);

		$result = apply_filters( 'npcink_toolbox_ai_image_generation_request', null, $request, $options );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( null === $result ) {
			return new WP_Error(
				'npcink_toolbox_missing_ai_image_runtime',
				__( 'No hosted image candidate runtime handled this request. Provide a generated_image_url or register the npcink_toolbox_ai_image_generation_request filter.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$candidates = $this->extract_ai_generated_image_candidates( $result );
		if ( array() === $candidates ) {
			return new WP_Error(
				'npcink_toolbox_empty_ai_image_response',
				__( 'The hosted image candidate runtime did not return an image URL candidate.', 'npcink-workflow-toolbox' ),
				array( 'status' => 502 )
			);
		}

		$images = array();
		foreach ( $candidates as $candidate ) {
			if ( ! is_array( $candidate ) ) {
				continue;
			}

			$candidate['warnings'] = array_merge(
				$this->sanitize_string_list( $candidate['warnings'] ?? array() ),
				array( __( 'Generated through the legacy filter seam; verify provider metadata before adoption.', 'npcink-workflow-toolbox' ) )
			);
			$normalized            = $this->normalize_ai_generated_image_candidate( $candidate, $query, $prompt, $media_context );
			if ( '' !== (string) ( $normalized['regular_url'] ?? '' ) ) {
				$images[] = $normalized;
			}
		}

		if ( array() === $images ) {
			return new WP_Error(
				'npcink_toolbox_empty_ai_image_response',
				__( 'The hosted image candidate runtime did not return an image URL candidate.', 'npcink-workflow-toolbox' ),
				array( 'status' => 502 )
			);
		}

		return array(
			'provider' => 'ai_generated',
			'images'   => array_slice( $images, 0, max( 1, min( 4, (int) ( $options['per_page'] ?? 1 ) ) ) ),
			'raw'      => is_array( $result ) ? $this->sanitize_debug_payload( $result ) : array(),
		);
	}


	public function normalize_image_candidate_contract( array $candidate ): array {
		$provider    = sanitize_key( (string) ( $candidate['provider'] ?? 'external' ) );
		$source_type = sanitize_key( (string) ( $candidate['source_type'] ?? '' ) );
		if ( '' === $source_type ) {
			if ( 'ai_generated' === $provider ) {
				$source_type = 'ai_generated';
			} elseif ( in_array( $provider, array( 'unsplash', 'pixabay', 'pexels' ), true ) ) {
				$source_type = 'stock';
			} else {
				$source_type = 'external';
			}
		}

		$download_url          = $this->first_non_empty_url(
			array(
				$candidate['download_url'] ?? '',
				$candidate['regular_url'] ?? '',
				$candidate['url'] ?? '',
				$candidate['image_url'] ?? '',
				$candidate['generated_image_url'] ?? '',
				$candidate['output_url'] ?? '',
				$candidate['small_url'] ?? '',
				$candidate['urls']['regular'] ?? '',
				$candidate['urls']['full'] ?? '',
				$candidate['src']['large'] ?? '',
				$candidate['src']['original'] ?? '',
			)
		);
		$thumbnail_url         = $this->first_non_empty_url(
			array(
				$candidate['thumbnail_url'] ?? '',
				$candidate['thumb_url'] ?? '',
				$candidate['small_url'] ?? '',
				$candidate['urls']['small'] ?? '',
				$candidate['urls']['thumb'] ?? '',
				$candidate['src']['medium'] ?? '',
				$candidate['src']['tiny'] ?? '',
				$download_url,
			)
		);
		$source_url            = esc_url_raw( (string) ( $candidate['source_url'] ?? $candidate['html_url'] ?? $candidate['links']['html'] ?? $candidate['url'] ?? '' ) );
		$prompt                = trim( sanitize_textarea_field( (string) ( $candidate['prompt'] ?? $candidate['generation_prompt'] ?? '' ) ) );
		$model                 = sanitize_text_field( (string) ( $candidate['model'] ?? $candidate['generation_model'] ?? '' ) );
		$license_review_status = $this->normalize_license_review_status( (string) ( $candidate['license_review_status'] ?? '' ), $source_type );
		$provider_origin       = sanitize_key( (string) ( $candidate['provider_origin'] ?? 'toolbox' ) );
		$warnings              = $this->sanitize_string_list( $candidate['warnings'] ?? array() );
		$match_reason          = sanitize_textarea_field( (string) ( $candidate['match_reason'] ?? $candidate['reason'] ?? $candidate['recommendation_reason'] ?? '' ) );
		$match_score           = is_numeric( $candidate['match_score'] ?? null ) ? (float) $candidate['match_score'] : null;
		$recommended_use       = sanitize_key( (string) ( $candidate['recommended_use'] ?? $candidate['image_use'] ?? $candidate['best_use'] ?? '' ) );
		if ( ! in_array( $recommended_use, array( 'featured_image', 'paragraph_image', 'inline_image', 'setting_image', 'not_recommended' ), true ) ) {
			$recommended_use = '';
		}
		$visual_keywords    = $this->sanitize_string_list( $candidate['visual_keywords'] ?? $candidate['keywords'] ?? array() );
		$quality_tags       = $this->sanitize_string_list( $candidate['quality_tags'] ?? $candidate['match_tags'] ?? array() );
		$risk_flags         = $this->sanitize_string_list( $candidate['risk_flags'] ?? $candidate['review_flags'] ?? array() );
		$seo_suggestions    = is_array( $candidate['seo_suggestions'] ?? null )
			? $this->sanitize_payload( $candidate['seo_suggestions'] )
			: ( is_array( $candidate['media_seo'] ?? null ) ? $this->sanitize_payload( $candidate['media_seo'] ) : array() );
		$asset_persistence  = is_array( $candidate['asset_persistence'] ?? null )
			? $this->sanitize_payload( $candidate['asset_persistence'] )
			: array();
		$file_name          = sanitize_file_name( (string) ( $candidate['file_name'] ?? '' ) );
		$suggested_filename = sanitize_file_name( (string) ( $candidate['suggested_filename'] ?? $file_name ) );
		if ( '' === $file_name && '' !== $suggested_filename ) {
			$file_name = $suggested_filename;
		}
		$filename_basis = is_array( $candidate['filename_basis'] ?? null )
			? $this->sanitize_payload( $candidate['filename_basis'] )
			: array(
				'owner'                          => 'wordpress_write_ability_final',
				'strategy'                       => 'candidate_suggested_filename',
				'final_sanitize_unique_required' => true,
			);

		$candidate['contract_version']              = 'image_candidate.v1';
		$candidate['source_type']                   = $source_type;
		$candidate['provider']                      = $provider;
		$candidate['provider_origin']               = '' !== $provider_origin ? $provider_origin : 'toolbox';
		$candidate['download_url']                  = $download_url;
		$candidate['thumbnail_url']                 = $thumbnail_url;
		$candidate['source_url']                    = $source_url;
		$candidate['regular_url']                   = esc_url_raw( (string) ( $candidate['regular_url'] ?? $candidate['urls']['regular'] ?? $download_url ) );
		$candidate['small_url']                     = esc_url_raw( (string) ( $candidate['small_url'] ?? $candidate['urls']['small'] ?? $thumbnail_url ) );
		$candidate['html_url']                      = esc_url_raw( (string) ( $candidate['html_url'] ?? $candidate['links']['html'] ?? $source_url ) );
		$candidate['download_location']             = esc_url_raw( (string) ( $candidate['download_location'] ?? $candidate['links']['download_location'] ?? '' ) );
		$candidate['photographer']                  = sanitize_text_field( (string) ( $candidate['photographer'] ?? $candidate['user']['name'] ?? '' ) );
		$candidate['photographer_url']              = esc_url_raw( (string) ( $candidate['photographer_url'] ?? $candidate['user']['links']['html'] ?? '' ) );
		$candidate['prompt']                        = $prompt;
		$candidate['model']                         = $model;
		$candidate['license_review_status']         = $license_review_status;
		$candidate['requires_human_license_review'] = 'not_required' !== $license_review_status;
		$candidate['warnings']                      = $warnings;
		$candidate['match_reason']                  = $match_reason;
		$candidate['match_score']                   = $match_score;
		$candidate['recommended_use']               = $recommended_use;
		$candidate['visual_keywords']               = $visual_keywords;
		$candidate['quality_tags']                  = array_slice( $quality_tags, 0, 6 );
		$candidate['risk_flags']                    = array_slice( $risk_flags, 0, 6 );
		$candidate['seo_suggestions']               = $seo_suggestions;
		if ( array() !== $asset_persistence ) {
			$candidate['asset_persistence'] = $asset_persistence;
		}
		$candidate['file_name']          = $file_name;
		$candidate['suggested_filename'] = '' !== $suggested_filename ? $suggested_filename : $file_name;
		$candidate['filename_basis']     = $filename_basis;
		$candidate['provenance']         = array(
			'provider'            => $provider,
			'provider_origin'     => $candidate['provider_origin'],
			'source_type'         => $source_type,
			'source_url'          => $source_url,
			'download_location'   => $candidate['download_location'],
			'photographer'        => $candidate['photographer'],
			'generation_provider' => sanitize_key( (string) ( $candidate['generation_provider'] ?? $candidate['provider_name'] ?? '' ) ),
			'generation_model'    => $model,
		);

		return $candidate;
	}


	private function normalize_license_review_status( string $status, string $source_type ): string {
		$status = sanitize_key( $status );
		if ( in_array( $status, array( 'required', 'reviewed', 'not_required' ), true ) ) {
			return $status;
		}
		if ( in_array( $status, array( 'needs_human_review', 'needs_review', 'human_review_required' ), true ) ) {
			return 'required';
		}
		if ( 'owned' === $source_type ) {
			return 'not_required';
		}
		return 'required';
	}


	private function extract_ai_generated_image_candidates( $result ): array {
		if ( ! is_array( $result ) ) {
			return array();
		}
		if (
			'image_generation_artifacts' === (string) ( $result['artifact_type'] ?? '' )
			&& 'image_generation_result.v1' === (string) ( $result['contract_version'] ?? '' )
			&& is_array( $result['artifacts'] ?? null )
		) {
			return array_values( array_filter( $result['artifacts'], 'is_array' ) );
		}

		if ( is_array( $result['images'] ?? null ) ) {
			return array_values( array_filter( $result['images'], 'is_array' ) );
		}

		if ( is_array( $result['candidates'] ?? null ) ) {
			return array_values( array_filter( $result['candidates'], 'is_array' ) );
		}

		foreach ( array( 'data', 'result', 'output', 'response' ) as $key ) {
			if ( is_array( $result[ $key ] ?? null ) ) {
				$nested = $this->extract_ai_generated_image_candidates( $result[ $key ] );
				if ( array() !== $nested ) {
					return $nested;
				}
			}
		}

		if ( $this->is_list( $result ) ) {
			return array_values( array_filter( $result, 'is_array' ) );
		}

		return array( $result );
	}


	private function normalize_ai_generated_image_candidate( array $candidate, string $query, string $fallback_prompt, array $media_context = array() ): array {
		$cloud_artifact     = array();
		$artifact_candidate = is_array( $candidate['cloud_artifact'] ?? null ) ? $candidate['cloud_artifact'] : $candidate;
		if ( isset( $artifact_candidate['artifact_id'], $artifact_candidate['artifact_reference'] ) ) {
			$validated_artifact = ( new Cloud_Image_Artifact_Transport() )->validate_artifact( $artifact_candidate );
			if ( ! is_wp_error( $validated_artifact ) ) {
				$cloud_artifact = $artifact_candidate;
			}
		}
		$url = $this->first_non_empty_url(
			array(
				$candidate['regular_url'] ?? '',
				$candidate['url'] ?? '',
				$candidate['image_url'] ?? '',
				$candidate['generated_image_url'] ?? '',
				$candidate['output_url'] ?? '',
			)
		);

		$thumb_url         = $this->first_non_empty_url(
			array(
				$candidate['thumb_url'] ?? '',
				$candidate['thumbnail_url'] ?? '',
				$candidate['small_url'] ?? '',
				$url,
			)
		);
		$small_url         = $this->first_non_empty_url(
			array(
				$candidate['small_url'] ?? '',
				$candidate['preview_url'] ?? '',
				$url,
			)
		);
		$provider          = sanitize_key( (string) ( $candidate['generation_provider'] ?? $candidate['provider_name'] ?? 'ai_generated' ) );
		$model             = sanitize_text_field( (string) ( $candidate['model'] ?? $candidate['generation_model'] ?? '' ) );
		$prompt            = trim( sanitize_textarea_field( (string) ( $candidate['prompt'] ?? $candidate['generation_prompt'] ?? $fallback_prompt ) ) );
		$asset_persistence = $this->ai_generated_asset_persistence_policy( $url, $candidate );
		$context_title     = trim( sanitize_text_field( (string) ( $media_context['title'] ?? '' ) ) );
		$prompt_subject    = $this->ai_image_subject_from_prompt( $prompt );
		$title             = trim( sanitize_text_field( (string) ( $candidate['title'] ?? '' ) ) );
		if ( '' === $title || $this->is_ai_generation_instruction_text( $title ) ) {
			$title = '' !== $context_title ? $context_title : $this->ai_image_media_title_from_subject( $prompt_subject );
		}
		$description = trim( sanitize_textarea_field( (string) ( $candidate['description'] ?? $media_context['description'] ?? '' ) ) );
		if ( '' === $description || $this->is_ai_generation_instruction_text( $description ) ) {
			$description = trim( sanitize_textarea_field( (string) ( $media_context['description'] ?? '' ) ) );
		}
		if ( '' === $description ) {
			$description = $this->ai_image_media_description_from_subject( '' !== $context_title ? $context_title : $title );
		}
		$alt = trim( sanitize_textarea_field( (string) ( $candidate['alt_description'] ?? $candidate['alt'] ?? $media_context['alt'] ?? '' ) ) );
		if ( '' === $alt || $this->is_ai_generation_instruction_text( $alt ) ) {
			$alt = trim( sanitize_textarea_field( (string) ( $media_context['alt'] ?? '' ) ) );
		}
		if ( '' === $alt ) {
			$alt = $this->ai_image_media_alt_from_subject( '' !== $context_title ? $context_title : $title );
		}
		$seo_suggestions = is_array( $candidate['seo_suggestions'] ?? null ) ? $this->sanitize_payload( $candidate['seo_suggestions'] ) : array();
		$seo_suggestions = array_merge(
			is_array( $seo_suggestions ) ? $seo_suggestions : array(),
			array(
				'title'       => $title,
				'alt'         => $alt,
				'alt_text'    => $alt,
				'description' => $description,
				'basis'       => 'reviewed_article_context',
			)
		);
		$warnings        = $this->sanitize_string_list( $candidate['warnings'] ?? array() );
		if ( 'temporary_provider_url' === (string) ( $asset_persistence['status'] ?? '' ) ) {
			$warnings[] = __( 'This AI-generated image URL appears temporary. Adopt it promptly or regenerate before Core approval.', 'npcink-workflow-toolbox' );
		}
		$risk_flags = $this->sanitize_string_list( $candidate['risk_flags'] ?? array() );
		if ( 'temporary_provider_url' === (string) ( $asset_persistence['status'] ?? '' ) ) {
			$risk_flags[] = 'temporary_provider_url';
		}

		$normalized = array(
			'id'                            => sanitize_text_field( (string) ( $candidate['id'] ?? ( '' !== $url ? md5( $url ) : '' ) ) ),
			'provider'                      => 'ai_generated',
			'provider_name'                 => $provider,
			'provider_origin'               => sanitize_key( (string) ( $candidate['provider_origin'] ?? 'toolbox' ) ),
			'hosted_profile'                => sanitize_text_field( (string) ( $candidate['hosted_profile'] ?? '' ) ),
			'source_type'                   => 'ai_generated',
			'title'                         => $title,
			'description'                   => $description,
			'alt_description'               => $alt,
			'thumb_url'                     => $thumb_url,
			'small_url'                     => $small_url,
			'regular_url'                   => $url,
			'html_url'                      => esc_url_raw( (string) ( $candidate['html_url'] ?? $candidate['source_url'] ?? '' ) ),
			'download_location'             => '',
			'source_url'                    => esc_url_raw( (string) ( $candidate['source_url'] ?? $candidate['html_url'] ?? '' ) ),
			'photographer'                  => '',
			'photographer_url'              => '',
			'attribution'                   => sanitize_text_field( (string) ( $candidate['attribution'] ?? __( 'AI-generated image candidate.', 'npcink-workflow-toolbox' ) ) ),
			'prompt'                        => $prompt,
			'model'                         => $model,
			'generation_prompt'             => $prompt,
			'generation_model'              => $model,
			'generation_provider'           => $provider,
			'license_review_status'         => $this->normalize_license_review_status( (string) ( $candidate['license_review_status'] ?? 'required' ), 'ai_generated' ),
			'requires_human_license_review' => true,
			'seo_suggestions'               => $seo_suggestions,
			'asset_persistence'             => $asset_persistence,
			'warnings'                      => array_values( array_unique( $warnings ) ),
			'risk_flags'                    => array_values( array_unique( $risk_flags ) ),
		);
		if ( array() !== $cloud_artifact ) {
			$normalized['artifact_id']    = sanitize_text_field( (string) $cloud_artifact['artifact_id'] );
			$normalized['cloud_artifact'] = $this->sanitize_payload( $cloud_artifact );
		}

		return $normalized;
	}


	private function ai_image_media_context_from_input( array $input, string $prompt ): array {
		$raw_context   = is_array( $input['media_context'] ?? null ) ? $input['media_context'] : array();
		$post_context  = is_array( $input['post_context'] ?? null ) ? $input['post_context'] : array();
		$post_title    = trim( sanitize_text_field( (string) ( $post_context['title'] ?? '' ) ) );
		$selected_text = trim( sanitize_textarea_field( (string) ( $post_context['selected_text'] ?? $post_context['selected_block_text'] ?? '' ) ) );
		$subject       = trim(
			sanitize_text_field(
				(string) (
					$input['media_title']
					?? $raw_context['title']
					?? $post_title
					?? $input['title']
					?? ''
				)
			)
		);
		if ( '' === $subject || $this->is_ai_generation_instruction_text( $subject ) ) {
			$subject = '' !== $post_title ? $post_title : ( '' !== $selected_text ? $selected_text : $this->ai_image_subject_from_prompt( $prompt ) );
		}
		$title       = $this->ai_image_media_title_from_subject( $subject );
		$description = trim( sanitize_textarea_field( (string) ( $input['media_description'] ?? '' ) ) );
		if ( '' === $description || $this->is_ai_generation_instruction_text( $description ) ) {
			$description = $this->ai_image_media_description_from_subject( $title );
		}
		$alt = trim(
			sanitize_textarea_field(
				(string) (
					$input['media_alt']
					?? $input['alt']
					?? $input['alt_text']
					?? $raw_context['alt']
					?? $raw_context['alt_text']
					?? ''
				)
			)
		);
		if ( '' === $alt || $this->is_ai_generation_instruction_text( $alt ) ) {
			$alt = $this->ai_image_media_alt_from_subject( $title );
		}

		return array(
			'title'       => $title,
			'alt'         => $alt,
			'description' => $description,
		);
	}


	private function ai_image_subject_from_prompt( string $prompt ): string {
		$prompt = trim( sanitize_textarea_field( $prompt ) );
		if ( '' === $prompt ) {
			return '';
		}
		$first_line = trim( (string) strtok( $prompt, "\r\n" ) );
		$subject    = preg_replace( '/^\\s*create\\s+an?\\s+original\\s+[^:：]*[:：]\\s*/i', '', $first_line );
		$subject    = preg_replace( '/^\\s*create\\s+a\\s+publication-safe\\s+editorial\\s+illustration\\s+for\\s+[^:：]*[:：]\\s*/i', '', (string) $subject );
		$subject    = preg_replace( '/^\\s*create\\s+[^:：]*\\s+for\\s*[:：]\\s*/i', '', (string) $subject );
		$subject    = preg_replace( '/\\s*composition\\s*[:：].*$/i', '', (string) $subject );
		$subject    = trim( sanitize_text_field( (string) $subject ) );
		if ( '' === $subject || $this->is_ai_generation_instruction_text( $subject ) ) {
			return '';
		}
		return $this->trim_ai_image_media_text( $subject, 120 );
	}


	private function ai_image_media_title_from_subject( string $subject ): string {
		$subject = trim( sanitize_text_field( $subject ) );
		if ( '' === $subject ) {
			return __( 'AI-generated editorial image candidate', 'npcink-workflow-toolbox' );
		}
		return $this->trim_ai_image_media_text( $subject, 120 );
	}


	private function ai_image_media_alt_from_subject( string $subject ): string {
		$subject = trim( sanitize_text_field( $subject ) );
		if ( '' === $subject ) {
			return __( 'Original editorial image candidate for the article.', 'npcink-workflow-toolbox' );
		}
		if ( $this->contains_cjk( $subject ) ) {
			return sprintf( '《%s》的原创编辑配图', $subject );
		}
		return sprintf(
			/* translators: %s: article title or topic. */
			__( 'Original editorial image for "%s".', 'npcink-workflow-toolbox' ),
			$subject
		);
	}


	private function ai_image_media_description_from_subject( string $subject ): string {
		$subject = trim( sanitize_text_field( $subject ) );
		if ( '' === $subject ) {
			return __( 'AI-generated image candidate. Review it before importing or setting it as featured media.', 'npcink-workflow-toolbox' );
		}
		if ( $this->contains_cjk( $subject ) ) {
			return sprintf( 'AI 生成的文章配图候选，用于《%s》。导入或设为特色图前需要人工审查。', $subject );
		}
		return sprintf(
			/* translators: %s: article title or topic. */
			__( 'AI-generated image candidate for "%s". Review it before importing or setting it as featured media.', 'npcink-workflow-toolbox' ),
			$subject
		);
	}


	private function is_ai_generation_instruction_text( string $text ): bool {
		$text = strtolower( trim( $text ) );
		if ( '' === $text ) {
			return false;
		}
		foreach ( array( 'create an original', 'create a publication-safe', 'editorial illustration for', 'source context:', 'context source:', 'visual task:', 'operator visual direction:', 'composition:', 'composition：', 'style:', 'style：', 'text rule:', 'avoid visible text', 'avoid distorted', 'watermarks', 'copyrighted characters', 'regenerate this ai image' ) as $needle ) {
			if ( false !== strpos( $text, $needle ) ) {
				return true;
			}
		}
		return false;
	}


	private function trim_ai_image_media_text( string $text, int $max_chars ): string {
		$text = trim( preg_replace( '/\\s+/u', ' ', sanitize_text_field( $text ) ) ?? sanitize_text_field( $text ) );
		if ( '' === $text || 0 >= $max_chars ) {
			return '';
		}
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			return mb_strlen( $text ) > $max_chars ? mb_substr( $text, 0, $max_chars ) : $text;
		}
		return strlen( $text ) > $max_chars ? substr( $text, 0, $max_chars ) : $text;
	}


	private function ai_generated_asset_persistence_policy( string $url, array $candidate ): array {
		$expires_at   = sanitize_text_field( (string) ( $candidate['expires_at'] ?? $candidate['url_expires_at'] ?? '' ) );
		$is_temporary = $this->is_temporary_generated_image_url( $url );
		$status       = $is_temporary ? 'temporary_provider_url' : 'remote_url';
		if ( '' !== $expires_at ) {
			$status = 'temporary_provider_url';
		}

		return array(
			'status'              => $status,
			'expires_at'          => $expires_at,
			'requires_local_copy' => true,
			'adoption_timing'     => 'temporary_provider_url' === $status ? 'adopt_promptly_or_regenerate' : 'core_import_on_approval',
			'owner'               => 'core_upload_ability_final',
		);
	}


	private function is_temporary_generated_image_url( string $url ): bool {
		$url = strtolower( trim( $url ) );
		if ( '' === $url ) {
			return false;
		}
		foreach ( array( 'xai-tmp', '/tmp-', 'tmp-imgen', 'temporary', 'expires=' ) as $needle ) {
			if ( false !== strpos( $url, $needle ) ) {
				return true;
			}
		}
		return false;
	}


	private function normalize_ai_image_generation_response( array $response, array $runtime_payload ) {
		$result          = $this->extract_cloud_runtime_result( $response );
		$input           = is_array( $runtime_payload['input'] ?? null ) ? $runtime_payload['input'] : array();
		$prompt          = trim( sanitize_textarea_field( (string) ( $input['prompt'] ?? '' ) ) );
		$model           = sanitize_text_field(
			(string) ( $result['model_id'] ?? $response['model_id'] ?? $response['data']['model_id'] ?? $result['model'] ?? 'managed-image' )
		);
		$hosted_profile  = sanitize_text_field(
			(string) ( $result['profile_id'] ?? $response['profile_id'] ?? $response['data']['profile_id'] ?? $runtime_payload['profile_id'] ?? 'wp-ai.image-generation' )
		);
		$media_context   = is_array( $input['media_context'] ?? null ) ? $this->sanitize_payload( $input['media_context'] ) : array();
		$review          = is_array( $input['review'] ?? null ) ? $this->sanitize_payload( $input['review'] ) : array();
		$prompt_reviewed = ! empty( $review['prompt_reviewed_by_operator'] );

		$candidates          = $this->extract_ai_generated_image_candidates( $result );
		$images              = array();
		$artifact_transport  = new Cloud_Image_Artifact_Transport();
		$trace_id            = sanitize_text_field( (string) ( $response['trace_id'] ?? $response['data']['trace_id'] ?? $result['trace_id'] ?? '' ) );
		$preview_total_bytes = 0;
		foreach ( array_slice( $candidates, 0, max( 1, min( 4, (int) ( $input['n'] ?? 1 ) ) ) ) as $candidate ) {
			if ( ! is_array( $candidate ) ) {
				continue;
			}
			if ( isset( $candidate['artifact_id'], $candidate['artifact_reference'] ) ) {
				$candidate['cloud_artifact'] = $candidate;
			}
			$candidate['provider_origin']     = 'cloud';
			$candidate['hosted_profile']      = $hosted_profile;
			$candidate['generation_provider'] = sanitize_key( (string) ( $candidate['generation_provider'] ?? $hosted_profile ) );
			$candidate['generation_model']    = sanitize_text_field( (string) ( $candidate['generation_model'] ?? $model ) );
			$candidate['generation_prompt']   = sanitize_textarea_field( (string) ( $candidate['generation_prompt'] ?? $prompt ) );
			$normalized                       = $this->normalize_ai_generated_image_candidate( $candidate, $prompt, $prompt, $media_context );
			if ( is_array( $normalized['cloud_artifact'] ?? null ) ) {
				$projected_preview_bytes = $preview_total_bytes + absint( $normalized['cloud_artifact']['filesize_bytes'] ?? 0 );
				if ( $projected_preview_bytes > self::AI_IMAGE_PREVIEW_TOTAL_BYTES ) {
					return new WP_Error(
						'npcink_toolbox_ai_image_preview_budget_exceeded',
						__( 'The generated image preview set exceeds the local memory budget. Request fewer or smaller images.', 'npcink-workflow-toolbox' ),
						array( 'status' => 413 )
					);
				}
				$received = $artifact_transport->receive( $normalized['cloud_artifact'], $trace_id );
				if ( is_wp_error( $received ) ) {
					return $received;
				}
				$preview_total_bytes       = $projected_preview_bytes;
				$normalized['id']          = sanitize_text_field( (string) $received['artifact_id'] );
				$normalized['preview_url'] = 'data:' . sanitize_text_field( (string) $received['content_type'] ) . ';base64,' . base64_encode( (string) $received['body'] );
			}
			if ( '' !== (string) ( $normalized['regular_url'] ?? '' ) || '' !== (string) ( $normalized['preview_url'] ?? '' ) ) {
				$images[] = $this->normalize_image_candidate_contract( $normalized );
			}
		}

		$payload = $this->with_output_contract(
			array(
				'provider'                   => 'npcink_cloud',
				'provider_mode'              => 'ai_generated',
				'requested_provider_mode'    => 'ai_generated',
				'resolved_provider'          => $hosted_profile,
				'candidate_contract_version' => 'image_candidate.v1',
				'cloud_ability'              => sanitize_text_field( (string) ( $runtime_payload['ability_name'] ?? 'npcink-cloud/generate-image' ) ),
				'cloud_runtime'              => 'npcink_cloud_addon',
				'contract_version'           => sanitize_text_field( (string) ( $runtime_payload['contract_version'] ?? 'image_generation_request.v1' ) ),
				'hosted_profile'             => $hosted_profile,
				'status'                     => sanitize_key( (string) ( $result['status'] ?? $response['status'] ?? ( array() === $images ? 'empty' : 'ready' ) ) ),
				'message'                    => sanitize_text_field( (string) ( $result['message'] ?? $response['message'] ?? '' ) ),
				'run_id'                     => sanitize_text_field( (string) ( $response['run_id'] ?? ( $response['data']['run_id'] ?? ( $result['run_id'] ?? '' ) ) ) ),
				'model_id'                   => $model,
				'query'                      => '',
				'generation_prompt'          => $prompt,
				'result_count'               => count( $images ),
				'candidate_source_count'     => count( $candidates ),
				'active_sources'             => array(
					array(
						'provider' => 'ai_generated',
						'count'    => count( $images ),
					),
				),
				'usage_summary'              => array(
					'provider'            => sanitize_key( (string) ( $result['provider'] ?? 'npcink_cloud' ) ),
					'provider_mode'       => 'ai_generated',
					'provider_call_count' => absint( $response['provider_call_count'] ?? ( $response['data']['provider_call_count'] ?? 1 ) ),
					'result_count'        => count( $images ),
					'model_id'            => $model,
				),
				'images'                     => $images,
				'handoff'                    => array(
					'candidate_contract'     => 'image_candidate.v1',
					'final_writes'           => 'core_proposal_required',
					'direct_wordpress_write' => false,
				),
				'ai_generation'              => array(
					'prompt_reviewed_by_operator' => $prompt_reviewed,
					'response_format'             => sanitize_key( (string) ( $input['response_format'] ?? 'url' ) ),
					'aspect_ratio'                => sanitize_text_field( (string) ( $input['aspect_ratio'] ?? '' ) ),
					'resolution'                  => sanitize_key( (string) ( $input['resolution'] ?? '' ) ),
					'write_posture'               => 'candidate_only',
					'direct_wordpress_write'      => false,
				),
			),
			'image_source_candidates',
			'image_source_candidates'
		);

		return $this->with_optional_raw( $payload, is_array( $response['raw'] ?? null ) ? $response['raw'] : $response );
	}
}
