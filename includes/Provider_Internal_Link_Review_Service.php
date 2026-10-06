<?php
/**
 * Internal-link review set service: samples published posts with sparse
 * internal linking and returns a bounded suggestion-only review set.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

/**
 * Bounded internal-link review-set builder over sampled public posts.
 */
final class Provider_Internal_Link_Review_Service extends Provider_Client_Support {

	public function __construct( Settings $settings ) {
		parent::__construct( $settings );
	}

	private const MAX_POSTS_PER_REQUEST = 50;
	private const MAX_TITLE_CHARS       = 200;
	private const MAX_EXCERPT_CHARS     = 300;
	private const MAX_EXISTING_LINKS    = 20;
	private const SPARSE_LINK_THRESHOLD = 3;

	/**
	 * Samples published posts with sparse internal linking.
	 *
	 * @param int $limit Maximum posts to sample.
	 * @return array<int,array<string,mixed>> Bounded post sample.
	 */
	public function sample_sparse_internal_link_posts( int $limit = 50 ): array {
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
			$post_object = get_post( $post_id );
			if ( ! $post_object instanceof \WP_Post ) {
				continue;
			}
			$existing_links = $this->extract_internal_link_targets( (string) $post_object->post_content );
			if ( count( $existing_links ) >= self::SPARSE_LINK_THRESHOLD ) {
				continue;
			}
			$sparse[] = array(
				'post_id'             => $post_id,
				'post_title'          => $this->bounded_text( (string) $post_object->post_title, self::MAX_TITLE_CHARS ),
				'post_excerpt'        => $this->bounded_text(
					'' !== (string) $post_object->post_excerpt
						? (string) $post_object->post_excerpt
						: wp_trim_words( (string) $post_object->post_content, 40, '…' ),
					self::MAX_EXCERPT_CHARS
				),
				'existing_links'      => $existing_links,
				'existing_link_count' => count( $existing_links ),
			);
		}

