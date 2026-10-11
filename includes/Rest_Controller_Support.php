<?php
/**
 * Shared REST input validation helpers for the controller facade and its
 * extracted cluster services.
 *
 * Internal plumbing for the Rest_Controller split, never an extension
 * point: bound text extraction that Cloud calls rely on.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

abstract class Rest_Controller_Support {

	protected const REQUIRED_TEXT_MAX_CHARS = 500;

	protected function required_text( WP_REST_Request $request, string $key, int $max_chars = self::REQUIRED_TEXT_MAX_CHARS ) {
		$value = trim( sanitize_textarea_field( (string) $request->get_param( $key ) ) );
		if ( '' === $value ) {
			return new WP_Error(
				'npcink_toolbox_missing_' . sanitize_key( $key ),
				sprintf(
					/* translators: %s: field name. */
					__( '%s is required.', 'npcink-workflow-toolbox' ),
					$key
				),
				array( 'status' => 400 )
			);
		}

		$max_chars = max( 1, $max_chars );
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			if ( mb_strlen( $value ) > $max_chars ) {
				return mb_substr( $value, 0, $max_chars );
			}
			return $value;
		}

		if ( strlen( $value ) > $max_chars ) {
			return substr( $value, 0, $max_chars );
		}

		return $value;
	}

	protected function csv_list( string $value ): array {
		$items = array_filter( array_map( 'trim', explode( ',', $value ) ) );
		return array_values(
			array_filter(
				array_map( 'sanitize_text_field', $items ),
				static fn( string $item ): bool => '' !== $item
			)
		);
	}

	protected function csv_absint_list( string $value ): array {
		$items = array_filter( array_map( 'trim', explode( ',', $value ) ) );
		return array_values(
			array_filter(
				array_map( 'absint', $items ),
				static fn( int $item ): bool => 0 < $item
			)
		);
	}

	/**
	 * Forwards an allow-listed request body to a provider call. Unknown
	 * top-level fields are rejected before anything reaches the provider
	 * (the media-derivative strict-input precedent), provider WP_Error
	 * data passes through the safe-diagnostics allow-list before it is
	 * returned, and coalesced routes share one short per-user transient
	 * unless a refresh token (refresh_variant / regeneration_mode) is
	 * supplied, bounding rapid-fire duplicate spend from relaxed-scope
	 * editor surfaces.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @param string[]        $allowed_fields Top-level fields the provider surface accepts.
	 * @param callable        $invoker Receives the filtered params array, returns the provider result.
	 * @param string          $cache_namespace Transient namespace used when coalescing.
	 * @param bool            $coalesce Whether identical payloads coalesce behind a per-user transient.
	 * @return mixed REST-ready response.
	 */
	protected function forward_provider_request( WP_REST_Request $request, array $allowed_fields, callable $invoker, string $cache_namespace, bool $coalesce = false, ?callable $cacheable = null ) {
		// Only the request body participates: get_params() merges URL and
		// query parameters, and core clients append _locale/_envelope keys
		// that must not trip the strict allow-list.
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$body_params = $request->get_body_params();
			$params      = is_array( $body_params ) && array() !== $body_params ? $body_params : array();
			$body        = (string) $request->get_body();
			if ( array() === $params && '' !== $body ) {
				// A JSON body without the application/json content type still
				// deserves object parsing; anything non-empty that does not
				// decode to an object fails with a clear 400 instead of
				// forwarding an empty payload to the provider.
				$decoded = json_decode( $body, true );
				$params  = is_array( $decoded ) ? $decoded : array();
				if ( array() === $params ) {
					return new WP_Error(
						'npcink_toolbox_invalid_request_body',
						__( 'The request body is not a valid JSON object.', 'npcink-workflow-toolbox' ),
						array( 'status' => 400 )
					);
				}
			}
		}
		if ( array() !== array_diff( array_keys( $params ), $allowed_fields ) ) {
			return new WP_Error(
				'npcink_toolbox_unsupported_provider_field',
				__( 'This request contains fields the provider call does not accept.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$filtered = array();
		foreach ( $allowed_fields as $field ) {
			if ( array_key_exists( $field, $params ) ) {
				$filtered[ $field ] = $params[ $field ];
			}
		}

		$cache_key = '';
		if ( $coalesce ) {
			$refresh = false;
			foreach ( array( $filtered['refresh_variant'] ?? '', $filtered['regeneration_mode'] ?? '' ) as $refresh_token ) {
				if ( is_scalar( $refresh_token ) && '' !== trim( (string) $refresh_token ) ) {
					$refresh = true;
					break;
				}
			}
			$key_source = $filtered;
			unset( $key_source['refresh_variant'], $key_source['regeneration_mode'] );
			$encoded = wp_json_encode( $key_source );
			if ( is_string( $encoded ) ) {
				$cache_key = 'npcink_toolbox_provider_' . sanitize_key( $cache_namespace ) . '_' . md5( (string) get_current_user_id() . $encoded );
				if ( ! $refresh ) {
					$cached = get_transient( $cache_key );
					if ( is_array( $cached ) && isset( $cached['__provider_coalesced'] ) ) {
						unset( $cached['__provider_coalesced'] );
						return rest_ensure_response( $cached );
					}
				}
			}
		}

		$result = $invoker( $filtered );
		if ( is_wp_error( $result ) ) {
			return rest_ensure_response( self::sanitize_provider_error_data( $result ) );
		}
		if ( '' !== $cache_key && is_array( $result ) && ! isset( $result['code'] ) && ( null === $cacheable || $cacheable( $result ) ) ) {
			// A refresh run overwrites the entry so the next plain request
			// sees the fresh result; degenerate results (error-shaped or
			// empty per the caller's predicate) and oversized payloads
			// (base64 preview bodies) skip coalescing rather than being
			// replayed or bloating wp_options.
			$cache_copy                         = $result;
			$cache_copy['__provider_coalesced'] = true;
			$encoded_result                     = wp_json_encode( $cache_copy );
			if ( is_string( $encoded_result ) && strlen( $encoded_result ) <= 102400 ) {
				set_transient( $cache_key, $cache_copy, 60 );
			}
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Rebuilds a provider WP_Error with its data reduced to the
	 * safe-diagnostics allow-list, so addon error details cannot leak
	 * unbounded fields into REST responses. Every error code keeps its
	 * message; scalar values are normalized (numbers cast to int, other
	 * scalars sanitized as text). By design, non-numeric scalar error
	 * data (string payloads attached without a status) and non-scalar
	 * values under allow-listed keys are dropped rather than passed
	 * through verbatim — first-party call sites attach array data.
	 *
	 * @param WP_Error $error Provider error.
	 * @return WP_Error
	 */
	public static function sanitize_provider_error_data( WP_Error $error ): WP_Error {
		$safe = new WP_Error();
		foreach ( $error->get_error_codes() as $code ) {
			$safe->add( $code, $error->get_error_message( $code ) );
			$data = $error->get_error_data( $code );
			if ( ! is_array( $data ) ) {
				// The core add_data( 400 ) convention carries the HTTP status
				// as a scalar; keep it instead of dropping it.
				if ( is_numeric( $data ) ) {
					$safe->add_data( array( 'status' => (int) $data ), $code );
				}
				continue;
			}
			$normalized = array();
			foreach ( $data as $key => $value ) {
				if ( in_array( sanitize_key( (string) $key ), array( 'status', 'provider_status', 'http_code', 'reason', 'request_id', 'cloud_error_code' ), true ) ) {
					$normalized[ sanitize_key( (string) $key ) ] = is_numeric( $value ) ? (int) $value : ( is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '' );
				}
			}
			if ( array() !== $normalized ) {
				// Errors without an explicit status serialize as HTTP 500 by
				// default; state that intent instead of relying on the
				// fallback silently.
				if ( ! array_key_exists( 'status', $normalized ) ) {
					$normalized['status'] = 500;
				}
				$safe->add_data( $normalized, $code );
			}
		}

		return $safe;
	}


	protected function sanitize_image_visual_context( array $context ): array {
		$mode = sanitize_key( (string) ( $context['image_mode'] ?? $context['image_use'] ?? '' ) );
		if ( ! in_array( $mode, array( 'featured', 'featured_image', 'paragraph', 'paragraph_image', 'inline', 'inline_image', 'setting', 'setting_image' ), true ) ) {
			$mode = 'featured_image';
		}
		if ( 'featured' === $mode ) {
			$mode = 'featured_image';
		}
		if ( 'paragraph' === $mode ) {
			$mode = 'paragraph_image';
		}
		if ( 'inline' === $mode ) {
			$mode = 'inline_image';
		}
		if ( 'setting' === $mode ) {
			$mode = 'setting_image';
		}

		return array(
			'image_mode'          => $mode,
			'manual_query'        => sanitize_text_field( (string) ( $context['manual_query'] ?? '' ) ),
			'fallback_query'      => sanitize_text_field( (string) ( $context['fallback_query'] ?? '' ) ),
			'post_id'             => max( 0, absint( $context['post_id'] ?? 0 ) ),
			'title'               => wp_trim_words( sanitize_text_field( (string) ( $context['title'] ?? '' ) ), 18, '' ),
			'excerpt'             => wp_trim_words( sanitize_textarea_field( (string) ( $context['excerpt'] ?? '' ) ), 36, '' ),
			'content_summary'     => wp_trim_words( sanitize_textarea_field( (string) ( $context['content_summary'] ?? $context['content_text'] ?? $context['content'] ?? '' ) ), 80, '' ),
			'selected_text'       => wp_trim_words( sanitize_textarea_field( (string) ( $context['selected_text'] ?? '' ) ), 80, '' ),
			'selected_block_text' => wp_trim_words( sanitize_textarea_field( (string) ( $context['selected_block_text'] ?? '' ) ), 80, '' ),
			'selected_block_name' => sanitize_key( (string) ( $context['selected_block_name'] ?? '' ) ),
			'avoid_brand_logos'   => ! empty( $context['avoid_brand_logos'] ),
			'latency_mode'        => sanitize_key( (string) ( $context['latency_mode'] ?? '' ) ),
			'refresh_variant'     => sanitize_text_field( (string) ( $context['refresh_variant'] ?? '' ) ),
			'query_intent'        => array(
				'rewrite_abstract_terms'       => ! empty( $context['query_intent']['rewrite_abstract_terms'] ),
				'prefer_concrete_visual_scene' => ! empty( $context['query_intent']['prefer_concrete_visual_scene'] ),
				'return_alternate_queries'     => ! empty( $context['query_intent']['return_alternate_queries'] ),
			),
		);
	}
}
