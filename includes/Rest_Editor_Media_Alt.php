<?php
/**
 * Editor media/ALT cluster: the current-article media-item snapshot
 * (request media items, attachment metadata, item sanitization), the
 * contextual image ALT review flow (article-context-first ALT drafting,
 * explicit-consent visual evidence, bounded visual summaries), the
 * visual context normalization, the bounded image-source support query,
 * the Toolkit image-candidate review projection, and the media brief
 * flow handler, moved verbatim from Rest_Editor_Content_Support (editor
 * split session 6). An instance service over the shared flow-cache base
 * because the visual-evidence and media-brief flows close over the
 * Provider_Client facade.
 *
 * Suggestion-only by contract: ALT drafts, image-candidate review
 * artifacts, and media briefs are review rows; nothing here writes
 * posts, attachments, or settings.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_REST_Request;

final class Rest_Editor_Media_Alt extends Rest_Editor_Flow_Cache {

	public function __construct( Provider_Client $client ) {
		$this->client = $client;
	}

	public function editor_media_items_from_request( WP_REST_Request $request ): array {
		$items  = array();
		$seen   = array();
		$intent = sanitize_key( (string) $request->get_param( 'intent' ) );

		$featured_media = absint( $request->get_param( 'featured_media' ) );
		if ( 'image_alt_suggestions' !== $intent && $featured_media > 0 ) {
			$featured = $this->editor_attachment_media_item( $featured_media, 'featured_media' );
			if ( ! empty( $featured ) ) {
				$items[] = $featured;
				$seen[]  = 'id:' . $featured_media;
			}
		}

		$request_items = $request->get_param( 'media_items' );
		if ( is_string( $request_items ) && '' !== trim( $request_items ) ) {
			$decoded       = json_decode( $request_items, true );
			$request_items = is_array( $decoded ) ? $decoded : array();
		}

		foreach ( is_array( $request_items ) ? $request_items : array() as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$media_item = $this->sanitize_editor_media_item( $item );
			if ( empty( $media_item ) ) {
				continue;
			}
			$key = ! empty( $media_item['occurrence_id'] )
				? 'occurrence:' . (string) $media_item['occurrence_id']
				: ( ! empty( $media_item['attachment_id'] )
					? 'id:' . (string) $media_item['attachment_id']
					: 'url:' . (string) ( $media_item['url'] ?? '' ) );
			if ( '' === $key || in_array( $key, $seen, true ) ) {
				continue;
			}
			$items[] = $media_item;
			$seen[]  = $key;
		}

		return $items;
	}

	public function editor_attachment_media_item( int $attachment_id, string $source ): array {
		if ( $attachment_id <= 0
			|| ( function_exists( 'current_user_can' ) && ! current_user_can( 'upload_files' ) )
			|| ( function_exists( 'wp_attachment_is_image' ) && ! wp_attachment_is_image( $attachment_id ) ) ) {
			return array();
		}

		$attachment = function_exists( 'get_post' ) ? get_post( $attachment_id ) : null;
		if ( ! $attachment || 'attachment' !== get_post_type( $attachment ) ) {
			return array();
		}

		$image_src = function_exists( 'wp_get_attachment_image_src' ) ? wp_get_attachment_image_src( $attachment_id, 'thumbnail' ) : false;
		return array(
			'source'        => sanitize_key( $source ),
			'attachment_id' => $attachment_id,
			'title'         => sanitize_text_field( (string) ( $attachment->post_title ?? '' ) ),
			'caption'       => sanitize_textarea_field( (string) ( $attachment->post_excerpt ?? '' ) ),
			'description'   => sanitize_textarea_field( wp_trim_words( wp_strip_all_tags( (string) ( $attachment->post_content ?? '' ) ), 80, '' ) ),
			'mime_type'     => sanitize_text_field( (string) ( $attachment->post_mime_type ?? '' ) ),
			'alt'           => function_exists( 'get_post_meta' ) ? sanitize_text_field( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) : '',
			'missing_alt'   => function_exists( 'get_post_meta' ) ? '' === trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) : true,
			'thumbnail_url' => is_array( $image_src ) ? esc_url_raw( (string) ( $image_src[0] ?? '' ) ) : '',
			'url'           => function_exists( 'wp_get_attachment_url' ) ? esc_url_raw( (string) wp_get_attachment_url( $attachment_id ) ) : '',
		);
	}

	public function editor_article_image_alt_suggestions( array $context ): array {
		$items       = is_array( $context['media_items'] ?? null ) ? $context['media_items'] : array();
		$items       = array_values(
			array_filter(
				$items,
				static fn( $item ): bool => is_array( $item ) && '' !== trim( (string) ( $item['occurrence_id'] ?? '' ) )
			)
		);
		$offset      = max( 0, absint( $context['occurrence_offset'] ?? 0 ) );
		$per_page    = 10;
		$total_items = max( count( $items ), absint( $context['total_occurrence_count'] ?? 0 ) );
		if ( empty( $items ) ) {
			return array(
				'artifact_type'                     => 'current_article_image_alt_suggestions.v1',
				'contract_version'                  => 'current_article_image_alt_context_review.v1',
				'status'                            => 'empty',
				'message'                           => __( 'No image blocks were found in the current article. Add an image block before previewing contextual ALT.', 'npcink-workflow-toolbox' ),
				'write_posture'                     => 'suggestion_only',
				'final_write_path'                  => 'core_proposal_required',
				'direct_wordpress_write'            => false,
				'items'                             => array(),
				'occurrence_offset'                 => $offset,
				'per_page'                          => $per_page,
				'total_occurrence_count'            => $total_items,
				'has_previous_page'                 => $offset > 0,
				'has_next_page'                     => false,
				'visual_recognition_required_count' => 0,
			);
		}

		$visual_evidence                   = $this->editor_article_image_visual_evidence( $items, ! empty( $context['visual_recognition_consent'] ) );
		$review_items                      = array();
		$visual_evidence_used_count        = 0;
		$visual_recognition_required_count = 0;
		foreach ( array_slice( $items, 0, $per_page ) as $index => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$current_alt           = sanitize_text_field( (string) ( $item['alt'] ?? '' ) );
			$attachment_id         = absint( $item['attachment_id'] ?? 0 );
			$item_visual_evidence  = $visual_evidence['items'][ $attachment_id ] ?? array();
			$needs_visual_evidence = $this->editor_article_image_needs_visual_evidence( $item );
			$suggested_alt         = $this->editor_contextual_image_alt_draft( $item, $context, $item_visual_evidence );
			$visual_evidence_used  = ! empty( $item_visual_evidence ) && $needs_visual_evidence;
			if ( $visual_evidence_used ) {
				++$visual_evidence_used_count;
			}
			$visual_evidence_status = 'not_required';
			if ( $needs_visual_evidence ) {
				$visual_evidence_status = $visual_evidence_used ? sanitize_key( (string) ( $item_visual_evidence['evidence_reuse'] ?? 'available' ) ) : 'recognition_required';
				if ( 'recognition_required' === $visual_evidence_status ) {
					++$visual_recognition_required_count;
				}
			}
			$context_data   = array(
				'heading'         => sanitize_text_field( (string) ( $item['context_heading'] ?? '' ) ),
				'before'          => sanitize_textarea_field( (string) ( $item['context_before'] ?? '' ) ),
				'after'           => sanitize_textarea_field( (string) ( $item['context_after'] ?? '' ) ),
				'caption'         => sanitize_textarea_field( (string) ( $item['context_caption'] ?? '' ) ),
				'visual_evidence' => $visual_evidence_used ? $this->editor_article_image_visual_summary( $item_visual_evidence ) : '',
			);
			$review_items[] = array(
				'occurrence_id'            => sanitize_text_field( (string) ( $item['occurrence_id'] ?? 'article-image-' . ( $index + 1 ) ) ),
				'occurrence_index'         => absint( $item['occurrence_index'] ?? ( $index + 1 ) ),
				'block_client_id'          => sanitize_text_field( (string) ( $item['block_client_id'] ?? '' ) ),
				'block_name'               => sanitize_text_field( (string) ( $item['block_name'] ?? '' ) ),
				'apply_supported'          => 'core/image' === (string) ( $item['block_name'] ?? '' ),
				'attachment_id'            => $attachment_id,
				'thumbnail_url'            => esc_url_raw( (string) ( $item['thumbnail_url'] ?? ( $item['url'] ?? '' ) ) ),
				'current_alt'              => $current_alt,
				'suggested_alt'            => $suggested_alt,
				'candidate_review_status'  => '' === $current_alt ? 'contextual_draft' : 'existing_alt_review',
				'candidate_basis'          => array_values( array_filter( array( 'article_context', '' !== $context_data['caption'] ? 'figure_caption' : '', '' !== $context_data['heading'] ? 'nearest_heading' : '', '' !== $context_data['before'] || '' !== $context_data['after'] ? 'adjacent_text' : '', $visual_evidence_used ? 'visual_evidence' : '' ) ) ),
				'context'                  => $context_data,
				'context_fingerprint'      => hash( 'sha256', wp_json_encode( $context_data ) ?: '' ),
				'target_scope'             => 'post_block_alt',
				'needs_human_visual_check' => false,
				'visual_evidence_status'   => $visual_evidence_status,
				'visual_evidence_used'     => $visual_evidence_used,
				'visual_evidence_source'   => $visual_evidence_used ? sanitize_key( (string) ( $item_visual_evidence['source'] ?? 'cloud_or_host_runtime' ) ) : '',
				'decorative'               => ! empty( $item['decorative'] ),
				'decorative_option'        => 'operator_may_choose_empty_alt',
				'write_status'             => 'preview_only_not_submitted',
			);
		}

		return array(
			'artifact_type'                     => 'current_article_image_alt_suggestions.v1',
			'contract_version'                  => 'current_article_image_alt_context_review.v1',
			'composition_role'                  => 'current_article_contextual_alt_review',
			'status'                            => 'ready',
			'runtime_owner'                     => 'npcink-workflow-toolbox',
			'provider_execution'                => ! empty( $context['visual_recognition_consent'] ) && $visual_evidence_used_count > 0 ? 'explicit_visual_recognition' : 'none',
			'source_policy'                     => 'article_context_then_current_fingerprint_cache_then_explicit_recognition',
			'target_scope'                      => 'post_block_alt',
			'post_id'                           => absint( $context['post_id'] ?? 0 ),
			'post_title'                        => sanitize_text_field( (string) ( $context['title'] ?? '' ) ),
			'image_occurrence_count'            => $total_items,
			'page_occurrence_count'             => count( $review_items ),
			'missing_alt_count'                 => count( array_filter( $review_items, static fn( array $item ): bool => '' === (string) ( $item['current_alt'] ?? '' ) ) ),
			'visual_evidence_used_count'        => $visual_evidence_used_count,
			'visual_recognition_required_count' => $visual_recognition_required_count,
			'visual_confirmation_policy'        => 'explicit_operator_action_before_provider_call',
			'items'                             => $review_items,
			'occurrence_offset'                 => $offset,
			'per_page'                          => $per_page,
			'total_occurrence_count'            => $total_items,
			'has_previous_page'                 => $offset > 0,
			'has_next_page'                     => ( $offset + count( $review_items ) ) < $total_items,
			'write_posture'                     => 'suggestion_only',
			'editor_apply_path'                 => 'local_admin_consent_audit_then_editor_state',
			'final_write_path'                  => 'wordpress_editor_save_after_confirmation',
			'proposal_ready'                    => false,
			'proposal_created'                  => false,
			'direct_wordpress_write'            => false,
			'guardrails'                        => array(
				'context_decides_image_purpose',
				'visual_facts_are_used_only_when_article_context_is_insufficient',
				'provider_calls_require_explicit_operator_consent',
				'no_keyword_stuffing',
				'decorative_images_may_use_empty_alt',
				'no_attachment_global_alt_write',
				'no_post_block_write_in_preview',
			),
		);
	}

	public function editor_contextual_image_alt_draft( array $item, array $context, array $visual_evidence = array() ): string {
		$current_alt = sanitize_text_field( (string) ( $item['alt'] ?? '' ) );
		$caption     = sanitize_text_field( (string) ( $item['context_caption'] ?? ( $item['caption'] ?? '' ) ) );
		$heading     = sanitize_text_field( (string) ( $item['context_heading'] ?? '' ) );
		$nearby      = sanitize_text_field( (string) ( $item['context_before'] ?? '' ) );
		if ( '' === $nearby ) {
			$nearby = sanitize_text_field( (string) ( $item['context_after'] ?? '' ) );
		}
		$article_title = sanitize_text_field( (string) ( $context['title'] ?? '' ) );
		$candidates    = array();
		if ( '' !== $caption ) {
			$candidates[] = $caption;
		}
		if ( '' !== $heading && '' !== $nearby ) {
			$candidates[] = false === stripos( $nearby, $heading ) ? $heading . '：' . $nearby : $nearby;
		}
		$candidates[] = $nearby;
		$candidates[] = $heading;
		$candidates[] = $current_alt;
		$candidates[] = $this->editor_article_image_visual_summary( $visual_evidence );
		$candidates[] = $article_title;

		foreach ( $candidates as $candidate ) {
			$candidate = trim( preg_replace( '/\s+/u', ' ', (string) $candidate ) ?: '' );
			if ( '' !== $candidate ) {
				return Rest_Editor_Audio_Text::trim( $candidate, 120 );
			}
		}

		return '';
	}

	public function editor_article_image_needs_visual_evidence( array $item ): bool {
		if ( '' !== trim( sanitize_text_field( (string) ( $item['alt'] ?? '' ) ) ) ) {
			return false;
		}

		foreach ( array( 'context_caption', 'context_heading', 'context_before', 'context_after' ) as $key ) {
			if ( '' !== trim( sanitize_textarea_field( (string) ( $item[ $key ] ?? '' ) ) ) ) {
				return false;
			}
		}

		return absint( $item['attachment_id'] ?? 0 ) > 0
			&& '' !== esc_url_raw( (string) ( $item['url'] ?? ( $item['thumbnail_url'] ?? '' ) ) );
	}

	/**
	 * @param array<int,array<string,mixed>> $items Article image occurrences.
	 * @return array<int,array<string,mixed>> Evidence indexed by attachment id.
	 */

	public function editor_article_image_visual_evidence( array $items, bool $allow_recognition ): array {
		$request_items = array();
		$requested_ids = array();
		foreach ( array_slice( $items, 0, 10 ) as $item ) {
			if ( ! is_array( $item ) || ! $this->editor_article_image_needs_visual_evidence( $item ) ) {
				continue;
			}
			$attachment_id = absint( $item['attachment_id'] ?? 0 );
			if ( isset( $requested_ids[ $attachment_id ] ) ) {
				continue;
			}
			$requested_ids[ $attachment_id ] = true;
			$request_items[]                 = array(
				'attachment_id'            => $attachment_id,
				'title'                    => sanitize_text_field( (string) ( $item['title'] ?? '' ) ),
				'filename'                 => sanitize_file_name( wp_basename( (string) wp_parse_url( (string) ( $item['url'] ?? '' ), PHP_URL_PATH ) ) ),
				'thumbnail_url'            => esc_url_raw( (string) ( $item['thumbnail_url'] ?? '' ) ),
				'url'                      => esc_url_raw( (string) ( $item['url'] ?? '' ) ),
				'mime_type'                => sanitize_text_field( (string) ( $item['mime_type'] ?? '' ) ),
				'current_alt_status'       => 'missing',
				'current_caption_status'   => '' === trim( (string) ( $item['caption'] ?? '' ) ) ? 'missing' : 'present',
				'candidate_quality_flags'  => array( 'article_context_insufficient' ),
				'filtered_candidate_notes' => array(),
			);
			if ( count( $request_items ) >= 10 ) {
				break;
			}
		}

		if ( empty( $request_items ) ) {
			return array();
		}

		$evidence = $this->client->resolve_media_image_context_evidence(
			array(
				'contract_version'           => 'image_context_evidence_request.v1',
				'artifact_type'              => 'image_context_evidence_request',
				'runtime_owner'              => 'cloud_or_host_runtime',
				'write_posture'              => 'suggestion_only',
				'direct_wordpress_write'     => false,
				'proposal_created'           => false,
				'execution_created'          => false,
				'no_local_model'             => true,
				'no_media_write'             => true,
				'source_policy'              => 'local_attachment_current_fingerprint_only',
				'expected_response_contract' => 'image_context_evidence.v1',
				'requested_count'            => count( $request_items ),
				'max_items'                  => count( $request_items ),
				'items'                      => $request_items,
				'operator_next_action'       => $allow_recognition ? 'explicit_visual_recognition_confirmed' : 'review_recognition_required',
			),
			$allow_recognition,
			$allow_recognition ? 'article_alt_review' : '',
			$allow_recognition
		);
		if ( empty( $evidence ) ) {
			return array( 'items' => array() );
		}

		$indexed = array();
		foreach ( (array) ( $evidence['items'] ?? array() ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$attachment_id = absint( $item['attachment_id'] ?? 0 );
			if ( empty( $requested_ids[ $attachment_id ] ) ) {
				continue;
			}
			$summary = $this->editor_article_image_visual_summary( $item );
			if ( '' === $summary ) {
				continue;
			}
			$indexed[ $attachment_id ] = array(
				'source'         => sanitize_key( (string) ( $item['source'] ?? 'cloud_or_host_runtime' ) ),
				'visual_summary' => $summary,
			);
		}

		return array(
			'items'                               => $indexed,
			'recognition_required_attachment_ids' => array_values( array_map( 'absint', (array) ( $evidence['recognition_required_attachment_ids'] ?? array() ) ) ),
		);
	}

	public function editor_article_image_visual_summary( array $evidence ): string {
		$summary = sanitize_text_field( (string) ( $evidence['visual_summary'] ?? ( $evidence['alt_text_basis'] ?? '' ) ) );
		if ( '' === $summary ) {
			$summary = sanitize_text_field( (string) ( $evidence['scene'] ?? '' ) );
		}
		if ( preg_match( '/(?:https?:\/\/|generated\s+by|system\s+prompt|model[_\s-]?id|provider[_\s-]?id|ignore\s+previous)/i', $summary ) ) {
			return '';
		}

		$trimmed = Rest_Editor_Audio_Text::trim( $summary, 120 );
		if ( $trimmed !== $summary && preg_match( '/\s/u', $trimmed ) ) {
			$trimmed = preg_replace( '/\s+\S*$/u', '', $trimmed ) ?: $trimmed;
		}

		return rtrim( $trimmed, " \t\n\r\0\x0B,;:-" );
	}

	public function editor_image_visual_context( array $context, string $query ): array {
		return $this->sanitize_image_visual_context(
			array(
				'manual_query'        => '',
				'fallback_query'      => $query,
				'title'               => (string) ( $context['title'] ?? '' ),
				'excerpt'             => (string) ( $context['excerpt'] ?? '' ),
				'content_summary'     => (string) ( $context['content_text'] ?? '' ),
				'selected_text'       => (string) ( $context['selected_text'] ?? '' ),
				'selected_block_text' => (string) ( $context['selected_block_text'] ?? '' ),
				'selected_block_name' => (string) ( $context['selected_block_name'] ?? '' ),
				'image_mode'          => (string) ( $context['image_mode'] ?? '' ),
				'latency_mode'        => (string) ( $context['latency_mode'] ?? '' ),
			)
		);
	}

	public function editor_image_support_query( array $context ): string {
		$instruction = trim( sanitize_textarea_field( (string) ( $context['user_instruction'] ?? '' ) ) );
		$selection   = trim(
			implode(
				' ',
				array_filter(
					array(
						(string) ( $context['selected_text'] ?? '' ),
						(string) ( $context['selected_block_text'] ?? '' ),
					)
				)
			)
		);
		$seed        = trim(
			implode(
				' ',
				array_filter(
					array(
						$selection,
						$instruction,
						(string) ( $context['title'] ?? '' ),
						(string) ( $context['excerpt'] ?? '' ),
						'' === $selection ? (string) ( $context['content_text'] ?? '' ) : '',
					)
				)
			)
		);

		if ( '' === $seed ) {
			return '';
		}

		$visual_terms = array();
		$lower_seed   = strtolower( $seed );
		$term_map     = array(
			'seo'       => 'search engine optimization',
			'aeo'       => 'answer engine optimization',
			'geo'       => 'generative engine optimization',
			'ai'        => 'artificial intelligence',
			'wordpress' => 'wordpress publishing',
			'content'   => 'content strategy',
			'上下文'       => 'editorial context',
			'文章'        => 'editorial content',
			'段落'        => 'editorial paragraph',
			'事实'        => 'evidence documentation',
			'表达'        => 'clear communication',
			'创作'        => 'creative writing workspace',
			'读者'        => 'reader research',
		);
		foreach ( $term_map as $needle => $visual_term ) {
			$pattern = preg_match( '/^[a-z0-9]+$/', $needle ) ? '/(?<![a-z0-9])' . preg_quote( $needle, '/' ) . '(?![a-z0-9])/' : '/' . preg_quote( $needle, '/' ) . '/u';
			if ( preg_match( $pattern, $lower_seed ) ) {
				$visual_terms[] = $visual_term;
			}
		}

		if ( ! empty( $visual_terms ) ) {
			return wp_trim_words( implode( ' ', array_unique( $visual_terms ) ) . ' digital marketing workspace analytics', 16, '' );
		}

		if ( '' !== $selection ) {
			return wp_trim_words( $selection, 12, '' );
		}

		$title = trim( sanitize_text_field( (string) ( $context['title'] ?? '' ) ) );
		if ( '' !== $title ) {
			return wp_trim_words( $title, 12, '' );
		}

		$excerpt = trim( sanitize_textarea_field( (string) ( $context['excerpt'] ?? '' ) ) );
		if ( '' !== $excerpt ) {
			return wp_trim_words( $excerpt, 12, '' );
		}

		return wp_trim_words( wp_strip_all_tags( (string) ( $context['content_text'] ?? '' ) ), 12, '' );
	}

	public function editor_image_recommendation_section( array $section ): array {
		$items        = $this->editor_image_candidate_items( $section );
		$target_field = 'paragraph_image' === sanitize_key( (string) ( $section['image_mode'] ?? $section['recommended_use'] ?? '' ) ) ? 'paragraph_image' : 'featured_image';
		$result       = $this->editor_toolkit_image_candidate_review_artifact(
			array(
				'image_candidates' => array_slice( $items, 0, 12 ),
				'target_field'     => $target_field,
				'candidate_limit'  => 8,
			)
		);
		if ( is_wp_error( $result ) ) {
			$review_artifact = $this->empty_toolkit_image_candidate_review_artifact( $result );
		} else {
			$data            = is_array( $result['data'] ?? null ) ? $result['data'] : $result;
			$review_artifact = is_array( $data ) ? $data : $this->empty_toolkit_image_candidate_review_artifact(
				new WP_Error(
					'npcink_toolbox_image_candidate_review_toolkit_invalid_artifact',
					__( 'The Toolkit image candidate review ability returned an invalid artifact.', 'npcink-workflow-toolbox' ),
					array( 'status' => 500 )
				)
			);
		}

		$section['image_candidate_review']    = $review_artifact;
		$section['image_candidate_contract']  = 'image_candidate.v1';
		$section['candidate_contract']        = 'recommendation_candidate.v1';
		$section['source_ability_id']         = 'npcink-abilities-toolkit/build-image-candidate-review-artifact';
		$section['recommendation_candidates'] = is_array( $review_artifact['recommendation_candidates'] ?? null ) ? $review_artifact['recommendation_candidates'] : array();

		return $section;
	}

	public function editor_image_candidate_items( array $section ): array {
		foreach ( array( 'image_candidates', 'images', 'image_source_candidates', 'source_candidates', 'media_candidates', 'assets', 'candidates' ) as $key ) {
			if ( is_array( $section[ $key ] ?? null ) ) {
				return array_values( array_filter( $section[ $key ], 'is_array' ) );
			}
		}

		return array();
	}

	public function editor_toolkit_image_candidate_review_artifact( array $input ) {
		$ability_id = 'npcink-abilities-toolkit/build-image-candidate-review-artifact';
		if ( ! function_exists( 'npcink_abilities_toolkit_get_registered' ) ) {
			return new WP_Error(
				'npcink_toolbox_image_candidate_review_toolkit_unavailable',
				__( 'Npcink Abilities Toolkit is required to build image candidate review artifacts.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$registered = npcink_abilities_toolkit_get_registered();
		$definition = is_array( $registered[ $ability_id ] ?? null ) ? $registered[ $ability_id ] : array();
		$callback   = $definition['execute_callback'] ?? null;
		if ( ! is_callable( $callback ) ) {
			return new WP_Error(
				'npcink_toolbox_image_candidate_review_toolkit_unavailable',
				__( 'The Toolkit image candidate review ability is not currently callable.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$result = call_user_func( $callback, $input );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! is_array( $result ) ) {
			return new WP_Error(
				'npcink_toolbox_image_candidate_review_toolkit_invalid_response',
				__( 'The Toolkit image candidate review ability returned an invalid response.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}

		return $result;
	}

	public function media_brief( WP_REST_Request $request ) {
		$post_id = absint( $request->get_param( 'post_id' ) );
		if ( 0 === $post_id ) {
			return new WP_Error(
				'npcink_toolbox_missing_post_id',
				__( 'A post_id is required for the media brief flow.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error(
				'npcink_toolbox_post_not_found',
				__( 'The requested post was not found.', 'npcink-workflow-toolbox' ),
				array( 'status' => 404 )
			);
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error(
				'npcink_toolbox_post_forbidden',
				__( 'You are not allowed to use this post for the media brief flow.', 'npcink-workflow-toolbox' ),
				array( 'status' => 403 )
			);
		}

		$context = wp_json_encode(
			array(
				'id'      => $post_id,
				'title'   => get_the_title( $post ),
				'type'    => get_post_type( $post ),
				'status'  => get_post_status( $post ),
				'excerpt' => wp_strip_all_tags( get_the_excerpt( $post ) ),
				'content' => wp_trim_words( wp_strip_all_tags( (string) $post->post_content ), 350 ),
			),
			JSON_PRETTY_PRINT
		);

		return rest_ensure_response(
			$this->client->build_media_brief(
				(string) $context,
				array(
					'refresh_variant' => sanitize_text_field( (string) ( $request->get_param( 'refresh_variant' ) ?: '' ) ),
					'image_mode'      => sanitize_key( (string) ( $request->get_param( 'image_mode' ) ?: 'featured_image' ) ),
				)
			)
		);
	}

	public function sanitize_editor_media_item( array $item ): array {
		$attachment_id     = absint( $item['attachment_id'] ?? ( $item['id'] ?? 0 ) );
		$occurrence_id     = sanitize_text_field( (string) ( $item['occurrence_id'] ?? '' ) );
		$occurrence_fields = array(
			'occurrence_id'    => $occurrence_id,
			'block_client_id'  => sanitize_text_field( (string) ( $item['block_client_id'] ?? '' ) ),
			'block_name'       => sanitize_text_field( (string) ( $item['block_name'] ?? '' ) ),
			'occurrence_index' => absint( $item['occurrence_index'] ?? 0 ),
			'context_heading'  => sanitize_text_field( (string) ( $item['context_heading'] ?? '' ) ),
			'context_before'   => sanitize_textarea_field( Rest_Editor_Audio_Text::trim( (string) ( $item['context_before'] ?? '' ), 240 ) ),
			'context_after'    => sanitize_textarea_field( Rest_Editor_Audio_Text::trim( (string) ( $item['context_after'] ?? '' ), 240 ) ),
			'context_caption'  => sanitize_textarea_field( Rest_Editor_Audio_Text::trim( (string) ( $item['context_caption'] ?? '' ), 220 ) ),
			'context_summary'  => sanitize_textarea_field( Rest_Editor_Audio_Text::trim( (string) ( $item['context_summary'] ?? '' ), 360 ) ),
			'decorative'       => ! empty( $item['decorative'] ),
			'target_scope'     => 'post_block_alt',
		);
		if ( $attachment_id > 0 ) {
			$attachment_item = $this->editor_attachment_media_item( $attachment_id, sanitize_key( (string) ( $item['source'] ?? 'content_image' ) ) );
			if ( ! empty( $attachment_item ) ) {
				$attachment_item['url']         = '' !== (string) ( $attachment_item['url'] ?? '' ) ? $attachment_item['url'] : esc_url_raw( (string) ( $item['url'] ?? '' ) );
				$attachment_item['alt']         = '' !== $occurrence_id ? sanitize_text_field( (string) ( $item['alt'] ?? '' ) ) : ( '' !== (string) ( $attachment_item['alt'] ?? '' ) ? $attachment_item['alt'] : sanitize_text_field( (string) ( $item['alt'] ?? '' ) ) );
				$attachment_item['caption']     = '' !== (string) ( $attachment_item['caption'] ?? '' ) ? $attachment_item['caption'] : sanitize_textarea_field( (string) ( $item['caption'] ?? '' ) );
				$attachment_item['missing_alt'] = '' === trim( (string) $attachment_item['alt'] );
				return array_merge( $attachment_item, $occurrence_fields );
			}
		}

		$url = esc_url_raw( (string) ( $item['url'] ?? '' ) );
		if ( '' === $url ) {
			return array();
		}

		$alt = sanitize_text_field( (string) ( $item['alt'] ?? '' ) );
		return array_merge(
			array(
				'source'        => sanitize_key( (string) ( $item['source'] ?? 'content_image' ) ),
				'attachment_id' => 0,
				'title'         => sanitize_text_field( (string) ( $item['title'] ?? '' ) ),
				'caption'       => sanitize_textarea_field( (string) ( $item['caption'] ?? '' ) ),
				'description'   => sanitize_textarea_field( (string) ( $item['description'] ?? '' ) ),
				'mime_type'     => sanitize_text_field( (string) ( $item['mime_type'] ?? '' ) ),
				'alt'           => $alt,
				'missing_alt'   => '' === trim( $alt ),
				'thumbnail_url' => $url,
				'url'           => $url,
			),
			$occurrence_fields
		);
	}

	public function empty_toolkit_image_candidate_review_artifact( WP_Error $error ): array {
		return array(
			'artifact_type'             => 'image_candidate_review.v1',
			'candidate_type'            => 'image_candidates',
			'candidate_contract'        => 'image_candidate.v1',
			'projection_contract'       => 'recommendation_candidate.v1',
			'write_posture'             => 'suggestion_only',
			'final_write_path'          => 'core_proposal_required',
			'direct_wordpress_write'    => false,
			'source_ability_id'         => 'npcink-abilities-toolkit/build-image-candidate-review-artifact',
			'adoption_plan_ability_id'  => 'npcink-abilities-toolkit/build-image-candidate-adoption-plan',
			'toolkit_required'          => true,
			'error_code'                => sanitize_key( $error->get_error_code() ),
			'error_message'             => sanitize_text_field( $error->get_error_message() ),
			'items'                     => array(),
			'recommendation_candidates' => array(),
		);
	}
}
