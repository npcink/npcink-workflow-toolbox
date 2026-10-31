<?php
/** Cloud formatting candidate validation; no formatting rules or WordPress writes. */

namespace Npcink_Toolbox;

use WP_Error;
use WP_HTML_Tag_Processor;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

final class Editor_Content_Format {
	/** Accept exact editor content before the ordinary context sanitizer or cache. */
	public static function request( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$keys   = is_array( $params ) ? array_keys( $params ) : array();
		sort( $keys );
		$content = $params['content'] ?? null;
		$post_id = $params['post_id'] ?? null;
		if ( array( 'content', 'intent', 'post_id' ) !== $keys || ! is_string( $content )
			|| '' === trim( $content ) || strlen( $content ) > 100000 || 1 !== preg_match( '//u', $content )
			|| ! is_int( $post_id ) || $post_id < 1 ) {
			return self::error( 'input', __( 'The article body is empty, too long, or the request format is invalid. The original text is unchanged.', 'npcink-workflow-toolbox' ), 400 );
		}
		if ( ! Rest_Controller::user_can_use_editor_support() || ! current_user_can( 'edit_post', $post_id ) || ! get_post( $post_id ) ) {
			return self::error( 'permission', __( 'You do not have permission to format this article.', 'npcink-workflow-toolbox' ), 403 );
		}
		if ( ! function_exists( 'npcink_cloud_addon_execute_toolbox_content_format_runtime' ) ) {
			return self::error( 'unavailable', __( 'Cloud Addon is not ready yet. The original text is unchanged.', 'npcink-workflow-toolbox' ), 503 );
		}
		$response = npcink_cloud_addon_execute_toolbox_content_format_runtime(
			array(
				'content'       => $content,
				'format'        => 'html',
				'source_sha256' => hash( 'sha256', $content ),
			)
		);
		if ( is_wp_error( $response ) ) {
			// Never echo arbitrary upstream errors or runtime payloads into the editor.
			return self::error( 'cloud', __( 'Cloud formatting is temporarily unavailable. The original text is unchanged.', 'npcink-workflow-toolbox' ), 502 );
		}
		$result = $response['data']['result'] ?? null;
		if ( ! self::valid_result( $content, $result ) ) {
			return self::error( 'candidate', __( 'The formatting result failed the body protection check. The original text is unchanged.', 'npcink-workflow-toolbox' ), 422 );
		}
		$result['post_id']   = $post_id;
		$result['persisted'] = false;
		$response            = rest_ensure_response( $result );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	public static function valid_result( string $source, $result ): bool {
		if ( is_array( $result ) && 'content_format_candidate.v2' === ( $result['contract_version'] ?? null ) ) {
			return self::valid_structure_result( $source, $result );
		}
		$keys = is_array( $result ) ? array_keys( $result ) : array();
		sort( $keys );
		if ( array( 'candidate', 'candidate_sha256', 'contract_version', 'direct_wordpress_write', 'format', 'inserted_spaces', 'protected_content_skipped', 'source_sha256', 'status', 'visible_characters_preserved' ) !== $keys ) {
			return false;
		}
		if ( ! is_array( $result ) || 'content_format_candidate.v1' !== ( $result['contract_version'] ?? null )
			|| 'html' !== ( $result['format'] ?? null )
			|| ! in_array( $result['status'] ?? null, array( 'CHANGED', 'UNCHANGED', 'PARTIAL', 'REVIEW' ), true )
			|| true !== ( $result['visible_characters_preserved'] ?? null )
			|| false !== ( $result['direct_wordpress_write'] ?? null )
			|| ! is_bool( $result['protected_content_skipped'] ?? null )
			|| ! is_string( $result['candidate'] ?? null )
			|| strlen( $result['candidate'] ) > 200000
			|| hash( 'sha256', $source ) !== ( $result['source_sha256'] ?? null )
			|| hash( 'sha256', $result['candidate'] ) !== ( $result['candidate_sha256'] ?? null ) ) {
			return false;
		}
		$candidate       = $result['candidate'];
		$expected_status = $result['protected_content_skipped']
			? ( $source === $candidate ? 'REVIEW' : 'PARTIAL' )
			: ( $source === $candidate ? 'UNCHANGED' : 'CHANGED' );
		if ( $expected_status !== $result['status']
			|| strlen( $candidate ) - strlen( $source ) !== ( $result['inserted_spaces'] ?? null ) ) {
			return false;
		}
		// Mask editable text using WordPress's tokenizer, retaining exact markup,
		// comments, attributes and protected text. This validates, not formats.
		return self::spacing_preserved( $source, $candidate );
	}

	private static function protected_blocks_unchanged( array $before, array $after ): bool {
		if ( count( $before ) !== count( $after ) ) {
			return false; }
		foreach ( $before as $index => $block ) {
			$next = $after[ $index ];
			if ( $block['blockName'] !== $next['blockName'] || $block['attrs'] !== $next['attrs'] ) {
				return false; }
			$editable = in_array( $block['blockName'], array( 'core/paragraph', 'core/heading', 'core/list', 'core/list-item', 'core/quote' ), true )
				&& ! isset( $block['attrs']['metadata'] );
			if ( ! $editable && null !== $block['blockName'] && $block !== $next ) {
				return false; }
			if ( ! self::protected_blocks_unchanged( $block['innerBlocks'], $next['innerBlocks'] ) ) {
				return false; }
		}
		return true;
	}

	private static function valid_structure_result( string $source, array $result ): bool {
		$keys = array_keys( $result );
		sort( $keys );
		if ( array( 'candidate', 'candidate_sha256', 'contract_version', 'direct_wordpress_write', 'format', 'inserted_spaces', 'protected_content_skipped', 'source_sha256', 'status', 'structural_changes', 'visible_characters_preserved' ) !== $keys
			|| 'html' !== $result['format'] || false !== $result['direct_wordpress_write'] || true !== $result['visible_characters_preserved']
			|| ! is_bool( $result['protected_content_skipped'] ) || ! is_int( $result['structural_changes'] ) || $result['structural_changes'] < 0
			|| ! is_int( $result['inserted_spaces'] ) || $result['inserted_spaces'] < 0
			|| ! is_string( $result['candidate'] ) || strlen( $result['candidate'] ) > 200000
			|| hash( 'sha256', $source ) !== $result['source_sha256'] || hash( 'sha256', $result['candidate'] ) !== $result['candidate_sha256'] ) {
			return false; }
		$changed = $source !== $result['candidate'];
		$status  = $result['protected_content_skipped'] ? ( $changed ? 'PARTIAL' : 'REVIEW' ) : ( $changed ? 'CHANGED' : 'UNCHANGED' );
		if ( $status !== $result['status'] || ( ! $changed && ( $result['structural_changes'] || $result['inserted_spaces'] ) ) ) {
			return false; }
		if ( ! $changed ) {
			return true; }
		$before = self::structure_projection( parse_blocks( $source ) );
		$after  = self::structure_projection( parse_blocks( $result['candidate'] ) );
		if ( false === $before || false === $after || count( $before ) !== count( $after ) ) {
			return false; }
		foreach ( $before as $index => $token ) {
			$next = $after[ $index ];
			if ( $token[0] !== $next[0] || ( 'text' === $token[0] ? ! self::only_inserted_spaces( $token[1], $next[1] )
				: ( 'spacing' === $token[0] ? ! self::spacing_preserved( $token[1], $next[1] ) : $token !== $next ) ) ) {
				return false; }
		}
		return true;
	}

	/** Compare content in order while allowing only plain paragraph/list boundaries and BR. */
	private static function structure_projection( array $blocks ) {
		$tokens = array();
		$append = static function ( string $kind, $value ) use ( &$tokens ): void {
			$last = count( $tokens ) - 1;
			if ( 'text' === $kind && $last >= 0 && 'text' === $tokens[ $last ][0] ) {
				$tokens[ $last ][1] .= $value; } else {
				$tokens[] = array( $kind, $value ); }
		};
		foreach ( $blocks as $block ) {
			if ( null === $block['blockName'] && '' === trim( $block['innerHTML'] ) ) {
				continue; }
			$fragments = array();
			if ( 'core/paragraph' === $block['blockName'] && ! $block['attrs'] && ! $block['innerBlocks']
				&& preg_match( '~^\s*<p>(.*)</p>\s*$~s', $block['innerHTML'], $match ) ) {
				$fragments[] = $match[1]; } elseif ( 'core/list' === $block['blockName'] && ! $block['attrs']
				&& preg_match( '~^\s*<ul class="wp-block-list">\s*</ul>\s*$~s', $block['innerHTML'] ) ) {
					foreach ( $block['innerBlocks'] as $item ) {
						if ( 'core/list-item' !== $item['blockName'] || $item['attrs'] || $item['innerBlocks']
						|| ! preg_match( '~^\s*<li>(.*)</li>\s*$~s', $item['innerHTML'], $match ) ) {
							return false; }
						$fragments[] = $match[1];
					}
				} else {
					$spacing = in_array( $block['blockName'], array( 'core/paragraph', 'core/heading', 'core/list', 'core/list-item', 'core/quote' ), true )
						&& ! isset( $block['attrs']['metadata'] );
					$append( $spacing ? 'spacing' : 'protected', serialize_block( $block ) );
					continue; }
				$inline = self::inline_projection( $fragments );
				if ( false === $inline ) {
					// An unsupported fragment is opaque, not a reason to reject safe siblings.
					$append( 'protected', serialize_block( $block ) );
					continue;
				}
				foreach ( $inline as $token ) {
					$append( $token[0], $token[1] );
				}
		}
		return $tokens;
	}

	/** Unsupported or unbalanced inline HTML must remain byte-identical. */
	private static function inline_projection( array $fragments ) {
		$tokens = array();
		foreach ( $fragments as $html ) {
			$parser = new WP_HTML_Tag_Processor( $html );
			$stack  = array();
			while ( $parser->next_token() ) {
				$type = $parser->get_token_type();
				if ( '#text' === $type ) {
					$tokens[] = array( array_intersect( array( 'A', 'CODE' ), $stack ) ? 'protected_text' : 'text', $parser->get_modifiable_text() );
					continue; }
				if ( '#tag' !== $type ) {
					return false; }
				$tag   = $parser->get_tag();
				$attrs = array();
				foreach ( $parser->get_attribute_names_with_prefix( '' ) ?? array() as $name ) {
					$attrs[ $name ] = $parser->get_attribute( $name ); }
				ksort( $attrs );
				if ( 'BR' === $tag && ! $attrs && ! $parser->is_tag_closer() ) {
					continue; }
				if ( ! in_array( $tag, array( 'A', 'STRONG', 'EM', 'B', 'I', 'S', 'DEL', 'CODE' ), true ) ) {
					return false; }
				if ( $parser->is_tag_closer() ) {
					if ( array_pop( $stack ) !== $tag ) {
						return false; }
				} else {
					$stack[] = $tag; }
				$tokens[] = array( 'tag', array( $tag, $parser->is_tag_closer(), $attrs ) );
			}
			if ( $stack ) {
				return false; }
		}
		return $tokens;
	}

	/** Reuse the v1 spacing guard for blocks whose layout must not change. */
	private static function spacing_preserved( string $source, string $candidate ): bool {
		if ( $source === $candidate ) {
			return true;
		}
		return self::only_inserted_spaces( $source, $candidate )
			&& self::protected_blocks_unchanged( parse_blocks( $source ), parse_blocks( $candidate ) )
			&& self::markup_fingerprint( $source ) === self::markup_fingerprint( $candidate );
	}

	public static function only_inserted_spaces( string $source, string $candidate ): bool {
		// Validate the Cloud spacing contract; never split a word, number or symbol.
		$source_chars    = preg_split( '//u', $source, -1, PREG_SPLIT_NO_EMPTY );
		$candidate_chars = preg_split( '//u', $candidate, -1, PREG_SPLIT_NO_EMPTY );
		if ( false === $source_chars || false === $candidate_chars ) {
			return false;
		}
		$i      = 0;
		$length = count( $source_chars );
		foreach ( $candidate_chars as $j => $char ) {
			if ( $i < $length && $source_chars[ $i ] === $char ) {
				++$i;
			} elseif ( ' ' !== $char || ( $candidate_chars[ $j + 1 ] ?? null ) !== ( $source_chars[ $i ] ?? null )
				|| 1 !== preg_match( '/^(?:[\x{3400}-\x{4dbf}\x{4e00}-\x{9fff}][A-Za-z0-9]|[A-Za-z0-9][\x{3400}-\x{4dbf}\x{4e00}-\x{9fff}])$/u', ( $source_chars[ $i - 1 ] ?? '' ) . ( $source_chars[ $i ] ?? '' ) ) ) {
				return false;
			}
		}
		return $i === $length;
	}

	private static function markup_fingerprint( string $html ): string {
		$processor = new WP_HTML_Tag_Processor( $html );
		$protected = array();
		$mask      = array();
		while ( $processor->next_token() ) {
			$type = $processor->get_token_type();
			if ( '#tag' === $type ) {
				$tag = $processor->get_tag();
				if ( in_array( $tag, array( 'A', 'CODE', 'PRE', 'SCRIPT', 'STYLE', 'TEXTAREA', 'TABLE', 'FIGURE', 'SVG', 'MATH' ), true ) ) {
					if ( $processor->is_tag_closer() ) {
						array_pop( $protected );
					} else {
						$protected[] = $tag;
					}
				}
			} elseif ( '#text' === $type && ! $protected ) {
				// Include non-space text identity per node so text cannot migrate between nodes.
				$mask[] = str_replace( ' ', '', $processor->get_modifiable_text() );
				$processor->set_modifiable_text( 'NPCINK_FORMAT_TEXT' );
			}
		}
		return hash( 'sha256', wp_json_encode( $mask ) . $processor->get_updated_html() );
	}

	private static function error( string $code, string $message, int $status ): WP_Error {
		return new WP_Error( 'npcink_content_format_' . $code, $message, array( 'status' => $status ) );
	}
}
