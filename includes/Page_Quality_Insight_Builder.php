<?php
/**
 * Builds a de-identified page-quality insight pack.
 *
 * Stratified public samples with completeness flags, optional heading hierarchy
 * and in-content missing-alt counts. Never returns post body, comment text,
 * emails, IPs, secrets, Lighthouse scores, or traffic/ranking claims.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

final class Page_Quality_Insight_Builder {
	public const CONTRACT_VERSION = 'page_quality_insight_pack.v1';
	public const ARTIFACT_TYPE    = 'page_quality_insight_pack';

	public const SAMPLE_LIMIT_DEFAULT = 60;
	public const SAMPLE_LIMIT_MIN     = 6;
	public const SAMPLE_LIMIT_MAX     = 120;
	public const SAMPLE_LIMIT_FILTER  = 'npcink_toolbox_page_quality_sample_limit';

	private const TOP_TERMS_LIMIT = 12;

	/** Publish-date strata, oldest first. Population is split into equal thirds. */
	private const STRATA = array(
		'oldest' => '最早发布的三分之一',
		'middle' => '中间的三分之一',
		'newest' => '最新发布的三分之一',
	);

	/**
	 * @return array<string,mixed>
	 */
	public function build(): array {
		$timestamp    = time();
		$generated_at = gmdate( 'c', $timestamp );
		$plan         = self::plan_strata( $this->collect_public_ids_by_date(), self::sample_limit() );
		$sample       = $this->collect_public_sample( $plan );
		$post_counts  = $this->collect_post_counts();
		$sampling     = self::sampling_summary( $plan, $sample );
		$taxonomies   = $this->collect_taxonomies();
		$signals      = $this->page_quality_signals( $sample, $generated_at );
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
			'run_id'                 => 'page-quality-' . gmdate( 'YmdHis', $timestamp ),
			'site_id'                => $this->site_id(),
			'generated_at'           => $generated_at,
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
			'cloud_required'         => false,
			'core_proposal_created'  => false,
			'summary'                => $this->summary( $post_counts, $taxonomies, $signals, $findings ),
			'post_counts'            => $post_counts,
			'taxonomies'             => $taxonomies,
			'page_quality_signals'  => $signals,
			'sampling'               => $sampling,
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
					'method'    => 'publish_date_thirds_systematic',
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

	/** Bounded sample size; filterable, clamped to keep the Cloud payload small. */
	public static function sample_limit(): int {
		$limit = self::SAMPLE_LIMIT_DEFAULT;
		if ( function_exists( 'apply_filters' ) ) {
			$limit = (int) apply_filters( self::SAMPLE_LIMIT_FILTER, $limit );
		}

		return max( self::SAMPLE_LIMIT_MIN, min( self::SAMPLE_LIMIT_MAX, $limit ) );
	}

	/**
	 * All published post/page IDs, oldest publish date first (ids only, no bodies).
	 *
	 * @return array<int,int>
	 */
	private function collect_public_ids_by_date(): array {
		if ( ! function_exists( 'get_posts' ) ) {
			return array();
		}
		$rows = get_posts(
			array(
				'post_type'              => array( 'post', 'page' ),
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'orderby'                => array(
					'date' => 'ASC',
					'ID'   => 'ASC',
				),
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'suppress_filters'       => true,
			)
		);
		$ids  = array();
		foreach ( $rows as $row ) {
			$id = $row instanceof \WP_Post ? (int) $row->ID : (int) $row;
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Splits date-ordered IDs into oldest/middle/newest thirds and picks evenly
	 * spaced IDs from each (deterministic systematic sampling). Pure: no WP calls.
	 *
	 * @param array<int,int> $ids_by_date Published IDs, oldest first.
	 * @return array<string,array{population:int,ids:array<int,int>,first_id:int,last_id:int}>
	 */
	public static function plan_strata( array $ids_by_date, int $limit ): array {
		$ids    = array_values( array_map( 'intval', $ids_by_date ) );
		$total  = count( $ids );
		$keys   = array_keys( self::STRATA );
		$k      = count( $keys );
		$limit  = max( 0, $limit );
		$plan   = array();
		$offset = 0;
		foreach ( $keys as $index => $key ) {
			$size    = intdiv( $total, $k ) + ( $index >= $k - ( $total % $k ) ? 1 : 0 );
			$slice   = array_slice( $ids, $offset, $size );
			$offset += $size;
			// Remainder of the budget goes to the newest strata first.
			$quota  = intdiv( $limit, $k ) + ( $index >= $k - ( $limit % $k ) ? 1 : 0 );
			$quota  = min( $quota, $size );
			$picked = array();
			if ( $quota > 0 ) {
				$step = $size / $quota;
				for ( $i = 0; $i < $quota; $i++ ) {
					$picked[] = $slice[ (int) floor( $i * $step + $step / 2 ) ];
				}
			}
			$plan[ $key ] = array(
				'population' => $size,
				'ids'        => $picked,
				'first_id'   => $size > 0 ? (int) $slice[0] : 0,
				'last_id'    => $size > 0 ? (int) $slice[ $size - 1 ] : 0,
			);
		}

		return $plan;
	}

	/**
	 * Bounded public titles + structure flags only. Body is read locally for word
	 * count and never returned.
	 *
	 * @param array<string,array<string,mixed>> $plan Strata plan.
	 * @return array<int,array<string,mixed>>
	 */
	private function collect_public_sample( array $plan ): array {
		if ( ! function_exists( 'get_posts' ) ) {
			return array();
		}
		$stratum_of = array();
		$lanes      = array();
		foreach ( array_reverse( $plan, true ) as $key => $row ) {
			$lanes[ $key ] = array_values( (array) $row['ids'] );
			foreach ( $lanes[ $key ] as $id ) {
				$stratum_of[ (int) $id ] = (string) $key;
			}
		}
		// Interleave newest/middle/oldest so short prefixes (title samples, refs) span all strata.
		$order = array();
		for ( $i = 0, $max = max( array_map( 'count', $lanes ?: array( array() ) ) ); $i < $max; $i++ ) {
			foreach ( $lanes as $lane ) {
				if ( isset( $lane[ $i ] ) ) {
					$order[] = (int) $lane[ $i ];
				}
			}
		}
		if ( empty( $order ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'post__in'       => $order,
				'orderby'        => 'post__in',
				'posts_per_page' => count( $order ),
				'no_found_rows'  => true,
			)
		);

		$sample = array();
		foreach ( $posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$post_id  = (int) $post->ID;
			$content   = (string) $post->post_content;
			$headings  = $this->heading_counts( $content );
			$sample[]  = array(
				'object_type'              => (string) $post->post_type,
				'object_id'                => $post_id,
				'stratum'                  => $stratum_of[ $post_id ] ?? 'newest',
				'title'                    => wp_strip_all_tags( (string) $post->post_title ),
				'published_at'             => $this->gmt_iso( (string) $post->post_date_gmt ),
				'modified_at'              => $this->gmt_iso( (string) $post->post_modified_gmt ),
				'word_count'               => $this->word_count( $content ),
				'excerpt_present'          => '' !== trim( (string) $post->post_excerpt ),
				'meta_description_present' => '' !== $this->meta_description( $post_id ),
				'featured_image_present'   => function_exists( 'get_post_thumbnail_id' ) && (int) get_post_thumbnail_id( $post_id ) > 0,
				'category_count'           => count( $this->term_names( $post_id, 'category' ) ),
				'tag_count'                => count( $this->term_names( $post_id, 'post_tag' ) ),
				'h1_count'                 => (int) $headings['h1'],
				'h2_count'                 => (int) $headings['h2'],
				'h3_count'                 => (int) $headings['h3'],
				'multiple_h1'              => (int) $headings['h1'] > 1,
				'missing_alt_count'        => $this->missing_alt_count( $post_id, $content ),
			);
		}

		return $sample;
	}

	/**
	 * De-identified sampling metadata: strata, per-stratum population vs sample,
	 * per-stratum sample rates, and population-weighted overall estimates.
	 *
	 * @param array<string,array<string,mixed>> $plan Strata plan.
	 * @param array<int,array<string,mixed>>    $sample Sample rows.
	 * @return array<string,mixed>
	 */
	public static function sampling_summary( array $plan, array $sample ): array {
		$flags    = array(
			'missing_meta_description_rate' => static fn( array $r ): bool => empty( $r['meta_description_present'] ),
			'missing_excerpt_rate'          => static fn( array $r ): bool => empty( $r['excerpt_present'] ),
			'missing_featured_image_rate'   => static fn( array $r ): bool => empty( $r['featured_image_present'] ),
			'thin_content_rate'             => static fn( array $r ): bool => (int) ( $r['word_count'] ?? 0 ) > 0 && (int) ( $r['word_count'] ?? 0 ) < 300,
			'multiple_h1_rate'              => static fn( array $r ): bool => ! empty( $r['multiple_h1'] ),
			'missing_heading_rate'          => static fn( array $r ): bool => ( (int) ( $r['h1_count'] ?? 0 ) + (int) ( $r['h2_count'] ?? 0 ) + (int) ( $r['h3_count'] ?? 0 ) ) <= 0 && (int) ( $r['word_count'] ?? 0 ) >= 300,
			'missing_alt_rate'              => static fn( array $r ): bool => (int) ( $r['missing_alt_count'] ?? 0 ) > 0,
		);
		$strata   = array();
		$weighted = array_fill_keys( array_keys( $flags ), 0.0 );
		$pop_used = 0;
		$total    = 0;
		$sampled  = 0;
		foreach ( $plan as $key => $row ) {
			$rows       = array_values( array_filter( $sample, static fn( array $r ): bool => (string) ( $r['stratum'] ?? '' ) === (string) $key ) );
			$n          = count( $rows );
			$population = (int) ( $row['population'] ?? 0 );
			$total     += $population;
			$sampled   += $n;
			$rates      = array();
			foreach ( $flags as $name => $test ) {
				$hits           = count( array_filter( $rows, $test ) );
				$rates[ $name ] = $n > 0 ? round( $hits / $n, 3 ) : 0.0;
				if ( $n > 0 ) {
					$weighted[ $name ] += $rates[ $name ] * $population;
				}
			}
			if ( $n > 0 ) {
				$pop_used += $population;
			}
			$strata[] = array(
				'id'               => (string) $key,
				'label'            => self::STRATA[ $key ] ?? (string) $key,
				'published_from'   => self::year_of( (int) ( $row['first_id'] ?? 0 ) ),
				'published_to'     => self::year_of( (int) ( $row['last_id'] ?? 0 ) ),
				'population_count' => $population,
				'sample_count'     => $n,
				'sample_rates'     => $rates,
			);
		}
		foreach ( $weighted as $name => $sum ) {
			$weighted[ $name ] = $pop_used > 0 ? round( $sum / $pop_used, 3 ) : 0.0;
		}

		return array(
			'method'            => 'publish_date_thirds_systematic',
			'strata_definition' => '已发布文章和页面按发布时间从早到晚排序，平均分成三批，每批等间隔抽取',
			'strata_count'      => count( $plan ),
			'sample_limit'      => self::sample_limit(),
			'total_published'   => $total,
			'total_sampled'     => $sampled,
			'strata'            => $strata,
			'weighted_rates'    => $weighted,
			'rate_basis'        => 'page_quality_signals rates are raw sample rates; weighted_rates weight each stratum by its population',
		);
	}

	private static function year_of( int $post_id ): string {
		if ( $post_id <= 0 || ! function_exists( 'get_post_field' ) ) {
			return '';
		}
		$date = (string) get_post_field( 'post_date', $post_id );
		if ( 1 !== preg_match( '/^(\d{4})/', $date, $m ) || (int) $m[1] < 1990 ) {
			return ''; // Placeholder or corrupt dates (e.g. 0000-00-00) are not reported.
		}

		return $m[1];
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
	private function page_quality_signals( array $sample, string $generated_at ): array {
		$n               = count( $sample );
		$missing_excerpt = 0;
		$missing_meta    = 0;
		$missing_feat    = 0;
		$thin            = 0;
		$no_category     = 0;
		$no_tag          = 0;
		$multiple_h1     = 0;
		$missing_heading = 0;
		$missing_alt     = 0;
		$missing_alt_sum = 0;
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
			if ( ! empty( $item['multiple_h1'] ) ) {
				++$multiple_h1;
			}
			$heading_total = (int) ( $item['h1_count'] ?? 0 ) + (int) ( $item['h2_count'] ?? 0 ) + (int) ( $item['h3_count'] ?? 0 );
			if ( $heading_total <= 0 && $words >= 300 ) {
				++$missing_heading;
			}
			$alt_gap = (int) ( $item['missing_alt_count'] ?? 0 );
			if ( $alt_gap > 0 ) {
				++$missing_alt;
				$missing_alt_sum += $alt_gap;
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
			'multiple_h1_count'              => $multiple_h1,
			'multiple_h1_rate'               => $rate( $multiple_h1, $n ),
			'missing_heading_count'          => $missing_heading,
			'missing_heading_rate'           => $rate( $missing_heading, $n ),
			'missing_alt_item_count'         => $missing_alt,
			'missing_alt_rate'               => $rate( $missing_alt, $n ),
			'missing_alt_total'              => $missing_alt_sum,
			'word_count_buckets'             => $buckets,
			'age_buckets'                    => $age,
			'title_samples'                  => $title_samples,
			'measurement_kind'               => 'page_completeness_sample_only',
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
				__( 'Prioritize meta descriptions for important pages via the existing metadata workflow — suggestion only.', 'npcink-workflow-toolbox' ),
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

		$multi_h1 = (int) ( $signals['multiple_h1_count'] ?? 0 );
		if ( $multi_h1 > 0 ) {
			$findings[] = $this->finding(
				'multiple_h1_structure',
				__( 'Some pages look like they have more than one top heading', 'npcink-workflow-toolbox' ),
				'heading_structure',
				min( 78, 40 + $multi_h1 * 5 ),
				$multi_h1 >= 3 ? 'medium' : 'low',
				sprintf(
					/* translators: 1: count, 2: sample size. */
					__( '%1$d of %2$d sampled items appear to use more than one H1.', 'npcink-workflow-toolbox' ),
					$multi_h1,
					$n
				),
				__( 'Messy heading levels make pages harder to skim and for search to understand.', 'npcink-workflow-toolbox' ),
				__( 'Open those pages in the editor and keep one clear top heading, with H2/H3 for sections. Manual review only.', 'npcink-workflow-toolbox' ),
				'manual_review_only',
				array()
			);
		}

		$missing_heading = (int) ( $signals['missing_heading_count'] ?? 0 );
		if ( $missing_heading > 0 ) {
			$findings[] = $this->finding(
				'missing_heading_structure',
				__( 'Longer pages are missing clear section headings', 'npcink-workflow-toolbox' ),
				'heading_structure',
				min( 82, 42 + $missing_heading * 5 ),
				$missing_heading >= 3 ? 'medium' : 'low',
				sprintf(
					/* translators: 1: count, 2: sample size. */
					__( '%1$d of %2$d sampled longer items have no H1/H2/H3 headings.', 'npcink-workflow-toolbox' ),
					$missing_heading,
					$n
				),
				__( 'Without section headings, long pages are hard to scan.', 'npcink-workflow-toolbox' ),
				__( 'Add a few short headings that match the sections you already wrote. Suggestion only.', 'npcink-workflow-toolbox' ),
				'manual_review_only',
				array()
			);
		}

		$missing_alt = (int) ( $signals['missing_alt_item_count'] ?? 0 );
		$alt_total   = (int) ( $signals['missing_alt_total'] ?? 0 );
		if ( $missing_alt > 0 ) {
			$findings[] = $this->finding(
				'in_content_missing_alt',
				__( 'Images in sampled pages are missing text descriptions', 'npcink-workflow-toolbox' ),
				'media_accessibility',
				min( 88, 44 + $missing_alt * 5 ),
				$missing_alt >= 4 ? 'high' : 'medium',
				sprintf(
					/* translators: 1: pages with gaps, 2: sample size, 3: total missing alts. */
					__( '%1$d of %2$d sampled items have images without ALT text (%3$d gaps counted).', 'npcink-workflow-toolbox' ),
					$missing_alt,
					$n,
					$alt_total
				),
				__( 'Missing image text hurts accessibility and makes pictures harder to reuse.', 'npcink-workflow-toolbox' ),
				__( 'In the editor or media library, add a short description for each image. Suggestion only.', 'npcink-workflow-toolbox' ),
				'manual_review_only',
				array()
			);
		}

		$stale = (int) ( ( $signals['age_buckets']['180_plus'] ?? 0 ) );
		if ( $stale > 0 ) {
			$findings[] = $this->finding(
				'stale_page_backlog',
				__( 'A share of public pages has not been refreshed in 180+ days', 'npcink-workflow-toolbox' ),
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
			'sampling_method'               => 'publish_date_thirds_systematic',
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


	/**
	 * Count h1/h2/h3 tags in HTML content (heuristic; body never leaves the site).
	 *
	 * @return array{h1:int,h2:int,h3:int}
	 */
	private function heading_counts( string $content ): array {
		$counts = array(
			'h1' => 0,
			'h2' => 0,
			'h3' => 0,
		);
		if ( '' === $content ) {
			return $counts;
		}
		foreach ( array( 'h1', 'h2', 'h3' ) as $tag ) {
			if ( preg_match_all( '/<' . $tag . '\b[^>]*>/i', $content, $m ) ) {
				$counts[ $tag ] = count( $m[0] );
			}
		}

		return $counts;
	}

	/**
	 * Count images in content (and featured image) missing ALT text.
	 */
	private function missing_alt_count( int $post_id, string $content ): int {
		$missing = 0;
		if ( function_exists( 'get_post_thumbnail_id' ) ) {
			$featured_id = (int) get_post_thumbnail_id( $post_id );
			if ( $featured_id > 0 && '' === trim( (string) get_post_meta( $featured_id, '_wp_attachment_image_alt', true ) ) ) {
				++$missing;
			}
		}
		if ( '' === $content || ! preg_match_all( '/<img\b[^>]*>/i', $content, $imgs ) ) {
			return $missing;
		}
		foreach ( $imgs[0] as $img ) {
			if ( preg_match( '/\balt\s*=\s*([\'"])\s*\1/i', $img ) || ! preg_match( '/\balt\s*=/i', $img ) ) {
				++$missing;
			}
		}

		return $missing;
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
