<?php
/**
 * Editor summary terms cluster: the summary-terms strategy/metrics
 * tables, the Core-handoff candidate shaping, and their single-caller
 * evidence helpers (related-content term evidence and summary shaping,
 * the Toolkit taxonomy adapters), moved verbatim from
 * Rest_Editor_Content_Support (editor split session 3). The summary
 * orchestrators stay in the service: they drive the cached Cloud flows
 * inherited from Rest_Editor_Flow_Cache.
 *
 * Suggestion-only by contract: candidates and metrics are review rows;
 * nothing here writes posts, terms, media, or settings.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

use WP_Error;

final class Rest_Editor_Summary_Terms {

	public static function editor_summary_terms_core_handoff_candidates( array $summary_layers, array $categories, array $tags, array $proposed_new_terms ): array {
		$summary_layer_ids = array_values(
			array_filter(
				array_map(
					static fn( array $item ): string => sanitize_key( (string) ( $item['id'] ?? '' ) ),
					is_array( $summary_layers['items'] ?? null ) ? $summary_layers['items'] : array()
				)
			)
		);
		$category_ids      = array_values(
			array_filter(
				array_map(
					static fn( array $item ): int => (int) ( $item['term_id'] ?? 0 ),
					array_slice( $categories, 0, 5 )
				)
			)
		);
		$tag_ids           = array_values(
			array_filter(
				array_map(
					static fn( array $item ): int => (int) ( $item['term_id'] ?? 0 ),
					array_slice( $tags, 0, 8 )
				)
			)
		);
		return array(
			array(
				'id'                    => 'generate_apply_summary',
				'name'                  => __( 'Generate and apply summary', 'npcink-workflow-toolbox' ),
				'status'                => 'core_auto_approval_eligible',
				'target_operation'      => 'update_post_excerpt',
				'available_fields'      => $summary_layer_ids,
				'auto_approval_request' => true,
				'toolbox_direct_apply'  => false,
				'proposal_policy'       => array(
					'core_proposal_required' => true,
					'eligible_if'            => array(
						'selected_summary_layer_is_returned_by_toolbox',
						'summary_adds_no_unsupported_facts',
						'current_user_can_edit_target_post',
					),
				),
				'reason'                => __( 'Core may auto-approve a selected summary when it is derived from the supplied draft context and the editor can edit the target post.', 'npcink-workflow-toolbox' ),
			),
			array(
				'id'                    => 'recommend_apply_tags',
				'name'                  => __( 'Recommend and apply tags', 'npcink-workflow-toolbox' ),
				'status'                => empty( $tag_ids ) ? 'no_existing_tag_candidate' : 'core_auto_approval_eligible',
				'target_operation'      => 'assign_existing_post_tags',
				'available_fields'      => $tag_ids,
				'auto_approval_request' => ! empty( $tag_ids ),
				'toolbox_direct_apply'  => false,
				'proposal_policy'       => array(
					'core_proposal_required' => true,
					'eligible_if'            => array(
						'selected_terms_are_existing_post_tags',
						'term_assignment_is_additive_or_operator_selected',
						'current_user_can_edit_target_post',
					),
				),
				'reason'                => __( 'Existing tag assignments can be proposed for Core auto-approval because Toolbox returns WordPress term ids and does not create taxonomy state.', 'npcink-workflow-toolbox' ),
			),
			array(
				'id'                    => 'recommend_categories',
				'name'                  => __( 'Recommend categories', 'npcink-workflow-toolbox' ),
				'status'                => empty( $category_ids ) ? 'no_existing_category_candidate' : 'recommendation_only',
				'target_operation'      => 'recommend_existing_categories',
				'available_fields'      => $category_ids,
				'auto_approval_request' => false,
				'toolbox_direct_apply'  => false,
				'proposal_policy'       => array(
					'core_proposal_required' => true,
					'default_mode'           => 'operator_review_required',
					'eligible_if'            => array(
						'selected_terms_are_existing_categories',
						'category_change_policy_allows_auto_assignment',
					),
				),
				'reason'                => __( 'Categories affect site structure, so Toolbox recommends existing categories by default and leaves any assignment policy to Core.', 'npcink-workflow-toolbox' ),
			),
		);
	}


	public static function editor_summary_terms_strategy(): array {
		return array(
			'candidate_type'         => 'summary_terms_precision_strategy',
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
			'existing_terms_first'   => true,
			'proposed_new_terms'     => 'deferred_taxonomy_governance',
			'ranking_signals'        => array(
				array(
					'name'   => __( 'Draft query overlap', 'npcink-workflow-toolbox' ),
					'weight' => 'high',
					'detail' => __( 'Match against title, excerpt, selected text, and draft body tokens.', 'npcink-workflow-toolbox' ),
				),
				array(
					'name'   => __( 'Existing taxonomy vocabulary', 'npcink-workflow-toolbox' ),
					'weight' => 'high',
					'detail' => __( 'Prefer existing WordPress categories and tags; defer new vocabulary creation to taxonomy governance.', 'npcink-workflow-toolbox' ),
				),
				array(
					'name'   => __( 'Site Knowledge similarity', 'npcink-workflow-toolbox' ),
					'weight' => 'medium',
					'detail' => __( 'Use related public content to avoid duplicate coverage and borrow proven term patterns.', 'npcink-workflow-toolbox' ),
				),
				array(
					'name'   => __( 'Discoverability context', 'npcink-workflow-toolbox' ),
					'weight' => 'medium',
					'detail' => __( 'Check saved SEO/AEO/GEO guidance and Cloud web-search evidence before recommending metadata.', 'npcink-workflow-toolbox' ),
				),
			),
			'dedupe_policy'          => array(
				__( 'Normalize candidate labels by case, punctuation, and whitespace before review.', 'npcink-workflow-toolbox' ),
				__( 'Treat near-synonyms, plural/singular variants, and translated duplicates as taxonomy-drift risks.', 'npcink-workflow-toolbox' ),
				__( 'Keep broad categories stable and use tags for narrower topic facets.', 'npcink-workflow-toolbox' ),
			),
			'evidence_requirements'  => array(
				__( 'Each accepted category or tag should have a reason tied to draft text, existing taxonomy, Site Knowledge, or search evidence.', 'npcink-workflow-toolbox' ),
				__( 'Fresh external search is useful for factual or current topics, but it should not override the supplied article draft.', 'npcink-workflow-toolbox' ),
			),
		);
	}


	public static function editor_summary_terms_review_metrics(): array {
		return array(
			'candidate_type'         => 'summary_terms_review_metrics',
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
			'items'                  => array(
				array(
					'name'   => 'accepted_suggestion_rate',
					'detail' => __( 'Track how many summary, category, and tag suggestions an editor accepts after review.', 'npcink-workflow-toolbox' ),
				),
				array(
					'name'   => 'summary_edit_distance',
					'detail' => __( 'Compare AI/fallback summaries with the final reviewed summary to detect overbroad or weak suggestions.', 'npcink-workflow-toolbox' ),
				),
				array(
					'name'   => 'taxonomy_gap_deferral_rate',
					'detail' => __( 'Track cases where no existing term fits so a later taxonomy governance workflow can review them.', 'npcink-workflow-toolbox' ),
				),
				array(
					'name'   => 'duplicate_topic_review',
					'detail' => __( 'Use related Site Knowledge results to flag whether the article overlaps existing public content.', 'npcink-workflow-toolbox' ),
				),
				array(
					'name'   => 'evidence_coverage',
					'detail' => __( 'Check whether accepted suggestions cite draft, taxonomy, Site Knowledge, or search evidence.', 'npcink-workflow-toolbox' ),
				),
			),
		);
	}


	public static function editor_related_content_term_evidence( array $related_content ): array {
		$evidence = array();
		foreach ( array_slice( Rest_Editor_Flow_Cache::editor_related_content_items( $related_content ), 0, 20 ) as $index => $item ) {
			$post_id = absint( $item['post_id'] ?? 0 );
			if ( 0 >= $post_id ) {
				continue;
			}

			$score      = is_numeric( $item['score'] ?? null ) ? (float) $item['score'] : 0.0;
			$source_ref = 'site_knowledge:' . sanitize_key( (string) ( $item['post_id'] ?? ( $item['id'] ?? $index ) ) );
			$title      = sanitize_text_field( (string) ( $item['title'] ?? $item['name'] ?? '' ) );

			foreach ( self::editor_related_post_terms_for_context( $post_id ) as $term ) {
				$term_id  = absint( $term['term_id'] ?? 0 );
				$taxonomy = sanitize_key( (string) ( $term['taxonomy'] ?? '' ) );
				if ( 0 >= $term_id || ! in_array( $taxonomy, array( 'category', 'post_tag' ), true ) ) {
					continue;
				}

				$key = $taxonomy . ':' . $term_id;
				if ( ! isset( $evidence[ $key ] ) ) {
					$evidence[ $key ] = array(
						'term_id'         => $term_id,
						'taxonomy'        => $taxonomy,
						'name'            => sanitize_text_field( (string) ( $term['name'] ?? '' ) ),
						'source_count'    => 0,
						'source_post_ids' => array(),
						'source_titles'   => array(),
						'source_refs'     => array(),
						'max_similarity'  => 0.0,
					);
				}

				++$evidence[ $key ]['source_count'];
				$evidence[ $key ]['source_post_ids'][] = $post_id;
				$evidence[ $key ]['source_refs'][]     = $source_ref;
				if ( '' !== $title ) {
					$evidence[ $key ]['source_titles'][] = $title;
				}
				$evidence[ $key ]['max_similarity'] = max( (float) $evidence[ $key ]['max_similarity'], $score );
			}
		}

		foreach ( $evidence as $key => $item ) {
			$evidence[ $key ]['source_post_ids'] = array_values( array_unique( array_map( 'absint', $item['source_post_ids'] ) ) );
			$evidence[ $key ]['source_titles']   = array_slice( array_values( array_unique( array_map( 'sanitize_text_field', $item['source_titles'] ) ) ), 0, 5 );
			$evidence[ $key ]['source_refs']     = array_values( array_unique( array_map( 'sanitize_text_field', $item['source_refs'] ) ) );
			$evidence[ $key ]['source_count']    = count( $evidence[ $key ]['source_post_ids'] );
		}

		return $evidence;
	}


	public static function editor_related_content_summary( array $related_content ): array {
		$items        = Rest_Editor_Flow_Cache::editor_related_content_items( $related_content );
		$evidence_ids = array();
		$titles       = array();

		foreach ( array_slice( $items, 0, 6 ) as $index => $item ) {
			$ref_id         = (string) ( $item['post_id'] ?? ( $item['id'] ?? $index ) );
			$evidence_ids[] = 'site_knowledge:' . sanitize_key( $ref_id );
			$title          = sanitize_text_field( (string) ( $item['title'] ?? $item['name'] ?? '' ) );
			if ( '' !== $title ) {
				$titles[] = $title;
			}
		}

		return array(
			'available'     => array() !== $items,
			'result_count'  => count( $items ),
			'evidence_refs' => array_values( array_unique( $evidence_ids ) ),
			'top_titles'    => array_slice( array_values( array_unique( $titles ) ), 0, 5 ),
			'policy'        => 'related_context_checks_duplicate_coverage_and_term_fit_without_adding_new_facts',
		);
	}


	public static function editor_toolkit_taxonomy_review_set( array $input ) {
		$ability_id = 'npcink-abilities-toolkit/build-taxonomy-tag-review-set';
		if ( ! function_exists( 'npcink_abilities_toolkit_get_registered' ) ) {
			return new WP_Error(
				'npcink_toolbox_taxonomy_review_set_toolkit_unavailable',
				__( 'Npcink Abilities Toolkit is required to build taxonomy review sets.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$registered = npcink_abilities_toolkit_get_registered();
		$definition = is_array( $registered[ $ability_id ] ?? null ) ? $registered[ $ability_id ] : array();
		$callback   = $definition['execute_callback'] ?? null;
		if ( ! is_callable( $callback ) ) {
			return new WP_Error(
				'npcink_toolbox_taxonomy_review_set_toolkit_unavailable',
				__( 'The Toolkit taxonomy review-set ability is not currently callable.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$result = call_user_func( $callback, $input );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! is_array( $result ) ) {
			return new WP_Error(
				'npcink_toolbox_taxonomy_review_set_invalid_response',
				__( 'The Toolkit taxonomy review-set ability returned an invalid response.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}

		return $result;
	}


	public static function editor_toolkit_taxonomy_suggestions( array $input ) {
		$ability_id = 'npcink-abilities-toolkit/suggest-post-taxonomy-terms';
		if ( ! function_exists( 'npcink_abilities_toolkit_get_registered' ) ) {
			return new WP_Error(
				'npcink_toolbox_taxonomy_toolkit_unavailable',
				__( 'Npcink Abilities Toolkit is required to build taxonomy suggestions.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$registered = npcink_abilities_toolkit_get_registered();
		$definition = is_array( $registered[ $ability_id ] ?? null ) ? $registered[ $ability_id ] : array();
		$callback   = $definition['execute_callback'] ?? null;
		if ( ! is_callable( $callback ) ) {
			return new WP_Error(
				'npcink_toolbox_taxonomy_toolkit_unavailable',
				__( 'The Toolkit taxonomy suggestion ability is not currently callable.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$result = call_user_func( $callback, $input );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! is_array( $result ) ) {
			return new WP_Error(
				'npcink_toolbox_taxonomy_toolkit_invalid_response',
				__( 'The Toolkit taxonomy suggestion ability returned an invalid response.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}

		return $result;
	}


	public static function editor_related_post_terms_for_context( int $post_id ): array {
		if ( 0 >= $post_id || ! function_exists( 'get_the_terms' ) ) {
			return array();
		}

		$terms = array();
		foreach ( array( 'category', 'post_tag' ) as $taxonomy ) {
			$items = get_the_terms( $post_id, $taxonomy );
			if ( is_wp_error( $items ) || ! is_array( $items ) ) {
				continue;
			}

			foreach ( $items as $term ) {
				$terms[] = array(
					'term_id'  => absint( $term->term_id ?? 0 ),
					'taxonomy' => sanitize_key( $taxonomy ),
					'name'     => sanitize_text_field( (string) ( $term->name ?? '' ) ),
				);
			}
		}

		return array_values( $terms );
	}
}
