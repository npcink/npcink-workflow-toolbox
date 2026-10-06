<?php
/**
 * Taxonomy and tag review set service: samples published posts with sparse
 * term assignments and returns a bounded suggestion-only review set.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

/**
 * Bounded taxonomy/tag review-set builder over sampled public posts.
 */
final class Provider_Taxonomy_Tag_Service extends Provider_Client_Support {

	private const SUGGESTED_ACTION_VALUES = array( 'open_in_wordpress_editor', 'review_manually' );

	private const MAX_POSTS_PER_REQUEST  = 50;
	private const MAX_TITLE_CHARS        = 200;
	private const MAX_EXCERPT_CHARS      = 300;
	private const MAX_EXISTING_CATEGORIES = 10;
	private const MAX_EXISTING_TAGS       = 20;

	/**
	 * Samples published posts with sparse taxonomy assignments.
	 *
	 * @param int $limit Maximum posts to sample (bounded by self::MAX_POSTS_PER_REQUEST).
	 * @return array<int,array<string,mixed>> Bounded post sample.
	 */
	public function sample_sparse_taxonomy_posts( int $limit = 50 ): array {
		$limit = max( 1, min( self::MAX_POSTS_PER_REQUEST, $limit ) );

		$query = new \WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => $limit * 3,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		if ( ! is_array( $query->posts ) ) {
			return array();
		}

		$sparse = array();
		foreach ( $query->posts as $post ) {
			if ( count( $sparse ) >= $limit ) {
				break;
			}
			$post_id = is_int( $post ) ? $post : (int) $post->ID;
			if ( 0 === $post_id ) {
				continue;
			}
			$category_slugs = $this->bounded_term_slugs( $post_id, 'category' );
			$tag_slugs      = $this->bounded_term_slugs( $post_id, 'post_tag' );
			$is_sparse      = count( $category_slugs ) < 1 || count( $tag_slugs ) < 3;
			if ( ! $is_sparse ) {
				continue;
			}
			$post_object = get_post( $post_id );
			if ( ! $post_object instanceof \WP_Post ) {
				continue;
			}
			$sparse[] = array(
				'post_id'            => $post_id,
				'post_title'         => $this->bounded_text( (string) $post_object->post_title, self::MAX_TITLE_CHARS ),
				'post_excerpt'       => $this->bounded_text(
					'' !== (string) $post_object->post_excerpt
						? (string) $post_object->post_excerpt
						: wp_trim_words( (string) $post_object->post_content, 40, '…' ),
					self::MAX_EXCERPT_CHARS
				),
				'existing_categories' => $category_slugs,
				'existing_tags'       => $tag_slugs,
			);
		}

		return $sparse;
	}

	/**
	 * Builds the taxonomy_tag_review_set.v1 artifact.
	 *
	 * @param array  $sample       Bounded post sample from sample_sparse_taxonomy_posts().
	 * @param array  $suggestions  Cloud suggestions indexed by post_id.
	 * @param string $cloud_status Cloud availability status.
	 * @return array<string,mixed> The review set artifact.
	 */
	public function build_taxonomy_tag_review_set( array $sample, array $suggestions = array(), string $cloud_status = 'cloud_required' ): array {
		$cloud_ready = in_array( $cloud_status, array( 'available', 'registered' ), true ) && ! empty( $suggestions );
		$indexed     = $cloud_ready ? $this->index_suggestions_by_post( $suggestions ) : array();

		$base = array(
			'artifact_type'             => 'taxonomy_tag_review_set',
			'contract_version'          => 'taxonomy_tag_review_set.v1',
			'write_posture'             => 'suggestion_only',
			'term_assignment_unchanged' => true,
			'direct_wordpress_write'    => false,
			'proposal_created'          => false,
			'execution_created'         => false,
			'cloud_status'              => $cloud_ready ? 'available' : $cloud_status,
			'eligibility_summary'       => array(
				'sampled_post_count' => count( $sample ),
				'sparse_post_count'  => count( $sample ),
				'selection_rule'     => 'published_posts_with_fewer_than_1_category_or_3_tags',
			),
			'selected_items'            => array(),
			'blocked_items'             => array(),
			'operator_next_action'      => $cloud_ready
				? __( 'Review each suggested term assignment, then open the post in the WordPress editor to apply changes.', 'npcink-workflow-toolbox' )
				: __( 'Connect Npcink Cloud to get taxonomy and tag suggestions for sparse posts.', 'npcink-workflow-toolbox' ),
			'retryable'                 => ! $cloud_ready,
			'retry_guidance'            => $cloud_ready
				? ''
				: __( 'This review set needs the Cloud hosted AI runtime. Connect Cloud in the Cloud Addon settings, then retry.', 'npcink-workflow-toolbox' ),
		);

		if ( ! $cloud_ready ) {
			foreach ( $sample as $post ) {
				$base['blocked_items'][] = array(
					'post_id'       => (int) ( $post['post_id'] ?? 0 ),
					'blocked_reason' => 'cloud_classification_unavailable',
				);
			}
			return $base;
		}

		$available_categories = $this->available_term_slugs( 'category' );
		$available_tags       = $this->available_term_slugs( 'post_tag' );

		foreach ( $sample as $post ) {
			$post_id     = (int) ( $post['post_id'] ?? 0 );
			$suggestion  = $indexed[ $post_id ] ?? null;
			$item_base   = array(
				'post_id'             => $post_id,
				'post_title'          => (string) ( $post['post_title'] ?? '' ),
				'existing_categories' => is_array( $post['existing_categories'] ?? null ) ? $post['existing_categories'] : array(),
				'existing_tags'       => is_array( $post['existing_tags'] ?? null ) ? $post['existing_tags'] : array(),
			);

			if ( ! is_array( $suggestion ) ) {
				$base['blocked_items'][] = array(
					'post_id'        => $post_id,
					'blocked_reason' => 'no_suggestion_returned',
				);
				continue;
			}

			$suggested_categories = $this->filter_to_existing_terms(
				is_array( $suggestion['suggested_categories'] ?? null ) ? $suggestion['suggested_categories'] : array(),
				$available_categories
			);
			$suggested_tags = $this->filter_to_existing_terms(
				is_array( $suggestion['suggested_tags'] ?? null ) ? $suggestion['suggested_tags'] : array(),
				$available_tags
			);

			if ( array() === $suggested_categories && array() === $suggested_tags ) {
				$base['blocked_items'][] = array(
					'post_id'        => $post_id,
					'blocked_reason' => 'no_existing_term_match',
				);
				continue;
			}

			$action = (string) ( $suggestion['suggested_action'] ?? 'review_manually' );
			if ( ! in_array( $action, self::SUGGESTED_ACTION_VALUES, true ) ) {
				$action = 'review_manually';
			}

			$base['selected_items'][] = array_merge(
				$item_base,
				array(
					'suggested_categories' => $suggested_categories,
					'suggested_tags'       => $suggested_tags,
					'confidence'           => max( 0.0, min( 1.0, (float) ( $suggestion['confidence'] ?? 0.0 ) ) ),
					'reasons'              => $this->bounded_string_list( is_array( $suggestion['reasons'] ?? null ) ? $suggestion['reasons'] : array(), 5, 120 ),
					'suggested_action'     => $action,
				)
			);
		}

		return $base;
	}

