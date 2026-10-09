<?php
/**
 * Editor progressive recommendation cluster: the recommendation-set
 * envelope (contract ids, artifact counts, candidate refs, retrieval
 * sources, definition-only Core proposal targets), the content
 * fingerprint and content-context contracts, the local taxonomy profile
 * prefetch, the pure media-library and preflight candidate shapers, and
 * the weighted contextual token-match scoring, moved verbatim from
 * Rest_Editor_Content_Support (editor split session 5). The flow
 * orchestrators (progressive section assembly and the media-library
 * WordPress query) stay in the service: they drive the shared attachment
 * snapshot helper and the taxonomy flows that close over client state.
 *
 * Suggestion-only by contract: every value is a review row with
 * definition-only handoff targets; nothing here writes posts, terms,
 * media, or settings.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

final class Rest_Editor_Progressive_Recommendations {

	public const EDITOR_PROGRESSIVE_TARGET_MS       = 2500;
	public const EDITOR_PROGRESSIVE_CANDIDATE_LIMIT = 8;


	public static function editor_recommendation_set( array $context, string $intent, array $sections ): array {
		$content_fingerprint = self::editor_content_fingerprint( $context );
		$artifact_counts     = array(
			'titles'         => self::editor_recommendation_count_by_kind( $sections, 'title' ),
			'excerpts'       => self::editor_recommendation_count_by_kind( $sections, 'excerpt' ),
			'categories'     => self::editor_recommendation_count_by_kind( $sections, 'category' ),
			'tags'           => self::editor_recommendation_count_by_kind( $sections, 'tag' ),
			'featured_image' => self::editor_recommendation_count_by_kind( $sections, 'image' ),
			'internal_links' => self::editor_recommendation_count_by_kind( $sections, 'internal_link' ),
			'preflight'      => self::editor_recommendation_count_by_kind( $sections, 'preflight' ),
		);
		$retrieval_sources   = self::editor_recommendation_set_sources( $sections );
		$proposal_targets    = self::editor_recommendation_set_proposal_targets( $sections );

		return array(
			'recommendation_set_id'  => 'rec_' . substr( hash( 'sha256', $content_fingerprint . '|' . $intent . '|' . wp_json_encode( $artifact_counts ) ), 0, 20 ),
			'contract_version'       => 'editor_recommendation_set.v1',
			'canonical_contract'     => 'recommendation_set.v1',
			'compatibility_contract' => 'editor_recommendation_set.v1',
			'generated_at'           => gmdate( 'c' ),
			'source_layer'           => self::editor_recommendation_set_source_layer( $sections ),
			'latency_profile'        => 'progressive_recommendations' === $intent ? 'local_300ms' : 'focused_intent',
			'content_fingerprint'    => $content_fingerprint,
			'content_context'        => self::editor_content_context( $context ),
			'intent'                 => sanitize_key( $intent ),
			'artifacts'              => $artifact_counts,
			'artifact_counts'        => $artifact_counts,
			'candidates'             => self::editor_recommendation_set_candidate_refs( $sections ),
			'retrieval_sources'      => $retrieval_sources,
			'proposal_targets'       => $proposal_targets,
			'no_write'               => true,
			'governance'             => array(
				'dry_run_available'      => true,
				'requires_proposal'      => self::editor_recommendation_set_required_proposals( $sections ),
				'write_posture'          => 'suggestion_only',
				'direct_wordpress_write' => false,
			),
			'debug'                  => array(
				'retrieval_sources'     => $retrieval_sources,
				'cache_ttl_seconds'     => Rest_Editor_Flow_Cache::EDITOR_FLOW_CACHE_TTL,
				'progressive_target_ms' => self::EDITOR_PROGRESSIVE_TARGET_MS,
			),
		);
	}


	public static function editor_content_fingerprint( array $context ): string {
		$payload = array(
			'post_id'             => absint( $context['post_id'] ?? 0 ),
			'post_type'           => sanitize_key( (string) ( $context['post_type'] ?? 'post' ) ),
			'title'               => (string) ( $context['title'] ?? '' ),
			'excerpt'             => (string) ( $context['excerpt'] ?? '' ),
			'content_text'        => (string) ( $context['content_text'] ?? '' ),
			'selected_text'       => (string) ( $context['selected_text'] ?? '' ),
			'selected_block_text' => (string) ( $context['selected_block_text'] ?? '' ),
			'category_ids'        => array_map( 'absint', is_array( $context['category_ids'] ?? null ) ? $context['category_ids'] : array() ),
			'tag_ids'             => array_map( 'absint', is_array( $context['tag_ids'] ?? null ) ? $context['tag_ids'] : array() ),
			'featured_media'      => absint( $context['featured_media'] ?? 0 ),
		);
		return 'sha256:' . hash( 'sha256', (string) wp_json_encode( $payload ) );
	}


	public static function editor_content_context( array $context ): array {
		$post_id = absint( $context['post_id'] ?? 0 );
		return array(
			'contract_version'       => 'content_context.v1',
			'platform'               => 'wordpress',
			'site_id'                => function_exists( 'get_current_blog_id' ) ? absint( get_current_blog_id() ) : 0,
			'post_id'                => $post_id,
			'post_type'              => sanitize_key( (string) ( $context['post_type'] ?? 'post' ) ),
			'post_status'            => sanitize_key( (string) ( $context['post_status'] ?? '' ) ),
			'canonical_url'          => $post_id > 0 && function_exists( 'get_permalink' ) ? esc_url_raw( (string) get_permalink( $post_id ) ) : '',
			'language'               => function_exists( 'determine_locale' ) ? sanitize_text_field( determine_locale() ) : sanitize_text_field( (string) get_locale() ),
			'context_scope'          => sanitize_key( (string) ( $context['context_scope'] ?? 'auto' ) ),
			'title'                  => sanitize_text_field( (string) ( $context['title'] ?? '' ) ),
			'excerpt'                => sanitize_textarea_field( (string) ( $context['excerpt'] ?? '' ) ),
			'content_text'           => sanitize_textarea_field( (string) ( $context['content_text'] ?? '' ) ),
			'selected_text'          => sanitize_textarea_field( (string) ( $context['selected_text'] ?? '' ) ),
			'selected_block_text'    => sanitize_textarea_field( (string) ( $context['selected_block_text'] ?? '' ) ),
			'category_ids'           => array_values( array_map( 'absint', is_array( $context['category_ids'] ?? null ) ? $context['category_ids'] : array() ) ),
			'tag_ids'                => array_values( array_map( 'absint', is_array( $context['tag_ids'] ?? null ) ? $context['tag_ids'] : array() ) ),
			'content_fingerprint'    => self::editor_content_fingerprint( $context ),
			'write_owner'            => 'wordpress_local',
			'direct_wordpress_write' => false,
		);
	}


	public static function editor_recommendation_count_by_kind( array $sections, string $kind ): int {
		$count = 0;
		foreach ( $sections as $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}
			if ( ! is_array( $section['recommendation_candidates'] ?? null ) ) {
				continue;
			}
			foreach ( $section['recommendation_candidates'] as $candidate ) {
				if ( is_array( $candidate ) && $kind === (string) ( $candidate['kind'] ?? '' ) ) {
					++$count;
				}
			}
		}
		return $count;
	}


	public static function editor_recommendation_set_required_proposals( array $sections ): array {
		$required = array();
		foreach ( $sections as $section ) {
			if ( ! is_array( $section ) || ! is_array( $section['recommendation_candidates'] ?? null ) ) {
				continue;
			}
			foreach ( $section['recommendation_candidates'] as $candidate ) {
				if ( is_array( $candidate ) && 'core_proposal_required' === (string) ( $candidate['action_policy'] ?? '' ) ) {
					$target = sanitize_key( (string) ( $candidate['target_field'] ?? $candidate['kind'] ?? 'candidate' ) );
					if ( '' !== $target && ! in_array( $target, $required, true ) ) {
						$required[] = $target;
					}
				}
			}
		}
		return $required;
	}


	public static function editor_recommendation_set_candidate_refs( array $sections ): array {
		$refs = array();
		foreach ( $sections as $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}
			if ( is_array( $section['recommendation_candidates'] ?? null ) ) {
				foreach ( $section['recommendation_candidates'] as $candidate ) {
					if ( ! is_array( $candidate ) ) {
						continue;
					}
					$id = sanitize_key( (string) ( $candidate['id'] ?? '' ) );
					if ( '' === $id ) {
						continue;
					}
					$refs[] = array(
						'candidate_id'  => $id,
						'kind'          => sanitize_key( (string) ( $candidate['kind'] ?? 'generic' ) ),
						'target_field'  => sanitize_key( (string) ( $candidate['target_field'] ?? '' ) ),
						'action_policy' => sanitize_key( (string) ( $candidate['action_policy'] ?? 'suggestion_only' ) ),
					);
				}
			}
		}
		return $refs;
	}


	public static function editor_recommendation_set_proposal_targets( array $sections ): array {
		$targets = array();
		$seen    = array();
		foreach ( $sections as $section ) {
			if ( ! is_array( $section ) || ! is_array( $section['recommendation_candidates'] ?? null ) ) {
				continue;
			}
			foreach ( $section['recommendation_candidates'] as $candidate ) {
				if ( ! is_array( $candidate ) || 'core_proposal_required' !== (string) ( $candidate['action_policy'] ?? '' ) ) {
					continue;
				}
				$candidate_id = sanitize_key( (string) ( $candidate['id'] ?? '' ) );
				if ( '' === $candidate_id ) {
					continue;
				}
				$kind         = sanitize_key( (string) ( $candidate['kind'] ?? 'generic' ) );
				$target_field = sanitize_key( (string) ( $candidate['target_field'] ?? $kind ) );
				$ability_id   = self::editor_recommendation_target_ability_id( $target_field, $kind );
				$dedupe_key   = $candidate_id . '|' . $target_field . '|' . $ability_id;
				if ( isset( $seen[ $dedupe_key ] ) ) {
					continue;
				}
				$seen[ $dedupe_key ] = true;
				$targets[]           = array(
					'candidate_id'             => $candidate_id,
					'candidate_kind'           => $kind,
					'target_field'             => $target_field,
					'proposal_target'          => 'core_ability_handoff',
					'required_ability_id'      => $ability_id,
					'proposed_payload_preview' => self::editor_recommendation_payload_preview( $candidate, $target_field, $ability_id ),
					'handoff_status'           => 'definition_only_user_trigger_required',
					'direct_wordpress_write'   => false,
				);
			}
		}
		return $targets;
	}


	public static function editor_recommendation_set_source_layer( array $sections ): string {
		foreach ( $sections as $section ) {
			if ( is_array( $section ) && ( ! empty( $section['provider_execution'] ) || 'cloud_vector' === (string) ( $section['candidate_source'] ?? '' ) ) ) {
				return 'cloud';
			}
		}
		return 'local';
	}


	public static function editor_recommendation_target_ability_id( string $target_field, string $kind ): string {
		$field = sanitize_key( $target_field );
		$type  = sanitize_key( $kind );
		if ( in_array( $field, array( 'category', 'post_tag', 'taxonomy_terms' ), true ) || in_array( $type, array( 'category', 'tag' ), true ) ) {
			return 'npcink-abilities-toolkit/set-post-terms';
		}
		if ( in_array( $field, array( 'featured_media', 'featured_image' ), true ) || 'image' === $type ) {
			return 'npcink-abilities-toolkit/set-post-featured-image';
		}
		if ( in_array( $field, array( 'seo_meta', 'seo_title', 'seo_description' ), true ) ) {
			return 'npcink-abilities-toolkit/set-post-seo-meta';
		}
		return 'npcink-abilities-toolkit/update-post';
	}


	public static function editor_recommendation_payload_preview( array $candidate, string $target_field, string $ability_id ): array {
		$value = sanitize_text_field( (string) ( $candidate['value'] ?? '' ) );
		return array(
			'candidate_id'       => sanitize_key( (string) ( $candidate['id'] ?? '' ) ),
			'target_field'       => sanitize_key( $target_field ),
			'ability_id'         => sanitize_text_field( $ability_id ),
			'value_preview'      => substr( $value, 0, 160 ),
			'source_contract'    => 'editor_recommendation_set.v1',
			'dry_run'            => true,
			'commit'             => false,
			'operator_triggered' => true,
		);
	}


	public static function editor_recommendation_set_sources( array $sections ): array {
		$sources = array( 'current_editor_context' );
		foreach ( $sections as $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}
			if ( ! empty( $section['available_context'] ) || ! empty( $section['taxonomy_terms'] ) || ! empty( $section['category_candidates'] ) || ! empty( $section['tag_candidates'] ) ) {
				$sources[] = 'site_taxonomy';
			}
			if ( ! empty( $section['media_library_candidates'] ) ) {
				$sources[] = 'media_library';
			}
			if ( ! empty( $section['provider_execution'] ) ) {
				$sources[] = sanitize_key( (string) $section['provider_execution'] );
			}
			if ( ! empty( $section['candidate_source'] ) ) {
				$sources[] = sanitize_key( (string) $section['candidate_source'] );
			}
			if ( ! empty( $section['ranking_context']['related_content_terms'] ) || ! empty( $section['related_context_summary'] ) ) {
				$sources[] = 'site_knowledge';
			}
		}
		return array_values( array_unique( array_filter( $sources ) ) );
	}


	public static function editor_local_taxonomy_profile( array $context ): array {
		$post_type  = sanitize_key( (string) ( $context['post_type'] ?? 'post' ) );
		$taxonomies = array_values(
			array_intersect(
				get_object_taxonomies( $post_type ),
				array( 'category', 'post_tag' )
			)
		);
		$items      = array();
		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'number'     => 'category' === $taxonomy ? 12 : 24,
					'orderby'    => 'count',
					'order'      => 'DESC',
				)
			);
			if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$items[] = array(
					'term_id'                      => (int) $term->term_id,
					'taxonomy'                     => sanitize_key( $taxonomy ),
					'name'                         => sanitize_text_field( $term->name ),
					'slug'                         => sanitize_title( $term->slug ),
					'score'                        => min( 10, max( 1, absint( $term->count ?? 0 ) ) ),
					'status'                       => 'existing_term',
					'controlled_vocabulary_status' => 'existing_wordpress_term',
					'normalization_key'            => sanitize_title( $term->name ),
					'matched_tokens'               => array(),
					'match_signals'                => array( 'existing_taxonomy_vocabulary', 'local_taxonomy_profile' ),
					'related_context'              => array(),
					'evidence_refs'                => array(),
					'reason'                       => __( 'Existing WordPress term from the local site taxonomy profile. Review against the current draft before applying.', 'npcink-workflow-toolbox' ),
				);
			}
		}
		return array(
			'candidate_type'         => 'taxonomy_tag_candidates',
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
			'ranking_context'        => array(
				'draft_query_overlap'   => false,
				'local_prefetch_only'   => true,
				'related_content_terms' => false,
			),
			'items'                  => $items,
		);
	}


	public static function editor_media_library_recommendation_candidates( array $media_items ): array {
		$candidates = array();
		foreach ( array_slice( $media_items, 0, 4 ) as $index => $item ) {
			$attachment_id = absint( $item['attachment_id'] ?? 0 );
			if ( $attachment_id <= 0 ) {
				continue;
			}
			$match_score   = (int) ( $item['score'] ?? 0 );
			$quality_score = min( 95, 55 + $match_score * 8 );
			$candidates[]  = Rest_Editor_Flow_Cache::editor_recommendation_candidate(
				array(
					'id'                   => 'media_library_' . $attachment_id,
					'kind'                 => 'image',
					'label'                => $match_score > 0 ? ( 0 === $index ? __( 'Existing media candidate', 'npcink-workflow-toolbox' ) : __( 'Media library option', 'npcink-workflow-toolbox' ) ) : __( 'Recent media review item', 'npcink-workflow-toolbox' ),
					'value'                => (string) ( $item['title'] ?? ( $item['url'] ?? '' ) ),
					'reason'               => (string) ( $item['reason'] ?? '' ),
					'confidence'           => $quality_score / 100,
					'target_field'         => 'featured_media',
					'action_policy'        => $match_score > 0 ? 'core_proposal_required' : 'operator_review_only_no_write',
					'quality_status'       => $match_score > 0 && $quality_score >= 70 ? 'good' : 'review',
					'quality_score'        => $quality_score,
					'quality_issues'       => array(
						$match_score > 0
							? __( 'Existing media still requires operator visual review before use.', 'npcink-workflow-toolbox' )
							: __( 'Recent media has no strong text match; treat it as a review-only local reference.', 'npcink-workflow-toolbox' ),
					),
					'evidence_refs'        => array( 'attachment:' . $attachment_id ),
					'source_candidate_ref' => 'attachment:' . $attachment_id,
				)
			);
		}
		return $candidates;
	}


	public static function editor_filter_high_confidence_taxonomy_recommendations( array $candidates ): array {
		return array_values(
			array_filter(
				$candidates,
				static function ( array $candidate ): bool {
					$issues = is_array( $candidate['quality_issues'] ?? null ) ? implode( ' ', $candidate['quality_issues'] ) : '';
					$reason = (string) ( $candidate['reason'] ?? '' );
					if (
						'weak' === (string) ( $candidate['quality_status'] ?? '' )
						|| false !== strpos( $issues, '仅描述字段匹配' )
						|| false !== strpos( $issues, '只有一个较弱 token 匹配' )
					) {
						return false;
					}
					return false !== strpos( $issues, '匹配当前草稿' )
						|| false !== strpos( $issues, 'Matched tokens:' )
						|| false !== strpos( $issues, '历史相关' )
						|| false !== strpos( $issues, 'Site Knowledge' )
						|| false !== strpos( $reason, 'Matched tokens:' )
						|| false !== strpos( $reason, 'matched the draft' )
						|| false !== strpos( $reason, 'matched against the current' )
						|| false !== strpos( $reason, 'Site Knowledge' );
				}
			)
		);
	}


	public static function editor_preflight_recommendation_candidates( array $preflight_checks ): array {
		$items      = is_array( $preflight_checks['items'] ?? null ) ? $preflight_checks['items'] : array();
		$candidates = array();
		$targets    = array(
			'title'          => 'post_title',
			'excerpt'        => 'post_excerpt',
			'terms'          => 'taxonomy_terms',
			'featured_media' => 'featured_media',
		);
		$priority   = array(
			'title'          => 10,
			'excerpt'        => 20,
			'terms'          => 30,
			'featured_media' => 40,
		);
		usort(
			$items,
			static function ( array $left, array $right ) use ( $priority ): int {
				$left_id  = sanitize_key( (string) ( $left['id'] ?? 'preflight' ) );
				$right_id = sanitize_key( (string) ( $right['id'] ?? 'preflight' ) );
				return (int) ( $priority[ $left_id ] ?? 99 ) <=> (int) ( $priority[ $right_id ] ?? 99 );
			}
		);

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || 'ok' === (string) ( $item['status'] ?? '' ) ) {
				continue;
			}
			$id           = sanitize_key( (string) ( $item['id'] ?? 'preflight' ) );
			$status       = sanitize_key( (string) ( $item['status'] ?? 'warning' ) );
			$score        = 'error' === $status ? 35 : 55;
			$candidates[] = Rest_Editor_Flow_Cache::editor_recommendation_candidate(
				array(
					'id'             => 'preflight_' . $id,
					'kind'           => 'preflight',
					'label'          => sanitize_text_field( (string) ( $item['label'] ?? __( 'Preflight review', 'npcink-workflow-toolbox' ) ) ),
					'value'          => sanitize_text_field( (string) ( $item['detail'] ?? '' ) ),
					'reason'         => __( 'Local pre-publish check found a review item before any Cloud enhancement or Core proposal handoff.', 'npcink-workflow-toolbox' ),
					'confidence'     => 0.9,
					'target_field'   => $targets[ $id ] ?? $id,
					'action_policy'  => 'operator_review_only_no_write',
					'quality_status' => 'error' === $status ? 'weak' : 'review',
					'quality_score'  => $score,
					'quality_issues' => array( sanitize_text_field( (string) ( $item['detail'] ?? '' ) ) ),
					'evidence_refs'  => array( 'local_preflight:' . $id ),
				)
			);
		}

		return $candidates;
	}


	public static function editor_contextual_match_score( string $candidate_text, array $context, string $query ): int {
		$weighted_score  = 0;
		$weighted_fields = array(
			'title'               => 4,
			'excerpt'             => 3,
			'selected_text'       => 3,
			'selected_block_text' => 3,
			'content_text'        => 1,
			'user_instruction'    => 2,
		);

		foreach ( $weighted_fields as $field => $weight ) {
			$value = trim( (string) ( $context[ $field ] ?? '' ) );
			if ( '' === $value ) {
				continue;
			}
			$weighted_score += count( self::term_match_tokens( $candidate_text, $value ) ) * $weight;
		}

		if ( $weighted_score <= 0 && '' !== trim( $query ) ) {
			$weighted_score = self::term_match_score( $candidate_text, $query );
		}

		return max( 0, min( 30, $weighted_score ) );
	}


	public static function term_match_score( string $term_text, string $query ): int {
		return count( self::term_match_tokens( $term_text, $query ) );
	}


	public static function term_match_tokens( string $term_text, string $query ): array {
		$term_tokens  = self::support_tokens( $term_text );
		$query_tokens = self::support_tokens( $query );
		if ( array() === $term_tokens || array() === $query_tokens ) {
			return array();
		}

		return array_values( array_intersect( $term_tokens, $query_tokens ) );
	}


	public static function support_tokens( string $text ): array {
		$tokens = preg_split( '/[^\p{L}\p{N}]+/u', strtolower( $text ) );
		if ( ! is_array( $tokens ) ) {
			return array();
		}
		$stopwords = array(
			'a'    => true,
			'an'   => true,
			'and'  => true,
			'are'  => true,
			'as'   => true,
			'at'   => true,
			'be'   => true,
			'by'   => true,
			'for'  => true,
			'from' => true,
			'has'  => true,
			'have' => true,
			'in'   => true,
			'into' => true,
			'is'   => true,
			'it'   => true,
			'of'   => true,
			'on'   => true,
			'or'   => true,
			'that' => true,
			'the'  => true,
			'this' => true,
			'to'   => true,
			'with' => true,
		);

		return array_values(
			array_unique(
				array_filter(
					$tokens,
					static function ( string $token ) use ( $stopwords ): bool {
						return strlen( $token ) >= 2 && empty( $stopwords[ $token ] );
					}
				)
			)
		);
	}
}
