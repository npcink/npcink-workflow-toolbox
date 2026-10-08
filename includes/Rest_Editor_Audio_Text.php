<?php
/**
 * Editor audio cluster: request-side narration preferences parsing, raw-
 * content audio text shaping, and summary-script text selection, moved
 * verbatim from Rest_Editor_Content_Support as the second cohesive
 * sub-service of the queued editor split (Provider Split Refactor
 * Standard v1). The preferences and text-shaping methods keep
 * WP_REST_Request/string/array inputs. This class owns the shared
 * editor trim helper for the whole split (service callers route
 * through Rest_Editor_Audio_Text::trim), and the service's own
 * EDITOR_AUDIO_TEXT_MAX_CHARS constant was removed in favor of this
 * class's copy so the cap has exactly one definition.
 *
 * Suggestion-only by contract: outputs feed review artifacts and
 * narration previews; nothing here writes posts, media, or settings.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

final class Rest_Editor_Audio_Text {

	public const EDITOR_AUDIO_TEXT_MAX_CHARS = 5000;

	public static function editor_audio_preferences_from_request( WP_REST_Request $request ): array {
		$raw = $request->get_param( 'audio_preferences' );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$defaults    = array(
			'tone'     => 'calm',
			'pace'     => 'normal',
			'handling' => 'skip_code',
			'focus'    => 'product_names',
		);
		$allowed     = array(
			'tone'     => array( 'calm', 'formal', 'casual', 'expressive' ),
			'pace'     => array( 'normal', 'slow', 'fast' ),
			'handling' => array( 'skip_code', 'read_code', 'skip_tables' ),
			'focus'    => array( 'product_names', 'numbers', 'headings' ),
		);
		$preferences = array();
		foreach ( $defaults as $key => $default ) {
			$value               = sanitize_key( (string) ( $raw[ $key ] ?? $default ) );
			$preferences[ $key ] = in_array( $value, $allowed[ $key ], true ) ? $value : $default;
		}
		return $preferences;
	}


	public static function editor_audio_text_from_raw_content( string $content, array $audio_preferences ): string {
		$source   = $content;
		$handling = sanitize_key( (string) ( $audio_preferences['handling'] ?? 'skip_code' ) );
		if ( 'skip_code' === $handling ) {
			$source = preg_replace( '/<!--\s*wp:code\b.*?<!--\s*\/wp:code\s*-->/is', ' ', $source );
			$source = preg_replace( '/<pre\b[^>]*>.*?<\/pre>/is', ' ', $source );
			$source = preg_replace( '/<code\b[^>]*>.*?<\/code>/is', ' ', $source );
		}
		if ( 'skip_tables' === $handling ) {
			$source = preg_replace( '/<!--\s*wp:table\b.*?<!--\s*\/wp:table\s*-->/is', ' ', $source );
			$source = preg_replace( '/<table\b[^>]*>.*?<\/table>/is', ' ', $source );
		}
		return trim( wp_strip_all_tags( (string) $source ) );
	}


	public static function editor_audio_source_text( string $text ): string {
		$plain = trim( wp_strip_all_tags( $text ) );
		if ( '' === $plain ) {
			return '';
		}
		return self::editor_trim_chars( $plain, self::EDITOR_AUDIO_TEXT_MAX_CHARS );
	}


	public static function editor_audio_summary_script_text( array $summary_ai ): string {
		$output_json = is_array( $summary_ai['output_json'] ?? null ) ? $summary_ai['output_json'] : array();
		$parts       = array();
		foreach ( array( 'opening', 'script', 'closing' ) as $key ) {
			$value = trim( sanitize_textarea_field( (string) ( $output_json[ $key ] ?? '' ) ) );
			if ( '' !== $value ) {
				$parts[] = $value;
			}
		}
		if ( is_array( $output_json['key_points'] ?? null ) ) {
			foreach ( array_slice( $output_json['key_points'], 0, 5 ) as $point ) {
				$value = trim( sanitize_textarea_field( (string) $point ) );
				if ( '' !== $value ) {
					$parts[] = $value;
				}
			}
		}
		$script = trim( implode( "\n\n", array_values( array_unique( $parts ) ) ) );
		if ( '' === $script ) {
			$script = trim( sanitize_textarea_field( (string) ( $summary_ai['output_text'] ?? '' ) ) );
		}
		return self::editor_trim_chars( $script, self::EDITOR_AUDIO_TEXT_MAX_CHARS );
	}

	private static function editor_trim_chars( string $value, int $max_chars ): string {
		$value     = trim( $value );
		$max_chars = max( 1, $max_chars );
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			return mb_strlen( $value, 'UTF-8' ) > $max_chars ? mb_substr( $value, 0, $max_chars, 'UTF-8' ) : $value;
		}

		return strlen( $value ) > $max_chars ? substr( $value, 0, $max_chars ) : $value;
	}

	public static function trim( string $value, int $max_chars ): string {
		return self::editor_trim_chars( $value, $max_chars );
	}
}