	/**
	 * Builds the local response when Cloud is not connected.
	 *
	 * @param array  $sample Bounded post sample.
	 * @return array<string,mixed> Local response with taxonomy_tag_review_set.
	 */
	public function local_taxonomy_tag_review_response( array $sample ): array {
		$review_set = $this->build_taxonomy_tag_review_set( $sample, array(), 'cloud_required' );
		return array(
			'intent'                => 'taxonomy_tag_suggestions',
			'cloud_status'          => 'cloud_required',
			'taxonomy_tag_review_set' => $review_set,
		);
	}

	/**
	 * Gets the Cloud request payload for this intent.
	 *
	 * @param array $sample Bounded post sample.
	 * @return array<string,mixed> The payload to send to Cloud.
	 */
	public function cloud_request_payload( array $sample ): array {
		return array(
			'intent'         => 'taxonomy_tag_suggestions',
			'data_classification' => 'pii',
			'post_sample'    => $sample,
			'available_categories' => $this->available_term_slugs( 'category', 200 ),
			'available_tags'       => $this->available_term_slugs( 'post_tag', 500 ),
		);
	}

	/**
	 * Gets bounded term slug list for a post.
	 *
	 * @param int    $post_id Post id.
	 * @param string $taxonomy Taxonomy name.
	 * @return array<int,string> Bounded slug list.
	 */
	private function bounded_term_slugs( int $post_id, string $taxonomy ): array {
		$terms = wp_get_post_terms( $post_id, $taxonomy, array( 'fields' => 'slugs' ) );
		if ( ! is_array( $terms ) ) {
			return array();
		}
		$limit = 'category' === $taxonomy ? self::MAX_EXISTING_CATEGORIES : self::MAX_EXISTING_TAGS;
		return array_slice( array_map( 'strval', $terms ), 0, $limit );
	}

	/**
	 * Gets the available term slugs for a taxonomy.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @param int    $limit Maximum terms to return.
	 * @return array<int,string> Slug list.
	 */
	private function available_term_slugs( string $taxonomy, int $limit = 200 ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => $limit,
				'fields'     => 'slugs',
			)
		);
		return is_array( $terms ) ? array_map( 'strval', $terms ) : array();
	}

	/**
	 * Filters suggested terms to only those that exist as WordPress terms.
	 *
	 * @param array        $suggested Suggested slugs.
	 * @param array|string $available Existing slugs.
	 * @return array<int,string> Filtered list.
	 */
	private function filter_to_existing_terms( array $suggested, $available ): array {
		if ( ! is_array( $available ) ) {
			return array();
		}
		$existing = array_flip( $available );
		$filtered = array();
		foreach ( $suggested as $slug ) {
			$slug = sanitize_title( (string) $slug );
			if ( '' !== $slug && isset( $existing[ $slug ] ) && ! in_array( $slug, $filtered, true ) ) {
				$filtered[] = $slug;
			}
		}
		return $filtered;
	}

	/**
	 * Indexes suggestions by post id.
	 *
	 * @param array $suggestions Raw suggestions.
	 * @return array<int,array> Indexed by post id.
	 */
	private function index_suggestions_by_post( array $suggestions ): array {
		$indexed = array();
		foreach ( $suggestions as $suggestion ) {
			if ( is_array( $suggestion ) ) {
				$post_id = (int) ( $suggestion['post_id'] ?? 0 );
				if ( $post_id > 0 ) {
					$indexed[ $post_id ] = $suggestion;
				}
			}
		}
		return $indexed;
	}

	/**
	 * Bounds a string list.
	 *
	 * @param array $items Raw items.
	 * @param int   $max_items Maximum items.
	 * @param int   $max_chars Maximum characters per item.
	 * @return array<int,string> Bounded list.
	 */
	private function bounded_string_list( array $items, int $max_items, int $max_chars ): array {
		$bounded = array();
		foreach ( $items as $item ) {
			if ( count( $bounded ) >= $max_items ) {
				break;
			}
			$text = $this->bounded_text( (string) $item, $max_chars );
			if ( '' !== $text ) {
				$bounded[] = $text;
			}
		}
		return $bounded;
	}
}
