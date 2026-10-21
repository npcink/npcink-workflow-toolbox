<?php
/**
 * Builds a de-identified content-structure / SEO-health insight pack.
 *
 * Aggregates public counts, taxonomy shape, and bounded public title samples.
 * Never returns post body, comment text, emails, IPs, or secrets.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

final class Content_Structure_Insight_Builder {
	public const CONTRACT_VERSION = 'content_structure_insight_pack.v1';
	public const ARTIFACT_TYPE    = 'content_structure_insight_pack';

	private const SAMPLE_LIMIT    = 24;
	private const TOP_TERMS_LIMIT = 12;

	/**
	 * @return array<string,mixed>
	 */
	public function build(): array {
		$timestamp    = time();
		$generated_at = gmdate( 'c', $timestamp );
		$sample       = $this->collect_public_sample( self::SAMPLE_LIMIT );
		$post_counts  = $this->collect_post_counts();
		$taxonomies   = $this->collect_taxonomies();
		$signals      = $this->structure_signals( $sample, $generated_at );
		$findings     = $this->build_findings( $post_counts, $taxonomies, $signals, $sample );

		usort(
			$findings,
			static function ( array $left, array $right ): int {
				$left_score  = (int) ( $left['priority_score'] ?? 0 );
				$right_score = (int) ( $right['priority_score'] ?? 0 );
				if ( $left_score === $right_score ) {
					return strcmp( (string) ( $left['id'] ?? '' ), (string) ( $right['id'] ?? '' ) );
				}

				return $right_score <=> $left_score;
			}
		);
		$findings = array_slice( $findings, 0, 8 );

		return array(
			'artifact_type'          => self::ARTIFACT_TYPE,
			'contract_version'       => self::CONTRACT_VERSION,
			'version'                => 1,
			'run_id'                 => 'content-structure-' . gmdate( 'YmdHis', $timestamp ),
			'site_id'                => $this->site_id(),
			'generated_at'           => $generated_at,
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
			'cloud_required'         => false,
			'core_proposal_created'  => false,
			'summary'                => $this->summary( $post_counts, $taxonomies, $signals, $findings ),
			'post_counts'            => $post_counts,
			'taxonomies'             => $taxonomies,
			'structure_signals'      => $signals,
			'top_findings'           => $findings,
			'opportunities'          => $this->opportunities( $findings ),
			'data_sources'           => array(
				'post_counts'   => array(
					'available' => true,
					'source'    => 'wp_count_posts',
				),
				'public_sample' => array(
					'available' => count( $sample ) > 0,
					'count'     => count( $sample ),
					'source'    => 'local_public_wordpress_titles_and_structure_flags',
				),
				'taxonomies'    => array(
					'available' => ! empty( $taxonomies ),
					'source'    => 'local_taxonomy_aggregates',
				),
			),
			'safety'                 => array(
				'operator_review_required'      => true,
				'direct_wordpress_write'        => false,
				'automatic_core_proposal'       => false,
				'post_body_returned'            => false,
				'comment_text_returned'         => false,
				'comment_author_email_returned' => false,
				'comment_ip_returned'           => false,
				'non_public_titles_returned'    => false,
				'cloud_called'                  => false,
				'local_scheduler_created'       => false,
				'custom_tables_created'         => false,
			),
		);
	}

	/**
	 * @return array<string,array<string,int>>
	 */
	private function collect_post_counts(): array {
		$types = array( 'post', 'page' );
		$out   = array();
		foreach ( $types as $type ) {
			$counts = function_exists( 'wp_count_posts' ) ? wp_count_posts( $type ) : (object) array();
			$row    = array(
				'publish' => 0,
				'draft'   => 0,
				'pending' => 0,
				'private' => 0,
				'future'  => 0,
			);
			foreach ( array_keys( $row ) as $status ) {
				$row[ $status ] = max( 0, (int) ( $counts->$status ?? 0 ) );
			}
			$out[ $type ] = $row;
		}

		return $out;
	}

	/**
	 * Bounded public titles + structure flags only (no body).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function collect_public_sample( int $limit ): array {
		if ( ! function_exists( 'get_posts' ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'     => array( 'post', 'page' ),
				'post_status'   => 'publish',
				'numberposts'   => max( 1, min( 50, $limit ) ),
				'orderby'       => 'modified',
				'order'         => 'ASC',
				'no_found_rows' => true,
				'cache_results' => true,
			)
		);

		$sample = array();
		foreach ( $posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$post_id  = (int) $post->ID;
			$content  = (string) $post->post_content;
			$sample[] = array(
				'object_type'              => (string) $post->post_type,
				'object_id'                => $post_id,
				'title'                    => wp_strip_all_tags( (string) $post->post_title ),
				'published_at'             => $this->gmt_iso( (string) $post->post_date_gmt ),
				'modified_at'              => $this->gmt_iso( (string) $post->post_modified_gmt ),
				'word_count'               => $this->word_count( $content ),
				'excerpt_present'          => '' !== trim( (string) $post->post_excerpt ),
				'meta_description_present' => '' !== $this->meta_description( $post_id ),
				'featured_image_present'   => function_exists( 'get_post_thumbnail_id' ) && (int) get_post_thumbnail_id( $post_id ) > 0,
				'category_count'           => count( $this->term_names( $post_id, 'category' ) ),
				'tag_count'                => count( $this->term_names( $post_id, 'post_tag' ) ),
			);
		}

		return $sample;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function collect_taxonomies(): array {
		return array(
			'category' => $this->taxonomy_shape( 'category' ),
			'post_tag' => $this->taxonomy_shape( 'post_tag' ),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function taxonomy_shape( string $taxonomy ): array {
		if ( ! function_exists( 'get_terms' ) ) {
			return array(
				'total'       => 0,
				'empty_count' => 0,
				'low_count'   => 0,
				'top_terms'   => array(),
			);
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 100,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array(
				'total'       => 0,
				'empty_count' => 0,
				'low_count'   => 0,
				'top_terms'   => array(),
			);
		}

		$empty_count = 0;
		$low_count   = 0;
		$top_terms   = array();
		foreach ( $terms as $term ) {
			$count = (int) $term->count;
			if ( 0 === $count ) {
				++$empty_count;
			}
			if ( $count > 0 && $count <= 1 ) {
				++$low_count;
			}
			if ( count( $top_terms ) < self::TOP_TERMS_LIMIT && $count > 0 ) {
				$top_terms[] = array(
					'name'  => sanitize_text_field( (string) $term->name ),
					'count' => $count,
				);
			}
		}

		return array(
			'total'       => count( $terms ),
			'empty_count' => $empty_count,
			'low_count'   => $low_count,
			'top_terms'   => $top_terms,
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $sample Sample.
	 * @return array<string,mixed>
	 */
	private function structure_signals( array $sample, string $generated_at ): array {
		$n               = count( $sample );
		$missing_excerpt = 0;
		$missing_meta    = 0;
		$missing_feat    = 0;
		$thin            = 0;
		$no_category     = 0;
		$no_tag          = 0;
		$buckets         = array(
			'under_300' => 0,
			'300_999'   => 0,
			'1000_plus' => 0,
		);
		$age             = array(
			'under_90d' => 0,
			'90_179d'   => 0,
			'180_plus'  => 0,
		);
		$title_samples   = array();

		foreach ( $sample as $item ) {
			$words = (int) ( $item['word_count'] ?? 0 );
			if ( $words > 0 && $words < 300 ) {
				++$thin;
				++$buckets['under_300'];
			} elseif ( $words >= 1000 ) {
				++$buckets['1000_plus'];
			} elseif ( $words >= 300 ) {
				++$buckets['300_999'];
			}

			if ( empty( $item['excerpt_present'] ) ) {
				++$missing_excerpt;
			}
			if ( empty( $item['meta_description_present'] ) ) {
				++$missing_meta;
			}
			if ( empty( $item['featured_image_present'] ) ) {
				++$missing_feat;
			}
			if ( (int) ( $item['category_count'] ?? 0 ) <= 0 && 'post' === (string) ( $item['object_type'] ?? '' ) ) {
				++$no_category;
			}
			if ( (int) ( $item['tag_count'] ?? 0 ) <= 0 && 'post' === (string) ( $item['object_type'] ?? '' ) ) {
				++$no_tag;
			}

			$days = $this->age_days( (string) ( $item['modified_at'] ?? '' ), $generated_at );
			if ( $days >= 180 ) {
				++$age['180_plus'];
			} elseif ( $days >= 90 ) {
				++$age['90_179d'];
			} else {
				++$age['under_90d'];
			}

			if ( count( $title_samples ) < 8 ) {
				$title_samples[] = array(
					'object_type' => (string) ( $item['object_type'] ?? 'post' ),
					'object_id'   => (int) ( $item['object_id'] ?? 0 ),
					'title'       => sanitize_text_field( (string) ( $item['title'] ?? '' ) ),
					'word_count'  => $words,
				);
			}
		}

		$rate = static function ( int $part, int $total ): float {
			return $total > 0 ? round( $part / $total, 3 ) : 0.0;
		};

		return array(
			'sample_size'                    => $n,
			'missing_excerpt_count'          => $missing_excerpt,
			'missing_excerpt_rate'           => $rate( $missing_excerpt, $n ),
			'missing_meta_description_count' => $missing_meta,
			'missing_meta_description_rate'  => $rate( $missing_meta, $n ),
			'missing_featured_image_count'   => $missing_feat,
			'missing_featured_image_rate'    => $rate( $missing_feat, $n ),
			'thin_content_count'             => $thin,
			'thin_content_rate'              => $rate( $thin, $n ),
			'posts_missing_category'         => $no_category,
			'posts_missing_tag'              => $no_tag,
			'word_count_buckets'             => $buckets,
			'age_buckets'                    => $age,
			'title_samples'                  => $title_samples,
		);
	}

	/**
	 * @param array<string,array<string,int>> $post_counts Counts.
	 * @param array<string,mixed>             $taxonomies Taxonomies.
	 * @param array<string,mixed>             $signals Signals.
	 * @param array<int,array<string,mixed>>  $sample Sample.
	 * @return array<int,array<string,mixed>>
	 */
	private function build_findings( array $post_counts, array $taxonomies, array $signals, array $sample ): array {
		$findings = array();
		$n        = max( 0, (int) ( $signals['sample_size'] ?? 0 ) );

		$missing_meta = (int) ( $signals['missing_meta_description_count'] ?? 0 );
		if ( $n > 0 && $missing_meta > 0 ) {
			$findings[] = $this->finding(
				'meta_description_coverage_gap',
				__( 'Many public items lack a meta description', 'npcink-workflow-toolbox' ),
				'seo_metadata',
				min( 92, 50 + $missing_meta * 4 ),
				$missing_meta >= max( 3, (int) floor( $n / 2 ) ) ? 'high' : 'medium',
				sprintf(
					/* translators: 1: missing count, 2: sample size. */
					__( '%1$d of %2$d sampled public posts/pages have no SEO meta description.', 'npcink-workflow-toolbox' ),
					$missing_meta,
					$n
				),
				__( 'Missing snippets weaken search CTR and AI citation clarity.', 'npcink-workflow-toolbox' ),
				__( 'Prioritize meta descriptions for high-traffic or cornerstone pages via the existing metadata workflow — suggestion only.', 'npcink-workflow-toolbox' ),
				'manual_review_only',
				array_slice( $this->refs_missing( $sample, 'meta_description_present' ), 0, 5 )
			);
		}

		$missing_excerpt = (int) ( $signals['missing_excerpt_count'] ?? 0 );
		if ( $n > 0 && $missing_excerpt > 0 ) {
			$findings[] = $this->finding(
				'excerpt_coverage_gap',
				__( 'Excerpts are missing on sampled public content', 'npcink-workflow-toolbox' ),
				'seo_metadata',
				min( 85, 40 + $missing_excerpt * 3 ),
				$missing_excerpt >= max( 4, (int) floor( $n / 2 ) ) ? 'medium' : 'low',
				sprintf(
					/* translators: 1: missing count, 2: sample size. */
					__( '%1$d of %2$d sampled items have no excerpt.', 'npcink-workflow-toolbox' ),
					$missing_excerpt,
					$n
				),
				__( 'Excerpts help archives, feeds, and discoverability cards stay readable.', 'npcink-workflow-toolbox' ),
				__( 'Add short excerpts on key posts/pages before expanding new topics.', 'npcink-workflow-toolbox' ),
				'manual_review_only',
				array_slice( $this->refs_missing( $sample, 'excerpt_present' ), 0, 5 )
			);
		}

		$thin = (int) ( $signals['thin_content_count'] ?? 0 );
		if ( $thin > 0 ) {
			$findings[] = $this->finding(
				'thin_content_sample',
				__( 'Some public pages look thin', 'npcink-workflow-toolbox' ),
				'content_depth',
				min( 90, 45 + $thin * 6 ),
				$thin >= 3 ? 'high' : 'medium',
				sprintf(
					/* translators: 1: thin count, 2: sample size. */
					__( '%1$d of %2$d sampled items are under 300 words.', 'npcink-workflow-toolbox' ),
					$thin,
					$n
				),
				__( 'Thin pages weaken topical authority and internal linking value.', 'npcink-workflow-toolbox' ),
				__( 'Refresh or merge thin pages before publishing near-duplicate topics.', 'npcink-workflow-toolbox' ),
				'manual_review_only',
				array_slice( $this->refs_thin( $sample ), 0, 5 )
			);
		}

		$stale = (int) ( ( $signals['age_buckets']['180_plus'] ?? 0 ) );
		if ( $stale > 0 ) {
			$findings[] = $this->finding(
				'stale_structure_backlog',
				__( 'A share of public content has not been refreshed in 180+ days', 'npcink-workflow-toolbox' ),
				'content_freshness',
				min( 88, 48 + $stale * 5 ),
				$stale >= 4 ? 'high' : 'medium',
				sprintf(
					/* translators: 1: stale count, 2: sample size. */
					__( '%1$d of %2$d sampled public items were last modified 180+ days ago.', 'npcink-workflow-toolbox' ),
					$stale,
					$n
				),
				__( 'Stale structure slows search freshness and reader trust.', 'npcink-workflow-toolbox' ),
				__( 'Schedule a refresh queue for the oldest active titles first.', 'npcink-workflow-toolbox' ),
				'core_handoff_candidate',
				array()
			);
		}

		$cat        = is_array( $taxonomies['category'] ?? null ) ? $taxonomies['category'] : array();
		$empty_cats = (int) ( $cat['empty_count'] ?? 0 );
		$low_cats   = (int) ( $cat['low_count'] ?? 0 );
		if ( $empty_cats + $low_cats >= 3 ) {
			$findings[] = $this->finding(
				'category_shape_imbalance',
				__( 'Category structure looks sparse or uneven', 'npcink-workflow-toolbox' ),
				'taxonomy_shape',
				min( 80, 35 + ( $empty_cats + $low_cats ) * 4 ),
				'medium',
				sprintf(
					/* translators: 1: empty categories, 2: low-count categories, 3: total. */
					__( '%1$d empty and %2$d single-item categories among %3$d category terms.', 'npcink-workflow-toolbox' ),
					$empty_cats,
					$low_cats,
					(int) ( $cat['total'] ?? 0 )
				),
				__( 'Sparse category trees confuse navigation and topical clustering.', 'npcink-workflow-toolbox' ),
				__( 'Merge empty/low categories and keep the top terms aligned with the site brief.', 'npcink-workflow-toolbox' ),
				'manual_review_only',
				array()
			);
		}

		$published_posts = (int) ( $post_counts['post']['publish'] ?? 0 );
		$published_pages = (int) ( $post_counts['page']['publish'] ?? 0 );
		if ( 0 === $published_posts && 0 === $published_pages ) {
			$findings[] = $this->finding(
				'no_public_content',
				__( 'No published posts or pages yet', 'npcink-workflow-toolbox' ),
				'content_depth',
				70,
				'medium',
				__( 'wp_count_posts reports zero publish status for posts and pages.', 'npcink-workflow-toolbox' ),
				__( 'Structure advice needs at least a small public corpus.', 'npcink-workflow-toolbox' ),
				__( 'Publish a cornerstone page or post, then re-ask this pack.', 'npcink-workflow-toolbox' ),
				'manual_review_only',
				array()
			);
		}

		return $findings;
	}

	/**
	 * @param array<string,array<string,int>> $post_counts Counts.
	 * @param array<string,mixed>             $taxonomies Taxonomies.
	 * @param array<string,mixed>             $signals Signals.
	 * @param array<int,array<string,mixed>>  $findings Findings.
	 * @return array<string,mixed>
	 */
	private function summary( array $post_counts, array $taxonomies, array $signals, array $findings ): array {
		$high = 0;
		foreach ( $findings as $finding ) {
			if ( 'high' === (string) ( $finding['severity'] ?? '' ) ) {
				++$high;
			}
		}

		return array(
			'published_posts'               => (int) ( $post_counts['post']['publish'] ?? 0 ),
			'published_pages'               => (int) ( $post_counts['page']['publish'] ?? 0 ),
			'draft_posts'                   => (int) ( $post_counts['post']['draft'] ?? 0 ),
			'sample_size'                   => (int) ( $signals['sample_size'] ?? 0 ),
			'category_terms'                => (int) ( $taxonomies['category']['total'] ?? 0 ),
			'tag_terms'                     => (int) ( $taxonomies['post_tag']['total'] ?? 0 ),
			'missing_meta_description_rate' => (float) ( $signals['missing_meta_description_rate'] ?? 0 ),
			'thin_content_rate'             => (float) ( $signals['thin_content_rate'] ?? 0 ),
			'stale_180d_rate'               => ( (int) ( $signals['sample_size'] ?? 0 ) > 0 )
				? round( ( (int) ( $signals['age_buckets']['180_plus'] ?? 0 ) ) / (int) $signals['sample_size'], 3 )
				: 0.0,
			'top_finding_count'             => count( $findings ),
			'high_priority_findings'        => $high,
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $findings Findings.
	 * @return array<int,array<string,mixed>>
	 */
	private function opportunities( array $findings ): array {
		$items = array();
		foreach ( $findings as $finding ) {
			$items[] = array(
				'finding_id'      => (string) ( $finding['id'] ?? '' ),
				'opportunity'     => (string) ( $finding['recommended_action'] ?? '' ),
				'write_boundary'  => (string) ( $finding['write_boundary'] ?? 'suggestion_only' ),
				'operator_action' => 'review_finding',
			);
		}

		return array_slice( $items, 0, 5 );
	}

	/**
	 * @param array<int,array<string,mixed>> $sample Sample.
	 * @return array<int,array<string,mixed>>
	 */
	private function refs_missing( array $sample, string $flag_key ): array {
		$refs = array();
		foreach ( $sample as $item ) {
			if ( ! empty( $item[ $flag_key ] ) ) {
				continue;
			}
			$refs[] = array(
				'object_type' => sanitize_key( (string) ( $item['object_type'] ?? 'post' ) ),
				'object_id'   => (int) ( $item['object_id'] ?? 0 ),
				'title'       => sanitize_text_field( (string) ( $item['title'] ?? '' ) ),
			);
		}

		return $refs;
	}

	/**
	 * @param array<int,array<string,mixed>> $sample Sample.
	 * @return array<int,array<string,mixed>>
	 */
	private function refs_thin( array $sample ): array {
		$refs = array();
		foreach ( $sample as $item ) {
			$words = (int) ( $item['word_count'] ?? 0 );
			if ( $words <= 0 || $words >= 300 ) {
				continue;
			}
			$refs[] = array(
				'object_type' => sanitize_key( (string) ( $item['object_type'] ?? 'post' ) ),
				'object_id'   => (int) ( $item['object_id'] ?? 0 ),
				'title'       => sanitize_text_field( (string) ( $item['title'] ?? '' ) ),
				'word_count'  => $words,
			);
		}

		return $refs;
	}

	/**
	 * @param array<int,array<string,mixed>> $source_refs Refs.
	 * @return array<string,mixed>
	 */
	private function finding( string $id, string $title, string $issue_type, int $priority_score, string $severity, string $evidence_summary, string $impact, string $recommended_action, string $write_boundary, array $source_refs ): array {
		return array(
			'id'                     => $id,
			'title'                  => $title,
			'issue_type'             => $issue_type,
			'category'               => $issue_type,
			'severity'               => $severity,
			'priority_score'         => max( 0, min( 100, $priority_score ) ),
			'evidence_summary'       => $evidence_summary,
			'impact'                 => $impact,
			'recommended_action'     => $recommended_action,
			'write_boundary'         => $write_boundary,
			'core_handoff_candidate' => 'core_handoff_candidate' === $write_boundary,
			'direct_wordpress_write' => false,
			'source_refs'            => $source_refs,
		);
	}

	/**
	 * @return array<int,string>
	 */
	private function term_names( int $post_id, string $taxonomy ): array {
		if ( ! function_exists( 'wp_get_post_terms' ) ) {
			return array();
		}
		$terms = wp_get_post_terms(
			$post_id,
			$taxonomy,
			array(
				'fields' => 'names',
			)
		);
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map(
					static fn( $term ): string => trim( (string) $term ),
					$terms
				)
			)
		);
	}

	private function meta_description( int $post_id ): string {
		foreach ( array( '_yoast_wpseo_metadesc', '_aioseo_description', 'rank_math_description' ) as $meta_key ) {
			$value = trim( (string) get_post_meta( $post_id, $meta_key, true ) );
			if ( '' !== $value ) {
				return $value;
			}
		}

		return '';
	}

	private function word_count( string $content ): int {
		$text = trim( wp_strip_all_tags( $content ) );
		if ( '' === $text ) {
			return 0;
		}
		$count = str_word_count( $text );
		if ( $count > 0 ) {
			return $count;
		}

		return (int) ceil( mb_strlen( preg_replace( '/\s+/u', '', $text ) ?: '' ) / 2 );
	}

	private function age_days( string $date, string $generated_at ): int {
		$date_ts = '' !== $date ? strtotime( $date ) : false;
		$now_ts  = '' !== $generated_at ? strtotime( $generated_at ) : false;
		if ( false === $date_ts || false === $now_ts || $now_ts <= $date_ts ) {
			return 0;
		}

		return (int) floor( ( $now_ts - $date_ts ) / 86400 );
	}

	private function gmt_iso( string $gmt ): string {
		$timestamp = '' !== $gmt ? strtotime( $gmt . ' UTC' ) : false;
		return false === $timestamp ? '' : gmdate( 'c', $timestamp );
	}

	private function site_id(): string {
		$host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		return sanitize_key( '' !== $host ? $host : 'local-site' );
	}
}
