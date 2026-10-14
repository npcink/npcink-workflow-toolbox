<?php
/**
 * Internal-link recommendation cluster for editor content support.
 *
 * The facade keeps the cached Cloud orchestration entrypoint; this service
 * owns candidate normalization, bounded evidence shaping, Toolkit delegation,
 * and the shared recommendation projection. It is suggestion-only and never
 * registers routes or writes WordPress state.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Rest_Editor_Internal_Links {

	public static function editor_internal_link_merge_cloud_relevance( array $items, array $evidence ): array {
		$by_post_id = array();
		$by_url     = array();
		foreach ( $evidence as $evidence_item ) {
			$post_id = absint( $evidence_item['post_id'] ?? 0 );
			$url     = self::editor_internal_link_safe_candidate_url( $evidence_item );
			if ( $post_id > 0 ) {
				$by_post_id[ $post_id ] = $evidence_item;
			}
			if ( '' !== $url ) {
				$by_url[ $url ] = $evidence_item;
			}
		}

		foreach ( $items as &$item ) {
			$post_id = absint( $item['target_post_id'] ?? 0 );
			$url     = self::editor_internal_link_safe_candidate_url( $item );
			$match   = $post_id > 0 && isset( $by_post_id[ $post_id ] )
				? $by_post_id[ $post_id ]
				: ( '' !== $url && isset( $by_url[ $url ] ) ? $by_url[ $url ] : array() );
			if ( empty( $match ) ) {
				continue;
			}
			if ( array_key_exists( 'candidate_relevance', $match ) ) {
				$item['candidate_relevance'] = $match['candidate_relevance'];
			}
		}
		unset( $item );

		return $items;
	}

	public static function editor_internal_link_safe_candidate_url( array $item ): string {
		$values = array(
			$item['target_url'] ?? null,
			$item['url'] ?? null,
			$item['permalink'] ?? null,
			$item['link'] ?? null,
			is_array( $item['target_ref'] ?? null ) ? ( $item['target_ref']['url'] ?? null ) : null,
			$item['source_candidate_ref'] ?? null,
			is_array( $item['source'] ?? null ) ? ( $item['source']['target_url'] ?? null ) : null,
			is_array( $item['source'] ?? null ) ? ( $item['source']['url'] ?? null ) : null,
		);

		foreach ( $values as $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}
			$url = esc_url_raw( trim( (string) $value ), array( 'http', 'https' ) );
			if ( '' !== $url ) {
				return $url;
			}
		}

		return '';
	}

	public static function editor_internal_link_related_content_evidence( array $source_knowledge ): array {
		$evidence = array();
		foreach ( array_slice( Rest_Editor_Flow_Cache::editor_related_content_items( $source_knowledge ), 0, 8 ) as $index => $item ) {
			$post_id             = absint( $item['post_id'] ?? ( $item['id'] ?? 0 ) );
			$candidate_relevance = sanitize_key( (string) ( $item['candidate_relevance'] ?? '' ) );
			if ( ! in_array( $candidate_relevance, array( 'strong', 'review', 'weak' ), true ) ) {
				$candidate_relevance = '';
			}
			$evidence[] = array(
				'post_id'             => $post_id,
				'title'               => sanitize_text_field( (string) ( $item['title'] ?? $item['name'] ?? '' ) ),
				'url'                 => self::editor_internal_link_safe_candidate_url( $item ),
				'anchor_or_context'   => sanitize_text_field( (string) ( $item['anchor_or_context'] ?? ( $item['suggested_anchor_text'] ?? '' ) ) ),
				'excerpt'             => sanitize_textarea_field( wp_trim_words( wp_strip_all_tags( (string) ( $item['excerpt'] ?? $item['snippet'] ?? $item['content_excerpt'] ?? '' ) ), 55, '' ) ),
				'score'               => is_numeric( $item['score'] ?? null ) ? (float) $item['score'] : null,
				'evidence_ref'        => 'site_knowledge:' . sanitize_key( (string) ( $post_id ?: $index ) ),
				'candidate_relevance' => $candidate_relevance,
			);
		}

		return array_values(
			array_filter(
				$evidence,
				static fn( array $item ): bool => '' !== (string) $item['title'] || '' !== (string) $item['url'] || 0 < absint( $item['post_id'] )
			)
		);
	}

	public static function editor_internal_link_source_passages( array $context ): array {
		$passages    = array();
		$total_chars = 0;
		$blocks      = is_array( $context['content_blocks'] ?? null ) ? $context['content_blocks'] : array();
		foreach ( array_slice( $blocks, 0, 24 ) as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$text = trim( sanitize_textarea_field( Rest_Editor_Audio_Text::trim( wp_strip_all_tags( (string) ( $block['text'] ?? '' ) ), 1200 ) ) );
			if ( '' === $text ) {
				continue;
			}
			$text_length = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
			if ( $total_chars + $text_length > 12000 ) {
				break;
			}
			$passages[]   = $text;
			$total_chars += $text_length;
		}

		return $passages;
	}

	public static function editor_internal_link_graph_evidence( array $evidence, int $current_post_id ): array {
		$ability_id = 'npcink-abilities-toolkit/get-internal-link-graph-health';
		if ( empty( $evidence ) || ! function_exists( 'npcink_abilities_toolkit_get_registered' ) || ! current_user_can( 'edit_posts' ) ) {
			return $evidence;
		}

		$registered = npcink_abilities_toolkit_get_registered();
		$definition = is_array( $registered[ $ability_id ] ?? null ) ? $registered[ $ability_id ] : array();
		$callback   = $definition['execute_callback'] ?? null;
		if ( ! is_callable( $callback ) ) {
			return $evidence;
		}

		$result = call_user_func(
			$callback,
			array(
				'post_type'          => 'post',
				'status'             => 'publish',
				'per_page'           => 100,
				'page'               => 1,
				'min_outbound_links' => 1,
				'max_outbound_links' => 20,
			)
		);
		if ( is_wp_error( $result ) || ! is_array( $result ) || empty( $result['success'] ) || ! is_array( $result['data'] ?? null ) ) {
			return $evidence;
		}

		$data = $result['data'];
		$rows = array();
		foreach ( is_array( $data['items'] ?? null ) ? $data['items'] : array() as $row ) {
			$post_id = absint( $row['post_id'] ?? 0 );
			if ( $post_id > 0 ) {
				$rows[ $post_id ] = $row;
			}
		}
		$pairs = array();
		foreach ( is_array( $data['candidate_pairs'] ?? null ) ? $data['candidate_pairs'] : array() as $pair ) {
			if ( $current_post_id === absint( $pair['source_post_id'] ?? 0 ) ) {
				$pairs[ absint( $pair['target_post_id'] ?? 0 ) ] = $pair;
			}
		}

		foreach ( $evidence as &$item ) {
			$post_id                          = absint( $item['post_id'] ?? 0 );
			$row                              = is_array( $rows[ $post_id ] ?? null ) ? $rows[ $post_id ] : array();
			$pair                             = is_array( $pairs[ $post_id ] ?? null ) ? $pairs[ $post_id ] : array();
			$item['incoming_count']           = absint( $row['incoming_count'] ?? 0 );
			$item['link_graph_issues']        = array_values( array_filter( array_map( 'sanitize_key', is_array( $row['issues'] ?? null ) ? $row['issues'] : array() ) ) );
			$item['shared_terms']             = array_slice( array_values( array_filter( array_map( 'sanitize_text_field', is_array( $pair['shared_terms'] ?? null ) ? $pair['shared_terms'] : array() ) ) ), 0, 3 );
			$item['graph_evidence_available'] = ! empty( $row );
		}
		unset( $item );

		return $evidence;
	}

	public static function editor_toolkit_internal_link_candidates( array $input ) {
		$ability_id = 'npcink-abilities-toolkit/resolve-internal-link-targets';
		if ( ! function_exists( 'npcink_abilities_toolkit_get_registered' ) ) {
			return new WP_Error(
				'npcink_toolbox_internal_link_toolkit_unavailable',
				__( 'Npcink Abilities Toolkit is required to build internal-link candidates.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$registered = npcink_abilities_toolkit_get_registered();
		$definition = is_array( $registered[ $ability_id ] ?? null ) ? $registered[ $ability_id ] : array();
		$callback   = $definition['execute_callback'] ?? null;
		if ( ! is_callable( $callback ) ) {
			return new WP_Error(
				'npcink_toolbox_internal_link_toolkit_unavailable',
				__( 'The Toolkit internal-link ability is not currently callable.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$result = call_user_func( $callback, $input );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! is_array( $result ) ) {
			return new WP_Error(
				'npcink_toolbox_internal_link_toolkit_invalid_response',
				__( 'The Toolkit internal-link ability returned an invalid response.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}

		return $result;
	}

	public static function editor_internal_link_recommendation_candidates( array $items, bool $cloud_evidence_available ): array {
		$candidates = array();
		foreach ( array_slice( $items, 0, 8 ) as $index => $item ) {
			$title                  = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
			$anchor                 = sanitize_text_field( (string) ( $item['anchor_or_context'] ?? ( $item['suggested_anchor_text'] ?? '' ) ) );
			$url                    = self::editor_internal_link_safe_candidate_url( $item );
			$target_post_id         = absint( $item['target_post_id'] ?? 0 );
			$target_readable        = $target_post_id > 0 && current_user_can( 'read_post', $target_post_id );
			$target_status          = $target_readable ? sanitize_key( (string) get_post_status( $target_post_id ) ) : '';
			$target_type            = $target_readable ? sanitize_key( (string) get_post_type( $target_post_id ) ) : '';
			$verified_url           = $target_readable ? esc_url_raw( (string) get_permalink( $target_post_id ) ) : '';
			$target_is_public_local = 'publish' === $target_status && in_array( $target_type, array( 'post', 'page' ), true ) && '' !== $verified_url;
			if ( $target_is_public_local ) {
				$url = $verified_url;
			}
			$reason              = sanitize_text_field( (string) ( $item['reason'] ?? '' ) );
			$source_match        = is_array( $item['source_match'] ?? null ) ? $item['source_match'] : array();
			$matched_anchor      = self::editor_internal_link_safe_matched_anchor( $anchor, $source_match );
			$candidate_relevance = sanitize_key( (string) ( $item['candidate_relevance'] ?? '' ) );
			if ( ! in_array( $candidate_relevance, array( 'strong', 'review', 'weak' ), true ) ) {
				$candidate_relevance = '';
			}
			$can_apply_to_editor = $cloud_evidence_available && '' !== $matched_anchor && $target_is_public_local;
			if ( '' === $title && '' === $anchor && '' === $url ) {
				continue;
			}

			$has_score = is_numeric( $item['score'] ?? null );
			$score     = $has_score ? (float) $item['score'] : 0.0;
			if ( ! $has_score ) {
				$quality_score = 65;
			} elseif ( $score <= 1 ) {
				$quality_score = 55 + (int) round( max( 0, min( 1, $score ) ) * 35 );
			} else {
				$quality_score = max( 0, min( 90, (int) round( $score ) ) );
			}
			$quality_issues = array( __( '人工确认目标文章与当前段落或全文语义相关后再插入。', 'npcink-workflow-toolbox' ) );
			if ( '' === $url ) {
				$quality_score    = min( $quality_score, 55 );
				$quality_issues[] = __( '缺少目标 URL，插入前需要人工补充或确认。', 'npcink-workflow-toolbox' );
			}
			if ( '' === $matched_anchor ) {
				$quality_score    = min( $quality_score, 55 );
				$quality_issues[] = __( '没有安全、具体且可匹配的正文锚文本；只能复制链接或打开目标文章。', 'npcink-workflow-toolbox' );
			}
			if ( ! $target_is_public_local ) {
				$quality_score    = min( $quality_score, 55 );
				$quality_issues[] = __( '目标必须是本站已发布的文章或页面；当前候选只能复制或打开检查。', 'npcink-workflow-toolbox' );
			}

			$candidates[] = Rest_Editor_Flow_Cache::editor_recommendation_candidate(
				array(
					'id'                    => 'internal_link_' . ( $index + 1 ),
					'kind'                  => 'internal_link',
					'label'                 => '' !== $title ? $title : __( 'Internal link candidate', 'npcink-workflow-toolbox' ),
					'value'                 => $matched_anchor,
					'reason'                => $reason,
					'confidence'            => $has_score && $score > 0 && $score <= 1 ? $score : null,
					'target_field'          => 'post_content',
					'action_policy'         => $can_apply_to_editor ? 'operator_confirmed_visible_editor_apply' : 'operator_review_copy_or_open_only',
					'target_ref'            => array(
						'post_id'   => $target_post_id,
						'title'     => $title,
						'url'       => $url,
						'status'    => $target_status,
						'post_type' => $target_type,
					),
					'anchor_or_context'     => $matched_anchor,
					'anchor_quality_status' => '' !== $matched_anchor ? 'safe_exact_source_match' : 'rejected_no_safe_exact_match',
					'can_apply_to_editor'   => $can_apply_to_editor,
					'evidence_note'         => '' !== $reason ? $reason : __( 'Related content candidate for manual internal-link review.', 'npcink-workflow-toolbox' ),
					'owner_label'           => 'human_editor',
					'next_safe_action'      => $can_apply_to_editor ? 'review_and_apply_to_visible_editor' : 'copy_or_open_target_only',
					'quality_status'        => '' !== $candidate_relevance ? $candidate_relevance : ( $quality_score >= 60 && $can_apply_to_editor ? 'review' : 'weak' ),
					'quality_score'         => $quality_score,
					'quality_issues'        => $quality_issues,
					'evidence_refs'         => is_array( $item['evidence_refs'] ?? null ) ? $item['evidence_refs'] : array(),
					'source_match'          => $can_apply_to_editor ? $source_match : array(),
					'candidate_source'      => sanitize_key( (string) ( $item['candidate_source'] ?? '' ) ),
					'candidate_relevance'   => $candidate_relevance,
					'priority_reason'       => sanitize_text_field( (string) ( $item['priority_reason'] ?? '' ) ),
					'link_graph_issues'     => is_array( $item['link_graph_issues'] ?? null ) ? $item['link_graph_issues'] : array(),
					'shared_terms'          => is_array( $item['shared_terms'] ?? null ) ? $item['shared_terms'] : array(),
					'incoming_count'        => absint( $item['incoming_count'] ?? 0 ),
					'source_candidate_ref'  => '' !== $url ? $url : 'internal_link_item_' . ( $index + 1 ),
				)
			);
		}

		return $candidates;
	}

	public static function editor_internal_link_safe_matched_anchor( string $anchor, array $source_match ): string {
		$matched_text  = sanitize_text_field( (string) ( $source_match['matched_text'] ?? '' ) );
		$expected_text = sanitize_text_field( (string) ( $source_match['expected_text'] ?? '' ) );
		$block_id      = sanitize_text_field( (string) ( $source_match['block_client_id'] ?? '' ) );
		$block_name    = sanitize_text_field( (string) ( $source_match['block_name'] ?? '' ) );
		$offset        = isset( $source_match['text_offset'] ) && is_numeric( $source_match['text_offset'] ) ? (int) $source_match['text_offset'] : -1;
		if ( '' === $anchor || '' === $matched_text || '' === $expected_text || '' === $block_id || in_array( $block_name, array( 'core/code', 'core-code' ), true ) || $offset < 0 ) {
			return '';
		}

		$normalize          = static function ( string $value ): string {
			$value = trim( preg_replace( '/[\p{P}\p{Z}\s]+/u', '', $value ) ?? '' );
			return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value ) : strtolower( $value );
		};
		$normalized_anchor  = $normalize( $anchor );
		$normalized_matched = $normalize( $matched_text );
		$generic_anchors    = array( '主题', '文章', '内容', '这里', '本文', '本页', 'topic', 'article', 'content', 'here', 'this', 'post', 'page', 'link', 'readmore' );
		$anchor_length      = function_exists( 'mb_strlen' ) ? mb_strlen( $normalized_matched ) : strlen( $normalized_matched );
		if ( $normalized_anchor !== $normalized_matched || $anchor_length < 4 || in_array( $normalized_matched, $generic_anchors, true ) ) {
			return '';
		}

		$matched_length = function_exists( 'mb_strlen' ) ? mb_strlen( $matched_text ) : strlen( $matched_text );
		$expected_slice = function_exists( 'mb_substr' ) ? mb_substr( $expected_text, $offset, $matched_length ) : substr( $expected_text, $offset, $matched_length );
		if ( $normalize( (string) $expected_slice ) !== $normalized_matched ) {
			return '';
		}

		return $matched_text;
	}
}
