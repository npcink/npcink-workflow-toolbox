<?php
/**
 * Shared editor flow-cache layer: the transient-backed client-result
 * cache and its typed accessors, moved verbatim from
 * Rest_Editor_Content_Support as the shared base for the editor split
 * (Provider Split Refactor Standard v1, section 3: helpers used by more
 * than one future cluster live in an abstract base the facade and every
 * sub-service extend). The Provider_Client dependency travels with the
 * cache because every accessor closes over it.
 *
 * Suggestion-only by contract: cached values are provider results for
 * review surfaces; nothing here writes posts, media, or settings.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

use WP_Error;

abstract class Rest_Editor_Flow_Cache extends Rest_Controller_Support {

	public const EDITOR_FLOW_CACHE_TTL = 300;

	protected Provider_Client $client;


	protected function editor_cached_site_knowledge( array $input ) {
		return $this->editor_cached_client_result(
			'site_knowledge',
			$input,
			function () use ( $input ) {
				return $this->client->search_site_knowledge( $input );
			}
		);
	}


	protected function editor_cached_content_discoverability( array $input ) {
		return $this->editor_cached_client_result(
			'content_discoverability',
			$input,
			function () use ( $input ) {
				return $this->client->build_content_discoverability_brief( $input );
			}
		);
	}


	protected function editor_cached_hosted_ai_content_support( array $input, bool $force_refresh = false ) {
		return $this->editor_cached_client_result(
			'hosted_ai_content_support',
			$input,
			function () use ( $input ) {
				return $this->client->run_hosted_ai_content_support( $input );
			},
			$force_refresh
		);
	}


	protected function editor_cached_audio_generation( array $input, bool $force_refresh = false ) {
		return $this->editor_cached_client_result(
			'audio_generation',
			$input,
			function () use ( $input ) {
				return $this->client->run_audio_generation( $input );
			},
			$force_refresh
		);
	}


	protected function editor_cached_cloud_web_search( array $input, bool $force_refresh = false ) {
		return $this->editor_cached_client_result(
			'cloud_web_search',
			$input,
			function () use ( $input ) {
				return $this->client->test_cloud_web_search( $input );
			},
			$force_refresh,
			function ( array $result ) use ( $input ): bool {
				return $this->editor_source_extraction_cacheable( $input, $result );
			},
			true
		);
	}


	protected function editor_source_extraction_cacheable( array $input, array $result ): bool {
		if ( 'source_extraction_preview' !== sanitize_key( (string) ( $input['intent'] ?? '' ) ) ) {
			return true;
		}

		return 'ready' === sanitize_key( (string) ( $result['status'] ?? '' ) )
			&& 'matched' === sanitize_key( (string) ( $result['url_match'] ?? '' ) )
			&& ! empty( $result['results'][0]['reader_excerpt'] ?? '' );
	}


	protected function editor_cached_client_result( string $namespace, array $input, callable $callback, bool $force_refresh = false, ?callable $should_cache = null, bool $replace_cache_on_force = false ) {
		$cache_key = $this->editor_flow_cache_key( $namespace, $input );
		$cached    = $force_refresh ? false : get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			if ( null === $should_cache || $should_cache( $cached ) ) {
				$cached['cache_status'] = 'hit';
				return $cached;
			}
			delete_transient( $cache_key );
		}

		$result = $callback();
		if ( ! is_wp_error( $result ) && is_array( $result ) ) {
			$result['cache_status'] = $force_refresh ? 'bypass' : 'miss';
			$cacheable              = null === $should_cache || $should_cache( $result );
			if ( $cacheable && ( ! $force_refresh || $replace_cache_on_force ) ) {
				set_transient( $cache_key, $result, self::EDITOR_FLOW_CACHE_TTL );
			} elseif ( $force_refresh && $replace_cache_on_force ) {
				delete_transient( $cache_key );
			}
		}

		return $result;
	}


	protected function editor_flow_cache_key( string $namespace, array $input ): string {
		$json = wp_json_encode( $input );
		if ( ! is_string( $json ) ) {
			$json = serialize( $input );
		}

		return 'npcink_toolbox_editor_' . sanitize_key( $namespace ) . '_' . md5( $json );
	}

	public static function editor_input_scope( array $context ): array {
		$scope     = sanitize_key( (string) ( $context['context_scope'] ?? 'auto' ) );
		$selection = trim( (string) ( $context['selected_text'] ?? '' ) . ' ' . (string) ( $context['selected_block_text'] ?? '' ) );
		if ( 'auto' === $scope ) {
			$scope = '' !== $selection ? 'selected_text' : ( '' !== trim( (string) ( $context['content_text'] ?? '' ) ) ? 'full_article' : 'topic_only' );
		}

		$labels = array(
			'selected_text' => __( 'Selected text or supplied snippet', 'npcink-workflow-toolbox' ),
			'full_article'  => __( 'Full article context', 'npcink-workflow-toolbox' ),
			'topic_only'    => __( 'Topic or short brief', 'npcink-workflow-toolbox' ),
		);

		$fields = array();
		foreach ( array( 'title', 'excerpt', 'content_text', 'selected_text', 'selected_block_text', 'post_id' ) as $field ) {
			if ( ! empty( $context[ $field ] ) ) {
				$fields[] = $field;
			}
		}

		return array(
			'id'                     => $scope,
			'label'                  => $labels[ $scope ] ?? __( 'Current context', 'npcink-workflow-toolbox' ),
			'source_fields'          => $fields,
			'operator_selected_mode' => sanitize_key( (string) ( $context['context_scope'] ?? 'auto' ) ),
			'detail'                 => __( 'This scope controls ranking context only. Toolbox still returns suggestions and does not write WordPress data.', 'npcink-workflow-toolbox' ),
		);
	}


	public static function editor_related_content_items( array $related_content ): array {
		$items = is_array( $related_content['results'] ?? null )
			? $related_content['results']
			: ( is_array( $related_content['items'] ?? null ) ? $related_content['items'] : array() );

		return array_values(
			array_filter(
				$items,
				static fn( $item ): bool => is_array( $item )
			)
		);
	}


	public static function editor_recommendation_candidate( array $args ): array {
		$candidate = array(
			'contract'               => 'recommendation_candidate.v1',
			'id'                     => sanitize_key( (string) ( $args['id'] ?? 'candidate' ) ),
			'kind'                   => sanitize_key( (string) ( $args['kind'] ?? 'generic' ) ),
			'label'                  => sanitize_text_field( (string) ( $args['label'] ?? __( 'Recommendation candidate', 'npcink-workflow-toolbox' ) ) ),
			'value'                  => sanitize_text_field( (string) ( $args['value'] ?? '' ) ),
			'reason'                 => sanitize_text_field( (string) ( $args['reason'] ?? '' ) ),
			'confidence'             => is_numeric( $args['confidence'] ?? null ) ? max( 0, min( 1, (float) $args['confidence'] ) ) : null,
			'quality_status'         => sanitize_key( (string) ( $args['quality_status'] ?? 'review' ) ),
			'quality_score'          => absint( $args['quality_score'] ?? 0 ),
			'quality_issues'         => is_array( $args['quality_issues'] ?? null ) ? array_values( array_map( 'sanitize_text_field', $args['quality_issues'] ) ) : array(),
			'action_policy'          => sanitize_key( (string) ( $args['action_policy'] ?? 'suggestion_only' ) ),
			'target_field'           => sanitize_key( (string) ( $args['target_field'] ?? '' ) ),
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
			'evidence_refs'          => is_array( $args['evidence_refs'] ?? null ) ? array_values( array_map( 'sanitize_text_field', $args['evidence_refs'] ) ) : array(),
		);

		if ( '' !== (string) ( $args['source_candidate_ref'] ?? '' ) ) {
			$candidate['source_candidate_ref'] = sanitize_text_field( (string) $args['source_candidate_ref'] );
		}
		if ( '' !== (string) ( $args['candidate_source'] ?? '' ) ) {
			$candidate['candidate_source'] = sanitize_key( (string) $args['candidate_source'] );
		}
		if ( in_array( (string) ( $args['candidate_relevance'] ?? '' ), array( 'strong', 'review', 'weak' ), true ) ) {
			$candidate['candidate_relevance'] = (string) $args['candidate_relevance'];
		}
		if ( is_array( $args['target_ref'] ?? null ) ) {
			$target_ref              = $args['target_ref'];
			$candidate['target_ref'] = array(
				'post_id'   => absint( $target_ref['post_id'] ?? 0 ),
				'title'     => sanitize_text_field( (string) ( $target_ref['title'] ?? '' ) ),
				'url'       => esc_url_raw( (string) ( $target_ref['url'] ?? '' ) ),
				'status'    => sanitize_key( (string) ( $target_ref['status'] ?? '' ) ),
				'post_type' => sanitize_key( (string) ( $target_ref['post_type'] ?? '' ) ),
			);
		}
		if ( '' !== (string) ( $args['anchor_or_context'] ?? '' ) ) {
			$candidate['anchor_or_context'] = sanitize_text_field( (string) $args['anchor_or_context'] );
		}
		if ( array_key_exists( 'can_apply_to_editor', $args ) ) {
			$candidate['can_apply_to_editor'] = true === $args['can_apply_to_editor'];
		}
		if ( '' !== (string) ( $args['anchor_quality_status'] ?? '' ) ) {
			$candidate['anchor_quality_status'] = sanitize_key( (string) $args['anchor_quality_status'] );
		}
		if ( '' !== (string) ( $args['evidence_note'] ?? '' ) ) {
			$candidate['evidence_note'] = sanitize_text_field( (string) $args['evidence_note'] );
		}
		if ( '' !== (string) ( $args['owner_label'] ?? '' ) ) {
			$candidate['owner_label'] = sanitize_key( (string) $args['owner_label'] );
		}
		if ( '' !== (string) ( $args['next_safe_action'] ?? '' ) ) {
			$candidate['next_safe_action'] = sanitize_key( (string) $args['next_safe_action'] );
		}
		if ( is_array( $args['source_match'] ?? null ) ) {
			$source_match  = $args['source_match'];
			$client_id     = sanitize_text_field( (string) ( $source_match['block_client_id'] ?? '' ) );
			$matched_text  = sanitize_text_field( (string) ( $source_match['matched_text'] ?? '' ) );
			$expected_text = sanitize_textarea_field( Rest_Editor_Audio_Text::trim( wp_strip_all_tags( (string) ( $source_match['expected_text'] ?? '' ) ), 1600 ) );
			if ( '' !== $client_id && '' !== $matched_text && '' !== $expected_text ) {
				$candidate['source_match'] = array(
					'block_client_id' => $client_id,
					'block_name'      => sanitize_text_field( (string) ( $source_match['block_name'] ?? '' ) ),
					'matched_text'    => $matched_text,
					'text_offset'     => absint( $source_match['text_offset'] ?? 0 ),
					'expected_text'   => $expected_text,
					'match_basis'     => sanitize_key( (string) ( $source_match['match_basis'] ?? '' ) ),
				);
			}
		}
		if ( '' !== (string) ( $args['priority_reason'] ?? '' ) ) {
			$candidate['priority_reason'] = sanitize_text_field( (string) $args['priority_reason'] );
		}
		if ( is_array( $args['link_graph_issues'] ?? null ) ) {
			$candidate['link_graph_issues'] = array_values( array_filter( array_map( 'sanitize_key', $args['link_graph_issues'] ) ) );
		}
		if ( is_array( $args['shared_terms'] ?? null ) ) {
			$candidate['shared_terms'] = array_slice( array_values( array_filter( array_map( 'sanitize_text_field', $args['shared_terms'] ) ) ), 0, 3 );
		}
		$candidate['incoming_count'] = absint( $args['incoming_count'] ?? 0 );

		return $candidate;
	}
}
