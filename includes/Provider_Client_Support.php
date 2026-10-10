<?php
/**
 * Shared support base for the provider client facade and its services.
 *
 * Carries the Settings dependency plus the payload, sanitization, and text
 * helpers shared across Provider_Client clusters so per-cluster services keep
 * identical `$this->` call semantics. It is internal plugin plumbing, not a
 * public extension point.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use WP_Error;

defined( 'ABSPATH' ) || exit;

abstract class Provider_Client_Support {
	protected const PAYLOAD_MAX_DEPTH                  = 8;
	protected const PAYLOAD_MAX_ITEMS                  = 80;
	protected const PAYLOAD_MAX_STRING_CHARS           = 4000;
	protected const DEBUG_PAYLOAD_MAX_DEPTH            = 6;
	protected const DEBUG_PAYLOAD_MAX_ITEMS            = 40;
	protected const DEBUG_PAYLOAD_MAX_STRING_CHARS     = 2000;
	protected const HTTP_CONNECT_TIMEOUT               = 5;
	protected const SITE_KNOWLEDGE_CONTENT_CHARS       = 30000;
	protected const SITE_KNOWLEDGE_SYNC_MAX_BYTES      = 750000;
	protected const ARTICLE_PLAN_CONTENT_CHARS         = 60000;
	protected const ARTICLE_PLAN_NOTES_CHARS           = 12000;
	protected const SITE_MEDIA_VISUAL_MAX_UPLOAD_BYTES = 262144;

	protected const AUDIO_GENERATION_TEXT_CHARS = 5000;

	protected Settings $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	protected function runtime_payload_data_classification( array $runtime_input, string $default, array $source_input = array() ): string {
		$requested_classification = sanitize_key( (string) ( $source_input['runtime_data_classification'] ?? $source_input['data_classification'] ?? '' ) );
		if ( $this->payload_contains_secret( $runtime_input ) || ( array() !== $source_input && $this->payload_contains_secret( $source_input ) ) ) {
			return 'secret';
		}
		if ( in_array( $requested_classification, array( 'pii', 'secret' ), true ) ) {
			return $requested_classification;
		}
		if ( $this->payload_contains_editor_free_text_context( $runtime_input ) || $this->payload_contains_personal_data( $runtime_input ) || $this->payload_contains_image_editor_context( $runtime_input ) ) {
			return 'pii';
		}
		if ( array() !== $source_input && ( $this->payload_contains_editor_free_text_context( $source_input ) || $this->payload_contains_personal_data( $source_input ) || $this->payload_contains_image_editor_context( $source_input ) ) ) {
			return 'pii';
		}

		$classification = sanitize_key( $default );
		return '' !== $classification ? $classification : 'internal';
	}


	protected function runtime_payload_with_data_classification( array $runtime_payload, string $default, array $source_input = array() ): array {
		$runtime_input                          = is_array( $runtime_payload['input'] ?? null ) ? $runtime_payload['input'] : array();
		$current                                = sanitize_key( (string) ( $runtime_payload['data_classification'] ?? $default ) );
		$classification                         = $this->runtime_payload_data_classification( $runtime_input, '' !== $current ? $current : $default, $source_input );
		$runtime_payload['data_classification'] = $classification;
		$runtime_payload['storage_mode']        = $this->runtime_payload_storage_mode(
			$classification,
			sanitize_key( (string) ( $runtime_payload['storage_mode'] ?? 'result_only' ) )
		);
		return $runtime_payload;
	}


	protected function runtime_payload_storage_mode( string $data_classification, string $default = 'result_only' ): string {
		$classification = sanitize_key( $data_classification );
		if ( in_array( $classification, array( 'pii', 'secret' ), true ) ) {
			return 'no_store';
		}

		$storage_mode = sanitize_key( $default );
		return '' !== $storage_mode ? $storage_mode : 'result_only';
	}


	protected function payload_contains_image_editor_context( $value, int $depth = 0 ): bool {
		if ( $depth > 6 || ! is_array( $value ) ) {
			return false;
		}

		foreach ( array( 'visual_context', 'post_context' ) as $context_key ) {
			if ( ! is_array( $value[ $context_key ] ?? null ) ) {
				continue;
			}
			$context = $value[ $context_key ];
			if (
				'' !== trim( sanitize_text_field( (string) ( $context['post_id'] ?? '' ) ) )
				|| '' !== trim( sanitize_text_field( (string) ( $context['manual_query'] ?? '' ) ) )
				|| '' !== trim( sanitize_text_field( (string) ( $context['fallback_query'] ?? '' ) ) )
				|| '' !== trim( sanitize_text_field( (string) ( $context['image_use'] ?? $context['image_mode'] ?? '' ) ) )
			) {
				return true;
			}
		}

		foreach ( $value as $child ) {
			if ( is_array( $child ) && $this->payload_contains_image_editor_context( $child, $depth + 1 ) ) {
				return true;
			}
		}

		return false;
	}


	protected function payload_contains_editor_free_text_context( $value, int $depth = 0 ): bool {
		if ( $depth > 6 || ! is_array( $value ) ) {
			return false;
		}

		$context_keys = array( 'visual_context', 'post_context' );
		$text_fields  = array( 'title', 'excerpt', 'content_summary', 'selected_text', 'selected_block_text' );
		foreach ( $context_keys as $context_key ) {
			$context = is_array( $value[ $context_key ] ?? null ) ? $value[ $context_key ] : array();
			foreach ( $text_fields as $field ) {
				if ( '' !== trim( sanitize_textarea_field( (string) ( $context[ $field ] ?? '' ) ) ) ) {
					return true;
				}
			}
		}

		foreach ( $value as $child ) {
			if ( is_array( $child ) && $this->payload_contains_editor_free_text_context( $child, $depth + 1 ) ) {
				return true;
			}
		}

		return false;
	}


	protected function payload_contains_personal_data( $value, int $depth = 0 ): bool {
		if ( $depth > 6 ) {
			return false;
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $child ) {
				$normalized_key = is_string( $key ) ? strtolower( preg_replace( '/[^a-z0-9]+/', '_', $key ) ?? $key ) : '';
				if ( in_array( trim( $normalized_key, '_' ), array( 'email', 'email_address', 'phone', 'phone_number', 'mobile', 'mobile_phone', 'contact_email', 'contact_phone' ), true ) && '' !== trim( (string) $child ) ) {
					return true;
				}
				if ( $this->payload_contains_personal_data( $child, $depth + 1 ) ) {
					return true;
				}
			}
			return false;
		}
		if ( ! is_scalar( $value ) ) {
			return false;
		}

		$text = trim( (string) $value );
		if ( '' === $text ) {
			return false;
		}
		if ( preg_match( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text ) ) {
			return true;
		}
		if ( preg_match( '/(?:\+?\d[\d\s().-]{7,}\d)/', $text ) || preg_match( '/\b1[3-9]\d{9}\b/', $text ) ) {
			return true;
		}
		if ( preg_match( '/\b\d{15}\b|\b\d{17}[\dXx]\b/', $text ) ) {
			return true;
		}

		return false;
	}


	protected function payload_contains_secret( $value, int $depth = 0, string $current_key = '' ): bool {
		if ( $depth >= self::PAYLOAD_MAX_DEPTH ) {
			if ( is_array( $value ) ) {
				return array() !== $value;
			}

			return is_scalar( $value ) && '' !== trim( (string) $value );
		}
		if ( '' !== $current_key && $this->is_secret_payload_key( $current_key ) && is_scalar( $value ) && '' !== trim( (string) $value ) ) {
			return true;
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $child ) {
				if ( $this->payload_contains_secret( $child, $depth + 1, is_string( $key ) ? $key : '' ) ) {
					return true;
				}
			}
			return false;
		}
		if ( ! is_scalar( $value ) ) {
			return false;
		}

		$text = trim( (string) $value );
		if ( '' === $text ) {
			return false;
		}

		return $this->redact_sensitive_debug_text( $text ) !== $text;
	}


	protected function is_secret_payload_key( string $key ): bool {
		$normalized = strtolower( preg_replace( '/[^a-z0-9]+/', '_', $key ) ?? $key );
		$normalized = trim( $normalized, '_' );
		if ( '' === $normalized ) {
			return false;
		}

		if (
			in_array(
				$normalized,
				array(
					'authorization',
					'api_key',
					'apikey',
					'access_token',
					'refresh_token',
					'id_token',
					'token',
					'secret',
					'password',
					'credential',
					'private_key',
					'cookie',
					'set_cookie',
					'headers',
					'request_headers',
					'response_headers',
					'raw_headers',
				),
				true
			)
		) {
			return true;
		}

		foreach ( array( '_api_key', '_token', '_secret', '_password', '_credential', '_private_key' ) as $suffix ) {
			if ( strlen( $normalized ) >= strlen( $suffix ) && substr( $normalized, -strlen( $suffix ) ) === $suffix ) {
				return true;
			}
		}

		return false;
	}


	protected function days_since_gmt( string $timestamp ): int {
		if ( '' === trim( $timestamp ) ) {
			return 0;
		}
		$parsed = strtotime( $timestamp );
		if ( false === $parsed ) {
			return 0;
		}

		$day_seconds = defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400;

		return max( 0, (int) floor( ( time() - $parsed ) / $day_seconds ) );
	}


	protected function first_non_empty_url( array $urls ): string {
		foreach ( $urls as $url ) {
			$clean = esc_url_raw( (string) $url );
			if ( '' !== $clean ) {
				return $clean;
			}
		}

		return '';
	}


	protected function contains_cjk( string $text ): bool {
		return 1 === preg_match( '/[\\x{3400}-\\x{9fff}\\x{f900}-\\x{faff}]/u', $text );
	}


	protected function sanitize_provider_error_data( $data ): array {
		if ( ! is_array( $data ) ) {
			return array();
		}

		$allowed = array();
		foreach ( array( 'status', 'provider_status', 'http_code', 'reason', 'request_id' ) as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$allowed[ $key ] = is_numeric( $data[ $key ] ) ? (int) $data[ $key ] : sanitize_text_field( (string) $data[ $key ] );
			}
		}

		return $allowed;
	}


	protected function hosted_ai_normalized_text( string $content ): string {
		$text = wp_strip_all_tags( $content );
		$text = preg_replace( '/[ \t]+/u', ' ', $text );
		$text = preg_replace( '/\R{3,}/u', "\n\n", is_string( $text ) ? $text : '' );

		return trim( is_string( $text ) ? $text : '' );
	}


	protected function hosted_ai_text_length( string $value ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
	}


	protected function hosted_ai_text_slice( string $value, int $start, int $length ): string {
		if ( function_exists( 'mb_substr' ) ) {
			return trim( mb_substr( $value, $start, $length, 'UTF-8' ) );
		}

		return trim( substr( $value, $start, $length ) );
	}


	protected function decode_json_object_from_text( string $text ): array {
		$trimmed = trim( $text );
		if ( '' === $trimmed ) {
			return array();
		}

		$direct = json_decode( $trimmed, true );
		if ( is_array( $direct ) ) {
			return $direct;
		}

		if ( 1 === preg_match( '/```(?:json)?\s*(\{.*\})\s*```/is', $trimmed, $matches ) ) {
			$fenced = json_decode( $matches[1], true );
			if ( is_array( $fenced ) ) {
				return $fenced;
			}
		}

		$first_brace = strpos( $trimmed, '{' );
		$last_brace  = strrpos( $trimmed, '}' );
		if ( false !== $first_brace && false !== $last_brace && $last_brace > $first_brace ) {
			$embedded = json_decode( substr( $trimmed, $first_brace, $last_brace - $first_brace + 1 ), true );
			if ( is_array( $embedded ) ) {
				return $embedded;
			}
		}

		return array();
	}


	protected function extract_cloud_runtime_result( array $response ): array {
		foreach ( array( 'result', 'output' ) as $key ) {
			if ( is_array( $response[ $key ] ?? null ) ) {
				return $response[ $key ];
			}
		}

		$data = is_array( $response['data'] ?? null ) ? $response['data'] : array();
		foreach ( array( 'result', 'output', 'result_json' ) as $key ) {
			if ( is_array( $data[ $key ] ?? null ) ) {
				return $data[ $key ];
			}
		}

		if ( is_array( $data['run']['result'] ?? null ) ) {
			return $data['run']['result'];
		}

		if ( array() !== $data && ( isset( $data['artifact_type'] ) || isset( $data['results'] ) || isset( $data['candidates'] ) || isset( $data['images'] ) || isset( $data['coverage'] ) || isset( $data['sync'] ) ) ) {
			return $data;
		}

		return $response;
	}


	protected function is_cloud_concurrency_error( WP_Error $error ): bool {
		$code    = (string) $error->get_error_code();
		$message = (string) $error->get_error_message();
		return false !== strpos( $code, 'concurrency' ) || false !== strpos( $message, 'max active cloud runs' );
	}


	protected function sanitize_absint_list( $value ): array {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			$value   = is_array( $decoded ) ? $decoded : explode( ',', $value );
		}
		$items = is_array( $value ) ? $value : array();

		return array_values(
			array_unique(
				array_filter(
					array_map( 'absint', $items ),
					static fn( int $item ): bool => 0 < $item
				)
			)
		);
	}


	protected function trace_id( string $prefix ): string {
		$prefix = sanitize_key( $prefix );
		$uuid   = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( '', true );

		return $prefix . '_' . $uuid;
	}


	protected function trim_chars( string $value, int $max_chars ): string {
		$value = trim( $value );
		if ( '' === $value || 0 >= $max_chars ) {
			return '';
		}

		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			return mb_strlen( $value ) > $max_chars ? mb_substr( $value, 0, $max_chars ) : $value;
		}

		return strlen( $value ) > $max_chars ? substr( $value, 0, $max_chars ) : $value;
	}


	protected function with_optional_raw( array $payload, array $raw ): array {
		if ( $this->raw_responses_enabled() ) {
			$payload['raw'] = $this->sanitize_debug_payload( $raw );
		}

		return $payload;
	}


	protected function raw_responses_enabled(): bool {
		return $this->settings->raw_responses_enabled();
	}


	protected function with_output_contract( array $payload, string $artifact_type, string $composition_role ): array {
		return array_merge(
			array(
				'artifact_type'          => $artifact_type,
				'composition_role'       => $composition_role,
				'write_posture'          => 'suggestion_only',
				'direct_wordpress_write' => false,
			),
			$payload
		);
	}


	protected function sanitize_string_list( $value, int $max_items = 0 ): array {
		$items = is_array( $value ) ? $value : array_filter( array_map( 'trim', explode( "\n", (string) $value ) ) );
		$list  = array_values(
			array_filter(
				array_map(
					static fn( $item ): string => sanitize_textarea_field( (string) $item ),
					$items
				),
				static fn( string $item ): bool => '' !== $item
			)
		);
		return $max_items > 0 ? array_slice( $list, 0, $max_items ) : $list;
	}


	/**
	 * Rewrites known URL fields on a list of Cloud-supplied items to http(s)
	 * only, so a tainted Cloud response cannot plant javascript: (or other
	 * scheme) URLs into admin or editor render surfaces.
	 */
	protected function sanitize_item_url_fields( array $items, array $fields = array( 'url', 'source_url', 'permalink', 'link', 'thumbnail_url' ) ): array {
		$normalized = array();
		foreach ( $items as $item ) {
			if ( is_array( $item ) ) {
				foreach ( $fields as $field ) {
					if ( array_key_exists( $field, $item ) ) {
						$item[ $field ] = esc_url_raw( (string) $item[ $field ], array( 'http', 'https' ) );
					}
				}
				$normalized[] = $item;
			}
		}

		return $normalized;
	}


	protected function bounded_text( string $value, int $max_chars ): string {
		$value     = sanitize_textarea_field( $value );
		$max_chars = max( 1, $max_chars );
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) && mb_strlen( $value ) > $max_chars ) {
			return mb_substr( $value, 0, $max_chars );
		}
		if ( strlen( $value ) > $max_chars ) {
			return substr( $value, 0, $max_chars );
		}

		return $value;
	}


	protected function sanitize_payload( $value, int $depth = 0 ) {
		if ( $depth >= self::PAYLOAD_MAX_DEPTH ) {
			return is_array( $value ) ? array() : $this->bounded_text( (string) $value, self::PAYLOAD_MAX_STRING_CHARS );
		}

		if ( is_array( $value ) ) {
			$sanitized = array();
			$count     = 0;
			foreach ( $value as $key => $child ) {
				if ( $count >= self::PAYLOAD_MAX_ITEMS ) {
					break;
				}
				$sanitized[ is_string( $key ) ? sanitize_key( $key ) : $key ] = $this->sanitize_payload( $child, $depth + 1 );
				++$count;
			}

			return $sanitized;
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}

		return $this->bounded_text( (string) $value, self::PAYLOAD_MAX_STRING_CHARS );
	}


	protected function sanitize_debug_payload( $value, int $depth = 0, string $current_key = '' ) {
		if ( '' !== $current_key && $this->is_sensitive_payload_key( $current_key ) ) {
			return '[redacted]';
		}

		if ( $depth >= self::DEBUG_PAYLOAD_MAX_DEPTH ) {
			return is_array( $value )
				? array( '_truncated' => true )
				: $this->bounded_text( $this->redact_sensitive_debug_text( (string) $value ), self::DEBUG_PAYLOAD_MAX_STRING_CHARS );
		}

		if ( is_array( $value ) ) {
			$sanitized = array();
			$count     = 0;
			foreach ( $value as $key => $child ) {
				if ( $count >= self::DEBUG_PAYLOAD_MAX_ITEMS ) {
					$sanitized['_truncated'] = true;
					break;
				}

				$payload_key               = is_string( $key ) ? sanitize_key( $key ) : $key;
				$sanitized[ $payload_key ] = $this->sanitize_debug_payload( $child, $depth + 1, is_string( $key ) ? $key : '' );
				++$count;
			}

			return $sanitized;
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}

		return $this->bounded_text( $this->redact_sensitive_debug_text( (string) $value ), self::DEBUG_PAYLOAD_MAX_STRING_CHARS );
	}


	protected function redact_sensitive_debug_text( string $value ): string {
		$patterns = array(
			'/\bBearer\s+[A-Za-z0-9._~+\/=-]{12,}\b/i',
			'/\b(?:api[_-]?key|access[_-]?token|refresh[_-]?token|secret|password)\s*[:=]\s*[A-Za-z0-9._~+\/=-]{8,}/i',
			'/\b(?:sk|pk|rk|ghp|gho|github_pat|xox[baprs])[_-][A-Za-z0-9._-]{12,}\b/i',
			'/\b[A-Za-z0-9_-]{24,}\.[A-Za-z0-9_-]{16,}\.[A-Za-z0-9_-]{16,}\b/',
		);

		foreach ( $patterns as $pattern ) {
			$value = preg_replace( $pattern, '[redacted]', $value ) ?? $value;
		}

		return $value;
	}


	protected function is_sensitive_payload_key( string $key ): bool {
		$normalized = strtolower( preg_replace( '/[^a-z0-9]+/', '_', $key ) ?? $key );
		$normalized = trim( $normalized, '_' );
		if ( '' === $normalized ) {
			return false;
		}

		$sensitive_keys = array(
			'authorization',
			'api_key',
			'apikey',
			'access_token',
			'refresh_token',
			'id_token',
			'token',
			'secret',
			'password',
			'credential',
			'private_key',
			'cookie',
			'set_cookie',
			'headers',
			'request_headers',
			'response_headers',
			'raw_headers',
			'billing',
			'quota',
			'request_log',
			'response_log',
		);
		if ( in_array( $normalized, $sensitive_keys, true ) ) {
			return true;
		}

		foreach ( array( '_api_key', '_token', '_secret', '_password', '_credential', '_private_key' ) as $suffix ) {
			if ( strlen( $normalized ) >= strlen( $suffix ) && substr( $normalized, -strlen( $suffix ) ) === $suffix ) {
				return true;
			}
		}

		return false;
	}


	protected function is_truthy( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			return 0 !== (int) $value;
		}
		if ( is_string( $value ) ) {
			return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes', 'on' ), true );
		}

		return false;
	}


	protected function is_list( array $value ): bool {
		$index = 0;
		foreach ( $value as $key => $unused ) {
			unset( $unused );
			if ( $key !== $index ) {
				return false;
			}
			++$index;
		}

		return true;
	}

	protected function local_media_visual_source( int $attachment_id ): array {
		if ( 0 >= $attachment_id || ! function_exists( 'get_attached_file' ) || ! function_exists( 'wp_upload_dir' ) ) {
			return array();
		}
		$path        = get_attached_file( $attachment_id );
		$upload_dir  = wp_upload_dir();
		$upload_root = realpath( (string) ( $upload_dir['basedir'] ?? '' ) );
		$real_path   = is_string( $path ) ? realpath( $path ) : false;
		if (
			false === $upload_root
			|| false === $real_path
			|| ! is_file( $real_path )
			|| ! is_readable( $real_path )
			|| ( $upload_root !== $real_path && 0 !== strpos( $real_path, trailingslashit( $upload_root ) ) )
		) {
			return array();
		}
		$file_size = filesize( $real_path );
		if ( false === $file_size || 0 >= $file_size || 8 * MB_IN_BYTES < $file_size ) {
			return array();
		}
		$original_mime_type = function_exists( 'wp_get_image_mime' ) ? wp_get_image_mime( $real_path ) : '';
		if ( ! is_string( $original_mime_type ) || ! in_array( $original_mime_type, array( 'image/avif', 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
			return array();
		}
		$fingerprint = hash_file( 'sha256', $real_path );
		if ( ! is_string( $fingerprint ) || '' === $fingerprint ) {
			return array();
		}
		$source_path = $real_path;
		if ( $file_size > self::SITE_MEDIA_VISUAL_MAX_UPLOAD_BYTES && function_exists( 'wp_get_attachment_metadata' ) ) {
			$metadata   = wp_get_attachment_metadata( $attachment_id );
			$sizes      = is_array( $metadata ) && is_array( $metadata['sizes'] ?? null ) ? $metadata['sizes'] : array();
			$candidates = array();
			foreach ( $sizes as $size ) {
				if ( ! is_array( $size ) || empty( $size['file'] ) ) {
					continue;
				}
				$candidate_path = realpath( dirname( $real_path ) . DIRECTORY_SEPARATOR . basename( (string) $size['file'] ) );
				if (
					false === $candidate_path
					|| ! is_file( $candidate_path )
					|| ! is_readable( $candidate_path )
					|| 0 !== strpos( $candidate_path, trailingslashit( $upload_root ) )
				) {
					continue;
				}
				$candidate_size = filesize( $candidate_path );
				if ( false === $candidate_size || 0 >= $candidate_size || self::SITE_MEDIA_VISUAL_MAX_UPLOAD_BYTES < $candidate_size ) {
					continue;
				}
				$candidates[] = array(
					'path' => $candidate_path,
					'area' => absint( $size['width'] ?? 0 ) * absint( $size['height'] ?? 0 ),
				);
			}
			usort(
				$candidates,
				static function ( array $left, array $right ): int {
					return (int) $right['area'] <=> (int) $left['area'];
				}
			);
			if ( ! empty( $candidates ) ) {
				$source_path = (string) $candidates[0]['path'];
			}
		}
		$source_size = filesize( $source_path );
		if ( false === $source_size || 0 >= $source_size || self::SITE_MEDIA_VISUAL_MAX_UPLOAD_BYTES < $source_size ) {
			return array();
		}
		$mime_type = function_exists( 'wp_get_image_mime' ) ? wp_get_image_mime( $source_path ) : $original_mime_type;
		if ( ! is_string( $mime_type ) || ! in_array( $mime_type, array( 'image/avif', 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
			return array();
		}
		$extension = array(
			'image/avif' => 'avif',
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/webp' => 'webp',
		)[ $mime_type ];

		return array(
			'path'              => $source_path,
			'filename'          => 'site-media-' . $attachment_id . '.' . $extension,
			'mime_type'         => $mime_type,
			'media_fingerprint' => 'sha256:' . strtolower( $fingerprint ),
		);
	}


	protected function runtime_safe_media_fingerprint( string $fingerprint ): string {
		$fingerprint = trim( sanitize_text_field( $fingerprint ) );
		if ( '' === $fingerprint ) {
			return '';
		}
		if ( 1 === preg_match( '/^sha256:[a-f0-9]{64}$/i', $fingerprint ) ) {
			return strtolower( $fingerprint );
		}

		if ( 1 === preg_match( '/^[a-f0-9]{64}$/i', $fingerprint ) ) {
			return 'sha256:' . strtolower( $fingerprint );
		}

		return '';
	}


	protected function runtime_safe_media_url( string $url ): string {
		$url = esc_url_raw( $url, array( 'http', 'https' ) );
		if ( '' === $url || 1 !== preg_match( '~^(https?://[^/?#]+)(.*)$~i', $url, $matches ) ) {
			return '';
		}

		$suffix = preg_replace_callback(
			'/%[0-9A-Fa-f]{2}|\d/',
			static function ( array $token ): string {
				return '%' === $token[0][0]
					? $token[0]
					: '%' . strtoupper( bin2hex( $token[0] ) );
			},
			$matches[2]
		);

		return $matches[1] . ( is_string( $suffix ) ? $suffix : '' );
	}
}