		return $sparse;
	}

	/**
	 * Builds the internal_link_review_set.v1 artifact.
	 *
	 * @param array  $sample Bounded post sample.
	 * @param array  $suggestions Cloud suggestions indexed by post_id.
	 * @param string $cloud_status Cloud availability.
	 * @return array<string,mixed> The review set artifact.
	 */
	public function build_internal_link_review_set( array $sample, array $suggestions = array(), string $cloud_status = 'cloud_required' ): array {
		$cloud_ready = in_array( $cloud_status, array( 'available', 'registered' ), true ) && ! empty( $suggestions );
		$indexed     = $cloud_ready ? $this->index_by_post( $suggestions ) : array();

		$base = array(
			'artifact_type'          => 'internal_link_review_set',
			'contract_version'       => 'internal_link_review_set.v1',
			'write_posture'          => 'suggestion_only',
			'post_content_unchanged' => true,
			'direct_wordpress_write' => false,
			'proposal_created'       => false,
			'cloud_status'           => $cloud_ready ? 'available' : $cloud_status,
			'eligibility_summary'    => array(
				'sampled_post_count'     => count( $sample ),
				'sparse_link_post_count' => count( $sample ),
				'selection_rule'         => 'published_posts_with_fewer_than_3_internal_links',
			),
			'selected_items'         => array(),
			'blocked_items'          => array(),
			'operator_next_action'   => $cloud_ready
				? __( 'Review each suggested internal link, then open the post in the WordPress editor to insert links.', 'npcink-workflow-toolbox' )
				: __( 'Connect Npcink Cloud to get internal-link suggestions for posts with sparse linking.', 'npcink-workflow-toolbox' ),
			'retryable'              => ! $cloud_ready,
			'retry_guidance'         => $cloud_ready
				? ''
				: __( 'This review set needs the Cloud hosted AI runtime. Connect Cloud in the Cloud Addon settings, then retry.', 'npcink-workflow-toolbox' ),
		);

		if ( ! $cloud_ready ) {
			foreach ( $sample as $post ) {
				$base['blocked_items'][] = array(
					'post_id'        => (int) ( $post['post_id'] ?? 0 ),
					'blocked_reason' => 'cloud_suggestions_unavailable',
				);
			}
			return $base;
		}

		foreach ( $sample as $post ) {
			$post_id    = (int) ( $post['post_id'] ?? 0 );
			$suggestion = $indexed[ $post_id ] ?? null;

			if ( ! is_array( $suggestion ) || empty( $suggestion['suggested_links'] ) || ! is_array( $suggestion['suggested_links'] ) ) {
				$base['blocked_items'][] = array(
					'post_id'        => $post_id,
					'blocked_reason' => is_array( $suggestion ) ? 'no_link_candidates_returned' : 'no_suggestion_returned',
				);
				continue;
			}

			$links = array();
			foreach ( array_slice( $suggestion['suggested_links'], 0, 8 ) as $link ) {
				if ( ! is_array( $link ) ) {
					continue;
				}
				$target_id  = absint( $link['target_post_id'] ?? 0 );
				$target_url = esc_url_raw( (string) ( $link['target_url'] ?? '' ) );
				if ( $target_id < 1 || '' === $target_url ) {
					continue;
				}
				$links[] = array(
					'target_post_id'        => $target_id,
					'target_title'          => $this->bounded_text( (string) ( $link['target_title'] ?? '' ), 120 ),
					'target_url'            => $target_url,
					'suggested_anchor_text' => $this->bounded_text( (string) ( $link['suggested_anchor_text'] ?? '' ), 80 ),
					'confidence'            => max( 0.0, min( 1.0, (float) ( $link['confidence'] ?? 0.0 ) ) ),
				);
			}

			if ( array() === $links ) {
				$base['blocked_items'][] = array(
					'post_id'        => $post_id,
					'blocked_reason' => 'no_valid_link_candidates',
				);
				continue;
			}

			$action = (string) ( $suggestion['suggested_action'] ?? 'review_manually' );
			if ( ! in_array( $action, array( 'open_in_wordpress_editor', 'review_manually' ), true ) ) {
				$action = 'review_manually';
			}

			$base['selected_items'][] = array(
				'post_id'             => $post_id,
				'post_title'          => (string) ( $post['post_title'] ?? '' ),
				'existing_link_count' => (int) ( $post['existing_link_count'] ?? 0 ),
				'suggested_links'     => $links,
				'suggested_action'    => $action,
			);
		}

		return $base;
	}

	/**
	 * Builds the local fail-closed response.
	 *
	 * @param array $sample Bounded post sample.
	 * @return array<string,mixed> Local response.
	 */
	public function local_internal_link_review_response( array $sample ): array {
		return array(
			'intent'                   => 'internal_link_suggestions',
			'cloud_status'             => 'cloud_required',
			'internal_link_review_set' => $this->build_internal_link_review_set( $sample ),
		);
	}

	/**
	 * Gets the Cloud request payload.
	 *
	 * @param array $sample Bounded post sample.
	 * @return array<string,mixed> Cloud payload.
	 */
	public function cloud_request_payload( array $sample ): array {
		return array(
			'intent'              => 'internal_link_suggestions',
			'data_classification' => 'pii',
			'post_sample'         => $sample,
		);
	}

	/**
	 * Extracts internal-link target paths from post content.
	 *
	 * @param string $content Raw post content HTML.
	 * @return array<int,string> Bounded list of internal link targets.
	 */
	private function extract_internal_link_targets( string $content ): array {
		if ( ! preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\']/i', $content, $matches ) ) {
			return array();
		}
		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$targets   = array();
		foreach ( $matches[1] as $href ) {
			if ( count( $targets ) >= self::MAX_EXISTING_LINKS ) {
				break;
			}
			$host = wp_parse_url( $href, PHP_URL_HOST );
			if ( null !== $host && $host !== $site_host ) {
				continue;
			}
			$path = wp_parse_url( $href, PHP_URL_PATH );
			if ( null === $path || '' === $path || '/' === $path ) {
				continue;
			}
			$targets[] = $path;
		}
		return array_slice( array_unique( $targets ), 0, self::MAX_EXISTING_LINKS );
	}

	/**
	 * Indexes suggestions by post id.
	 *
	 * @param array $suggestions Raw suggestions.
	 * @return array<int,array> Indexed.
	 */
	private function index_by_post( array $suggestions ): array {
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
}
