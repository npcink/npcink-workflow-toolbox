<?php
/**
 * Provider_Article_Audio_Service extracted from Provider_Client.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Provider_Article_Audio_Service extends Provider_Client_Support {

	private Provider_Client $client;

	public function __construct( Settings $settings, Provider_Client $client ) {
		parent::__construct( $settings );
		$this->client = $client;
	}

	public function run_audio_generation( array $input ) {
		$intent = sanitize_key( (string) ( $input['intent'] ?? 'article_narration' ) );
		if ( ! in_array( $intent, array( 'article_narration', 'article_audio_summary' ), true ) ) {
			return new WP_Error(
				'npcink_toolbox_invalid_audio_generation_intent',
				__( 'A supported audio generation intent is required.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$text = $this->trim_chars(
			trim(
				sanitize_textarea_field(
					wp_strip_all_tags(
						(string) ( $input['summary_text'] ?? ( $input['script'] ?? ( $input['text'] ?? '' ) ) )
					)
				)
			),
			self::AUDIO_GENERATION_TEXT_CHARS
		);
		if ( '' === $text ) {
			return new WP_Error(
				'npcink_toolbox_missing_audio_generation_text',
				__( 'Narration text or summary script is required before calling Cloud audio generation.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$voice_id = sanitize_text_field( (string) ( $input['voice_id'] ?? '' ) );
		$format   = sanitize_key( (string) ( $input['format'] ?? 'mp3' ) );
		$user_instruction = sanitize_textarea_field( (string) ( $input['user_instruction'] ?? '' ) );
		$audio_preferences = is_array( $input['audio_preferences'] ?? null ) ? $this->sanitize_payload( $input['audio_preferences'] ) : array();
		if ( ! in_array( $format, array( 'mp3', 'wav', 'pcm' ), true ) ) {
			$format = 'mp3';
		}

		$runtime_payload = array(
			'ability_name'        => 'npcink-toolbox/generate-audio',
			'contract_version'    => 'audio_generation_request.v1',
			'execution_pattern'   => 'inline',
			'execution_kind'      => 'audio_generation',
			'profile_id'          => sanitize_text_field( (string) ( $input['profile_id'] ?? 'audio.narration.default' ) ),
			'input'               => array(
				'intent'          => $intent,
				'text'            => $text,
				'summary_text'    => 'article_audio_summary' === $intent ? $text : '',
				'script'          => $text,
				'voice_id'        => $voice_id,
				'format'          => $format,
				'response_format' => 'url',
				'purpose'         => 'article_audio_summary' === $intent ? 'longform_audio_summary' : 'article_narration',
				'user_instruction' => $user_instruction,
				'audio_preferences' => $audio_preferences,
				'context'         => is_array( $input['context'] ?? null ) ? $this->sanitize_payload( $input['context'] ) : array(),
				'review'          => array(
					'script_review_required' => true,
					'write_posture'          => 'candidate_only',
					'direct_wordpress_write' => false,
				),
			),
			'data_classification' => 'public_site_content',
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
		$runtime_payload = $this->runtime_payload_with_data_classification( $runtime_payload, 'public_site_content', $input );

		$runtime_payload = apply_filters( 'npcink_toolbox_audio_generation_runtime_payload', $runtime_payload, $input );
		if ( ! is_array( $runtime_payload ) ) {
			return new WP_Error(
				'npcink_toolbox_invalid_audio_generation_runtime_payload',
				__( 'The audio generation runtime payload was not valid.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}
		$runtime_payload = $this->runtime_payload_with_data_classification( $runtime_payload, 'public_site_content', $input );

		$handled = apply_filters( 'npcink_toolbox_audio_generation_cloud_request', null, $runtime_payload, $input );
		if ( is_wp_error( $handled ) ) {
			return $handled;
		}
		if ( is_array( $handled ) ) {
			return $this->normalize_audio_generation_response( $handled, $runtime_payload );
		}

		$trace_id        = $this->trace_id( 'audio_generation' );
		$idempotency_key = $this->trace_id( 'audio_generation_request' );
		$request         = $this->toolbox_audio_generation_runtime_request( $runtime_payload );

		if ( function_exists( 'npcink_cloud_addon_execute_toolbox_audio_generation_runtime' ) ) {
			$response = npcink_cloud_addon_execute_toolbox_audio_generation_runtime( $request, $trace_id, $idempotency_key );
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			return $this->normalize_audio_generation_response( is_array( $response ) ? $response : array(), $runtime_payload );
		}

		return new WP_Error(
			'npcink_toolbox_audio_generation_cloud_unavailable',
			__( 'Connect Npcink Cloud before generating article audio.', 'npcink-workflow-toolbox' ),
			array( 'status' => 503 )
		);
	}

	private function toolbox_audio_generation_runtime_request( array $runtime_payload ): array {
		$input = is_array( $runtime_payload['input'] ?? null ) ? $runtime_payload['input'] : array();

		return array(
			'contract_version'  => 'audio_generation_request.v1',
			'intent'            => sanitize_key( (string) ( $input['intent'] ?? 'article_narration' ) ),
			'text'              => sanitize_textarea_field( (string) ( $input['text'] ?? '' ) ),
			'summary_text'      => sanitize_textarea_field( (string) ( $input['summary_text'] ?? '' ) ),
			'script'            => sanitize_textarea_field( (string) ( $input['script'] ?? '' ) ),
			'voice_id'          => sanitize_text_field( (string) ( $input['voice_id'] ?? '' ) ),
			'format'            => sanitize_key( (string) ( $input['format'] ?? 'mp3' ) ),
			'source_surface'    => 'toolbox_article_audio_candidates',
			'profile_id'        => sanitize_text_field( (string) ( $runtime_payload['profile_id'] ?? 'audio.narration.default' ) ),
			'timeout_seconds'   => absint( $runtime_payload['timeout_seconds'] ?? 60 ),
			'retention_ttl'     => absint( $runtime_payload['retention_ttl'] ?? 3600 ),
			'user_instruction'  => sanitize_textarea_field( (string) ( $input['user_instruction'] ?? '' ) ),
			'audio_preferences' => is_array( $input['audio_preferences'] ?? null ) ? $this->sanitize_payload( $input['audio_preferences'] ) : array(),
			'context'           => is_array( $input['context'] ?? null ) ? $this->sanitize_payload( $input['context'] ) : array(),
		);
	}

	private function normalize_audio_generation_response( array $response, array $runtime_payload ): array {
		$result = $this->extract_cloud_runtime_result( $response );
		$data   = is_array( $response['data'] ?? null ) ? $response['data'] : array();
		$input  = is_array( $runtime_payload['input'] ?? null ) ? $runtime_payload['input'] : array();
		$audios = array();

		foreach ( array( 'audios', 'audio_candidates', 'candidates', 'items' ) as $key ) {
			if ( is_array( $result[ $key ] ?? null ) ) {
				$audios = $result[ $key ];
				break;
			}
		}
		if ( array() === $audios && ( ! empty( $result['url'] ) || ! empty( $result['audio_url'] ) ) ) {
			$audios = array( $result );
		}

		$items = array();
		foreach ( array_slice( array_values( array_filter( $audios, 'is_array' ) ), 0, 4 ) as $index => $audio ) {
			$url = esc_url_raw( (string) ( $audio['url'] ?? ( $audio['audio_url'] ?? '' ) ) );
			$b64 = sanitize_textarea_field( (string) ( $audio['b64_json'] ?? '' ) );
			if ( '' === $url && '' === $b64 ) {
				continue;
			}
			$items[] = array(
				'id'               => sanitize_key( (string) ( $audio['id'] ?? 'audio_' . ( $index + 1 ) ) ),
				'name'             => sanitize_text_field( (string) ( $audio['name'] ?? ( 'article_audio_summary' === (string) ( $input['intent'] ?? '' ) ? __( 'Audio summary candidate', 'npcink-workflow-toolbox' ) : __( 'Narration candidate', 'npcink-workflow-toolbox' ) ) ) ),
				'url'              => $url,
				'b64_json'         => $b64,
				'format'           => sanitize_key( (string) ( $audio['format'] ?? ( $input['format'] ?? 'mp3' ) ) ),
				'duration_seconds' => is_numeric( $audio['duration_seconds'] ?? null ) ? (float) $audio['duration_seconds'] : null,
				'size_bytes'       => absint( $audio['size_bytes'] ?? 0 ),
				'voice_id'         => sanitize_text_field( (string) ( $audio['voice_id'] ?? ( $result['voice_id'] ?? ( $input['voice_id'] ?? '' ) ) ) ),
				'model_id'         => sanitize_text_field( (string) ( $audio['model_id'] ?? ( $result['model_id'] ?? '' ) ) ),
				'provider'         => sanitize_key( (string) ( $audio['provider'] ?? ( $result['provider'] ?? 'npcink_cloud' ) ) ),
				'action_policy'    => 'operator_review_only_no_media_import',
				'quality_status'   => 'review',
			);
		}

		return $this->with_output_contract(
			array(
				'provider'                 => 'npcink_cloud',
				'cloud_runtime'            => 'npcink_cloud_addon',
				'cloud_ability'            => sanitize_text_field( (string) ( $runtime_payload['ability_name'] ?? 'npcink-toolbox/generate-audio' ) ),
				'contract_version'         => sanitize_text_field( (string) ( $runtime_payload['contract_version'] ?? 'audio_generation_request.v1' ) ),
				'hosted_profile'           => sanitize_text_field( (string) ( $runtime_payload['profile_id'] ?? 'audio.narration.default' ) ),
				'model_id'                 => sanitize_text_field( (string) ( $result['model_id'] ?? '' ) ),
				'intent'                   => sanitize_key( (string) ( $input['intent'] ?? '' ) ),
				'status'                   => sanitize_key( (string) ( $result['status'] ?? ( $response['status'] ?? 'ready' ) ) ),
				'run_id'                   => sanitize_text_field( (string) ( $response['run_id'] ?? ( $result['run_id'] ?? '' ) ) ),
				'cloud_run_id'             => sanitize_text_field( (string) ( $data['run_id'] ?? $response['run_id'] ?? '' ) ),
				'provider_response_format' => sanitize_key( (string) ( $result['provider_response_format'] ?? 'url' ) ),
				'user_instruction'         => sanitize_textarea_field( (string) ( $input['user_instruction'] ?? '' ) ),
				'audio_preferences'        => is_array( $input['audio_preferences'] ?? null ) ? $this->sanitize_payload( $input['audio_preferences'] ) : array(),
				'items'                    => $this->sanitize_payload( $items ),
				'audios'                   => $this->sanitize_payload( $items ),
				'script_preview'           => $this->trim_chars( sanitize_textarea_field( (string) ( $input['script'] ?? ( $input['text'] ?? '' ) ) ), 1200 ),
				'result'                   => $this->sanitize_payload( $result ),
				'candidate_count'          => count( $items ),
				'write_posture'            => 'suggestion_only',
				'final_write_path'         => 'operator_review_only_no_media_import',
				'direct_wordpress_write'   => false,
				'handoff'                  => array(
					'final_writes'           => 'operator_review_only_no_media_import',
					'direct_wordpress_write' => false,
					'blocked_actions'        => array(
						'no_media_import_in_toolbox',
						'no_post_content_patch',
						'no_direct_wordpress_write',
					),
				),
			),
			'audio_generation_candidates',
			'article_audio_support'
		);
	}
}
