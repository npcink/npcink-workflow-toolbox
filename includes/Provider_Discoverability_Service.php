<?php
/**
 * Content discoverability projection service for the provider client.
 *
 * Resolves the bounded discoverability source (current post or topic brief)
 * and derives the suggestion-only SEO/AEO/GEO field candidates and slug
 * candidate for the content discoverability brief. Suggestion-only: no SEO
 * meta, slug, excerpt, or taxonomy write lives here.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

final class Provider_Discoverability_Service extends Provider_Client_Support {

	public function resolve_discoverability_source( array $input ) {
		$post_id = absint( $input['post_id'] ?? 0 );
		$title   = trim( sanitize_text_field( (string) ( $input['title'] ?? '' ) ) );
		$topic   = trim( sanitize_text_field( (string) ( $input['topic'] ?? '' ) ) );
		$content = trim( $this->bounded_text( (string) ( $input['content'] ?? ( $input['content_markdown'] ?? '' ) ), self::ARTICLE_PLAN_CONTENT_CHARS ) );
		$excerpt = trim( sanitize_textarea_field( (string) ( $input['excerpt'] ?? '' ) ) );

		if ( 0 < $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				return new WP_Error(
					'npcink_toolbox_post_not_found',
					__( 'The requested post was not found.', 'npcink-workflow-toolbox' ),
					array( 'status' => 404 )
				);
			}

			$title   = '' !== $title ? $title : get_the_title( $post );
			$content = '' !== $content ? $content : wp_strip_all_tags( (string) $post->post_content );
			$excerpt = '' !== $excerpt ? $excerpt : wp_strip_all_tags( get_the_excerpt( $post ) );
			$topic   = '' !== $topic ? $topic : $title;

			return array(
				'input_type'      => 'post',
				'post_id'         => $post_id,
				'post_type'       => get_post_type( $post ),
				'post_status'     => get_post_status( $post ),
				'title'           => sanitize_text_field( (string) $title ),
				'topic'           => sanitize_text_field( (string) $topic ),
				'excerpt'         => sanitize_textarea_field( (string) $excerpt ),
				'content_excerpt' => wp_trim_words( wp_strip_all_tags( $content ), 180, '' ),
			);
		}

		if ( '' === $title && '' === $topic ) {
			return new WP_Error(
				'npcink_toolbox_missing_discoverability_source',
				__( 'A post_id, topic, or title is required to build a content discoverability brief.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		if ( '' === $title ) {
			$title = $topic;
		}
		if ( '' === $topic ) {
			$topic = $title;
		}

		return array(
			'input_type'      => 'supplied_context',
			'post_id'         => 0,
			'post_type'       => sanitize_key( (string) ( $input['post_type'] ?? 'post' ) ),
			'post_status'     => sanitize_key( (string) ( $input['post_status'] ?? 'draft' ) ),
			'title'           => $title,
			'topic'           => $topic,
			'excerpt'         => $excerpt,
			'content_excerpt' => wp_trim_words( wp_strip_all_tags( $content ), 180, '' ),
		);
	}


	public function content_discoverability_field_instruction( string $field ): string {
		$instructions = array(
			'seo_title'             => __( 'Suggest a concise search title based on the source topic and primary keywords. Avoid clickbait and unsupported claims.', 'npcink-workflow-toolbox' ),
			'seo_description'       => __( 'Suggest a meta description that summarizes the reader problem, topic, and value using verified source facts only.', 'npcink-workflow-toolbox' ),
			'slug'                  => __( 'Suggest a short, readable URL slug from the title or topic.', 'npcink-workflow-toolbox' ),
			'excerpt'               => __( 'Suggest an editorial excerpt grounded in the supplied content.', 'npcink-workflow-toolbox' ),
			'faq'                   => __( 'Suggest FAQ question and answer pairs only when the context allows FAQ generation and the source supports the answers.', 'npcink-workflow-toolbox' ),
			'answer_summary'        => __( 'Suggest a direct one-sentence AEO answer summary grounded in the supplied source.', 'npcink-workflow-toolbox' ),
			'geo_summary'           => __( 'Suggest a standalone GEO summary that is easy for AI systems to quote without adding unsupported facts.', 'npcink-workflow-toolbox' ),
			'structured_data_hints' => __( 'Suggest schema hints only when the source supports them; do not claim schema has been applied.', 'npcink-workflow-toolbox' ),
		);

		return $instructions[ $field ] ?? __( 'Suggest a reviewable content improvement grounded in the supplied source.', 'npcink-workflow-toolbox' );
	}


	public function content_discoverability_field_group( string $field ): string {
		if ( in_array( $field, array( 'faq', 'answer_summary' ), true ) ) {
			return 'aeo';
		}

		if ( in_array( $field, array( 'geo_summary', 'structured_data_hints' ), true ) ) {
			return 'geo';
		}

		return 'seo';
	}


	public function content_discoverability_candidate( string $field, array $source, array $context ) {
		$title   = sanitize_text_field( (string) ( $source['title'] ?? $source['topic'] ?? '' ) );
		$topic   = sanitize_text_field( (string) ( $source['topic'] ?? $title ) );
		$content = sanitize_textarea_field( (string) ( $source['content_excerpt'] ?? '' ) );
		$excerpt = sanitize_textarea_field( (string) ( $source['excerpt'] ?? '' ) );
		$text    = '' !== $excerpt ? $excerpt : $content;

		if ( '' === $text ) {
			$text = $topic;
		}

		if ( 'seo_title' === $field ) {
			return wp_trim_words( $title, 12, '' );
		}
		if ( 'seo_description' === $field ) {
			return wp_trim_words( wp_strip_all_tags( $text ), 26, '' );
		}
		if ( 'slug' === $field ) {
			return $this->content_discoverability_slug_candidate( $title, $topic, $text );
		}
		if ( 'excerpt' === $field ) {
			return wp_trim_words( wp_strip_all_tags( $text ), 36, '' );
		}
		if ( 'answer_summary' === $field && ! empty( $context['rules']['allow_aeo_summary'] ) ) {
			return wp_trim_words( wp_strip_all_tags( $text ), 28, '' );
		}
		if ( 'geo_summary' === $field && ! empty( $context['rules']['allow_geo_summary'] ) ) {
			return wp_trim_words( wp_strip_all_tags( $text ), 42, '' );
		}
		if ( 'faq' === $field && ! empty( $context['rules']['allow_faq_generation'] ) ) {
			return array(
				array(
					'question' => sprintf(
						/* translators: %s: topic. */
						__( 'What should readers know about %s?', 'npcink-workflow-toolbox' ),
						$topic
					),
					'answer_guidance' => __( 'Answer only with facts supported by the supplied source and site context.', 'npcink-workflow-toolbox' ),
				),
				array(
					'question' => sprintf(
						/* translators: %s: topic. */
						__( 'How does %s affect the target audience?', 'npcink-workflow-toolbox' ),
						$topic
					),
					'answer_guidance' => __( 'Connect the answer to target audience needs without inventing outcomes or guarantees.', 'npcink-workflow-toolbox' ),
				),
			);
		}
		if ( 'structured_data_hints' === $field && ! empty( $context['rules']['allow_structured_data_suggestions'] ) ) {
			return array(
				'Article',
				! empty( $context['rules']['allow_faq_generation'] ) ? 'FAQPage candidate if final FAQ answers are verified' : 'FAQPage disabled by context',
			);
		}

		return null;
	}


	public function content_discoverability_slug_candidate( string $title, string $topic, string $text ): string {
		$source = remove_accents( $title . ' ' . $topic . ' ' . wp_trim_words( wp_strip_all_tags( $text ), 12, '' ) );
		preg_match_all( '/[a-z0-9]+/i', strtolower( $source ), $matches );
		$tokens = array();
		foreach ( $matches[0] ?? array() as $token ) {
			$token = sanitize_key( $token );
			if ( '' === $token || in_array( $token, array( 'the', 'and', 'for', 'with', 'about' ), true ) ) {
				continue;
			}
			if ( ! in_array( $token, $tokens, true ) ) {
				$tokens[] = $token;
			}
			if ( 6 <= count( $tokens ) ) {
				break;
			}
		}
		if ( ! empty( $tokens ) ) {
			return implode( '-', $tokens );
		}

		return sanitize_title( $title );
	}
}
