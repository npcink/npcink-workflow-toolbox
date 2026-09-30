<?php
/**
 * Bounded content collector service for the provider client.
 *
 * Owns the read-only local snapshot collectors: hosted AI post and site
 * snapshots, current-article and media-library ALT metadata snapshots, and
 * the bounded public Site Knowledge sync manifest documents and comments.
 * Collection is read-only with byte budgets; no indexing lifecycle, queue,
 * or WordPress write lives here.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

final class Provider_Content_Collector_Service extends Provider_Client_Support {
	private Provider_Client $client;

	public function __construct( Settings $settings, Provider_Client $client ) {
		parent::__construct( $settings );
		$this->client = $client;
	}

	public function collect_hosted_ai_post_context( int $post_id ): array {
		if ( 0 >= $post_id || ! function_exists( 'get_post' ) ) {
			return array();
		}

		$post = get_post( $post_id );
		if ( ! is_object( $post ) ) {
			return array();
		}

		$content = wp_strip_all_tags( (string) ( $post->post_content ?? '' ) );
		$terms   = array();
		if ( function_exists( 'get_the_terms' ) ) {
			foreach ( array( 'category', 'post_tag' ) as $taxonomy ) {
				$items = get_the_terms( $post_id, $taxonomy );
				if ( is_wp_error( $items ) || ! is_array( $items ) ) {
					continue;
				}
				foreach ( $items as $term ) {
					$terms[] = sanitize_text_field( (string) ( $term->name ?? '' ) );
				}
			}
		}

		$thumbnail_id = function_exists( 'get_post_thumbnail_id' ) ? absint( get_post_thumbnail_id( $post_id ) ) : 0;

		return array(
			'post_id'             => $post_id,
			'post_type'           => function_exists( 'get_post_type' ) ? sanitize_key( (string) get_post_type( $post_id ) ) : sanitize_key( (string) ( $post->post_type ?? '' ) ),
			'post_status'         => function_exists( 'get_post_status' ) ? sanitize_key( (string) get_post_status( $post_id ) ) : sanitize_key( (string) ( $post->post_status ?? '' ) ),
			'title'               => function_exists( 'get_the_title' ) ? sanitize_text_field( (string) get_the_title( $post_id ) ) : sanitize_text_field( (string) ( $post->post_title ?? '' ) ),
			'url'                 => function_exists( 'get_permalink' ) ? esc_url_raw( (string) get_permalink( $post_id ) ) : '',
			'excerpt'             => function_exists( 'get_the_excerpt' ) ? sanitize_textarea_field( (string) wp_strip_all_tags( get_the_excerpt( $post ) ) ) : '',
			'content_excerpt'     => sanitize_textarea_field( wp_trim_words( $content, 180, '' ) ),
			'terms'               => array_values( array_filter( array_unique( $terms ) ) ),
			'featured_image_id'   => $thumbnail_id,
			'featured_image_alt'  => $thumbnail_id && function_exists( 'get_post_meta' ) ? sanitize_text_field( (string) get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) ) : '',
			'modified_gmt'        => sanitize_text_field( (string) ( $post->post_modified_gmt ?? '' ) ),
			'operator_reviewable' => true,
		);
	}


	public function collect_hosted_ai_site_snapshot(): array {
		$query_defaults = array(
			'post_type'           => array( 'post', 'page' ),
			'post_status'         => 'publish',
			'posts_per_page'      => 6,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);
		$items_by_id    = array();
		$append_posts   = function ( array $posts, string $sample_group, string $sample_reason ) use ( &$items_by_id ): void {
			foreach ( $posts as $post ) {
				if ( ! is_object( $post ) ) {
					continue;
				}
				$post_id = absint( $post->ID ?? 0 );
				if ( 0 >= $post_id ) {
					continue;
				}

				if ( isset( $items_by_id[ $post_id ] ) ) {
					$items_by_id[ $post_id ]['sample_groups'][]  = $sample_group;
					$items_by_id[ $post_id ]['sample_reasons'][] = $sample_reason;
					$items_by_id[ $post_id ]['sample_groups']    = array_values( array_unique( $items_by_id[ $post_id ]['sample_groups'] ) );
					$items_by_id[ $post_id ]['sample_reasons']   = array_values( array_unique( $items_by_id[ $post_id ]['sample_reasons'] ) );
					continue;
				}

				$content = wp_strip_all_tags( (string) ( $post->post_content ?? '' ) );
				$excerpt = function_exists( 'get_the_excerpt' ) ? wp_strip_all_tags( (string) get_the_excerpt( $post ) ) : '';
				$items_by_id[ $post_id ] = array(
					'post_id'            => $post_id,
					'post_type'          => function_exists( 'get_post_type' ) ? sanitize_key( (string) get_post_type( $post_id ) ) : sanitize_key( (string) ( $post->post_type ?? '' ) ),
					'title'              => function_exists( 'get_the_title' ) ? sanitize_text_field( (string) get_the_title( $post_id ) ) : sanitize_text_field( (string) ( $post->post_title ?? '' ) ),
					'url'                => function_exists( 'get_permalink' ) ? esc_url_raw( (string) get_permalink( $post_id ) ) : '',
					'excerpt'            => sanitize_textarea_field( (string) $excerpt ),
					'content_excerpt'    => sanitize_textarea_field( wp_trim_words( $content, 90, '' ) ),
					'word_count_approx'  => str_word_count( wp_strip_all_tags( $content ) ),
					'modified_gmt'       => sanitize_text_field( (string) ( $post->post_modified_gmt ?? '' ) ),
					'published_gmt'      => sanitize_text_field( (string) ( $post->post_date_gmt ?? '' ) ),
					'has_featured_image' => function_exists( 'has_post_thumbnail' ) ? (bool) has_post_thumbnail( $post_id ) : false,
					'sample_groups'      => array( $sample_group ),
					'sample_reasons'     => array( $sample_reason ),
				);
			}
		};

		if ( function_exists( 'get_posts' ) ) {
			$append_posts(
				get_posts(
					array_merge(
						$query_defaults,
						array(
							'orderby' => 'modified',
							'order'   => 'DESC',
						)
					)
				),
				'recently_updated',
				'recent public content that may need follow-up or internal links'
			);
			$append_posts(
				get_posts(
					array_merge(
						$query_defaults,
						array(
							'orderby' => 'modified',
							'order'   => 'ASC',
						)
					)
				),
				'older_content',
				'older public content that may need refresh or consolidation'
			);
			$missing_featured_image_posts = function_exists( 'has_post_thumbnail' )
				? array_slice(
					array_values(
						array_filter(
							get_posts(
								array_merge(
									$query_defaults,
									array(
										'posts_per_page' => 18,
										'orderby'        => 'modified',
										'order'          => 'DESC',
									)
								)
							),
							static function ( $post ): bool {
								$post_id = is_object( $post ) ? absint( $post->ID ?? 0 ) : 0;
								return 0 < $post_id && ! has_post_thumbnail( $post_id );
							}
						)
					),
					0,
					6
				)
				: array();
			$append_posts(
				$missing_featured_image_posts,
				'missing_featured_image',
				'public content without a featured image candidate'
			);
		}

		$items = array_values( $items_by_id );
		$items_in_group = static function ( array $sample_items, string $group ): array {
			return array_values(
				array_filter(
					$sample_items,
					static function ( array $item ) use ( $group ): bool {
						return in_array( $group, (array) ( $item['sample_groups'] ?? array() ), true );
					}
				)
			);
		};

		$counts = array();
		if ( function_exists( 'wp_count_posts' ) ) {
			foreach ( array( 'post', 'page' ) as $post_type ) {
				$count = wp_count_posts( $post_type );
				$counts[ $post_type ] = array(
					'publish' => absint( $count->publish ?? 0 ),
					'draft'   => absint( $count->draft ?? 0 ),
					'future'  => absint( $count->future ?? 0 ),
				);
			}
		}

		$terms = array();
		if ( function_exists( 'get_terms' ) ) {
			$term_items = get_terms(
				array(
					'taxonomy'   => array( 'category', 'post_tag' ),
					'hide_empty' => true,
					'number'     => 12,
					'orderby'    => 'count',
					'order'      => 'DESC',
				)
			);
			if ( ! is_wp_error( $term_items ) && is_array( $term_items ) ) {
				foreach ( $term_items as $term ) {
					$terms[] = array(
						'name'     => sanitize_text_field( (string) ( $term->name ?? '' ) ),
						'taxonomy' => sanitize_key( (string) ( $term->taxonomy ?? '' ) ),
						'count'    => absint( $term->count ?? 0 ),
					);
				}
			}
		}

		return array(
			'site_name'       => function_exists( 'get_bloginfo' ) ? sanitize_text_field( (string) get_bloginfo( 'name' ) ) : '',
			'tagline'         => function_exists( 'get_bloginfo' ) ? sanitize_text_field( (string) get_bloginfo( 'description' ) ) : '',
			'home_url'        => function_exists( 'home_url' ) ? esc_url_raw( (string) home_url( '/' ) ) : '',
			'post_counts'     => $counts,
			'top_terms'       => $terms,
			'content_samples' => $items,
			'recent_content'  => $items_in_group( $items, 'recently_updated' ),
			'older_content'   => $items_in_group( $items, 'older_content' ),
			'missing_featured_image_content' => $items_in_group( $items, 'missing_featured_image' ),
			'sample_summary'  => array(
				'total_unique_content_items'       => count( $items ),
				'recent_content_count'             => count( $items_in_group( $items, 'recently_updated' ) ),
				'older_content_count'              => count( $items_in_group( $items, 'older_content' ) ),
				'missing_featured_image_count'     => count( $items_in_group( $items, 'missing_featured_image' ) ),
				'top_term_count'                   => count( $terms ),
			),
			'snapshot_policy' => 'bounded_public_content_opportunity_sample_only',
		);
	}


	public function collect_hosted_ai_current_article_media_alt_snapshot( int $post_id, int $limit ): array {
		$items = array();
		$seen  = array();
		$post  = $post_id > 0 && function_exists( 'get_post' ) ? get_post( $post_id ) : null;

		if ( $post && function_exists( 'get_post_thumbnail_id' ) ) {
			$thumbnail_id = absint( get_post_thumbnail_id( $post_id ) );
			if ( $thumbnail_id > 0 ) {
				$item = $this->client->hosted_ai_media_alt_snapshot_item( $thumbnail_id, 'featured_media' );
				if ( ! empty( $item ) ) {
					$items[] = $item;
					$seen[]  = $thumbnail_id;
				}
			}
		}

		$content = $post ? (string) ( $post->post_content ?? '' ) : '';
		foreach ( $this->client->hosted_ai_content_image_attachment_ids( $content ) as $attachment_id ) {
			if ( in_array( $attachment_id, $seen, true ) ) {
				continue;
			}
			$item = $this->client->hosted_ai_media_alt_snapshot_item( $attachment_id, 'content_image' );
			if ( empty( $item ) ) {
				continue;
			}
			$items[] = $item;
			$seen[]  = $attachment_id;
			if ( count( $items ) >= max( 1, $limit ) ) {
				break;
			}
		}

		$items       = array_slice( $items, 0, max( 1, $limit ) );
		$missing_alt = count(
			array_filter(
				$items,
				static function ( array $item ): bool {
					return ! empty( $item['missing_alt'] );
				}
			)
		);

		return array(
			'sample_size'       => count( $items ),
			'missing_alt_count' => $missing_alt,
			'items'             => $items,
			'snapshot_policy'   => 'current_article_media_metadata_only',
			'media_scope'       => 'current_article_used_images',
			'media_filter'      => 'missing_or_weak_alt',
			'post_context'      => array(
				'post_id' => $post_id,
				'title'   => $post ? sanitize_text_field( (string) ( $post->post_title ?? '' ) ) : '',
				'status'  => $post ? sanitize_key( (string) ( $post->post_status ?? '' ) ) : '',
			),
		);
	}


	public function collect_hosted_ai_media_alt_snapshot( int $limit, string $filter = 'missing_or_weak_alt' ): array {
		if ( ! in_array( $filter, array( 'missing_or_weak_alt', 'missing_alt', 'all_recent' ), true ) ) {
			$filter = 'missing_or_weak_alt';
		}
		$attachments = function_exists( 'get_posts' ) ? get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image',
				'posts_per_page' => max( 1, min( 60, $limit * 4 ) ),
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		) : array();

		$items       = array();
		$missing_alt = 0;
		$weak_alt    = 0;
		foreach ( is_array( $attachments ) ? $attachments : array() as $attachment ) {
			if ( ! is_object( $attachment ) ) {
				continue;
			}
			$attachment_id = absint( $attachment->ID ?? 0 );
			if ( 0 >= $attachment_id ) {
				continue;
			}
			$item = $this->client->hosted_ai_media_alt_snapshot_item( $attachment_id, 'media_library_sample' );
			if ( empty( $item ) ) {
				continue;
			}
			if ( ! empty( $item['missing_alt'] ) ) {
				++$missing_alt;
			}
			$is_weak_alt = ! empty( $item['missing_alt'] )
				|| $this->client->media_alt_caption_candidate_is_too_short( (string) ( $item['alt'] ?? '' ) )
				|| $this->client->media_alt_caption_is_filename_like( (string) ( $item['alt'] ?? '' ), $item );
			if ( $is_weak_alt ) {
				++$weak_alt;
			}
			if ( 'missing_alt' === $filter && empty( $item['missing_alt'] ) ) {
				continue;
			}
			if ( 'missing_or_weak_alt' === $filter && ! $is_weak_alt ) {
				continue;
			}
			$items[] = $item;
			if ( count( $items ) >= $limit ) {
				break;
			}
		}

		return array(
			'sample_size'       => count( $items ),
			'missing_alt_count' => $missing_alt,
			'weak_alt_count'    => $weak_alt,
			'items'             => array_slice( $items, 0, $limit ),
			'snapshot_policy'   => 'media_library_metadata_sample_only',
			'media_scope'       => 'media_library_sample',
			'media_filter'      => $filter,
		);
	}


	public function collect_hosted_ai_selected_media_alt_snapshot( array $attachment_ids, int $limit, string $filter = 'missing_or_weak_alt' ): array {
		if ( ! in_array( $filter, array( 'missing_or_weak_alt', 'missing_alt', 'all_recent' ), true ) ) {
			$filter = 'missing_or_weak_alt';
		}

		$items       = array();
		$missing_alt = 0;
		$weak_alt    = 0;
		foreach ( array_slice( $attachment_ids, 0, max( 1, min( 50, $limit * 4 ) ) ) as $attachment_id ) {
			$item = $this->client->hosted_ai_media_alt_snapshot_item( absint( $attachment_id ), 'selected_media_library_image' );
			if ( empty( $item ) ) {
				continue;
			}
			if ( ! empty( $item['missing_alt'] ) ) {
				++$missing_alt;
			}
			$is_weak_alt = ! empty( $item['missing_alt'] )
				|| $this->client->media_alt_caption_candidate_is_too_short( (string) ( $item['alt'] ?? '' ) )
				|| $this->client->media_alt_caption_is_filename_like( (string) ( $item['alt'] ?? '' ), $item );
			if ( $is_weak_alt ) {
				++$weak_alt;
			}
			if ( 'missing_alt' === $filter && empty( $item['missing_alt'] ) ) {
				continue;
			}
			if ( 'missing_or_weak_alt' === $filter && ! $is_weak_alt ) {
				continue;
			}
			$items[] = $item;
			if ( count( $items ) >= $limit ) {
				break;
			}
		}

		return array(
			'sample_size'       => count( $items ),
			'missing_alt_count' => $missing_alt,
			'weak_alt_count'    => $weak_alt,
			'items'             => array_slice( $items, 0, $limit ),
			'snapshot_policy'   => 'selected_media_library_metadata_only',
			'media_scope'       => 'selected_media_library_images',
			'media_filter'      => $filter,
			'attachment_ids'    => array_values( array_slice( $attachment_ids, 0, 50 ) ),
		);
	}


	public function collect_site_knowledge_documents( array $post_ids, int $max_posts ): array {
		if ( ! function_exists( 'get_posts' ) ) {
			return array();
		}

		$args = array(
			'post_type'      => $this->site_knowledge_post_types(),
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, min( 50, $max_posts ) ),
			'orderby'        => 'modified',
			'order'          => 'DESC',
		);

		if ( array() !== $post_ids ) {
			$args['post__in'] = $post_ids;
			$args['orderby']  = 'post__in';
		}

		$posts = get_posts( $args );
		if ( ! is_array( $posts ) ) {
			return array();
		}

		$documents = array();
		$indexed_post_ids = array();
		$remaining_bytes  = self::SITE_KNOWLEDGE_SYNC_MAX_BYTES;
		foreach ( $posts as $post ) {
			if ( ! is_object( $post ) ) {
				continue;
			}

			$post_id = absint( $post->ID ?? 0 );
			if ( 0 >= $post_id ) {
				continue;
			}

			$indexed_post_ids[] = $post_id;
			$content = wp_strip_all_tags( (string) ( $post->post_content ?? '' ) );
			$excerpt = function_exists( 'get_the_excerpt' ) ? wp_strip_all_tags( get_the_excerpt( $post ) ) : '';
			$document = array(
				'post_id'         => $post_id,
				'post_type'       => function_exists( 'get_post_type' ) ? sanitize_key( (string) get_post_type( $post ) ) : '',
				'post_status'     => function_exists( 'get_post_status' ) ? sanitize_key( (string) get_post_status( $post ) ) : 'publish',
				'title'           => function_exists( 'get_the_title' ) ? sanitize_text_field( (string) get_the_title( $post ) ) : '',
				'url'             => function_exists( 'get_permalink' ) ? esc_url_raw( (string) get_permalink( $post ) ) : '',
				'modified_gmt'    => sanitize_text_field( (string) ( $post->post_modified_gmt ?? '' ) ),
				'excerpt'         => sanitize_textarea_field( (string) $excerpt ),
				'content_excerpt' => $this->trim_site_knowledge_content( $content ),
				'content_hash'    => md5( $content ),
			);
			if ( ! $this->append_site_knowledge_document( $documents, $document, $remaining_bytes ) ) {
				break;
			}
		}

		if ( array() !== $indexed_post_ids && $remaining_bytes > 0 ) {
			$documents = array_merge(
				$documents,
				$this->collect_site_knowledge_comments(
					array_values( array_unique( $indexed_post_ids ) ),
					max( 1, min( 100, max( 1, $max_posts ) * 3 ) ),
					$remaining_bytes
				)
			);
		}

		return $documents;
	}


	private function append_site_knowledge_document( array &$documents, array $document, int &$remaining_bytes ): bool {
		$encoded = wp_json_encode( $document );
		$bytes   = is_string( $encoded ) ? strlen( $encoded ) : 0;
		if ( $bytes <= 0 || $bytes > $remaining_bytes ) {
			return false;
		}

		$documents[] = $document;
		$remaining_bytes -= $bytes;
		return true;
	}


	public function site_knowledge_post_types(): array {
		$post_types = apply_filters( 'npcink_toolbox_site_knowledge_post_types', array( 'post', 'page' ) );
		if ( ! is_array( $post_types ) ) {
			$post_types = array( 'post', 'page' );
		}

		$post_types = array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_key', $post_types ),
					static fn( string $post_type ): bool => '' !== $post_type && 'attachment' !== $post_type
				)
			)
		);

		return array() === $post_types ? array( 'post', 'page' ) : $post_types;
	}


	private function trim_site_knowledge_content( string $content ): string {
		$content = trim( preg_replace( '/\s+/', ' ', $content ) ?? $content );
		if ( '' === $content ) {
			return '';
		}

		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			if ( self::SITE_KNOWLEDGE_CONTENT_CHARS >= mb_strlen( $content ) ) {
				return sanitize_textarea_field( $content );
			}
			return sanitize_textarea_field( mb_substr( $content, 0, self::SITE_KNOWLEDGE_CONTENT_CHARS ) );
		}

		if ( self::SITE_KNOWLEDGE_CONTENT_CHARS >= strlen( $content ) ) {
			return sanitize_textarea_field( $content );
		}
		return sanitize_textarea_field( substr( $content, 0, self::SITE_KNOWLEDGE_CONTENT_CHARS ) );
	}


	private function collect_site_knowledge_comments( array $post_ids, int $max_comments, int &$remaining_bytes ): array {
		if ( array() === $post_ids || ! function_exists( 'get_comments' ) ) {
			return array();
		}

		$comments = get_comments(
			array(
				'post__in' => array_values( array_unique( array_map( 'absint', $post_ids ) ) ),
				'status'   => 'approve',
				'type'     => 'comment',
				'number'   => max( 1, min( 100, $max_comments ) ),
				'orderby'  => 'comment_date_gmt',
				'order'    => 'DESC',
			)
		);
		if ( ! is_array( $comments ) ) {
			return array();
		}

		$documents = array();
		foreach ( $comments as $comment ) {
			if ( ! is_object( $comment ) ) {
				continue;
			}

			$comment_id = absint( $comment->comment_ID ?? 0 );
			$post_id    = absint( $comment->comment_post_ID ?? 0 );
			if ( 0 >= $comment_id || 0 >= $post_id || ! in_array( $post_id, $post_ids, true ) ) {
				continue;
			}

			$content = wp_strip_all_tags( (string) ( $comment->comment_content ?? '' ) );
			if ( '' === trim( $content ) ) {
				continue;
			}

			$document = array(
				'comment_id'      => $comment_id,
				'post_id'         => $post_id,
				'comment_status'  => 'approve',
				'created_gmt'     => sanitize_text_field( (string) ( $comment->comment_date_gmt ?? '' ) ),
				'url'             => function_exists( 'get_comment_link' ) ? esc_url_raw( (string) get_comment_link( $comment ) ) : '',
				'content_excerpt' => wp_trim_words( $content, 280, '' ),
				'content_hash'    => md5( $content ),
			);
			if ( ! $this->append_site_knowledge_document( $documents, $document, $remaining_bytes ) ) {
				break;
			}
		}

		return $documents;
	}

	/**
	 * Bounded pending-comment sample for the Cloud comment moderation review.
	 *
	 * Collects only approved-would-be-public fields: comment content truncated
	 * to 2000 characters, author display name, author URL, and a bounded parent
	 * post title. Comment author email, IP address, and user agent are never
	 * collected or forwarded.
	 */
	public function collect_hosted_ai_comment_moderation_sample( int $limit ): array {
		$limit    = max( 1, min( 50, $limit ) );
		$comments = array();
		if ( function_exists( 'get_comments' ) ) {
			$comments = get_comments(
				array(
					'status'  => 'hold',
					'type'    => 'comment',
					'number'  => $limit,
					'orderby' => 'comment_date_gmt',
					'order'   => 'DESC',
				)
			);
		}

		$items = array();
		foreach ( $comments as $comment ) {
			if ( ! is_object( $comment ) ) {
				continue;
			}
			$comment_id = absint( $comment->comment_ID ?? 0 );
			if ( 0 >= $comment_id ) {
				continue;
			}

			$post_id    = absint( $comment->comment_post_ID ?? 0 );
			$post_title = '';
			if ( $post_id && function_exists( 'get_post_status' ) && 'publish' === (string) get_post_status( $post_id ) && function_exists( 'get_the_title' ) ) {
				// Explicit character bound: wp_trim_words switches to character mode on character-count locales and would truncate mid-word.
				$post_title = sanitize_text_field( (string) get_the_title( $post_id ) );
				$post_title = function_exists( 'mb_substr' ) ? mb_substr( $post_title, 0, 80 ) : substr( $post_title, 0, 80 );
			}
			$content = (string) ( $comment->comment_content ?? '' );
			if ( function_exists( 'mb_substr' ) ) {
				$content = mb_substr( $content, 0, 2000 );
			} else {
				$content = substr( $content, 0, 2000 );
			}

			$items[] = array(
				'comment_id'  => $comment_id,
				'post_id'     => $post_id,
				'post_title'  => $post_title,
				'author_name' => sanitize_text_field( (string) ( $comment->comment_author ?? '' ) ),
				'author_url'  => esc_url_raw( (string) ( $comment->comment_author_url ?? '' ) ),
				'content'     => sanitize_textarea_field( $content ),
				'date_gmt'    => sanitize_text_field( (string) ( $comment->comment_date_gmt ?? '' ) ),
			);
		}

		$pending_total = count( $items );
		if ( function_exists( 'wp_count_comments' ) ) {
			$counts        = wp_count_comments();
			$pending_total = absint( $counts->moderated ?? 0 );
		}

		return array(
			'snapshot_policy' => 'pending_hold_approved_would_be_public_fields_only',
			'sampled_status'  => 'hold',
			'limit'           => $limit,
			'pending_total'   => $pending_total,
			'items'           => $items,
		);
	}
}
