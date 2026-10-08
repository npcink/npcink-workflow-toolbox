<?php
/**
 * Editor taxonomy shaping cluster: taxonomy quality assessment, the
 * only-suggestion section, recommendation candidates with per-candidate
 * quality, and the review-set shape from suggestions, moved verbatim
 * from Rest_Editor_Content_Support (editor split session 2). The
 * term-candidates assembler stays in the service: it drives the cached
 * site-knowledge and related-content flows whose helpers are shared
 * through Rest_Editor_Flow_Cache.
 *
 * Suggestion-only by contract: candidates are review rows; nothing here
 * writes posts, terms, media, or settings.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Rest_Editor_Taxonomy_Shaping {

	public static function editor_taxonomy_quality( array $context, array $categories, array $tags, array $proposed_new_terms ): string {
		$current_categories = is_array( $context['category_ids'] ?? null ) ? array_filter( array_map( 'absint', $context['category_ids'] ) ) : array();
		$current_tags       = is_array( $context['tag_ids'] ?? null ) ? array_filter( array_map( 'absint', $context['tag_ids'] ) ) : array();
		if ( empty( $current_categories ) && empty( $current_tags ) ) {
			return 'missing';
		}
		if ( ! empty( $proposed_new_terms['items'] ) || 12 < count( $current_tags ) ) {
			return 'noisy';
		}
		if ( empty( $categories ) && empty( $tags ) ) {
			return 'acceptable';
		}

		return 'weak';
	}


	public static function editor_taxonomy_only_suggestion_section( string $candidate_type, array $categories, array $tags, array $proposed_new_terms, array $taxonomy_terms, array $context ): array {
		return array(
			'artifact_type'             => 'article_taxonomy_suggestions.v1',
			'composition_role'          => 'taxonomy_candidates_only',
			'candidate_type'            => sanitize_key( $candidate_type ),
			'candidate_contract'        => 'recommendation_candidate.v1',
			'write_posture'             => 'suggestion_only',
			'final_write_path'          => 'core_proposal_required',
			'direct_wordpress_write'    => false,
			'input_scope'               => Rest_Editor_Flow_Cache::editor_input_scope( $context ),
			'category_candidates'       => array_slice( $categories, 0, 5 ),
			'tag_candidates'            => array_slice( $tags, 0, 8 ),
			'proposed_new_terms'        => $proposed_new_terms,
			'taxonomy_terms'            => $taxonomy_terms,
			'recommendation_candidates' => self::editor_taxonomy_recommendation_candidates( $candidate_type, $categories, $tags, $proposed_new_terms ),
			'quality_gate'              => array(
				'name'           => 'runtime_taxonomy_candidate_rerank',
				'policy'         => 'existing_terms_first_current_draft_match_then_related_history',
				'candidate_sort' => 'score_desc_then_existing_term_order',
			),
			'selection_policy'          => array(
				'prefer_existing_terms'    => true,
				'new_terms_deferred'       => true,
				'no_toolbox_term_creation' => true,
				'accepted_write_path'      => 'core_proposal_required',
			),
		);
	}


	public static function editor_taxonomy_recommendation_candidates( string $candidate_type, array $categories, array $tags, array $proposed_new_terms ): array {
		$items  = 'category_suggestions' === $candidate_type ? array_slice( $categories, 0, 5 ) : array_slice( $tags, 0, 8 );
		$result = array();
		foreach ( $items as $index => $item ) {
			$taxonomy = sanitize_key( (string) ( $item['taxonomy'] ?? '' ) );
			$term_id  = absint( $item['term_id'] ?? 0 );
			$name     = sanitize_text_field( (string) ( $item['name'] ?? '' ) );
			if ( '' === $name || 0 >= $term_id ) {
				continue;
			}
			$quality  = self::editor_taxonomy_candidate_quality( $item );
			$result[] = Rest_Editor_Flow_Cache::editor_recommendation_candidate(
				array(
					'id'             => ( 'category' === $taxonomy ? 'category_' : 'tag_' ) . $term_id,
					'kind'           => 'category' === $taxonomy ? 'category' : 'tag',
					'label'          => 'category' === $taxonomy ? __( 'Existing category', 'npcink-workflow-toolbox' ) : __( 'Existing tag', 'npcink-workflow-toolbox' ),
					'value'          => $name,
					'reason'         => sanitize_text_field( (string) ( $item['reason'] ?? '' ) ),
					'confidence'     => $quality['confidence'],
					'target_field'   => 'category' === $taxonomy ? 'category' : 'post_tag',
					'action_policy'  => 'core_proposal_required',
					'quality_status' => $quality['status'],
					'quality_score'  => $quality['score'],
					'quality_issues' => $quality['issues'],
					'evidence_refs'  => is_array( $item['evidence_refs'] ?? null ) ? $item['evidence_refs'] : array(),
				)
			);
		}

		return $result;
	}


	public static function editor_taxonomy_candidate_quality( array $item ): array {
		$score           = is_numeric( $item['score'] ?? null ) ? (float) $item['score'] : 0.0;
		$match_signals   = is_array( $item['match_signals'] ?? null ) ? $item['match_signals'] : array();
		$related_context = is_array( $item['related_context'] ?? null ) ? $item['related_context'] : array();
		$quality_score   = max( 0, min( 100, 45 + (int) round( $score * 10 ) ) );
		$quality_issues  = array();
		if ( in_array( 'current_draft_match', $match_signals, true ) ) {
			$quality_issues[] = __( '匹配当前草稿中的标题、摘要或正文词。', 'npcink-workflow-toolbox' );
		}
		if ( in_array( 'title_term_name_match', $match_signals, true ) ) {
			$quality_issues[] = __( '词条名称在标题中完整出现，优先级更高。', 'npcink-workflow-toolbox' );
		}
		if ( in_array( 'slug_alias_match', $match_signals, true ) ) {
			$quality_issues[] = __( '词条 slug 或别名与当前编辑上下文匹配。', 'npcink-workflow-toolbox' );
		}
		if ( in_array( 'related_site_knowledge_term', $match_signals, true ) ) {
			$quality_issues[] = __( '历史相关文章使用过该词汇，可作为站内词库证据。', 'npcink-workflow-toolbox' );
		}
		if ( in_array( 'description_only_match', $match_signals, true ) ) {
			$quality_score   -= 20;
			$quality_issues[] = __( '仅描述字段匹配，避免把弱说明文字当作强分类依据。', 'npcink-workflow-toolbox' );
		}
		if ( in_array( 'low_specificity_match', $match_signals, true ) ) {
			$quality_score   -= 15;
			$quality_issues[] = __( '只有一个较弱 token 匹配，需人工确认是否为标题党或泛化词。', 'npcink-workflow-toolbox' );
		}
		if ( empty( $quality_issues ) ) {
			$quality_issues[] = __( '仅作为现有 WordPress 词条候选，采用人工审查。', 'npcink-workflow-toolbox' );
		}
		if ( 0 === absint( $related_context['source_count'] ?? 0 ) && ! in_array( 'current_draft_match', $match_signals, true ) ) {
			$quality_score   -= 15;
			$quality_issues[] = __( '缺少当前草稿或历史文章的强匹配证据。', 'npcink-workflow-toolbox' );
		}

		$status = 'good';
		if ( $quality_score < 70 ) {
			$status = 'review';
		}
		if ( $quality_score < 55 ) {
			$status = 'weak';
		}

		return array(
			'score'      => max( 0, min( 100, $quality_score ) ),
			'status'     => $status,
			'confidence' => max( 0.0, min( 1.0, $score / 5 ) ),
			'issues'     => array_values( array_unique( $quality_issues ) ),
		);
	}


	public static function editor_taxonomy_review_set_from_suggestions( array $taxonomy_terms, WP_Error $fallback_reason ): array {
		$items            = is_array( $taxonomy_terms['items'] ?? null ) ? array_values( array_filter( $taxonomy_terms['items'], 'is_array' ) ) : array();
		$review_set_limit = 8;
		$selected         = array();
		$blocked          = array();

		foreach ( $items as $item ) {
			$quality = self::editor_taxonomy_candidate_quality( $item );
			$row     = array(
				'candidate_id'               => ( 'category' === (string) ( $item['taxonomy'] ?? '' ) ? 'category_' : 'tag_' ) . absint( $item['term_id'] ?? 0 ),
				'candidate_contract'         => 'taxonomy_tag_review_candidate.v1',
				'taxonomy'                   => sanitize_key( (string) ( $item['taxonomy'] ?? '' ) ),
				'term_id'                    => absint( $item['term_id'] ?? 0 ),
				'name'                       => sanitize_text_field( (string) ( $item['name'] ?? '' ) ),
				'slug'                       => sanitize_title( (string) ( $item['slug'] ?? '' ) ),
				'score'                      => is_numeric( $item['score'] ?? null ) ? (float) $item['score'] : 0.0,
				'quality'                    => $quality,
				'reason'                     => sanitize_text_field( (string) ( $item['reason'] ?? '' ) ),
				'evidence_refs'              => is_array( $item['evidence_refs'] ?? null ) ? array_values( array_map( 'sanitize_text_field', $item['evidence_refs'] ) ) : array(),
				'proposed_action'            => 'append_existing_term',
				'needs_operator_review'      => true,
				'direct_wordpress_write'     => false,
				'term_creation_allowed'      => false,
				'term_assignment_authorized' => false,
			);
			if ( '' === $row['name'] || 0 >= $row['term_id'] ) {
				$row['blocked_reason'] = 'invalid_existing_term_candidate';
				$blocked[]             = $row;
				continue;
			}
			if ( 'weak' === (string) $quality['status'] ) {
				$row['blocked_reason'] = 'weak_taxonomy_evidence';
				$blocked[]             = $row;
				continue;
			}
			if ( count( $selected ) >= $review_set_limit ) {
				$row['blocked_reason'] = 'review_set_limit_reached';
				$blocked[]             = $row;
				continue;
			}
			$row['review_status'] = 'good' === (string) $quality['status'] ? 'ready_for_review' : 'review_recommended';
			$selected[]           = $row;
		}

		return array(
			'contract_version'            => 'taxonomy_tag_review_set.v1',
			'artifact_type'               => 'taxonomy_tag_review_set',
			'mode'                        => 'governed_review_set',
			'write_posture'               => 'suggestion_only',
			'final_write_path'            => 'core_proposal_required',
			'direct_wordpress_write'      => false,
			'proposal_created'            => false,
			'execution_created'           => false,
			'commit_execution'            => false,
			'source_ability_id'           => 'npcink-abilities-toolkit/suggest-post-taxonomy-terms',
			'preferred_source_ability_id' => 'npcink-abilities-toolkit/build-taxonomy-tag-review-set',
			'runtime_owner'               => 'npcink-workflow-toolbox',
			'fallback_reason'             => sanitize_key( $fallback_reason->get_error_code() ),
			'fallback_message'            => sanitize_text_field( $fallback_reason->get_error_message() ),
			'review_set_limit'            => $review_set_limit,
			'eligibility_summary'         => array(
				'scanned'  => count( $items ),
				'selected' => count( $selected ),
				'blocked'  => count( $blocked ),
			),
			'selected_items'              => $selected,
			'blocked_items'               => $blocked,
			'safety'                      => array(
				'term_creation_allowed'    => false,
				'term_assignment_allowed'  => false,
				'proposal_created'         => false,
				'direct_wordpress_write'   => false,
				'provider_runtime_used'    => false,
				'cloud_runtime_dependency' => false,
			),
			'handoff'                     => array(
				'accepted_selection_target' => 'npcink-abilities-toolkit/build-content-metadata-apply-plan',
				'term_assignment_target'    => 'npcink-abilities-toolkit/set-post-terms',
				'final_write_path'          => 'core_proposal_required',
				'operator_review_required'  => true,
			),
		);
	}
}
