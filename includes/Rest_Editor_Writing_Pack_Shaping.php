<?php
/**
 * Editor writing-pack shaping cluster: required-field checks, hosted
 * output shaping, inferred/resolved field merging, list and payload
 * value normalization, related-article rows, and the request brief
 * parser, moved verbatim from Rest_Editor_Content_Support (editor split
 * session 4). The flow orchestrators (context, flow, query) stay in the
 * service: they drive the cached Cloud flows inherited from
 * Rest_Editor_Flow_Cache.
 *
 * Suggestion-only by contract: values are review rows for operator
 * confirmation; nothing here writes posts, terms, media, or settings.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

final class Rest_Editor_Writing_Pack_Shaping {

	public static function editor_writing_pack_required_fields( array $pack ): array {
		$required = array(
			'inputs.editorial_brief.audience'     => $pack['inputs']['editorial_brief']['audience']['value'] ?? '',
			'inputs.editorial_brief.article_goal' => $pack['inputs']['editorial_brief']['article_goal']['value'] ?? '',
			'inputs.editorial_brief.focus_points' => $pack['inputs']['editorial_brief']['focus_points']['value'] ?? array(),
			'site_adaptation.unique_angle'        => $pack['site_adaptation']['unique_angle']['value'] ?? '',
			'writing_plan.outline'                => $pack['writing_plan']['outline'] ?? array(),
		);
		if ( in_array( (string) ( $pack['input_mode'] ?? '' ), array( 'url_reference', 'mixed' ), true ) ) {
			$required['research_basis.fact_ledger'] = $pack['research_basis']['fact_ledger'] ?? array();
			$required['inputs.source_materials']    = $pack['inputs']['source_materials'] ?? array();
		}
		$missing = array();
		foreach ( $required as $path => $value ) {
			if ( ! self::editor_writing_pack_has_value( $value ) ) {
				$missing[] = $path;
			}
		}

		return $missing;
	}


	public static function editor_writing_pack_has_value( $value ): bool {
		if ( is_scalar( $value ) ) {
			return '' !== trim( (string) $value );
		}
		if ( ! is_array( $value ) ) {
			return false;
		}
		foreach ( $value as $item ) {
			if ( self::editor_writing_pack_has_value( $item ) ) {
				return true;
			}
		}

		return false;
	}


	public static function editor_writing_pack_hosted_output( array $review ): array {
		if ( is_array( $review['output_json'] ?? null ) ) {
			return $review['output_json'];
		}
		$result = is_array( $review['result'] ?? null ) ? $review['result'] : array();
		if ( is_array( $result['output_json'] ?? null ) ) {
			return $result['output_json'];
		}

		return array();
	}


	public static function editor_writing_pack_inferred_field( $value ): array {
		if ( is_array( $value ) && array_key_exists( 'value', $value ) ) {
			$value = $value['value'];
		}
		return array(
			'value'              => self::editor_writing_pack_payload_value( $value ),
			'source'             => 'ai_inferred_from_source_and_site_context',
			'operator_confirmed' => false,
		);
	}


	public static function editor_writing_pack_resolved_field( $operator_value, $inferred_value ): array {
		$has_operator_value = is_array( $operator_value ) ? ! empty( $operator_value ) : '' !== trim( (string) $operator_value );
		if ( $has_operator_value ) {
			return array(
				'value'              => self::editor_writing_pack_payload_value( $operator_value ),
				'source'             => 'operator_supplied_brief',
				'operator_confirmed' => true,
			);
		}

		return self::editor_writing_pack_inferred_field( $inferred_value );
	}


	public static function editor_writing_pack_list( $value, int $limit ): array {
		$is_list = is_array( $value ) && ( array() === $value || array_keys( $value ) === range( 0, count( $value ) - 1 ) );
		$items   = $is_list ? $value : ( null === $value || '' === $value ? array() : array( $value ) );
		$result  = array();
		foreach ( array_slice( $items, 0, max( 1, min( 20, $limit ) ) ) as $item ) {
			$sanitized = self::editor_writing_pack_payload_value( $item );
			if ( null !== $sanitized && '' !== $sanitized && array() !== $sanitized ) {
				$result[] = $sanitized;
			}
		}

		return $result;
	}


	public static function editor_writing_pack_payload_value( $value, int $depth = 0 ) {
		if ( $depth > 6 || null === $value ) {
			return null;
		}
		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			return wp_trim_words( sanitize_textarea_field( wp_strip_all_tags( $value ) ), 180, '' );
		}
		if ( ! is_array( $value ) ) {
			return null;
		}

		$result = array();
		foreach ( array_slice( $value, 0, 20, true ) as $key => $item ) {
			$clean_key = is_int( $key ) ? $key : sanitize_key( (string) $key );
			if ( '' === (string) $clean_key && ! is_int( $clean_key ) ) {
				continue;
			}
			$clean_value = self::editor_writing_pack_payload_value( $item, $depth + 1 );
			if ( null !== $clean_value ) {
				$result[ $clean_key ] = $clean_value;
			}
		}

		return $result;
	}


	public static function editor_writing_pack_related_articles( array $knowledge ): array {
		$items  = Rest_Editor_Flow_Cache::editor_related_content_items( $knowledge );
		$result = array();
		foreach ( array_slice( $items, 0, 6 ) as $index => $item ) {
			$result[] = array(
				'post_id'      => absint( $item['post_id'] ?? $item['id'] ?? 0 ),
				'title'        => sanitize_text_field( (string) ( $item['title'] ?? $item['name'] ?? '' ) ),
				'url'          => esc_url_raw( (string) ( $item['url'] ?? $item['permalink'] ?? '' ) ),
				'score'        => is_numeric( $item['score'] ?? null ) ? (float) $item['score'] : null,
				'evidence_ref' => 'site_knowledge:' . sanitize_key( (string) ( $item['post_id'] ?? $item['id'] ?? $index ) ),
			);
		}

		return $result;
	}


	public static function editor_writing_pack_request_brief( $raw ): array {
		if ( is_string( $raw ) && '' !== trim( $raw ) ) {
			$decoded = json_decode( $raw, true );
			$raw     = is_array( $decoded ) ? $decoded : array();
		}
		$raw    = is_array( $raw ) ? $raw : array();
		$result = array();
		foreach ( array( 'audience', 'article_goal', 'reader_problem', 'unique_angle', 'reader_promise', 'content_type', 'operator_instruction' ) as $field ) {
			$result[ $field ] = wp_trim_words( sanitize_textarea_field( wp_strip_all_tags( (string) ( $raw[ $field ] ?? '' ) ) ), 120, '' );
		}
		foreach ( array( 'focus_points', 'title_directions', 'outline' ) as $field ) {
			$value = $raw[ $field ] ?? array();
			if ( is_string( $value ) ) {
				$value = preg_split( '/\r\n|\r|\n/', $value );
			}
			$result[ $field ] = self::editor_writing_pack_list( is_array( $value ) ? $value : array(), 12 );
		}

		return $result;
	}
}
