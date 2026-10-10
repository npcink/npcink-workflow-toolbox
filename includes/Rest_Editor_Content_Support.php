<?php
/**
 * Editor content-support REST cluster: the /editor/content-support and
 * /ai/content-support fallback handler, the media brief flow, and every
 * editor_* helper, moved verbatim from Rest_Controller behind one-line
 * facade delegates.
 *
 * Suggestion-only by contract: responses are review artifacts and
 * Core-ready plans; nothing here writes posts, terms, media, or settings.
 * This service is intentionally extracted as one unit to close the facade
 * split; sub-dividing it into cohesive editor sub-services is queued
 * follow-up work under the same standard.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class Rest_Editor_Content_Support extends Rest_Editor_Flow_Cache {

	private const EDITOR_SUMMARY_FULL_CONTENT_MAX_CHARS = 30000;
	private const EDITOR_SELECTED_TEXT_MAX_CHARS        = 2000;
	private const EDITOR_COMMENT_TEXT_MAX_CHARS         = 1200;

	private Publish_Preflight_Service $publish_preflight;

	private Rest_Editor_Media_Alt $media_alt;

	public function __construct( Provider_Client $client, Publish_Preflight_Service $publish_preflight ) {
		$this->client            = $client;
		$this->publish_preflight = $publish_preflight;
		$this->media_alt         = new Rest_Editor_Media_Alt( $client );
	}

	public function editor_content_support( WP_REST_Request $request ) {
		$intent = sanitize_key( (string) ( $request->get_param( 'intent' ) ?: '' ) );
		if ( 'format_content' === $intent ) {
			return Editor_Content_Format::request( $request );
		}
		if ( ! in_array( $intent, array( 'progressive_recommendations', 'source_adaptation_review', 'polish_notes', 'article_narration', 'article_audio_summary', 'category_suggestions', 'tag_suggestions', 'summary_terms_optimization', 'internal_links', 'image_candidates', 'image_alt_suggestions', 'publish_preflight' ), true ) ) {
			return new WP_Error(
				'npcink_toolbox_invalid_editor_support_intent',
				__( 'A supported editor content-support intent is required.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$context         = $this->editor_post_context( $request );
		$context_post_id = absint( $context['post_id'] ?? 0 );
		if ( $context_post_id > 0 && ! current_user_can( 'edit_post', $context_post_id ) ) {
			return new WP_Error(
				'npcink_toolbox_editor_post_context_forbidden',
				__( 'You are not allowed to use this post as editor content-support context.', 'npcink-workflow-toolbox' ),
				array( 'status' => 403 )
			);
		}
		if ( 'source_adaptation_review' === $intent ) {
			$writing_pack_context = $this->editor_writing_pack_context( $request, $context );
			if ( is_wp_error( $writing_pack_context ) ) {
				return $writing_pack_context;
			}
			$context = $writing_pack_context;
		}
		if ( 'polish_notes' === $intent ) {
			if ( '' === $this->editor_polish_notes_selected_text( $context ) ) {
				return new WP_Error(
					'npcink_toolbox_missing_editor_selection',
					__( 'Select paragraph text before running paragraph review.', 'npcink-workflow-toolbox' ),
					array( 'status' => 400 )
				);
			}
			$context['context_scope'] = 'selected_text';
		}
			$query = 'source_adaptation_review' === $intent
				? $this->editor_writing_pack_query( $context )
				: $this->editor_support_query( $context );
		if ( 'image_candidates' === $intent ) {
			$query = $this->media_alt->editor_image_support_query( $context );
		}
		if ( '' === $query && 'progressive_recommendations' !== $intent ) {
			return new WP_Error(
				'npcink_toolbox_missing_editor_context',
				__( 'A title, excerpt, or post content is required for editor content support.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$result = array(
			'artifact_type'           => 'editor_content_support_flow',
			'composition_role'        => 'editor_content_support',
			'intent'                  => $intent,
			'write_posture'           => 'suggestion_only',
			'final_write_path'        => 'core_proposal_required',
			'direct_wordpress_write'  => false,
			'post_context'            => $context,
			'content_context'         => Rest_Editor_Progressive_Recommendations::editor_content_context( $context ),
			'query'                   => $query,
			'sections'                => array(),
			'remote_execution_policy' => array(
				'cache_ttl_seconds'     => self::EDITOR_FLOW_CACHE_TTL,
				'cache_scope'           => 'site_transient_by_intent_query_and_post_context',
				'workflow_runtime'      => false,
				'direct_async_queue'    => false,
				'progressive_target_ms' => Rest_Editor_Progressive_Recommendations::EDITOR_PROGRESSIVE_TARGET_MS,
			),
			'handoff'                 => array(
				'surface'                => 'post_editor_sidebar',
				'final_writes'           => 'core_proposal_required',
				'direct_wordpress_write' => false,
			),
		);

		if ( 'progressive_recommendations' === $intent ) {
			$result['sections']['progressive_recommendations'] = $this->editor_progressive_recommendations( $context, $query );
			$result['recommendation_set']                      = Rest_Editor_Progressive_Recommendations::editor_recommendation_set( $context, $intent, $result['sections'] );
			$result['content_fingerprint']                     = $result['recommendation_set']['content_fingerprint'];
			return rest_ensure_response( $result );
		}

		if ( 'source_adaptation_review' === $intent ) {
			return $this->editor_writing_pack_flow( $request, $context, $result, $intent );
		}

		if ( 'polish_notes' === $intent ) {
			$result['sections']['polish_notes'] = $this->editor_hosted_draft_support( $context, 'polish_notes' );
		}

		if ( 'article_narration' === $intent ) {
			$result['sections']['audio_generation'] = $this->editor_article_audio_generation( $context, 'article_narration' );
		}

		if ( 'article_audio_summary' === $intent ) {
			$result['sections']['audio_generation'] = $this->editor_article_audio_generation( $context, 'article_audio_summary' );
		}

		if ( 'category_suggestions' === $intent ) {
			$result['sections']['summary_terms_optimization'] = $this->editor_fast_category_suggestions( $context, $query );
		}

		if ( 'tag_suggestions' === $intent ) {
			$result['sections']['summary_terms_optimization'] = $this->editor_fast_tag_suggestions( $context, $query );
		}

		if ( 'summary_terms_optimization' === $intent ) {
			$result['sections']['summary_terms_optimization'] = $this->editor_summary_terms_optimization( $context, $query );
		}

		if ( 'internal_links' === $intent ) {
			$result['sections']['internal_links'] = $this->editor_internal_link_candidates( $context, $query );
		}

		if ( 'image_candidates' === $intent ) {
			$result['sections']['image_candidates'] = $this->media_alt->editor_image_recommendation_section(
				$this->editor_support_section(
					$this->client->image_candidates(
						$query,
						array(
							'provider'       => 'auto',
							'per_page'       => 6,
							'image_mode'     => 'paragraph' === sanitize_key( (string) ( $context['image_mode'] ?? '' ) ) ? 'paragraph_image' : 'featured_image',
							'latency_mode'   => sanitize_key( (string) ( $context['latency_mode'] ?? '' ) ),
							'visual_context' => $this->media_alt->editor_image_visual_context( $context, $query ),
						)
					)
				)
			);
		}

		if ( 'image_alt_suggestions' === $intent ) {
			$result['sections']['image_alt_suggestions'] = $this->media_alt->editor_article_image_alt_suggestions( $context );
		}

		if ( 'publish_preflight' === $intent ) {
			$result['sections']['discoverability'] = $this->editor_support_section(
				$this->editor_cached_content_discoverability(
					array(
						'post_id'                 => absint( $context['post_id'] ?? 0 ),
						'title'                   => (string) ( $context['title'] ?? '' ),
						'topic'                   => $query,
						'excerpt'                 => (string) ( $context['excerpt'] ?? '' ),
						'content'                 => (string) ( $context['content_text'] ?? '' ),
						'external_search_intent'  => 'fact_check',
						'include_external_search' => true,
					)
				)
			);
			$local_checkup                         = $this->editor_article_checkup_section( $context );
			$duplicate_check                       = $this->editor_support_section(
				$this->editor_cached_site_knowledge(
					array(
						'query'           => $query,
						'intent'          => 'duplicate_check',
						'current_post_id' => absint( $context['post_id'] ?? 0 ),
						'max_results'     => 5,
					)
				)
			);
			$result['sections']                    = array_merge(
				$result['sections'],
				$this->publish_preflight->build_sections( $context, $result['sections']['discoverability'], $duplicate_check, $local_checkup )
			);
		}

		$result['recommendation_set']  = Rest_Editor_Progressive_Recommendations::editor_recommendation_set( $context, $intent, $result['sections'] );
		$result['content_fingerprint'] = $result['recommendation_set']['content_fingerprint'];

		return rest_ensure_response( $result );
	}

	private function editor_post_context( WP_REST_Request $request ): array {
		$content_raw         = (string) $request->get_param( 'content' );
		$audio_preferences   = Rest_Editor_Audio_Text::editor_audio_preferences_from_request( $request );
		$content             = trim( wp_strip_all_tags( $content_raw ) );
		$selected_text       = trim( wp_strip_all_tags( (string) $request->get_param( 'selected_text' ) ) );
		$selected_block_text = trim( wp_strip_all_tags( (string) $request->get_param( 'selected_block_text' ) ) );
		$user_instruction    = trim( wp_strip_all_tags( (string) $request->get_param( 'user_instruction' ) ) );
		$context_scope       = sanitize_key( (string) ( $request->get_param( 'context_scope' ) ?: 'auto' ) );
		$summary_mode        = sanitize_key( (string) ( $request->get_param( 'summary_generation_mode' ) ?: 'fast_brief' ) );
		if ( ! in_array( $context_scope, array( 'auto', 'full_article', 'selected_text', 'topic_only' ), true ) ) {
			$context_scope = 'auto';
		}
		if ( ! in_array( $summary_mode, array( 'fast_brief', 'full_context' ), true ) ) {
			$summary_mode = 'fast_brief';
		}

		return array(
			'post_id'                    => absint( $request->get_param( 'post_id' ) ),
			'post_type'                  => sanitize_key( (string) ( $request->get_param( 'post_type' ) ?: 'post' ) ),
			'post_status'                => sanitize_key( (string) $request->get_param( 'post_status' ) ),
			'context_scope'              => $context_scope,
			'title'                      => sanitize_text_field( (string) $request->get_param( 'title' ) ),
			'excerpt'                    => sanitize_textarea_field( (string) $request->get_param( 'excerpt' ) ),
			'content_text'               => wp_trim_words( $content, 220, '' ),
			'content_full_text'          => sanitize_textarea_field( Rest_Editor_Audio_Text::trim( $content, self::EDITOR_SUMMARY_FULL_CONTENT_MAX_CHARS ) ),
			'content_audio_text'         => sanitize_textarea_field( Rest_Editor_Audio_Text::trim( Rest_Editor_Audio_Text::editor_audio_text_from_raw_content( $content_raw, $audio_preferences ), Rest_Editor_Audio_Text::EDITOR_AUDIO_TEXT_MAX_CHARS ) ),
			'selected_text'              => wp_trim_words( sanitize_textarea_field( $selected_text ), 110, '' ),
			'selected_block_text'        => wp_trim_words( sanitize_textarea_field( $selected_block_text ), 110, '' ),
			'selected_text_full'         => sanitize_textarea_field( Rest_Editor_Audio_Text::trim( $selected_text, self::EDITOR_SELECTED_TEXT_MAX_CHARS ) ),
			'selected_block_text_full'   => sanitize_textarea_field( Rest_Editor_Audio_Text::trim( $selected_block_text, self::EDITOR_SELECTED_TEXT_MAX_CHARS ) ),
			'selected_block_name'        => sanitize_text_field( (string) $request->get_param( 'selected_block_name' ) ),
			'user_instruction'           => wp_trim_words( sanitize_textarea_field( $user_instruction ), 60, '' ),
			'audio_preferences'          => $audio_preferences,
			'generation_variant'         => sanitize_text_field( (string) $request->get_param( 'generation_variant' ) ),
			'force_regenerate'           => (bool) $request->get_param( 'force_regenerate' ),
			'summary_generation_mode'    => $summary_mode,
			'image_mode'                 => sanitize_key( (string) $request->get_param( 'image_mode' ) ),
			'category_ids'               => $this->csv_absint_list( (string) $request->get_param( 'category_ids' ) ),
			'tag_ids'                    => $this->csv_absint_list( (string) $request->get_param( 'tag_ids' ) ),
			'featured_media'             => absint( $request->get_param( 'featured_media' ) ),
			'media_items'                => $this->media_alt->editor_media_items_from_request( $request ),
			'occurrence_offset'          => max( 0, absint( $request->get_param( 'occurrence_offset' ) ) ),
			'total_occurrence_count'     => max( 0, absint( $request->get_param( 'total_occurrence_count' ) ) ),
			'visual_recognition_consent' => (bool) $request->get_param( 'visual_recognition_consent' ),
			'content_blocks'             => $this->editor_content_blocks_from_request( $request ),
			'comment_id'                 => absint( $request->get_param( 'comment_id' ) ),
			'comment_author'             => sanitize_text_field( (string) $request->get_param( 'comment_author' ) ),
			'comment_text'               => sanitize_textarea_field( Rest_Editor_Audio_Text::trim( trim( wp_strip_all_tags( (string) $request->get_param( 'comment_text' ) ) ), self::EDITOR_COMMENT_TEXT_MAX_CHARS ) ),
		);
	}

	private function editor_content_blocks_from_request( WP_REST_Request $request ): array {
		$blocks = $request->get_param( 'content_blocks' );
		if ( ! is_array( $blocks ) ) {
			return array();
		}
		$items = array();
		foreach ( array_slice( $blocks, 0, 80 ) as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$client_id = sanitize_text_field( (string) ( $block['client_id'] ?? '' ) );
			$text      = sanitize_textarea_field( Rest_Editor_Audio_Text::trim( wp_strip_all_tags( (string) ( $block['text'] ?? '' ) ), 1600 ) );
			if ( '' !== $client_id && '' !== $text ) {
				$items[] = array(
					'client_id'  => $client_id,
					'block_name' => sanitize_text_field( (string) ( $block['block_name'] ?? '' ) ),
					'text'       => $text,
				);
			}
		}
		return $items;
	}

	private function editor_support_query( array $context ): string {
		$scope               = sanitize_key( (string) ( $context['context_scope'] ?? 'auto' ) );
		$instruction         = trim( (string) ( $context['user_instruction'] ?? '' ) );
		$selection           = trim(
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
		$selected_scope_text = '' !== $selection ? $selection : trim( (string) ( $context['content_text'] ?? '' ) );

		if ( 'selected_text' === $scope && '' !== $selected_scope_text ) {
			return wp_trim_words(
				trim(
					implode(
						' ',
						array_filter(
							array(
								$selected_scope_text,
								(string) ( $context['title'] ?? '' ),
								$instruction,
							)
						)
					)
				),
				80,
				''
			);
		}

		if ( 'topic_only' === $scope ) {
			return wp_trim_words(
				trim(
					implode(
						' ',
						array_filter(
							array(
								(string) ( $context['title'] ?? '' ),
								(string) ( $context['excerpt'] ?? '' ),
								$instruction,
							)
						)
					)
				),
				80,
				''
			);
		}

		$query = trim(
			implode(
				' ',
				array_filter(
					array(
						'auto' === $scope ? $selection : '',
						(string) ( $context['title'] ?? '' ),
						(string) ( $context['excerpt'] ?? '' ),
						(string) ( $context['content_text'] ?? '' ),
						$instruction,
					)
				)
			)
		);

		return wp_trim_words( $query, 80, '' );
	}

	/**
	 * Builds the request-scoped writing-pack context from typed input modes.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param array $context Base editor post context.
	 * @return array|WP_Error Augmented context, or a rejection error.
	 */

	private function editor_writing_pack_context( WP_REST_Request $request, array $context ) {
		$input_mode = sanitize_key( (string) ( $request->get_param( 'input_mode' ) ?: 'url_reference' ) );
		if ( ! in_array( $input_mode, array( 'url_reference', 'manual_brief', 'mixed' ), true ) ) {
			return new WP_Error(
				'npcink_toolbox_writing_pack_input_mode_not_supported',
				__( 'Choose URL reference, manual brief, or mixed input for the article writing pack.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}
		$default_stage = 'manual_brief' === $input_mode ? 'research_plan' : 'extract';
		$source_stage  = sanitize_key( (string) ( $request->get_param( 'source_stage' ) ?: $default_stage ) );
		$source_url    = '';
		if ( 'draft' !== $source_stage && in_array( $input_mode, array( 'url_reference', 'mixed' ), true ) ) {
			$source_url = $this->editor_source_adaptation_url( (string) $request->get_param( 'source_url' ) );
			if ( is_wp_error( $source_url ) ) {
				return $source_url;
			}
		}
		$context['source_url']                = $source_url;
		$context['source_stage']              = in_array( $source_stage, array( 'extract', 'adapt', 'research_plan', 'draft' ), true ) ? $source_stage : $default_stage;
		$context['source_stage_requested']    = $context['source_stage'];
		$context['input_mode']                = $input_mode;
		$context['editorial_brief']           = Rest_Editor_Writing_Pack_Shaping::editor_writing_pack_request_brief( $request->get_param( 'editorial_brief' ) );
		$context['reviewed_writing_pack']     = $request->get_param( 'reviewed_writing_pack' );
		$context['writing_pack_confirmation'] = $request->get_param( 'writing_pack_confirmation' );
		$context['draft_review_feedback']     = 'draft' === $context['source_stage']
			? $this->editor_draft_review_feedback_request( $request->get_param( 'draft_review_feedback' ) )
			: array();
		$context['user_instruction']          = (string) ( $context['editorial_brief']['operator_instruction'] ?? '' );

		return $context;
	}

	/**
	 * Returns the joined selection text used by paragraph review.
	 */

	private function editor_polish_notes_selected_text( array $context ): string {
		return trim(
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
	}

	/**
	 * Runs the staged writing-pack flow: exact-URL reader evidence, Site
	 * Knowledge context, and the reviewed article writing pack.
	 */

	private function editor_writing_pack_flow( WP_REST_Request $request, array $context, array $result, string $intent ) {
		$source_url           = (string) ( $context['source_url'] ?? '' );
		$source_stage         = (string) ( $context['source_stage'] ?? 'extract' );
		$force_source_refresh = 'extract' === $source_stage && rest_sanitize_boolean( $request->get_param( 'force_refresh' ) );
		if ( 'adapt' === $source_stage ) {
			$source_stage = 'research_plan';
		}
		if ( 'draft' === $source_stage ) {
			return $this->editor_article_draft_response( $context, $result );
		}
		if ( 'manual_brief' === (string) ( $context['input_mode'] ?? '' ) ) {
			return $this->editor_manual_writing_pack_response( $context, $result );
		}
		$external_raw                         = $this->editor_cached_cloud_web_search(
			array(
				'query'        => $source_url,
				'source_url'   => $source_url,
				'intent'       => 'source_extraction_preview',
				'max_results'  => 1,
				'recency_days' => 0,
			),
			$force_source_refresh
		);
		$external                             = $this->editor_support_section( $external_raw );
		$source_item                          = ! is_wp_error( $external_raw ) && is_array( $external_raw['results'][0] ?? null ) ? $external_raw['results'][0] : array();
		$source_text                          = trim( (string) ( $source_item['reader_excerpt'] ?? $source_item['snippet'] ?? '' ) );
		$source_title                         = sanitize_text_field( (string) ( $external['title'] ?? $source_item['title'] ?? '' ) );
		$source_body_text                     = $this->editor_source_article_body_text( $source_title, $source_text );
		$source_resolved_url                  = esc_url_raw( (string) ( $external['resolved_url'] ?? $source_item['url'] ?? '' ) );
		$cloud_url_match                      = sanitize_key( (string) ( $external['url_match'] ?? '' ) );
		$source_url_matches                   = 'matched' === $cloud_url_match && $this->editor_source_adaptation_url_matches( $source_url, $source_resolved_url );
		$result['sections']['source_article'] = $external;
		$result['sections']['source_article']['requested_url']  = esc_url_raw( (string) ( $external['requested_url'] ?? $source_url ) );
		$result['sections']['source_article']['resolved_url']   = $source_resolved_url;
		$result['sections']['source_article']['url_match']      = $source_url_matches ? 'matched' : ( $cloud_url_match ?: 'unavailable' );
		$source_body_ready                                      = $source_url_matches && $this->editor_source_body_is_draftable( $source_body_text );
		$result['sections']['source_article']['body_ready']     = $source_body_ready;
		$result['sections']['source_article']['body_readiness'] = $source_body_ready ? 'ready' : 'insufficient';
		$result['artifact_type']                                = 'source_extraction_preview.v1';
		$result['contract_version']                             = 'source_extraction_preview.v1';
		$result['input_mode']                                   = (string) ( $context['input_mode'] ?? 'url_reference' );
		$result['composition_role']                             = 'external_source_extraction_review';
		$result['final_write_path']                             = 'operator_review_only_no_insert';
		$result['handoff']['final_writes']                      = 'operator_review_only_no_insert';
		$result['handoff']['source_runtime']                    = 'cloud_exact_url_reader';
		$result['handoff']['body_generation']                   = false;
		$result['handoff']['body_replacement']                  = false;

		if ( 'extract' === $source_stage ) {
			$result['recommendation_set']  = Rest_Editor_Progressive_Recommendations::editor_recommendation_set( $context, $intent, $result['sections'] );
			$result['content_fingerprint'] = $result['recommendation_set']['content_fingerprint'];
			return rest_ensure_response( $result );
		}

		if ( ! $source_url_matches ) {
			$result['sections']['source_adaptation_review'] = array(
				'status'                 => 'blocked',
				'message'                => __( 'Cloud search returned a different article URL on the same site. Verify the source URL before continuing.', 'npcink-workflow-toolbox' ),
				'write_posture'          => 'suggestion_only',
				'direct_wordpress_write' => false,
			);
		} elseif ( $source_body_ready ) {
			$site_query                                     = trim( $source_title . ' ' . wp_trim_words( wp_strip_all_tags( $source_body_text ), 80, '' ) );
			$knowledge_raw                                  = $this->editor_cached_site_knowledge(
				array(
					'query'              => $site_query,
					'intent'             => 'writing_support_plan',
					'result_granularity' => 'document',
					'current_post_id'    => absint( $context['post_id'] ?? 0 ),
					'max_results'        => 6,
				)
			);
			$result['sections']['source_site_context']      = $this->editor_support_section( $knowledge_raw );
			$result['sections']['source_adaptation_review'] = $this->editor_hosted_source_adaptation_review(
				$context,
				array(
					'title'         => $source_title,
					'url'           => $source_resolved_url,
					'content'       => $source_body_text,
					'reader_status' => sanitize_key( (string) ( $source_item['reader_status'] ?? 'snippet_only' ) ),
				),
				is_wp_error( $knowledge_raw ) ? array() : $knowledge_raw
			);
		} else {
			$result['sections']['source_adaptation_review'] = array(
				'status'                 => 'blocked',
				'message'                => __( 'The source reader did not return enough article body text. Try another public article URL; no draft will be generated from navigation or metadata alone.', 'npcink-workflow-toolbox' ),
				'write_posture'          => 'suggestion_only',
				'direct_wordpress_write' => false,
			);
		}

		$result['sections']['article_writing_pack']     = $this->editor_article_writing_pack(
			$context,
			$result['sections']['source_article'],
			$result['sections']['source_site_context'] ?? array(),
			$result['sections']['source_adaptation_review'] ?? array(),
			$source_body_text
		);
		$legacy_adapt_stage                             = 'adapt' === (string) ( $context['source_stage_requested'] ?? '' );
		$result['artifact_type']                        = $legacy_adapt_stage ? 'source_adaptation_review.v1' : 'article_writing_pack.v1';
		$result['contract_version']                     = $legacy_adapt_stage ? 'source_adaptation_review.v1' : 'article_writing_pack.v1';
		$result['primary_artifact_type']                = 'article_writing_pack.v1';
		$result['input_mode']                           = (string) ( $context['input_mode'] ?? 'url_reference' );
		$result['composition_role']                     = 'source_grounded_article_planning';
		$result['final_write_path']                     = 'operator_review_only_no_insert';
		$result['handoff']['final_writes']              = 'operator_review_only_no_insert';
		$result['handoff']['source_runtime']            = 'cloud_exact_url_reader';
		$result['handoff']['style_runtime']             = 'cloud_site_knowledge';
		$result['handoff']['required_input_contract']   = 'article_writing_pack.v1';
		$result['handoff']['article_generation_status'] = 'not_admitted_current_stage';
		$result['handoff']['body_generation']           = false;
		$result['handoff']['body_replacement']          = false;
		$result['recommendation_set']                   = Rest_Editor_Progressive_Recommendations::editor_recommendation_set( $context, $intent, $result['sections'] );
		$result['content_fingerprint']                  = $result['recommendation_set']['content_fingerprint'];
		return rest_ensure_response( $result );
	}

	private function editor_support_section( $value ): array {
		if ( is_wp_error( $value ) ) {
			return array(
				'status'                 => 'error',
				'code'                   => sanitize_key( (string) $value->get_error_code() ),
				'message'                => sanitize_text_field( $value->get_error_message() ),
				'write_posture'          => 'suggestion_only',
				'direct_wordpress_write' => false,
			);
		}

		return is_array( $value ) ? $value : array();
	}

	private function editor_progressive_recommendations( array $context, string $query ): array {
		$taxonomy_terms           = '' !== $query ? $this->editor_taxonomy_term_candidates( $context, $query ) : Rest_Editor_Progressive_Recommendations::editor_local_taxonomy_profile( $context );
		$taxonomy_items           = is_array( $taxonomy_terms['items'] ?? null ) ? $taxonomy_terms['items'] : array();
		$category_items           = array_values(
			array_filter(
				$taxonomy_items,
				static fn( array $item ): bool => 'category' === (string) ( $item['taxonomy'] ?? '' )
			)
		);
		$tag_items                = array_values(
			array_filter(
				$taxonomy_items,
				static fn( array $item ): bool => 'post_tag' === (string) ( $item['taxonomy'] ?? '' )
			)
		);
		$media_items              = $this->editor_media_library_candidates( $context, $query );
		$preflight_checks         = $this->publish_preflight->local_checks( $context );
		$preflight_candidates     = Rest_Editor_Progressive_Recommendations::editor_preflight_recommendation_candidates( $preflight_checks );
		$taxonomy_recommendations = '' !== trim( $query )
			? array_merge(
				Rest_Editor_Taxonomy_Shaping::editor_taxonomy_recommendation_candidates( 'category_suggestions', $category_items, array(), $this->empty_proposed_new_terms_review() ),
				Rest_Editor_Taxonomy_Shaping::editor_taxonomy_recommendation_candidates( 'tag_suggestions', array(), $tag_items, $this->empty_proposed_new_terms_review() )
			)
			: array();
		$recommendations          = array_merge(
			Rest_Editor_Progressive_Recommendations::editor_filter_high_confidence_taxonomy_recommendations( $taxonomy_recommendations ),
			Rest_Editor_Progressive_Recommendations::editor_media_library_recommendation_candidates( $media_items ),
			$preflight_candidates
		);

		return array(
			'artifact_type'             => 'editor_progressive_recommendations.v1',
			'composition_role'          => 'local_progressive_prefetch',
			'candidate_type'            => 'progressive_recommendations',
			'candidate_contract'        => 'recommendation_candidate.v1',
			'latency_profile'           => 'local_300ms',
			'target_latency_ms'         => 300,
			'write_posture'             => 'suggestion_only',
			'final_write_path'          => 'core_proposal_required',
			'direct_wordpress_write'    => false,
			'content_fingerprint'       => Rest_Editor_Progressive_Recommendations::editor_content_fingerprint( $context ),
			'available_context'         => array(
				'post_id'             => absint( $context['post_id'] ?? 0 ),
				'post_type'           => sanitize_key( (string) ( $context['post_type'] ?? 'post' ) ),
				'has_title'           => '' !== trim( (string) ( $context['title'] ?? '' ) ),
				'has_excerpt'         => '' !== trim( (string) ( $context['excerpt'] ?? '' ) ),
				'content_words'       => str_word_count( (string) ( $context['content_text'] ?? '' ) ),
				'category_count'      => count( $category_items ),
				'tag_count'           => count( $tag_items ),
				'media_library_count' => count( $media_items ),
				'context_source'      => '' !== $query ? 'current_draft_and_local_wordpress' : 'local_wordpress_prefetch',
			),
			'category_candidates'       => array_slice( $category_items, 0, 2 ),
			'tag_candidates'            => array_slice( $tag_items, 0, 8 ),
			'media_library_candidates'  => $media_items,
			'preflight_candidates'      => $preflight_candidates,
			'recommendation_candidates' => array_slice( $recommendations, 0, Rest_Editor_Progressive_Recommendations::EDITOR_PROGRESSIVE_CANDIDATE_LIMIT ),
			'preflight_checks'          => $preflight_checks,
			'next_fast_intents'         => array_values(
				array_filter(
					array(
						! empty( $category_items ) ? 'category_suggestions' : '',
						! empty( $tag_items ) ? 'tag_suggestions' : '',
						empty( $context['featured_media'] ) && ! empty( $media_items ) ? 'image_candidates' : '',
					)
				)
			),
			'deferred_enhancements'     => array(
				'cloud_title_generation',
				'hosted_summary_generation',
				'cloud_image_source_search',
				'site_knowledge_internal_links',
				'publish_preflight_duplicate_check',
			),
			'remote_execution_policy'   => array(
				'cloud_calls'             => false,
				'workflow_runtime'        => false,
				'direct_async_queue'      => false,
				'fallback_for_timeout_ms' => Rest_Editor_Progressive_Recommendations::EDITOR_PROGRESSIVE_TARGET_MS,
			),
		);
	}

	private function editor_media_library_candidates( array $context, string $query ): array {
		if ( ! function_exists( 'get_posts' ) ) {
			return array();
		}
		$attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image',
				'posts_per_page' => 12,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$items       = array();
		foreach ( is_array( $attachments ) ? $attachments : array() as $attachment ) {
			if ( ! is_object( $attachment ) ) {
				continue;
			}
			$item = $this->media_alt->editor_attachment_media_item( absint( $attachment->ID ?? 0 ), 'media_library_prefetch' );
			if ( empty( $item ) ) {
				continue;
			}
			$score                = Rest_Editor_Progressive_Recommendations::editor_contextual_match_score( implode( ' ', array( $item['title'] ?? '', $item['alt'] ?? '', $item['caption'] ?? '', $item['description'] ?? '' ) ), $context, $query );
			$item['score']        = $score;
			$item['recency_rank'] = count( $items );
			$item['reason']       = $score > 0
				? __( 'Existing media matched weighted title, excerpt, selected text, or draft terms.', 'npcink-workflow-toolbox' )
				: __( 'Recent existing media candidate from the local library. Review visual fit before adoption.', 'npcink-workflow-toolbox' );
			$items[]              = $item;
		}
		usort(
			$items,
			static function ( array $left, array $right ): int {
				$score_compare = (int) ( $right['score'] ?? 0 ) <=> (int) ( $left['score'] ?? 0 );
				if ( 0 !== $score_compare ) {
					return $score_compare;
				}
				return (int) ( $left['recency_rank'] ?? 0 ) <=> (int) ( $right['recency_rank'] ?? 0 );
			}
		);
		return array_slice( $items, 0, 8 );
	}

	private function editor_source_adaptation_url( string $value ) {
		$value = trim( $value );
		$url   = esc_url_raw( $value, array( 'http', 'https' ) );
		$parts = '' !== $url ? wp_parse_url( $url ) : false;
		if ( ! is_array( $parts ) ) {
			return new WP_Error(
				'npcink_toolbox_source_url_invalid',
				__( 'Enter one valid public article URL.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$scheme          = strtolower( (string) ( $parts['scheme'] ?? '' ) );
		$host            = strtolower( trim( (string) ( $parts['host'] ?? '' ), '[]' ) );
		$port            = isset( $parts['port'] ) ? (int) $parts['port'] : ( 'https' === $scheme ? 443 : 80 );
		$has_credentials = '' !== (string) ( $parts['user'] ?? '' ) || '' !== (string) ( $parts['pass'] ?? '' );
		$blocked_host    = '' === $host
			|| 'localhost' === $host
			|| str_ends_with( $host, '.localhost' )
			|| str_ends_with( $host, '.local' )
			|| str_ends_with( $host, '.test' )
			|| str_ends_with( $host, '.invalid' )
			|| str_ends_with( $host, '.example' )
			|| str_ends_with( $host, '.internal' )
			|| str_ends_with( $host, '.home.arpa' );
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			$blocked_host = false === filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
		}

		$safe_port          = ( 'http' === $scheme && 80 === $port ) || ( 'https' === $scheme && 443 === $port );
		$wordpress_safe_url = function_exists( 'wp_http_validate_url' ) ? wp_http_validate_url( $url ) : $url;
		$public_host        = $this->editor_source_adaptation_host_is_public( $host );

		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || $has_credentials || $blocked_host || ! $safe_port || false === $wordpress_safe_url || ! $public_host ) {
			return new WP_Error(
				'npcink_toolbox_source_url_not_public',
				__( 'Use a public HTTP or HTTPS article URL on a standard port without credentials, localhost, or private network addresses.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		return is_string( $wordpress_safe_url ) ? $wordpress_safe_url : $url;
	}

	private function editor_source_adaptation_host_is_public( string $host ): bool {
		$host = strtolower( trim( $host, '[]' ) );
		if ( '' === $host ) {
			return false;
		}
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return $this->editor_source_adaptation_ip_is_public( $host );
		}

		// Cloud Addon owns fetch-time DNS and redirect validation. Resolving a
		// hostname here would duplicate transport policy and break hosts that use
		// a local outbound proxy address for public destinations.
		return true;
	}

	private function editor_source_adaptation_ip_is_public( string $address ): bool {
		$packed = @inet_pton( $address );
		if ( false === $packed ) {
			return false;
		}

		if ( 16 === strlen( $packed ) && substr( $packed, 0, 12 ) === str_repeat( "\0", 10 ) . "\xff\xff" ) {
			$mapped_ipv4 = @inet_ntop( substr( $packed, 12, 4 ) );
			return is_string( $mapped_ipv4 ) && $this->editor_source_adaptation_ip_is_public( $mapped_ipv4 );
		}

		$blocked_cidrs = 4 === strlen( $packed )
			? array(
				'0.0.0.0/8',
				'10.0.0.0/8',
				'100.64.0.0/10',
				'127.0.0.0/8',
				'169.254.0.0/16',
				'172.16.0.0/12',
				'192.0.0.0/24',
				'192.0.2.0/24',
				'192.88.99.0/24',
				'192.168.0.0/16',
				'198.18.0.0/15',
				'198.51.100.0/24',
				'203.0.113.0/24',
				'224.0.0.0/4',
				'240.0.0.0/4',
			)
			: array(
				'::/96',
				'::1/128',
				'64:ff9b::/96',
				'64:ff9b:1::/48',
				'100::/64',
				'2001:2::/48',
				'2001:10::/28',
				'2001:20::/28',
				'2001:db8::/32',
				'2002::/16',
				'fc00::/7',
				'fe80::/10',
				'ff00::/8',
			);

		foreach ( $blocked_cidrs as $blocked_cidr ) {
			if ( $this->editor_source_adaptation_ip_is_in_cidr( $address, $blocked_cidr ) ) {
				return false;
			}
		}

		return true;
	}

	private function editor_source_adaptation_ip_is_in_cidr( string $address, string $cidr ): bool {
		$parts = explode( '/', $cidr, 2 );
		if ( 2 !== count( $parts ) || ! ctype_digit( $parts[1] ) ) {
			return false;
		}

		$address_bytes = @inet_pton( $address );
		$network_bytes = @inet_pton( $parts[0] );
		if ( false === $address_bytes || false === $network_bytes || strlen( $address_bytes ) !== strlen( $network_bytes ) ) {
			return false;
		}

		$prefix_bits = (int) $parts[1];
		$total_bits  = strlen( $address_bytes ) * 8;
		if ( 0 > $prefix_bits || $prefix_bits > $total_bits ) {
			return false;
		}

		$full_bytes = intdiv( $prefix_bits, 8 );
		if ( 0 < $full_bytes && substr( $address_bytes, 0, $full_bytes ) !== substr( $network_bytes, 0, $full_bytes ) ) {
			return false;
		}

		$remaining_bits = $prefix_bits % 8;
		if ( 0 === $remaining_bits ) {
			return true;
		}

		$mask = ( 0xff << ( 8 - $remaining_bits ) ) & 0xff;
		return ( ord( $address_bytes[ $full_bytes ] ) & $mask ) === ( ord( $network_bytes[ $full_bytes ] ) & $mask );
	}

	private function editor_source_adaptation_url_matches( string $requested_url, string $resolved_url ): bool {
		$requested = wp_parse_url( $requested_url );
		$resolved  = wp_parse_url( $resolved_url );
		if ( ! is_array( $requested ) || ! is_array( $resolved ) ) {
			return false;
		}

		$requested_host = strtolower( trim( (string) ( $requested['host'] ?? '' ), '[]' ) );
		$resolved_host  = strtolower( trim( (string) ( $resolved['host'] ?? '' ), '[]' ) );
		if ( '' === $requested_host || $requested_host !== $resolved_host ) {
			return false;
		}

		$normalize_path = static function ( array $parts ): string {
			$path = rawurldecode( (string) ( $parts['path'] ?? '/' ) );
			$path = preg_replace( '#/+#', '/', '/' . ltrim( $path, '/' ) );
			return '/' === $path ? '/' : rtrim( $path, '/' );
		};

		return $normalize_path( $requested ) === $normalize_path( $resolved );
	}

	private function editor_source_article_body_text( string $source_title, string $source_text ): string {
		$text  = trim( $source_text );
		$title = trim( $source_title );
		if ( '' === $text || '' === $title ) {
			return '';
		}
		$title_candidates = array( $title );
		$short_title      = preg_split( '/\s+[–—|]\s+/u', $title, 2 );
		if ( is_array( $short_title ) && '' !== trim( (string) ( $short_title[0] ?? '' ) ) ) {
			$title_candidates[] = trim( (string) $short_title[0] );
		}

		foreach ( array_unique( $title_candidates ) as $title_candidate ) {
			$pattern = '/^#{1,3}\s+' . preg_quote( $title_candidate, '/' ) . '\s*$/miu';
			if ( 1 !== preg_match( $pattern, $text, $match, PREG_OFFSET_CAPTURE ) ) {
				continue;
			}
			$heading = (string) ( $match[0][0] ?? '' );
			$offset  = (int) ( $match[0][1] ?? 0 );

			return trim( substr( $text, $offset + strlen( $heading ) ) );
		}

		return '';
	}

	private function editor_source_body_is_draftable( string $source_text ): bool {
		$text = trim( wp_strip_all_tags( $source_text ) );
		if ( '' === $text ) {
			return false;
		}
		$char_count     = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
		$prose          = preg_replace( '#https?://\S+#iu', '', $text );
		$sentence_count = preg_match_all( '/[.!?。！？]/u', is_string( $prose ) ? $prose : '' );

		return $char_count >= 600 && is_int( $sentence_count ) && $sentence_count >= 3;
	}

	private function editor_draft_review_feedback_request( $raw ): array {
		if ( is_string( $raw ) && '' !== trim( $raw ) ) {
			$decoded = json_decode( $raw, true );
			$raw     = is_array( $decoded ) ? $decoded : array();
		}
		$raw    = is_array( $raw ) ? $raw : array();
		$status = sanitize_key( (string) ( $raw['status'] ?? '' ) );
		if ( ! in_array( $status, array( 'usable', 'usable_after_changes', 'not_usable' ), true ) ) {
			$status = '';
		}
		$allowed_issues = array( 'fact_accuracy', 'site_tone', 'structure', 'source_similarity', 'rights_attribution' );
		$issue_codes    = array();
		foreach ( is_array( $raw['issue_codes'] ?? null ) ? $raw['issue_codes'] : array() as $issue_code ) {
			$issue_code = sanitize_key( (string) $issue_code );
			if ( in_array( $issue_code, $allowed_issues, true ) && ! in_array( $issue_code, $issue_codes, true ) ) {
				$issue_codes[] = $issue_code;
			}
		}
		$notes = wp_trim_words( sanitize_textarea_field( wp_strip_all_tags( (string) ( $raw['notes'] ?? '' ) ) ), 120, '' );
		if ( '' === $status ) {
			return array();
		}

		return array(
			'artifact_type'          => 'article_draft_review_feedback.v1',
			'contract_version'       => 'article_draft_review_feedback.v1',
			'status'                 => $status,
			'issue_codes'            => $issue_codes,
			'notes'                  => $notes,
			'authorization_scope'    => 'single_draft_regeneration_request',
			'durable_review_state'   => false,
			'direct_wordpress_write' => false,
		);
	}

	private function editor_writing_pack_query( array $context ): string {
		$source_url = trim( (string) ( $context['source_url'] ?? '' ) );
		if ( '' !== $source_url ) {
			return $source_url;
		}
		$reviewed_pack = $context['reviewed_writing_pack'] ?? array();
		if ( is_string( $reviewed_pack ) ) {
			$decoded       = json_decode( $reviewed_pack, true );
			$reviewed_pack = is_array( $decoded ) ? $decoded : array();
		}
		if ( is_array( $reviewed_pack ) ) {
			$reviewed_ref = sanitize_text_field( (string) ( $reviewed_pack['content_fingerprint'] ?? $reviewed_pack['writing_pack_id'] ?? '' ) );
			if ( '' !== $reviewed_ref ) {
				return $reviewed_ref;
			}
		}
		$brief = is_array( $context['editorial_brief'] ?? null ) ? $context['editorial_brief'] : array();
		$parts = array(
			(string) ( $brief['audience'] ?? '' ),
			(string) ( $brief['article_goal'] ?? '' ),
			(string) ( $brief['reader_problem'] ?? '' ),
			implode( ' ', is_array( $brief['focus_points'] ?? null ) ? $brief['focus_points'] : array() ),
			(string) ( $brief['operator_instruction'] ?? '' ),
		);

		return wp_trim_words( trim( implode( ' ', array_filter( $parts ) ) ), 120, '' );
	}

	private function editor_manual_writing_pack_response( array $context, array $result ) {
		$brief   = is_array( $context['editorial_brief'] ?? null ) ? $context['editorial_brief'] : array();
		$missing = array();
		foreach ( array( 'audience', 'article_goal' ) as $field ) {
			if ( '' === trim( (string) ( $brief[ $field ] ?? '' ) ) ) {
				$missing[] = 'editorial_brief.' . $field;
			}
		}
		if ( empty( $brief['focus_points'] ) ) {
			$missing[] = 'editorial_brief.focus_points';
		}
		if ( ! empty( $missing ) ) {
			return new WP_Error(
				'npcink_toolbox_writing_pack_manual_brief_incomplete',
				__( 'Manual writing packs require an audience, article goal, and at least one focus point.', 'npcink-workflow-toolbox' ),
				array(
					'status'                  => 400,
					'missing_required_fields' => $missing,
				)
			);
		}

		$query                                     = $this->editor_writing_pack_query( $context );
		$knowledge_raw                             = $this->editor_cached_site_knowledge(
			array(
				'query'              => $query,
				'intent'             => 'writing_support_plan',
				'result_granularity' => 'document',
				'current_post_id'    => absint( $context['post_id'] ?? 0 ),
				'max_results'        => 6,
			)
		);
		$source                                    = array(
			'status'        => 'not_required',
			'url_match'     => 'not_applicable',
			'content_trust' => 'operator_supplied_brief',
			'coverage'      => array( 'mode' => 'manual_brief' ),
		);
		$review                                    = $this->editor_hosted_source_adaptation_review(
			$context,
			array( 'reader_status' => 'not_applicable' ),
			is_wp_error( $knowledge_raw ) ? array() : $knowledge_raw
		);
		$result['sections']['source_article']      = $source;
		$result['sections']['source_site_context'] = $this->editor_support_section( $knowledge_raw );
		$result['sections']['source_adaptation_review'] = $review;
		$result['sections']['article_writing_pack']     = $this->editor_article_writing_pack(
			$context,
			$source,
			$result['sections']['source_site_context'],
			$review,
			''
		);
		$result['artifact_type']                        = 'article_writing_pack.v1';
		$result['contract_version']                     = 'article_writing_pack.v1';
		$result['primary_artifact_type']                = 'article_writing_pack.v1';
		$result['input_mode']                           = 'manual_brief';
		$result['composition_role']                     = 'operator_brief_article_planning';
		$result['final_write_path']                     = 'operator_review_only_no_insert';
		$result['handoff']['final_writes']              = 'operator_review_only_no_insert';
		$result['handoff']['style_runtime']             = 'cloud_site_knowledge';
		$result['handoff']['required_input_contract']   = 'article_writing_pack.v1';
		$result['handoff']['article_generation_status'] = 'awaiting_operator_confirmation';
		$result['handoff']['body_generation']           = false;
		$result['handoff']['body_replacement']          = false;
		$result['recommendation_set']                   = Rest_Editor_Progressive_Recommendations::editor_recommendation_set( $context, 'source_adaptation_review', $result['sections'] );
		$result['content_fingerprint']                  = $result['recommendation_set']['content_fingerprint'];

		return rest_ensure_response( $result );
	}

	private function editor_article_draft_response( array $context, array $result ) {
		$review = $this->editor_confirmed_writing_pack_review( $context );
		if ( is_wp_error( $review ) ) {
			return $review;
		}
		$pack                  = $review['reviewed_writing_pack'];
		$draft_review_feedback = is_array( $context['draft_review_feedback'] ?? null ) ? $context['draft_review_feedback'] : array();
		$public_review         = $review;
		unset( $public_review['reviewed_writing_pack'] );
		$raw   = $this->editor_support_section(
			$this->editor_cached_hosted_ai_content_support(
				array(
					'intent'                => 'article_draft_from_writing_pack',
					'post_id'               => absint( $context['post_id'] ?? 0 ),
					'input_mode'            => (string) ( $pack['input_mode'] ?? 'manual_brief' ),
					'writing_pack'          => $pack,
					'writing_pack_review'   => $public_review,
					'draft_review_feedback' => $draft_review_feedback,
					'generation_variant'    => (string) ( $context['generation_variant'] ?? '' ),
				),
				! empty( $context['force_regenerate'] )
			)
		);
		$draft = $this->editor_article_draft_preview( $pack, $public_review, $raw );
		unset( $context['reviewed_writing_pack'], $context['writing_pack_confirmation'], $context['draft_review_feedback'] );
		$result['post_context']                      = $context;
		$result['artifact_type']                     = 'article_draft_preview.v1';
		$result['contract_version']                  = 'article_draft_preview.v1';
		$result['primary_artifact_type']             = 'article_draft_preview.v1';
		$result['input_mode']                        = (string) ( $pack['input_mode'] ?? 'manual_brief' );
		$result['composition_role']                  = 'confirmed_writing_pack_draft_preview';
		$result['sections']['article_writing_pack']  = $pack;
		$result['sections']['writing_pack_review']   = $public_review;
		$result['sections']['article_draft_preview'] = $draft;
		if ( ! empty( $draft_review_feedback ) ) {
			$result['sections']['draft_review_feedback'] = $draft_review_feedback;
		}
		$result['write_posture']                        = 'suggestion_only';
		$result['final_write_path']                     = 'operator_review_only_no_insert';
		$result['direct_wordpress_write']               = false;
		$result['handoff']['final_writes']              = 'operator_review_only_no_insert';
		$result['handoff']['body_generation']           = true;
		$result['handoff']['body_insertion']            = false;
		$result['handoff']['body_replacement']          = false;
		$result['handoff']['article_generation_status'] = 'draft_preview_generated_from_confirmed_pack';
		$result['content_fingerprint']                  = (string) $review['review_fingerprint'];

		return rest_ensure_response( $result );
	}

	private function editor_confirmed_writing_pack_review( array $context ) {
		$raw_pack         = $context['reviewed_writing_pack'] ?? array();
		$raw_confirmation = $context['writing_pack_confirmation'] ?? array();
		if ( is_string( $raw_pack ) ) {
			$decoded  = json_decode( $raw_pack, true );
			$raw_pack = is_array( $decoded ) ? $decoded : array();
		}
		if ( is_string( $raw_confirmation ) ) {
			$decoded          = json_decode( $raw_confirmation, true );
			$raw_confirmation = is_array( $decoded ) ? $decoded : array();
		}
		$pack         = Rest_Editor_Writing_Pack_Shaping::editor_writing_pack_payload_value( is_array( $raw_pack ) ? $raw_pack : array() );
		$confirmation = Rest_Editor_Writing_Pack_Shaping::editor_writing_pack_payload_value( is_array( $raw_confirmation ) ? $raw_confirmation : array() );
		if ( ! is_array( $pack ) || 'article_writing_pack.v1' !== (string) ( $pack['artifact_type'] ?? '' ) ) {
			return new WP_Error(
				'npcink_toolbox_writing_pack_review_invalid_artifact',
				__( 'A reviewed article_writing_pack.v1 artifact is required before draft generation.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}
		$input_mode = sanitize_key( (string) ( $pack['input_mode'] ?? '' ) );
		if ( ! in_array( $input_mode, array( 'url_reference', 'manual_brief', 'mixed' ), true ) ) {
			return new WP_Error(
				'npcink_toolbox_writing_pack_review_invalid_input_mode',
				__( 'The reviewed writing pack has an unsupported input mode.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}
		if ( $input_mode !== sanitize_key( (string) ( $context['input_mode'] ?? '' ) ) ) {
			return new WP_Error(
				'npcink_toolbox_writing_pack_review_input_mode_mismatch',
				__( 'The reviewed writing pack input mode does not match the draft request.', 'npcink-workflow-toolbox' ),
				array( 'status' => 409 )
			);
		}
		$base_fingerprint      = sanitize_text_field( (string) ( $pack['content_fingerprint'] ?? '' ) );
		$confirmed_fingerprint = sanitize_text_field( (string) ( $confirmation['base_content_fingerprint'] ?? '' ) );
		if ( empty( $confirmation['confirmed'] ) || 'confirmed_by_operator' !== sanitize_key( (string) ( $confirmation['status'] ?? '' ) ) ) {
			return new WP_Error(
				'npcink_toolbox_writing_pack_review_confirmation_required',
				__( 'Review and confirm the writing pack before generating a draft.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}
		if ( '' === $base_fingerprint || ! hash_equals( $base_fingerprint, $confirmed_fingerprint ) ) {
			return new WP_Error(
				'npcink_toolbox_writing_pack_review_fingerprint_mismatch',
				__( 'The writing pack changed after confirmation. Review and confirm the current version again.', 'npcink-workflow-toolbox' ),
				array( 'status' => 409 )
			);
		}
		$admission = is_array( $pack['generation_admission'] ?? null ) ? $pack['generation_admission'] : array();
		if ( 'blocked' === sanitize_key( (string) ( $admission['status'] ?? '' ) ) ) {
			return new WP_Error(
				'npcink_toolbox_writing_pack_generation_blocked',
				__( 'This writing pack cannot generate a draft because the source body or required writing evidence is insufficient.', 'npcink-workflow-toolbox' ),
				array(
					'status'           => 400,
					'blocking_reasons' => Rest_Editor_Writing_Pack_Shaping::editor_writing_pack_list( $admission['blocking_reasons'] ?? array(), 12 ),
				)
			);
		}
		$missing = Rest_Editor_Writing_Pack_Shaping::editor_writing_pack_required_fields( $pack );
		if ( ! empty( $missing ) ) {
			return new WP_Error(
				'npcink_toolbox_writing_pack_review_incomplete',
				__( 'The reviewed writing pack is missing fields required for draft generation.', 'npcink-workflow-toolbox' ),
				array(
					'status'                  => 400,
					'missing_required_fields' => $missing,
				)
			);
		}
		$fingerprint_basis = $pack;
		unset( $fingerprint_basis['generated_at'], $fingerprint_basis['generation_admission'] );
		$review_fingerprint = 'sha256:' . hash( 'sha256', (string) wp_json_encode( $fingerprint_basis ) );

		return array(
			'artifact_type'              => 'article_writing_pack_review.v1',
			'contract_version'           => 'article_writing_pack_review.v1',
			'status'                     => 'confirmed_by_operator',
			'base_content_fingerprint'   => $base_fingerprint,
			'review_fingerprint'         => $review_fingerprint,
			'reviewed_writing_pack'      => $pack,
			'article_generation_allowed' => true,
			'authorization_scope'        => 'single_synchronous_draft_preview_request',
			'durable_approval_state'     => false,
			'direct_wordpress_write'     => false,
		);
	}

	private function editor_article_draft_preview( array $pack, array $review, array $raw ): array {
		$output   = Rest_Editor_Writing_Pack_Shaping::editor_writing_pack_hosted_output( $raw );
		$sections = array();
		foreach ( array_slice( is_array( $output['sections'] ?? null ) ? $output['sections'] : array(), 0, 20 ) as $index => $section ) {
			$section = is_array( $section ) ? $section : array( 'body' => $section );
			/* translators: %d: One-based section number. */
			$heading = sanitize_text_field( (string) ( $section['heading'] ?? $section['title'] ?? sprintf( __( 'Section %d', 'npcink-workflow-toolbox' ), $index + 1 ) ) );
			$body    = wp_trim_words( sanitize_textarea_field( wp_strip_all_tags( (string) ( $section['body'] ?? $section['content'] ?? '' ) ) ), 700, '' );
			if ( '' !== $heading || '' !== $body ) {
				$sections[] = array(
					'heading'              => $heading,
					'body'                 => $body,
					'supporting_fact_refs' => Rest_Editor_Writing_Pack_Shaping::editor_writing_pack_list( $section['supporting_fact_refs'] ?? array(), 12 ),
				);
			}
		}
		$status = ! empty( $sections ) ? 'ready' : 'blocked';

		return array(
			'artifact_type'                   => 'article_draft_preview.v1',
			'contract_version'                => 'article_draft_preview.v1',
			'status'                          => $status,
			'title'                           => sanitize_text_field( (string) ( $output['title'] ?? '' ) ),
			'excerpt'                         => wp_trim_words( sanitize_textarea_field( wp_strip_all_tags( (string) ( $output['excerpt'] ?? '' ) ) ), 120, '' ),
			'sections'                        => $sections,
			'verification_notes'              => Rest_Editor_Writing_Pack_Shaping::editor_writing_pack_list( $output['verification_notes'] ?? array(), 20 ),
			'source_attribution_notes'        => Rest_Editor_Writing_Pack_Shaping::editor_writing_pack_list( $output['source_attribution_notes'] ?? array(), 12 ),
			'writing_pack_id'                 => sanitize_text_field( (string) ( $pack['writing_pack_id'] ?? '' ) ),
			'writing_pack_review_fingerprint' => sanitize_text_field( (string) ( $review['review_fingerprint'] ?? '' ) ),
			'write_posture'                   => 'suggestion_only',
			'operator_review_required'        => true,
			'direct_wordpress_write'          => false,
			'body_insertion'                  => false,
			'body_replacement'                => false,
			'message'                         => 'ready' === $status ? '' : __( 'The hosted runtime did not return a structured draft preview.', 'npcink-workflow-toolbox' ),
		);
	}

	private function editor_hosted_source_adaptation_review( array $context, array $source, array $knowledge ): array {
		$section                           = $this->editor_support_section(
			$this->editor_cached_hosted_ai_content_support(
				array(
					'intent'                  => 'source_adaptation_review',
					'input_mode'              => (string) ( $context['input_mode'] ?? 'url_reference' ),
					'post_id'                 => absint( $context['post_id'] ?? 0 ),
					'title'                   => (string) ( $source['title'] ?? '' ),
					'content'                 => (string) ( $source['content'] ?? '' ),
					'user_instruction'        => (string) ( $context['user_instruction'] ?? '' ),
					'generation_variant'      => (string) ( $context['generation_variant'] ?? '' ),
					'source_url'              => (string) ( $source['url'] ?? '' ),
					'source_reader_status'    => (string) ( $source['reader_status'] ?? '' ),
					'editorial_brief'         => is_array( $context['editorial_brief'] ?? null ) ? $context['editorial_brief'] : array(),
					'related_content_context' => $knowledge,
				),
				! empty( $context['force_regenerate'] )
			)
		);
		$section['provider_execution']     = 'hosted_ai_source_adaptation_review';
		$section['provider_intent']        = 'source_adaptation_review';
		$section['artifact_type']          = 'source_adaptation_review.v1';
		$section['write_posture']          = 'suggestion_only';
		$section['direct_wordpress_write'] = false;
		$section['source_url']             = esc_url_raw( (string) ( $source['url'] ?? '' ) );
		$section['source_reader_status']   = sanitize_key( (string) ( $source['reader_status'] ?? '' ) );
		$section['body_generation']        = false;
		$section['body_replacement']       = false;

		return $section;
	}

	private function editor_article_writing_pack( array $context, array $source, array $knowledge, array $review, string $source_text ): array {
		$output           = Rest_Editor_Writing_Pack_Shaping::editor_writing_pack_hosted_output( $review );
		$editorial        = is_array( $output['editorial_direction'] ?? null ) ? $output['editorial_direction'] : $output;
		$research         = is_array( $output['research_basis'] ?? null ) ? $output['research_basis'] : $output;
		$adaptation       = is_array( $output['site_adaptation'] ?? null ) ? $output['site_adaptation'] : $output;
		$plan             = is_array( $output['writing_plan'] ?? null ) ? $output['writing_plan'] : $output;
		$risk             = is_array( $output['risk_review'] ?? null ) ? $output['risk_review'] : $output;
		$input_mode       = sanitize_key( (string) ( $context['input_mode'] ?? 'url_reference' ) );
		$manual_only      = 'manual_brief' === $input_mode;
		$source_ready     = $manual_only || ( 'ready' === sanitize_key( (string) ( $source['status'] ?? '' ) )
			&& 'matched' === sanitize_key( (string) ( $source['url_match'] ?? '' ) )
			&& $this->editor_source_body_is_draftable( $source_text ) );
		$review_ready     = ! empty( $output ) && ! in_array( sanitize_key( (string) ( $review['status'] ?? '' ) ), array( 'blocked', 'error', 'failed' ), true );
		$blocking_reasons = array();
		if ( ! $source_ready ) {
			$blocking_reasons[] = 'source_body_evidence_insufficient';
		}
		if ( ! $review_ready ) {
			$blocking_reasons[] = 'writing_pack_output_not_ready';
		}

		$brief                   = is_array( $context['editorial_brief'] ?? null ) ? $context['editorial_brief'] : array();
		$operator_instruction    = trim( sanitize_textarea_field( (string) ( $brief['operator_instruction'] ?? $context['user_instruction'] ?? '' ) ) );
		$source_materials        = $manual_only ? array() : array(
			array(
				'material_type'          => 'public_url',
				'requested_url'          => esc_url_raw( (string) ( $source['requested_url'] ?? $context['source_url'] ?? '' ) ),
				'resolved_url'           => esc_url_raw( (string) ( $source['resolved_url'] ?? '' ) ),
				'title'                  => sanitize_text_field( (string) ( $source['title'] ?? '' ) ),
				'content_hash'           => sanitize_text_field( (string) ( $source['content_hash'] ?? '' ) ),
				'coverage'               => Rest_Editor_Writing_Pack_Shaping::editor_writing_pack_payload_value( $source['coverage'] ?? array() ),
				'content_trust'          => sanitize_key( (string) ( $source['content_trust'] ?? 'untrusted_external_source' ) ),
				'url_match'              => sanitize_key( (string) ( $source['url_match'] ?? '' ) ),
				'continuation_requested' => true,
				'operator_confirmed'     => false,
			),
		);
		$pack                    = array(
			'artifact_type'          => 'article_writing_pack.v1',
			'contract_version'       => 'article_writing_pack.v1',
			'composition_role'       => 'source_grounded_article_planning',
			'input_mode'             => $input_mode,
			'inputs'                 => array(
				'source_materials'    => $source_materials,
				'editorial_brief'     => array(
					'audience'             => Rest_Editor_Writing_Pack_Shaping::editor_writing_pack_resolved_field( $brief['audience'] ?? '', $editorial['audience'] ?? $editorial['inferred_audience'] ?? '' ),
					'article_goal'         => Rest_Editor_Writing_Pack_Shaping::editor_writing_pack_resolved_field( $brief['article_goal'] ?? '', $editorial['article_goal'] ?? '' ),
					'reader_problem'       => Rest_Editor_Writing_Pack_Shaping::editor_writing_pack_resolved_field( $brief['reader_problem'] ?? '', $editorial['reader_problem'] ?? '' ),
					'focus_points'         => Rest_Editor_Writing_Pack_Shaping::editor_writing_pack_resolved_field( $brief['focus_points'] ?? array(), Rest_Editor_Writing_Pack_Shaping::editor_writing_pack_list( $editorial['focus_points'] ?? $output['adaptation_directions'] ?? array(), 8 ) ),
					'operator_instruction' => array(
						'value'              => $operator_instruction,
						'source'             => '' !== $operator_instruction ? 'operator' : 'not_supplied',
						'operator_confirmed' => '' !== $operator_instruction,
					),
				),
				'site_context_policy' => array(
					'role'                               => 'overlap_tone_terminology_and_internal_reference_only',
					'factual_source_for_external_claims' => false,
					'index_lifecycle_owner'              => 'npcink_ai_cloud',
				),
			),
			'research_basis'         => array(
				'source_summary'     => $manual_only ? array() : Rest_Editor_Writing_Pack_Shaping::editor_writing_pack_list( $research['source_summary'] ?? $research['source_summary_zh'] ?? $output['source_summary_zh'] ?? array(), 6 ),
				'fact_ledger'        => $manual_only ? array() : Rest_Editor_Writing_Pack_Shaping::editor_writing_pack_list( $research['fact_ledger'] ?? array(), 16 ),
				'source_coverage'    => Rest_Editor_Writing_Pack_Shaping::editor_writing_pack_payload_value( $source['coverage'] ?? array() ),
				'verification_items' => Rest_Editor_Writing_Pack_Shaping::editor_writing_pack_list( $research['verification_items'] ?? $output['facts_to_verify'] ?? array(), 12 ),
			),
			'site_adaptation'        => array(
				'related_articles'   => Rest_Editor_Writing_Pack_Shaping::editor_writing_pack_related_articles( $knowledge ),
				'overlap_map'        => Rest_Editor_Writing_Pack_Shaping::editor_writing_pack_list( $adaptation['overlap_map'] ?? array(), 10 ),
				'site_style_signals' => Rest_Editor_Writing_Pack_Shaping::editor_writing_pack_list( $adaptation['site_style_signals'] ?? $output['site_style_signals'] ?? array(), 8 ),
				'unique_angle'       => Rest_Editor_Writing_Pack_Shaping::editor_writing_pack_resolved_field( $brief['unique_angle'] ?? '', $adaptation['unique_angle'] ?? $output['unique_angle'] ?? '' ),
			),
			'writing_plan'           => array(
				'title_directions' => Rest_Editor_Writing_Pack_Shaping::editor_writing_pack_list( ! empty( $brief['title_directions'] ) ? $brief['title_directions'] : ( $plan['title_directions'] ?? array() ), 6 ),
				'reader_promise'   => Rest_Editor_Writing_Pack_Shaping::editor_writing_pack_resolved_field( $brief['reader_promise'] ?? '', $plan['reader_promise'] ?? '' ),
				'content_type'     => Rest_Editor_Writing_Pack_Shaping::editor_writing_pack_resolved_field( $brief['content_type'] ?? '', $plan['content_type'] ?? '' ),
				'outline'          => Rest_Editor_Writing_Pack_Shaping::editor_writing_pack_list( ! empty( $brief['outline'] ) ? $brief['outline'] : ( $plan['outline'] ?? $output['suggested_outline'] ?? array() ), 12 ),
				'cta_direction'    => Rest_Editor_Writing_Pack_Shaping::editor_writing_pack_inferred_field( $plan['cta_direction'] ?? '' ),
			),
			'risk_review'            => array(
				'fact_risks'       => Rest_Editor_Writing_Pack_Shaping::editor_writing_pack_list( $risk['fact_risks'] ?? $output['facts_to_verify'] ?? array(), 12 ),
				'rights_risks'     => Rest_Editor_Writing_Pack_Shaping::editor_writing_pack_list( $risk['rights_risks'] ?? $output['copyright_and_attribution'] ?? array(), 12 ),
				'similarity_risks' => Rest_Editor_Writing_Pack_Shaping::editor_writing_pack_list( $risk['similarity_risks'] ?? array(), 10 ),
			),
			'generation_admission'   => array(
				'status'                     => empty( $blocking_reasons ) ? 'needs_review' : 'blocked',
				'blocking_reasons'           => $blocking_reasons,
				'review_requirements'        => array(
					'operator_editorial_review',
					'fact_traceability_review',
					'source_rights_confirmation',
					'similarity_and_overlap_review',
				),
				'article_generation_allowed' => false,
				'next_gate'                  => 'review_and_confirm_article_writing_pack_before_future_draft_generation',
			),
			'provenance'             => array(
				'source_facts'        => $manual_only ? 'operator_brief_no_external_fact_source' : 'cloud_exact_source_extraction',
				'site_context'        => 'cloud_site_knowledge',
				'editorial_direction' => 'hosted_ai_inference_not_operator_confirmed',
				'operator_input'      => '' !== $operator_instruction ? 'optional_instruction_only' : 'none',
			),
			'write_posture'          => 'suggestion_only',
			'final_write_path'       => 'operator_review_only_no_insert',
			'direct_wordpress_write' => false,
			'body_generation'        => false,
			'body_replacement'       => false,
		);
		$missing_required_fields = array();
		if ( empty( $pack['inputs']['editorial_brief']['audience']['value'] ) ) {
			$missing_required_fields[] = 'editorial_brief.audience';
		}
		if ( empty( $pack['inputs']['editorial_brief']['article_goal']['value'] ) ) {
			$missing_required_fields[] = 'editorial_brief.article_goal';
		}
		if ( empty( $pack['inputs']['editorial_brief']['focus_points']['value'] ) ) {
			$missing_required_fields[] = 'editorial_brief.focus_points';
		}
		if ( ! $manual_only && empty( $pack['research_basis']['fact_ledger'] ) ) {
			$missing_required_fields[] = 'research_basis.fact_ledger';
		}
		if ( empty( $pack['site_adaptation']['unique_angle']['value'] ) ) {
			$missing_required_fields[] = 'site_adaptation.unique_angle';
		}
		if ( empty( $pack['writing_plan']['outline'] ) ) {
			$missing_required_fields[] = 'writing_plan.outline';
		}
		if ( ! empty( $missing_required_fields ) ) {
			$pack['generation_admission']['status']                  = 'blocked';
			$pack['generation_admission']['blocking_reasons'][]      = 'writing_pack_required_fields_missing';
			$pack['generation_admission']['missing_required_fields'] = $missing_required_fields;
		}
		$fingerprint_basis           = $pack;
		$fingerprint                 = 'sha256:' . hash( 'sha256', (string) wp_json_encode( $fingerprint_basis ) );
		$pack['writing_pack_id']     = 'awp_' . substr( hash( 'sha256', $fingerprint ), 0, 20 );
		$pack['content_fingerprint'] = $fingerprint;
		$pack['generated_at']        = gmdate( 'c' );

		return $pack;
	}

	private function editor_summary_terms_optimization( array $context, string $query ): array {
		$related_content = $this->editor_support_section(
			$this->editor_cached_site_knowledge(
				array(
					'query'           => $query,
					'intent'          => 'related_content',
					'current_post_id' => absint( $context['post_id'] ?? 0 ),
					'max_results'     => 6,
				)
			)
		);
		$taxonomy_terms  = $this->editor_taxonomy_term_candidates( $context, $query, $related_content );
		$summary_ai      = $this->editor_support_section(
			$this->editor_cached_hosted_ai_content_support(
				array(
					'intent'                  => 'summary_terms_optimization',
					'post_id'                 => absint( $context['post_id'] ?? 0 ),
					'title'                   => (string) ( $context['title'] ?? '' ),
					'excerpt'                 => (string) ( $context['excerpt'] ?? '' ),
					'content'                 => (string) ( $context['content_text'] ?? '' ),
					'related_content_context' => $this->editor_related_content_context_for_ai( $related_content ),
				)
			)
		);
		$discoverability = $this->editor_support_section(
			$this->editor_cached_content_discoverability(
				array(
					'post_id'                 => absint( $context['post_id'] ?? 0 ),
					'title'                   => (string) ( $context['title'] ?? '' ),
					'topic'                   => $query,
					'excerpt'                 => (string) ( $context['excerpt'] ?? '' ),
					'content'                 => (string) ( $context['content_text'] ?? '' ),
					'external_search_intent'  => 'writing_context',
					'include_external_search' => true,
				)
			)
		);

		$items              = is_array( $taxonomy_terms['items'] ?? null ) ? $taxonomy_terms['items'] : array();
		$categories         = array_values(
			array_filter(
				$items,
				static fn( array $item ): bool => 'category' === (string) ( $item['taxonomy'] ?? '' )
			)
		);
		$tags               = array_values(
			array_filter(
				$items,
				static fn( array $item ): bool => 'post_tag' === (string) ( $item['taxonomy'] ?? '' )
			)
		);
		$summary_layers     = $this->editor_summary_layer_candidates( $context, $related_content );
		$proposed_new_terms = $this->empty_proposed_new_terms_review();
		$handoff_preview    = $this->editor_summary_terms_handoff_preview( $summary_layers, $categories, $tags, $proposed_new_terms );
		$metadata_delta     = $this->editor_content_metadata_delta( $context, $query, $summary_layers, $categories, $tags, $proposed_new_terms, $related_content, $discoverability, $handoff_preview );

		return array(
			'artifact_type'          => 'article_discoverability_optimization.v1',
			'composition_role'       => 'summary_taxonomy_tag_candidates',
			'candidate_type'         => 'summary_terms_optimization',
			'write_posture'          => 'suggestion_only',
			'final_write_path'       => 'core_proposal_required',
			'direct_wordpress_write' => false,
			'input_scope'            => Rest_Editor_Flow_Cache::editor_input_scope( $context ),
			'summary_candidates'     => $summary_ai,
			'summary_layers'         => $summary_layers,
			'category_candidates'    => array_slice( $categories, 0, 5 ),
			'tag_candidates'         => array_slice( $tags, 0, 8 ),
			'proposed_new_terms'     => $proposed_new_terms,
			'taxonomy_terms'         => $taxonomy_terms,
			'related_content'        => $related_content,
			'discoverability'        => $discoverability,
			'optimization_strategy'  => Rest_Editor_Summary_Terms::editor_summary_terms_strategy(),
			'review_metrics'         => Rest_Editor_Summary_Terms::editor_summary_terms_review_metrics(),
			'handoff_preview'        => $handoff_preview,
			'content_metadata_delta' => $metadata_delta,
			'risk_notes'             => array(
				__( 'Reject summaries that add facts not present in the draft, site context, or cited evidence.', 'npcink-workflow-toolbox' ),
				__( 'Prefer existing categories and tags; defer new vocabulary to a later taxonomy governance workflow.', 'npcink-workflow-toolbox' ),
				__( 'Use related Site Knowledge results to avoid duplicate coverage and taxonomy drift.', 'npcink-workflow-toolbox' ),
			),
			'handoff'                => array(
				'final_writes'           => 'core_proposal_required',
				'direct_wordpress_write' => false,
				'next_steps'             => array(
					__( 'Review the summary, category, and tag candidates in the editor.', 'npcink-workflow-toolbox' ),
					__( 'Prepare a Core proposal only after an operator chooses accepted metadata changes.', 'npcink-workflow-toolbox' ),
				),
			),
		);
	}

	private function editor_content_metadata_delta( array $context, string $query, array $summary_layers, array $categories, array $tags, array $proposed_new_terms, array $related_content, array $discoverability, array $handoff_preview ): array {
		$evidence_refs = $this->editor_content_metadata_evidence_refs( $context, $related_content, $discoverability );
		$summary_items = is_array( $summary_layers['items'] ?? null ) ? $summary_layers['items'] : array();
		$excerpt_item  = $summary_items[0] ?? array();
		$authorization = ( new Operation_Classifier() )->classify(
			array(
				'request_source'         => Operation_Classifier::SOURCE_WP_ADMIN_UI,
				'actor_presence'         => Operation_Classifier::ACTOR_PRESENT_CLICK,
				'preview_completeness'   => Operation_Classifier::PREVIEW_SUFFICIENT,
				'scope'                  => Operation_Classifier::SCOPE_ONE_OBJECT,
				'reversibility'          => Operation_Classifier::REVERSIBILITY_EASY_UNDO,
				'operation_kind'         => Operation_Classifier::KIND_SUGGEST,
				'writes_wordpress_state' => false,
			)
		);

		return array(
			'artifact_type'          => 'content_metadata_delta',
			'version'                => 1,
			'target_post_id'         => absint( $context['post_id'] ?? 0 ),
			'write_posture'          => 'suggestion_only',
			'final_write_path'       => 'core_proposal_required',
			'direct_wordpress_write' => false,
			'issue_record'           => array(
				'user_expression'  => '' !== trim( $query ) ? $query : __( 'Improve summary, category, and tag discoverability for the current draft.', 'npcink-workflow-toolbox' ),
				'target_post'      => array(
					'id'           => absint( $context['post_id'] ?? 0 ),
					'type'         => sanitize_key( (string) ( $context['post_type'] ?? 'post' ) ),
					'status'       => sanitize_key( (string) ( $context['post_status'] ?? '' ) ),
					'title'        => sanitize_text_field( (string) ( $context['title'] ?? '' ) ),
					'category_ids' => array_values( array_map( 'absint', is_array( $context['category_ids'] ?? null ) ? $context['category_ids'] : array() ) ),
					'tag_ids'      => array_values( array_map( 'absint', is_array( $context['tag_ids'] ?? null ) ? $context['tag_ids'] : array() ) ),
				),
				'observed_signals' => $this->editor_content_metadata_observed_signals( $context, $categories, $tags, $proposed_new_terms ),
				'context_refs'     => $evidence_refs,
			),
			'diagnosis'              => array(
				'summary_quality'   => $this->editor_summary_quality( $context ),
				'taxonomy_quality'  => Rest_Editor_Taxonomy_Shaping::editor_taxonomy_quality( $context, $categories, $tags, $proposed_new_terms ),
				'hypotheses'        => array(
					__( 'A clearer excerpt can improve archive, social, and answer-summary presentation without rewriting the article body.', 'npcink-workflow-toolbox' ),
					__( 'Existing WordPress terms should be reused; new vocabulary belongs in a later taxonomy governance workflow.', 'npcink-workflow-toolbox' ),
					__( 'Related Site Knowledge evidence can reveal duplicate coverage and proven term patterns.', 'npcink-workflow-toolbox' ),
				),
				'warnings'          => array(
					__( 'Do not accept summaries that add unsupported claims.', 'npcink-workflow-toolbox' ),
					__( 'Do not use the editor recommendation loop to create categories or tags.', 'npcink-workflow-toolbox' ),
					__( 'Do not treat related-content evidence as indexing or RAG lifecycle ownership inside Toolbox.', 'npcink-workflow-toolbox' ),
				),
				'evidence_strength' => empty( $evidence_refs ) ? 'draft_only' : 'draft_plus_tool_context',
			),
			'delta'                  => array(
				'excerpt'             => array(
					'recommended'   => sanitize_text_field( (string) ( $excerpt_item['value'] ?? '' ) ),
					'reason'        => sanitize_text_field( (string) ( $excerpt_item['reason'] ?? __( 'Use the short summary candidate only after operator review.', 'npcink-workflow-toolbox' ) ) ),
					'evidence_refs' => $this->editor_content_metadata_evidence_ids( $evidence_refs ),
				),
				'categories'          => $this->editor_content_metadata_term_delta_items( array_slice( $categories, 0, 5 ), $evidence_refs ),
				'tags'                => $this->editor_content_metadata_term_delta_items( array_slice( $tags, 0, 8 ), $evidence_refs ),
				'new_term_candidates' => $this->editor_content_metadata_new_term_delta_items( $proposed_new_terms ),
			),
			'authorization'          => array(
				'classification'           => sanitize_key( (string) ( $authorization['classification'] ?? Operation_Classifier::SUGGESTION_ONLY ) ),
				'reason'                   => __( 'This Content Metadata Delta only recommends excerpt and existing-term changes. Accepted writes must use the Core handoff preview and reusable WordPress abilities.', 'npcink-workflow-toolbox' ),
				'reasons'                  => array_values( array_map( 'sanitize_key', (array) ( $authorization['reasons'] ?? array() ) ) ),
				'required_evidence'        => array_values(
					array_unique(
						array_merge(
							array_map( 'sanitize_key', (array) ( $authorization['required_evidence'] ?? array() ) ),
							array(
								'operator_selected_final_excerpt_or_existing_terms',
								'exact_or_sufficient_preview_before_any_apply_action',
								'core_proposal_required_for_incomplete_preview_or_future_taxonomy_governance',
							)
						)
					)
				),
				'policy_version'           => sanitize_text_field( (string) ( $authorization['policy_version'] ?? 'operation-classification-v1' ) ),
				'local_admin_consent_note' => __( 'A later local-admin-consent path requires a present administrator, one post, exact preview, and activity evidence before any direct local apply can be considered.', 'npcink-workflow-toolbox' ),
				'handoff_preview_ref'      => sanitize_text_field( (string) ( $handoff_preview['artifact_type'] ?? 'summary_terms_handoff_preview.v1' ) ),
			),
			'outcome_contract'       => array(
				'checks' => array(
					'excerpt_reviewed_with_no_unsupported_claims',
					'existing_categories_or_tags_reused_when_possible',
					'related_content_terms_used_for_ranking_only',
					'related_content_ranking_evidence_only',
					'new_term_candidates_deferred_to_taxonomy_governance',
					'no_toolbox_direct_wordpress_write',
					'accepted_write_like_changes_route_through_core_or_future_classified_local_consent',
				),
			),
			'learning_candidates'    => array(
				'accepted_excerpt_style',
				'accepted_existing_category_or_tag_patterns',
				'future_taxonomy_gap_feedback',
				'duplicate_topic_or_taxonomy_noise_feedback',
			),
		);
	}

	private function editor_content_metadata_observed_signals( array $context, array $categories, array $tags, array $proposed_new_terms ): array {
		$signals = array();
		if ( '' === trim( (string) ( $context['excerpt'] ?? '' ) ) ) {
			$signals[] = 'missing_excerpt';
		}
		if ( empty( $context['category_ids'] ) ) {
			$signals[] = 'no_current_categories_supplied';
		}
		if ( empty( $context['tag_ids'] ) ) {
			$signals[] = 'no_current_tags_supplied';
		}
		if ( ! empty( $categories ) ) {
			$signals[] = 'existing_category_candidates_available';
		}
		if ( ! empty( $tags ) ) {
			$signals[] = 'existing_tag_candidates_available';
		}
		if ( ! empty( $proposed_new_terms['items'] ) ) {
			$signals[] = 'proposed_new_terms_require_review';
		}

		return array_values( array_unique( $signals ) );
	}

	private function editor_summary_quality( array $context ): string {
		$excerpt = trim( sanitize_textarea_field( (string) ( $context['excerpt'] ?? '' ) ) );
		if ( '' === $excerpt ) {
			return 'missing';
		}

		return strlen( $excerpt ) < 80 ? 'weak' : 'acceptable';
	}

	private function editor_related_content_context_for_ai( array $related_content ): array {
		$items         = Rest_Editor_Flow_Cache::editor_related_content_items( $related_content );
		$context_items = array();

		foreach ( array_slice( $items, 0, 6 ) as $item ) {
			$post_id         = absint( $item['post_id'] ?? 0 );
			$context_items[] = array(
				'post_id'        => $post_id,
				'title'          => sanitize_text_field( (string) ( $item['title'] ?? $item['name'] ?? '' ) ),
				'score'          => is_numeric( $item['score'] ?? null ) ? (float) $item['score'] : null,
				'excerpt'        => sanitize_textarea_field( wp_trim_words( wp_strip_all_tags( (string) ( $item['excerpt'] ?? $item['snippet'] ?? $item['content_excerpt'] ?? '' ) ), 55, '' ) ),
				'existing_terms' => Rest_Editor_Summary_Terms::editor_related_post_terms_for_context( $post_id ),
			);
		}

		return array(
			'policy' => 'related_context_for_summary_and_term_review_only_no_new_facts_no_writes',
			'items'  => array_values( array_filter( $context_items, static fn( array $item ): bool => '' !== (string) ( $item['title'] ?? '' ) || 0 < absint( $item['post_id'] ?? 0 ) ) ),
		);
	}

	private function editor_internal_link_candidates( array $context, string $query ): array {
		$source_passages          = $this->editor_internal_link_source_passages( $context );
		$source_knowledge         = $this->editor_support_section(
			$this->editor_cached_site_knowledge(
				array(
					'query'           => $query,
					'intent'          => 'internal_links',
					'current_post_id' => absint( $context['post_id'] ?? 0 ),
					'max_results'     => 8,
					'source_passages' => $source_passages,
				)
			)
		);
		$related_content_evidence = $this->editor_internal_link_graph_evidence(
			$this->editor_internal_link_related_content_evidence( $source_knowledge ),
			absint( $context['post_id'] ?? 0 )
		);
		$cloud_status             = sanitize_key( (string) ( $source_knowledge['status'] ?? '' ) );
		$retrieval_status         = 'cloud_vector_evidence';
		$source_status            = 'cloud_vector';
		if ( in_array( $cloud_status, array( 'error', 'failed' ), true ) ) {
			$retrieval_status = 'cloud_unavailable';
			$source_status    = 'cloud_unavailable';
		} elseif ( empty( $related_content_evidence ) ) {
			$retrieval_status = 'no_cloud_evidence';
			$source_status    = 'local_fallback';
		}
		$input = array(
			'current_post_id'          => absint( $context['post_id'] ?? 0 ),
			'post_type'                => sanitize_key( (string) ( $context['post_type'] ?? 'post' ) ),
			'query'                    => sanitize_textarea_field( $query ),
			'title'                    => sanitize_text_field( (string) ( $context['title'] ?? '' ) ),
			'excerpt'                  => sanitize_textarea_field( (string) ( $context['excerpt'] ?? '' ) ),
			'content_text'             => sanitize_textarea_field( (string) ( $context['content_text'] ?? '' ) ),
			'selected_text'            => sanitize_textarea_field( (string) ( $context['selected_text'] ?? '' ) ),
			'selected_block_text'      => sanitize_textarea_field( (string) ( $context['selected_block_text'] ?? '' ) ),
			'user_instruction'         => sanitize_textarea_field( (string) ( $context['user_instruction'] ?? '' ) ),
			'candidate_limit'          => 8,
			'max_targets'              => 6,
			'related_content_evidence' => $related_content_evidence,
			'candidate_source'         => $source_status,
			'content_blocks'           => is_array( $context['content_blocks'] ?? null ) ? $context['content_blocks'] : array(),
		);
		if ( 0 >= (int) $input['current_post_id'] ) {
			unset( $input['current_post_id'] );
		}
		if ( array() === $related_content_evidence ) {
			unset( $input['related_content_evidence'] );
		}

		$result = $this->editor_toolkit_internal_link_candidates( $input );
		if ( is_wp_error( $result ) ) {
			return $this->empty_toolkit_internal_link_candidates( $result, $source_knowledge );
		}

		$data     = is_array( $result['data'] ?? null ) ? $result['data'] : $result;
		$artifact = is_array( $data['internal_link_candidates'] ?? null ) ? $data['internal_link_candidates'] : array();
		if ( 'internal_link_candidates' !== (string) ( $artifact['candidate_type'] ?? '' ) ) {
			return $this->empty_toolkit_internal_link_candidates(
				new WP_Error(
					'npcink_toolbox_internal_link_toolkit_invalid_artifact',
					__( 'The Toolkit internal-link ability returned an invalid artifact.', 'npcink-workflow-toolbox' ),
					array( 'status' => 500 )
				),
				$source_knowledge
			);
		}

		$items                                 = is_array( $artifact['items'] ?? null ) ? $artifact['items'] : array();
		$items                                 = $this->editor_internal_link_merge_cloud_relevance( $items, $related_content_evidence );
		$artifact['input_scope']               = Rest_Editor_Flow_Cache::editor_input_scope( $context );
		$artifact['source_ability_id']         = 'npcink-abilities-toolkit/resolve-internal-link-targets';
		$artifact['source_knowledge']          = $source_knowledge;
		$artifact['candidate_source']          = $source_status;
		$artifact['source_status']             = $retrieval_status;
		$artifact['retrieval_status']          = $retrieval_status;
		$artifact['cloud_result_count']        = count( $related_content_evidence );
		$artifact['fallback_used']             = 'local_fallback' === $source_status;
		$artifact['toolkit_artifact']          = $data;
		$artifact['recommendation_candidates'] = $this->editor_internal_link_recommendation_candidates(
			$items,
			'cloud_vector' === $source_status && 'cloud_vector_evidence' === $retrieval_status
		);
		$artifact['final_write_path']          = 'native_editor_commit';
		$artifact['direct_wordpress_write']    = false;
		$artifact['owner_label']               = 'human_editor';
		$artifact['next_safe_action']          = 'review_and_apply_to_visible_editor';
		$artifact['action_policy']             = 'operator_confirmed_visible_editor_apply';
		$artifact['editor_transaction']        = array(
			'schema'                 => 'current_article_multi_link_result.v1',
			'max_selected'           => 8,
			'write_posture'          => 'native_editor_commit',
			'direct_wordpress_write' => false,
			'persisted'              => false,
			'partial_results'        => true,
			'undo_scope'             => 'request_scoped_editor_state',
		);
		$artifact['review_policy']             = array_merge(
			is_array( $artifact['review_policy'] ?? null ) ? $artifact['review_policy'] : array(),
			array(
				'link_insertion_owner'       => 'human_editor',
				'automatic_anchor_insert'    => false,
				'post_content_patch_handoff' => false,
				'current_post_excluded'      => true,
			)
		);
		$handoff                               = is_array( $artifact['handoff'] ?? null ) ? $artifact['handoff'] : array();
		$blocked_actions                       = is_array( $handoff['blocked_actions'] ?? null ) ? $handoff['blocked_actions'] : array();
		$handoff['final_writes']               = 'native_editor_commit';
		$handoff['direct_wordpress_write']     = false;
		$handoff['blocked_actions']            = array_values(
			array_unique(
				array_merge(
					$blocked_actions,
					array(
						'no_backend_post_content_patch',
						'no_patch_post_content_handoff_yet',
						'no_automatic_anchor_insertion',
					)
				)
			)
		);
		$artifact['handoff']                   = $handoff;

		return $artifact;
	}

	private function editor_internal_link_merge_cloud_relevance( array $items, array $evidence ): array {
		$by_post_id = array();
		$by_url     = array();
		foreach ( $evidence as $evidence_item ) {
			$post_id = absint( $evidence_item['post_id'] ?? 0 );
			$url     = esc_url_raw( (string) ( $evidence_item['url'] ?? '' ) );
			if ( $post_id > 0 ) {
				$by_post_id[ $post_id ] = $evidence_item;
			}
			if ( '' !== $url ) {
				$by_url[ $url ] = $evidence_item;
			}
		}

		foreach ( $items as &$item ) {
			$post_id = absint( $item['target_post_id'] ?? 0 );
			$url     = esc_url_raw( (string) ( $item['target_url'] ?? '' ) );
			$match   = $post_id > 0 && isset( $by_post_id[ $post_id ] )
				? $by_post_id[ $post_id ]
				: ( '' !== $url && isset( $by_url[ $url ] ) ? $by_url[ $url ] : array() );
			if ( empty( $match ) ) {
				continue;
			}
			if ( array_key_exists( 'candidate_relevance', $match ) ) {
				$item['candidate_relevance'] = $match['candidate_relevance'];
			}
		}
		unset( $item );

		return $items;
	}

	private function editor_internal_link_related_content_evidence( array $source_knowledge ): array {
		$evidence = array();
		foreach ( array_slice( Rest_Editor_Flow_Cache::editor_related_content_items( $source_knowledge ), 0, 8 ) as $index => $item ) {
			$post_id             = absint( $item['post_id'] ?? ( $item['id'] ?? 0 ) );
			$candidate_relevance = sanitize_key( (string) ( $item['candidate_relevance'] ?? '' ) );
			if ( ! in_array( $candidate_relevance, array( 'strong', 'review', 'weak' ), true ) ) {
				$candidate_relevance = '';
			}
			$evidence[] = array(
				'post_id'             => $post_id,
				'title'               => sanitize_text_field( (string) ( $item['title'] ?? $item['name'] ?? '' ) ),
				'url'                 => esc_url_raw( (string) ( $item['url'] ?? ( $item['permalink'] ?? ( $item['link'] ?? ( $item['source_url'] ?? '' ) ) ) ) ),
				'anchor_or_context'   => sanitize_text_field( (string) ( $item['anchor_or_context'] ?? ( $item['suggested_anchor_text'] ?? '' ) ) ),
				'excerpt'             => sanitize_textarea_field( wp_trim_words( wp_strip_all_tags( (string) ( $item['excerpt'] ?? $item['snippet'] ?? $item['content_excerpt'] ?? '' ) ), 55, '' ) ),
				'score'               => is_numeric( $item['score'] ?? null ) ? (float) $item['score'] : null,
				'evidence_ref'        => 'site_knowledge:' . sanitize_key( (string) ( $post_id ?: $index ) ),
				'candidate_relevance' => $candidate_relevance,
			);
		}

		return array_values(
			array_filter(
				$evidence,
				static fn( array $item ): bool => '' !== (string) ( $item['title'] ?? '' ) || '' !== (string) ( $item['url'] ?? '' ) || 0 < absint( $item['post_id'] ?? 0 )
			)
		);
	}

	private function editor_internal_link_source_passages( array $context ): array {
		$passages    = array();
		$total_chars = 0;
		$blocks      = is_array( $context['content_blocks'] ?? null ) ? $context['content_blocks'] : array();
		foreach ( array_slice( $blocks, 0, 24 ) as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$text = trim( sanitize_textarea_field( Rest_Editor_Audio_Text::trim( wp_strip_all_tags( (string) ( $block['text'] ?? '' ) ), 1200 ) ) );
			if ( '' === $text ) {
				continue;
			}
			$text_length = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
			if ( $total_chars + $text_length > 12000 ) {
				break;
			}
			$passages[]   = $text;
			$total_chars += $text_length;
		}

		return $passages;
	}

	private function editor_internal_link_graph_evidence( array $evidence, int $current_post_id ): array {
		$ability_id = 'npcink-abilities-toolkit/get-internal-link-graph-health';
		if ( empty( $evidence ) || ! function_exists( 'npcink_abilities_toolkit_get_registered' ) || ! current_user_can( 'edit_posts' ) ) {
			return $evidence;
		}

		$registered = npcink_abilities_toolkit_get_registered();
		$definition = is_array( $registered[ $ability_id ] ?? null ) ? $registered[ $ability_id ] : array();
		$callback   = $definition['execute_callback'] ?? null;
		if ( ! is_callable( $callback ) ) {
			return $evidence;
		}

		$result = call_user_func(
			$callback,
			array(
				'post_type'          => 'post',
				'status'             => 'publish',
				'per_page'           => 100,
				'page'               => 1,
				'min_outbound_links' => 1,
				'max_outbound_links' => 20,
			)
		);
		if ( is_wp_error( $result ) || ! is_array( $result ) || empty( $result['success'] ) || ! is_array( $result['data'] ?? null ) ) {
			return $evidence;
		}

		$data = $result['data'];
		$rows = array();
		foreach ( is_array( $data['items'] ?? null ) ? $data['items'] : array() as $row ) {
			$post_id = absint( $row['post_id'] ?? 0 );
			if ( $post_id > 0 ) {
				$rows[ $post_id ] = $row;
			}
		}
		$pairs = array();
		foreach ( is_array( $data['candidate_pairs'] ?? null ) ? $data['candidate_pairs'] : array() as $pair ) {
			if ( $current_post_id === absint( $pair['source_post_id'] ?? 0 ) ) {
				$pairs[ absint( $pair['target_post_id'] ?? 0 ) ] = $pair;
			}
		}

		foreach ( $evidence as &$item ) {
			$post_id                          = absint( $item['post_id'] ?? 0 );
			$row                              = is_array( $rows[ $post_id ] ?? null ) ? $rows[ $post_id ] : array();
			$pair                             = is_array( $pairs[ $post_id ] ?? null ) ? $pairs[ $post_id ] : array();
			$item['incoming_count']           = absint( $row['incoming_count'] ?? 0 );
			$item['link_graph_issues']        = array_values( array_filter( array_map( 'sanitize_key', is_array( $row['issues'] ?? null ) ? $row['issues'] : array() ) ) );
			$item['shared_terms']             = array_slice( array_values( array_filter( array_map( 'sanitize_text_field', is_array( $pair['shared_terms'] ?? null ) ? $pair['shared_terms'] : array() ) ) ), 0, 3 );
			$item['graph_evidence_available'] = ! empty( $row );
		}
		unset( $item );

		return $evidence;
	}

	private function editor_toolkit_internal_link_candidates( array $input ) {
		$ability_id = 'npcink-abilities-toolkit/resolve-internal-link-targets';
		if ( ! function_exists( 'npcink_abilities_toolkit_get_registered' ) ) {
			return new WP_Error(
				'npcink_toolbox_internal_link_toolkit_unavailable',
				__( 'Npcink Abilities Toolkit is required to build internal-link candidates.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$registered = npcink_abilities_toolkit_get_registered();
		$definition = is_array( $registered[ $ability_id ] ?? null ) ? $registered[ $ability_id ] : array();
		$callback   = $definition['execute_callback'] ?? null;
		if ( ! is_callable( $callback ) ) {
			return new WP_Error(
				'npcink_toolbox_internal_link_toolkit_unavailable',
				__( 'The Toolkit internal-link ability is not currently callable.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$result = call_user_func( $callback, $input );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! is_array( $result ) ) {
			return new WP_Error(
				'npcink_toolbox_internal_link_toolkit_invalid_response',
				__( 'The Toolkit internal-link ability returned an invalid response.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}

		return $result;
	}

	private function editor_internal_link_recommendation_candidates( array $items, bool $cloud_evidence_available ): array {
		$candidates = array();
		foreach ( array_slice( $items, 0, 8 ) as $index => $item ) {
			$title                  = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
			$anchor                 = sanitize_text_field( (string) ( $item['anchor_or_context'] ?? ( $item['suggested_anchor_text'] ?? '' ) ) );
			$url                    = esc_url_raw( (string) ( $item['target_url'] ?? '' ) );
			$target_post_id         = absint( $item['target_post_id'] ?? 0 );
			$target_status          = $target_post_id > 0 ? sanitize_key( (string) get_post_status( $target_post_id ) ) : '';
			$target_type            = $target_post_id > 0 ? sanitize_key( (string) get_post_type( $target_post_id ) ) : '';
			$verified_url           = $target_post_id > 0 ? esc_url_raw( (string) get_permalink( $target_post_id ) ) : '';
			$target_is_public_local = 'publish' === $target_status && in_array( $target_type, array( 'post', 'page' ), true ) && '' !== $verified_url;
			if ( $target_is_public_local ) {
				$url = $verified_url;
			}
			$reason              = sanitize_text_field( (string) ( $item['reason'] ?? '' ) );
			$source_match        = is_array( $item['source_match'] ?? null ) ? $item['source_match'] : array();
			$matched_anchor      = $this->editor_internal_link_safe_matched_anchor( $anchor, $source_match );
			$candidate_relevance = sanitize_key( (string) ( $item['candidate_relevance'] ?? '' ) );
			if ( ! in_array( $candidate_relevance, array( 'strong', 'review', 'weak' ), true ) ) {
				$candidate_relevance = '';
			}
			$can_apply_to_editor = $cloud_evidence_available && '' !== $matched_anchor && $target_is_public_local;
			if ( '' === $title && '' === $anchor && '' === $url ) {
				continue;
			}

			$has_score = is_numeric( $item['score'] ?? null );
			$score     = $has_score ? (float) $item['score'] : 0.0;
			if ( ! $has_score ) {
				$quality_score = 65;
			} elseif ( $score <= 1 ) {
				$quality_score = 55 + (int) round( max( 0, min( 1, $score ) ) * 35 );
			} else {
				$quality_score = max( 0, min( 90, (int) round( $score ) ) );
			}
			$quality_issues = array( __( '人工确认目标文章与当前段落或全文语义相关后再插入。', 'npcink-workflow-toolbox' ) );
			if ( '' === $url ) {
				$quality_score    = min( $quality_score, 55 );
				$quality_issues[] = __( '缺少目标 URL，插入前需要人工补充或确认。', 'npcink-workflow-toolbox' );
			}
			if ( '' === $matched_anchor ) {
				$quality_score    = min( $quality_score, 55 );
				$quality_issues[] = __( '没有安全、具体且可匹配的正文锚文本；只能复制链接或打开目标文章。', 'npcink-workflow-toolbox' );
			}
			if ( ! $target_is_public_local ) {
				$quality_score    = min( $quality_score, 55 );
				$quality_issues[] = __( '目标必须是本站已发布的文章或页面；当前候选只能复制或打开检查。', 'npcink-workflow-toolbox' );
			}

			$candidates[] = Rest_Editor_Flow_Cache::editor_recommendation_candidate(
				array(
					'id'                    => 'internal_link_' . ( $index + 1 ),
					'kind'                  => 'internal_link',
					'label'                 => '' !== $title ? $title : __( 'Internal link candidate', 'npcink-workflow-toolbox' ),
					'value'                 => $matched_anchor,
					'reason'                => $reason,
					'confidence'            => $has_score && $score > 0 && $score <= 1 ? $score : null,
					'target_field'          => 'post_content',
					'action_policy'         => $can_apply_to_editor ? 'operator_confirmed_visible_editor_apply' : 'operator_review_copy_or_open_only',
					'target_ref'            => array(
						'post_id'   => $target_post_id,
						'title'     => $title,
						'url'       => $url,
						'status'    => $target_status,
						'post_type' => $target_type,
					),
					'anchor_or_context'     => $matched_anchor,
					'anchor_quality_status' => '' !== $matched_anchor ? 'safe_exact_source_match' : 'rejected_no_safe_exact_match',
					'can_apply_to_editor'   => $can_apply_to_editor,
					'evidence_note'         => '' !== $reason ? $reason : __( 'Related content candidate for manual internal-link review.', 'npcink-workflow-toolbox' ),
					'owner_label'           => 'human_editor',
					'next_safe_action'      => $can_apply_to_editor ? 'review_and_apply_to_visible_editor' : 'copy_or_open_target_only',
					'quality_status'        => '' !== $candidate_relevance ? $candidate_relevance : ( $quality_score >= 60 && $can_apply_to_editor ? 'review' : 'weak' ),
					'quality_score'         => $quality_score,
					'quality_issues'        => $quality_issues,
					'evidence_refs'         => is_array( $item['evidence_refs'] ?? null ) ? $item['evidence_refs'] : array(),
					'source_match'          => $can_apply_to_editor ? $source_match : array(),
					'candidate_source'      => sanitize_key( (string) ( $item['candidate_source'] ?? '' ) ),
					'candidate_relevance'   => $candidate_relevance,
					'priority_reason'       => sanitize_text_field( (string) ( $item['priority_reason'] ?? '' ) ),
					'link_graph_issues'     => is_array( $item['link_graph_issues'] ?? null ) ? $item['link_graph_issues'] : array(),
					'shared_terms'          => is_array( $item['shared_terms'] ?? null ) ? $item['shared_terms'] : array(),
					'incoming_count'        => absint( $item['incoming_count'] ?? 0 ),
					'source_candidate_ref'  => '' !== $url ? $url : 'internal_link_item_' . ( $index + 1 ),
				)
			);
		}

		return $candidates;
	}

	private function editor_internal_link_safe_matched_anchor( string $anchor, array $source_match ): string {
		$matched_text  = sanitize_text_field( (string) ( $source_match['matched_text'] ?? '' ) );
		$expected_text = sanitize_text_field( (string) ( $source_match['expected_text'] ?? '' ) );
		$block_id      = sanitize_text_field( (string) ( $source_match['block_client_id'] ?? '' ) );
		$block_name    = sanitize_text_field( (string) ( $source_match['block_name'] ?? '' ) );
		$offset        = isset( $source_match['text_offset'] ) && is_numeric( $source_match['text_offset'] ) ? (int) $source_match['text_offset'] : -1;
		if ( '' === $anchor || '' === $matched_text || '' === $expected_text || '' === $block_id || in_array( $block_name, array( 'core/code', 'core-code' ), true ) || $offset < 0 ) {
			return '';
		}

		$normalize          = static function ( string $value ): string {
			$value = trim( preg_replace( '/[\p{P}\p{Z}\s]+/u', '', $value ) ?? '' );
			return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value ) : strtolower( $value );
		};
		$normalized_anchor  = $normalize( $anchor );
		$normalized_matched = $normalize( $matched_text );
		$generic_anchors    = array( '主题', '文章', '内容', '这里', '本文', '本页', 'topic', 'article', 'content', 'here', 'this', 'post', 'page', 'link', 'readmore' );
		$anchor_length      = function_exists( 'mb_strlen' ) ? mb_strlen( $normalized_matched ) : strlen( $normalized_matched );
		if ( $normalized_anchor !== $normalized_matched || $anchor_length < 4 || in_array( $normalized_matched, $generic_anchors, true ) ) {
			return '';
		}

		$matched_length = function_exists( 'mb_strlen' ) ? mb_strlen( $matched_text ) : strlen( $matched_text );
		$expected_slice = function_exists( 'mb_substr' ) ? mb_substr( $expected_text, $offset, $matched_length ) : substr( $expected_text, $offset, $matched_length );
		if ( $normalize( (string) $expected_slice ) !== $normalized_matched ) {
			return '';
		}

		return $matched_text;
	}

	private function editor_article_audio_generation( array $context, string $intent ): array {
		$source_text       = trim( (string) ( $context['content_audio_text'] ?? $context['content_full_text'] ?? $context['content_text'] ?? '' ) );
		$force_regenerate  = ! empty( $context['force_regenerate'] );
		$audio_preferences = is_array( $context['audio_preferences'] ?? null ) ? $context['audio_preferences'] : array();
		$script_source     = array(
			'artifact_type'          => 'article_audio_script_source.v1',
			'intent'                 => $intent,
			'audio_preferences'      => $audio_preferences,
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
		);

		if ( 'article_audio_summary' === $intent ) {
			$summary_ai                      = $this->editor_support_section(
				$this->editor_cached_hosted_ai_content_support(
					array(
						'intent'                  => 'audio_summary_script',
						'post_id'                 => absint( $context['post_id'] ?? 0 ),
						'title'                   => (string) ( $context['title'] ?? '' ),
						'excerpt'                 => (string) ( $context['excerpt'] ?? '' ),
						'content'                 => (string) ( $context['content_full_text'] ?? $context['content_text'] ?? '' ),
						'user_instruction'        => (string) ( $context['user_instruction'] ?? '' ),
						'generation_variant'      => (string) ( $context['generation_variant'] ?? '' ),
						'summary_generation_mode' => 'fast_brief',
					),
					$force_regenerate
				)
			);
			$script                          = Rest_Editor_Audio_Text::editor_audio_summary_script_text( $summary_ai );
			$script_source['summary_script'] = $summary_ai;
		} else {
			$script                       = Rest_Editor_Audio_Text::editor_audio_source_text( $source_text );
			$script_source['source_mode'] = 'article_text';
		}

		$audio = $this->editor_support_section(
			$this->editor_cached_audio_generation(
				array(
					'intent'            => $intent,
					'text'              => $script,
					'script'            => $script,
					'user_instruction'  => (string) ( $context['user_instruction'] ?? '' ),
					'audio_preferences' => $audio_preferences,
					'format'            => 'mp3',
					'context'           => array(
						'post_id'           => absint( $context['post_id'] ?? 0 ),
						'post_type'         => sanitize_key( (string) ( $context['post_type'] ?? 'post' ) ),
						'title'             => sanitize_text_field( (string) ( $context['title'] ?? '' ) ),
						'excerpt'           => sanitize_textarea_field( (string) ( $context['excerpt'] ?? '' ) ),
						'source_text_hash'  => md5( $source_text ),
						'user_instruction'  => sanitize_textarea_field( (string) ( $context['user_instruction'] ?? '' ) ),
						'audio_preferences' => $audio_preferences,
						'surface'           => 'editor_content_support',
					),
				),
				$force_regenerate
			)
		);

		return array(
			'artifact_type'          => 'article_audio_support.v1',
			'composition_role'       => 'article_audio_support',
			'candidate_type'         => $intent,
			'write_posture'          => 'suggestion_only',
			'final_write_path'       => 'core_proposal_required',
			'adoption_plan_route'    => '/wp-json/npcink-toolbox/v1/flows/article-audio-adoption-plan',
			'direct_wordpress_write' => false,
			'script'                 => $script,
			'script_source'          => $script_source,
			'audio_preferences'      => $audio_preferences,
			'audio'                  => $audio,
			'items'                  => is_array( $audio['items'] ?? null ) ? $audio['items'] : array(),
			'audio_generation'       => $audio,
			'use_case'               => 'article_audio_summary' === $intent ? 'longform_listening_summary' : 'full_article_narration',
			'review_policy'          => array(
				'script_review_required' => true,
				'audio_meta_owner'       => 'core_governed_handoff',
				'media_import_owner'     => 'core_governed_handoff',
				'no_post_content_patch'  => true,
			),
			'handoff'                => array(
				'final_writes'           => 'core_proposal_required',
				'adoption_plan_route'    => '/wp-json/npcink-toolbox/v1/flows/article-audio-adoption-plan',
				'direct_wordpress_write' => false,
				'blocked_actions'        => array(
					'no_media_import_in_toolbox',
					'no_post_content_patch',
					'no_direct_wordpress_write',
				),
			),
		);
	}

	private function editor_article_checkup_section( array $context ): array {
		$text       = trim( (string) ( $context['content_full_text'] ?? $context['content_text'] ?? '' ) );
		$title      = trim( (string) ( $context['title'] ?? '' ) );
		$excerpt    = trim( (string) ( $context['excerpt'] ?? '' ) );
		$paragraphs = $this->editor_article_checkup_paragraphs( $text );
		$items      = array();

		foreach ( $paragraphs as $index => $paragraph ) {
			$length           = $this->editor_text_length( $paragraph );
			$sentence_parts   = preg_split( '/[。！？!?；;]+/u', $paragraph );
			$longest_sentence = 0;
			foreach ( is_array( $sentence_parts ) ? $sentence_parts : array() as $sentence ) {
				$longest_sentence = max( $longest_sentence, $this->editor_text_length( trim( (string) $sentence ) ) );
			}
			$location = sprintf(
				/* translators: %d: paragraph number. */
				__( 'Paragraph %d', 'npcink-workflow-toolbox' ),
				$index + 1
			);

			if ( $length >= 220 ) {
				$items[] = $this->editor_article_checkup_issue(
					'paragraph_too_long_' . ( $index + 1 ),
					'format',
					'warning',
					$location,
					$paragraph,
					__( 'Paragraph is dense and may be hard to scan.', 'npcink-workflow-toolbox' ),
					__( 'Consider splitting the paragraph by claim, condition, or conclusion. Keep the editor responsible for the final wording.', 'npcink-workflow-toolbox' )
				);
			}

			if ( $longest_sentence >= 95 ) {
				$items[] = $this->editor_article_checkup_issue(
					'long_sentence_' . ( $index + 1 ),
					'clarity',
					'warning',
					$location,
					$paragraph,
					__( 'One sentence carries too many clauses.', 'npcink-workflow-toolbox' ),
					__( 'Review whether the sentence should be broken into shorter factual steps before publishing.', 'npcink-workflow-toolbox' )
				);
			}

			if ( 1 === preg_match( '/(\d|万|倍|%|百分|快|慢|耗时|性能|测试|经测试|同等|相当|无明显|明显|适合)/u', $paragraph ) ) {
				$items[] = $this->editor_article_checkup_issue(
					'fact_claim_' . ( $index + 1 ),
					'fact_gap',
					'warning',
					$location,
					$paragraph,
					__( 'The paragraph contains a metric, comparison, performance, or scope claim.', 'npcink-workflow-toolbox' ),
					__( 'Verify the source, test condition, comparison object, and applicable scope. Do not let Toolbox turn one observed result into a universal fact.', 'npcink-workflow-toolbox' )
				);
			}

			if ( 1 === preg_match( '/(绝对|完全|一键|显著|极大|领先|革命性|无敌|完美|保证)/u', $paragraph ) ) {
				$items[] = $this->editor_article_checkup_issue(
					'tone_risk_' . ( $index + 1 ),
					'tone',
					'info',
					$location,
					$paragraph,
					__( 'Tone may read stronger than the supporting evidence.', 'npcink-workflow-toolbox' ),
					__( 'Review whether the claim should be softened or tied to a specific condition.', 'npcink-workflow-toolbox' )
				);
			}

			foreach ( $this->editor_article_checkup_structure_glue_issues( $paragraph, $index + 1, $location ) as $structure_issue ) {
				$items[] = $structure_issue;
			}
		}

		$word_count  = str_word_count( $text );
		$text_length = $this->editor_text_length( $text );
		if ( '' === $title ) {
			$items[] = $this->editor_article_checkup_issue(
				'missing_title',
				'structure',
				'error',
				__( 'Title', 'npcink-workflow-toolbox' ),
				'',
				__( 'The article title is missing.', 'npcink-workflow-toolbox' ),
				__( 'Add a human-reviewed title before running title or metadata handoff actions.', 'npcink-workflow-toolbox' )
			);
		}
		if ( '' === $excerpt && ( $word_count >= 120 || $text_length >= 360 ) ) {
			$items[] = $this->editor_article_checkup_issue(
				'missing_excerpt',
				'structure',
				'warning',
				__( 'Excerpt', 'npcink-workflow-toolbox' ),
				'',
				__( 'The article has enough body content but no excerpt.', 'npcink-workflow-toolbox' ),
				__( 'Review whether a summary suggestion should be generated, then accept it manually before saving.', 'npcink-workflow-toolbox' )
			);
		}
		if ( count( $paragraphs ) >= 5 && ! $this->editor_article_checkup_has_heading_signal( $text ) ) {
			$items[] = $this->editor_article_checkup_issue(
				'missing_heading_structure',
				'structure',
				'info',
				__( 'Full article', 'npcink-workflow-toolbox' ),
				'',
				__( 'The draft is long enough to need scan-friendly structure, but no obvious heading signal was found.', 'npcink-workflow-toolbox' ),
				__( 'Review whether section headings, lists, or clearer paragraph grouping would help readers scan the article.', 'npcink-workflow-toolbox' )
			);
		}

		$format_consistency = $this->editor_article_checkup_format_consistency( $paragraphs );
		$format_items       = is_array( $format_consistency['items'] ?? null ) ? $format_consistency['items'] : array();
		foreach ( $format_items as $format_item ) {
			$items[] = $format_item;
		}

		$semantic_consistency = $this->editor_article_checkup_semantic_consistency( $text );
		$semantic_items       = is_array( $semantic_consistency['items'] ?? null ) ? $semantic_consistency['items'] : array();
		foreach ( $semantic_items as $semantic_item ) {
			$items[] = $semantic_item;
		}

		if ( empty( $items ) ) {
			$items[] = $this->editor_article_checkup_issue(
				'no_blocking_local_issue',
				'clarity',
				'info',
				__( 'Full article', 'npcink-workflow-toolbox' ),
				'',
				__( 'No high-confidence local article issues were found.', 'npcink-workflow-toolbox' ),
				__( 'This is a local heuristic check only. Run focused title, summary, taxonomy, internal-link, image, or publish preflight actions when needed.', 'npcink-workflow-toolbox' )
			);
		}

		return array(
			'artifact_type'          => 'article_checkup.v1',
			'composition_role'       => 'full_draft_review',
			'candidate_contract'     => 'article_checkup_issue.v1',
			'status'                 => 'ready',
			'provider_execution'     => 'local_article_checkup',
			'write_posture'          => 'suggestion_only',
			'final_write_path'       => 'operator_review_only',
			'direct_wordpress_write' => false,
			'format_consistency'     => $format_consistency,
			'semantic_consistency'   => $semantic_consistency,
			'items'                  => array_slice( $items, 0, 12 ),
			'summary'                => array(
				'paragraph_count'            => count( $paragraphs ),
				'issue_count'                => count( $items ),
				'format_consistency_count'   => count( $format_items ),
				'semantic_consistency_count' => count( $semantic_items ),
				'cloud_calls'                => false,
				'no_rewrite'                 => true,
			),
		);
	}

	private function editor_article_checkup_paragraphs( string $text ): array {
		$normalized = preg_replace( "/\r\n?/", "\n", $text );
		$parts      = preg_split( "/\n{2,}|(?<=[。！？!?])\s+(?=\\S)/u", is_string( $normalized ) ? $normalized : $text );
		$paragraphs = array();
		foreach ( is_array( $parts ) ? $parts : array( $text ) as $part ) {
			$paragraph = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $part ) ) ?: '' );
			if ( '' === $paragraph ) {
				continue;
			}
			$paragraphs[] = $paragraph;
			if ( count( $paragraphs ) >= 24 ) {
				break;
			}
		}
		return $paragraphs;
	}

	private function editor_article_checkup_structure_glue_issues( string $paragraph, int $paragraph_number, string $location ): array {
		$signals = array();

		if ( Rest_Editor_Paragraph_Check::editor_text_has_heading_label_glue( $paragraph ) ) {
			$signals[] = __( '标题式标签直接黏在正文前', 'npcink-workflow-toolbox' );
		}
		if ( Rest_Editor_Paragraph_Check::editor_text_has_phrase_cluster_glue( $paragraph ) ) {
			$signals[] = __( '短语组之间缺少分隔', 'npcink-workflow-toolbox' );
		}
		if ( Rest_Editor_Paragraph_Check::editor_text_has_alnum_cjk_glue( $paragraph ) ) {
			$signals[] = __( '字母、数字或方案标签与中文黏连', 'npcink-workflow-toolbox' );
		}

		if ( empty( $signals ) ) {
			return array();
		}

		return array(
			$this->editor_article_checkup_issue(
				'structural_glue_' . $paragraph_number,
				'format',
				'warning',
				$location,
				$paragraph,
				__( '标题标签、短语组或方案标签可能与正文黏连。', 'npcink-workflow-toolbox' ),
				sprintf(
					/* translators: %s: comma-separated structural glue signals. */
					__( '请检查这些分隔问题：%s。发布前可改为小标题、标点、项目符号或表格行。', 'npcink-workflow-toolbox' ),
					implode( ', ', $signals )
				)
			),
		);
	}

	private function editor_article_checkup_format_consistency( array $paragraphs ): array {
		$items = array();

		foreach ( $paragraphs as $index => $paragraph ) {
			if ( ! is_string( $paragraph ) || '' === trim( $paragraph ) ) {
				continue;
			}
			$inline_marker_count = preg_match_all( '/(?:^|[。；;：:\\s])(?:\\d+[.．、]|[（(]?\\d+[）)]|[A-Za-z][.．、])(?=\\s*[^\\s])/u', $paragraph );
			if ( (int) $inline_marker_count < 2 ) {
				continue;
			}
			$location = sprintf(
				/* translators: %d: paragraph number. */
				__( 'Paragraph %d', 'npcink-workflow-toolbox' ),
				$index + 1
			);
			$items[] = $this->editor_article_checkup_issue(
				'format_inline_list_' . ( $index + 1 ),
				'format',
				'info',
				$location,
				$paragraph,
				__( 'The paragraph looks like an inline numbered or option list.', 'npcink-workflow-toolbox' ),
				__( 'Review whether these points should become bullets, table rows, or separate paragraphs. Keep this as layout guidance only; do not auto-rewrite the article.', 'npcink-workflow-toolbox' )
			);
			if ( count( $items ) >= 3 ) {
				break;
			}
		}

		return array(
			'artifact_type'          => 'format_consistency.v1',
			'source'                 => 'current_full_draft_local_heuristic',
			'status'                 => 'ready',
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
			'no_rewrite'             => true,
			'items'                  => $items,
		);
	}

	private function editor_article_checkup_semantic_consistency( string $text ): array {
		$sentences = $this->editor_article_checkup_sentences( $text );
		$items     = array();

		foreach ( $this->editor_article_checkup_semantic_aeo_answer_order_issues( $sentences ) as $item ) {
			$items[] = $item;
			if ( count( $items ) >= 4 ) {
				break;
			}
		}

		if ( count( $items ) < 4 ) {
			foreach ( $this->editor_article_checkup_semantic_term_tensions( $sentences ) as $item ) {
				$items[] = $item;
				if ( count( $items ) >= 4 ) {
					break;
				}
			}
		}

		if ( count( $items ) < 4 ) {
			foreach ( $this->editor_article_checkup_semantic_boundary_tensions( $sentences ) as $item ) {
				$items[] = $item;
				if ( count( $items ) >= 4 ) {
					break;
				}
			}
		}

		return array(
			'artifact_type'          => 'semantic_consistency.v1',
			'status'                 => empty( $items ) ? 'clear' : 'review',
			'source'                 => 'current_full_draft_local_heuristic',
			'write_posture'          => 'suggestion_only_no_replacement_text',
			'action_policy'          => 'operator_review_only_no_insert',
			'direct_wordpress_write' => false,
			'no_rewrite'             => true,
			'items'                  => array_slice( $items, 0, 4 ),
		);
	}

	private function editor_article_checkup_sentences( string $text ): array {
		$normalized = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $text ) ) ?: '' );
		if ( '' === $normalized ) {
			return array();
		}

		$parts     = preg_split( '/(?<=[。！？!?；;])\s*/u', $normalized );
		$sentences = array();
		foreach ( is_array( $parts ) ? $parts : array( $normalized ) as $part ) {
			$sentence = trim( (string) $part );
			if ( '' === $sentence ) {
				continue;
			}
			$sentences[] = $sentence;
			if ( count( $sentences ) >= 80 ) {
				break;
			}
		}

		return $sentences;
	}

	private function editor_article_checkup_semantic_aeo_answer_order_issues( array $sentences ): array {
		$items = array();
		foreach ( $sentences as $index => $sentence ) {
			$next_sentence = isset( $sentences[ $index + 1 ] ) ? (string) $sentences[ $index + 1 ] : '';
			$window        = trim( $sentence . ' ' . $next_sentence );
			if ( 1 !== preg_match( '/(AEO|回答型体验|回答型|直接答案)/iu', $window ) ) {
				continue;
			}
			if ( 1 !== preg_match( '/(不能|不应|不要|避免)先给(出)?直接答案/u', $window ) ) {
				continue;
			}
			$items[] = $this->editor_article_checkup_issue(
				'semantic_aeo_answer_order',
				'semantic_consistency',
				'warning',
				__( 'Full article', 'npcink-workflow-toolbox' ),
				$window,
				__( 'The AEO section may reverse answer-first guidance.', 'npcink-workflow-toolbox' ),
				__( 'Confirm whether the cannot-answer-first wording is intentional. For answer-oriented content, manually verify the direct answer, conditions, steps, and limits before publishing.', 'npcink-workflow-toolbox' )
			);
			return $items;
		}

		return $items;
	}

	private function editor_article_checkup_semantic_term_tensions( array $sentences ): array {
		$terms = array( 'SEO', 'AEO', 'GEO', 'AI', 'Toolbox', 'Core', 'Cloud', 'Adapter', 'WordPress', 'OpenClaw' );
		$items = array();

		foreach ( $terms as $term ) {
			$negative = '';
			$positive = '';
			foreach ( $sentences as $sentence ) {
				if ( false === stripos( $sentence, $term ) ) {
					continue;
				}
				if ( '' === $negative && $this->editor_article_checkup_has_negative_semantic_marker( $sentence ) ) {
					$negative = $sentence;
				}
				if ( '' === $positive && $this->editor_article_checkup_has_positive_semantic_marker( $sentence ) ) {
					$positive = $sentence;
				}
				if ( '' !== $negative && '' !== $positive && $negative !== $positive ) {
					$items[] = $this->editor_article_checkup_issue(
						'semantic_term_tension_' . strtolower( $term ),
						'semantic_consistency',
						'warning',
						__( 'Full article', 'npcink-workflow-toolbox' ),
						$negative . ' ' . $positive,
						sprintf(
							/* translators: %s: term. */
							__( 'The draft uses both limiting and enabling language around %s.', 'npcink-workflow-toolbox' ),
							$term
						),
						__( 'Confirm whether the contrast is intentional, stage-specific, or a real contradiction before publishing. Keep any final wording change manual.', 'npcink-workflow-toolbox' )
					);
					break;
				}
			}
		}

		return $items;
	}

	private function editor_article_checkup_semantic_boundary_tensions( array $sentences ): array {
		$items = array();
		foreach ( $sentences as $index => $sentence ) {
			if ( ! $this->editor_article_checkup_has_free_generation_marker( $sentence ) ) {
				continue;
			}
			foreach ( $sentences as $other_index => $other_sentence ) {
				if ( $index === $other_index || ! $this->editor_article_checkup_has_review_boundary_marker( $other_sentence ) ) {
					continue;
				}
				$items[] = $this->editor_article_checkup_issue(
					'semantic_generation_boundary_' . ( $index + 1 ),
					'semantic_consistency',
					'warning',
					__( 'Full article', 'npcink-workflow-toolbox' ),
					$other_sentence . ' ' . $sentence,
					__( 'The draft mixes review-boundary wording with free-generation or replacement wording.', 'npcink-workflow-toolbox' ),
					__( 'Check whether the free-generation phrase is a counterexample or the actual recommendation. Do not turn this into an automatic rewrite.', 'npcink-workflow-toolbox' )
				);
				return $items;
			}
		}

		return $items;
	}

	private function editor_article_checkup_has_negative_semantic_marker( string $sentence ): bool {
		return 1 === preg_match( '/(不是|不能|不要|不应|不得|避免|禁止|不等于|不要让|不替换|不生成|不写入)/u', $sentence );
	}

	private function editor_article_checkup_has_positive_semantic_marker( string $sentence ): bool {
		return 1 === preg_match( '/(应该|需要|可以|用于|负责|依赖|直接|自动|一键|生成|替换|写入|发布)/u', $sentence );
	}

	private function editor_article_checkup_has_free_generation_marker( string $sentence ): bool {
		return 1 === preg_match( '/(自由发挥|生成全文|替换正文|一键优化|自动改写|自动发布|直接写入)/u', $sentence );
	}

	private function editor_article_checkup_has_review_boundary_marker( string $sentence ): bool {
		return 1 === preg_match( '/(人工|审阅|审核|建议|只读|不生成|不替换|不写入|约束|治理|Core|proposal)/iu', $sentence );
	}

	private function editor_article_checkup_has_heading_signal( string $text ): bool {
		return 1 === preg_match( '/(^|\n)\s*(#{2,6}\s+|[一二三四五六七八九十]+[、.．]|\\d+[.．、]|[（(][一二三四五六七八九十\\d]+[）)])|<h[1-6][^>]*>/iu', $text );
	}

	private function editor_article_checkup_issue( string $id, string $type, string $severity, string $location, string $evidence, string $issue, string $edit_direction ): array {
		return array(
			'id'             => sanitize_key( $id ),
			'type'           => sanitize_key( $type ),
			'severity'       => sanitize_key( $severity ),
			'location'       => sanitize_text_field( $location ),
			'evidence'       => sanitize_text_field( Rest_Editor_Audio_Text::trim( $evidence, 120 ) ),
			'issue'          => sanitize_text_field( $issue ),
			'edit_direction' => sanitize_textarea_field( $edit_direction ),
			'action_policy'  => 'operator_review_only_no_insert',
			'evidence_refs'  => array( 'current_draft:' . sanitize_key( $id ) ),
		);
	}

	private function editor_text_length( string $text ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
	}

	private function editor_hosted_draft_support( array $context, string $provider_intent ): array {
		$content = (string) ( $context['content_text'] ?? '' );
		if ( 'polish_notes' === $provider_intent ) {
			$selected = trim(
				implode(
					"\n\n",
					array_values(
						array_unique(
							array_filter(
								array_map(
									'trim',
									array(
										(string) ( $context['selected_text_full'] ?? $context['selected_text'] ?? '' ),
										(string) ( $context['selected_block_text_full'] ?? $context['selected_block_text'] ?? '' ),
									)
								)
							)
						)
					)
				)
			);
			if ( '' !== $selected ) {
				$content = $selected;
			}
		}

		$section                       = $this->editor_support_section(
			$this->editor_cached_hosted_ai_content_support(
				array(
					'intent'             => $provider_intent,
					'post_id'            => absint( $context['post_id'] ?? 0 ),
					'title'              => (string) ( $context['title'] ?? '' ),
					'excerpt'            => (string) ( $context['excerpt'] ?? '' ),
					'content'            => $content,
					'user_instruction'   => (string) ( $context['user_instruction'] ?? '' ),
					'generation_variant' => (string) ( $context['generation_variant'] ?? '' ),
				),
				! empty( $context['force_regenerate'] )
			)
		);
		$section['provider_execution'] = 'hosted_ai';
		$section['provider_intent']    = $provider_intent;
		$section['write_posture']      = 'suggestion_only';
		if ( 'polish_notes' === $provider_intent ) {
			if ( ! Rest_Editor_Paragraph_Check::editor_paragraph_check_has_output( $section ) ) {
				$section = Rest_Editor_Paragraph_Check::editor_paragraph_check_local_fallback_section( $section, $content );
			} else {
				$section = Rest_Editor_Paragraph_Check::editor_paragraph_check_local_overlay_section( $section, $content );
			}
		}

		return $section;
	}

	private function editor_fast_category_suggestions( array $context, string $query ): array {
		$taxonomy_terms = $this->editor_taxonomy_term_candidates( $context, $query );
		$items          = is_array( $taxonomy_terms['items'] ?? null ) ? $taxonomy_terms['items'] : array();
		$categories     = array_values(
			array_filter(
				$items,
				static fn( array $item ): bool => 'category' === (string) ( $item['taxonomy'] ?? '' )
			)
		);

		return Rest_Editor_Taxonomy_Shaping::editor_taxonomy_only_suggestion_section(
			'category_suggestions',
			$categories,
			array(),
			$this->empty_proposed_new_terms_review(),
			$taxonomy_terms,
			$context
		);
	}

	private function editor_fast_tag_suggestions( array $context, string $query ): array {
		$taxonomy_terms     = $this->editor_taxonomy_term_candidates( $context, $query );
		$items              = is_array( $taxonomy_terms['items'] ?? null ) ? $taxonomy_terms['items'] : array();
		$tags               = array_values(
			array_filter(
				$items,
				static fn( array $item ): bool => 'post_tag' === (string) ( $item['taxonomy'] ?? '' )
			)
		);
		$proposed_new_terms = $this->empty_proposed_new_terms_review();

		return Rest_Editor_Taxonomy_Shaping::editor_taxonomy_only_suggestion_section(
			'tag_suggestions',
			array(),
			$tags,
			$proposed_new_terms,
			$taxonomy_terms,
			$context
		);
	}

	private function editor_content_metadata_evidence_refs( array $context, array $related_content, array $discoverability ): array {
		$refs    = array();
		$post_id = absint( $context['post_id'] ?? 0 );
		if ( 0 < $post_id ) {
			$refs[] = array(
				'id'    => 'target_post:' . $post_id,
				'type'  => 'target_post',
				'label' => sanitize_text_field( (string) ( $context['title'] ?? '' ) ),
			);
		}

		$related = is_array( $related_content['results'] ?? null ) ? $related_content['results'] : ( is_array( $related_content['items'] ?? null ) ? $related_content['items'] : array() );
		foreach ( array_slice( $related, 0, 6 ) as $index => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$ref_id = (string) ( $item['post_id'] ?? ( $item['id'] ?? $index ) );
			$refs[] = array(
				'id'    => 'site_knowledge:' . sanitize_key( $ref_id ),
				'type'  => 'related_content',
				'label' => sanitize_text_field( (string) ( $item['title'] ?? $item['name'] ?? $item['url'] ?? '' ) ),
				'score' => is_numeric( $item['score'] ?? null ) ? (float) $item['score'] : null,
			);
		}

		$sources = is_array( $discoverability['external_search'] ?? null ) && is_array( $discoverability['external_search']['sources'] ?? null )
			? $discoverability['external_search']['sources']
			: ( is_array( $discoverability['sources'] ?? null ) ? $discoverability['sources'] : array() );
		foreach ( array_slice( $sources, 0, 5 ) as $index => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$refs[] = array(
				'id'    => 'source:' . ( $index + 1 ),
				'type'  => 'external_source_candidate',
				'label' => sanitize_text_field( (string) ( $item['title'] ?? $item['url'] ?? $item['source_url'] ?? '' ) ),
			);
		}

		return array_values( array_filter( $refs, static fn( array $ref ): bool => '' !== (string) ( $ref['label'] ?? '' ) || 'target_post' === (string) ( $ref['type'] ?? '' ) ) );
	}

	private function editor_content_metadata_evidence_ids( array $evidence_refs ): array {
		return array_values(
			array_filter(
				array_map(
					static fn( array $ref ): string => sanitize_text_field( (string) ( $ref['id'] ?? '' ) ),
					$evidence_refs
				)
			)
		);
	}

	private function editor_content_metadata_term_delta_items( array $items, array $evidence_refs ): array {
		$evidence_ids = $this->editor_content_metadata_evidence_ids( $evidence_refs );
		return array_values(
			array_map(
				static function ( array $item ) use ( $evidence_ids ): array {
					$score              = is_numeric( $item['score'] ?? null ) ? (float) $item['score'] : 0.0;
					$item_evidence_refs = is_array( $item['evidence_refs'] ?? null ) ? array_values(
						array_filter(
							array_map( 'sanitize_text_field', $item['evidence_refs'] )
						)
					) : array();
					return array(
						'term_id'         => absint( $item['term_id'] ?? 0 ),
						'name'            => sanitize_text_field( (string) ( $item['name'] ?? '' ) ),
						'taxonomy'        => sanitize_key( (string) ( $item['taxonomy'] ?? '' ) ),
						'confidence'      => max( 0.0, min( 1.0, $score / 5 ) ),
						'reason'          => sanitize_text_field( (string) ( $item['reason'] ?? '' ) ),
						'evidence_refs'   => array() !== $item_evidence_refs ? $item_evidence_refs : $evidence_ids,
						'match_signals'   => is_array( $item['match_signals'] ?? null ) ? array_values( array_map( 'sanitize_key', $item['match_signals'] ) ) : array(),
						'related_context' => is_array( $item['related_context'] ?? null ) ? $item['related_context'] : array(),
						'status'          => 'existing_wordpress_term',
					);
				},
				$items
			)
		);
	}

	private function editor_content_metadata_new_term_delta_items( array $proposed_new_terms ): array {
		$items = is_array( $proposed_new_terms['items'] ?? null ) ? $proposed_new_terms['items'] : array();
		return array_values(
			array_map(
				static function ( array $item ): array {
					return array(
						'taxonomy'               => sanitize_key( (string) ( $item['taxonomy'] ?? 'post_tag' ) ),
						'name'                   => sanitize_text_field( (string) ( $item['name'] ?? '' ) ),
						'reason'                 => sanitize_text_field( (string) ( $item['reason'] ?? '' ) ),
						'review_required'        => true,
						'strong_review_required' => true,
						'authorization_path'     => 'deferred_taxonomy_governance',
						'status'                 => 'deferred_taxonomy_gap',
					);
				},
				$items
			)
		);
	}

	private function editor_summary_terms_handoff_preview( array $summary_layers, array $categories, array $tags, array $proposed_new_terms ): array {
		$core_handoff_candidates = Rest_Editor_Summary_Terms::editor_summary_terms_core_handoff_candidates( $summary_layers, $categories, $tags, $proposed_new_terms );

		return array(
			'artifact_type'             => 'summary_terms_handoff_preview.v1',
			'status'                    => 'operator_selection_required',
			'write_posture'             => 'suggestion_only',
			'final_write_path'          => 'core_proposal_required',
			'direct_wordpress_write'    => false,
			'preview_only'              => true,
			'core_handoff_candidates'   => $core_handoff_candidates,
			'core_auto_approval_policy' => array(
				'request_supported'          => true,
				'toolbox_direct_apply'       => false,
				'approval_owner'             => 'npcink-governance-core',
				'execution_owner'            => 'wordpress_abilities',
				'default_safe_actions'       => array(
					'generate_apply_summary',
					'recommend_apply_tags',
				),
				'operator_review_by_default' => array(
					'recommend_categories',
				),
			),
			'available_fields'          => array(
				'summary_layers'      => $core_handoff_candidates[0]['available_fields'],
				'existing_categories' => $core_handoff_candidates[2]['available_fields'],
				'existing_tags'       => $core_handoff_candidates[1]['available_fields'],
			),
			'blocked_actions'           => array(
				'no_excerpt_update_in_toolbox',
				'no_term_assignment_in_toolbox',
				'no_new_term_creation_in_toolbox',
				'no_seo_meta_write_in_toolbox',
			),
			'next_steps'                => array(
				__( 'Use Generate and apply summary when Core policy can auto-approve the selected summary layer.', 'npcink-workflow-toolbox' ),
				__( 'Use Recommend and apply tags for existing tag ids returned by Toolbox.', 'npcink-workflow-toolbox' ),
				__( 'Use Recommend categories as review-first guidance unless Core explicitly allows category auto-assignment.', 'npcink-workflow-toolbox' ),
			),
		);
	}

	private function editor_summary_layer_candidates( array $context, array $related_content = array() ): array {
		$title                 = trim( sanitize_text_field( (string) ( $context['title'] ?? '' ) ) );
		$excerpt               = trim( sanitize_textarea_field( (string) ( $context['excerpt'] ?? '' ) ) );
		$content               = trim( wp_strip_all_tags( (string) ( $context['content_text'] ?? '' ) ) );
		$base                  = '' !== $excerpt ? $excerpt : ( '' !== $content ? $content : $title );
		$related_summary       = Rest_Editor_Summary_Terms::editor_related_content_summary( $related_content );
		$summary_evidence_refs = is_array( $related_summary['evidence_refs'] ?? null ) ? $related_summary['evidence_refs'] : array();

		return array(
			'candidate_type'          => 'summary_layer_candidates',
			'write_posture'           => 'suggestion_only',
			'direct_wordpress_write'  => false,
			'related_context_summary' => $related_summary,
			'items'                   => array(
				array(
					'id'            => 'short_summary',
					'label'         => __( 'Short summary', 'npcink-workflow-toolbox' ),
					'limit'         => '160_chars',
					'value'         => sanitize_text_field( wp_html_excerpt( $base, 160, '' ) ),
					'reason'        => __( 'Use as an excerpt-style candidate after checking related Site Knowledge evidence for duplicate coverage and term fit.', 'npcink-workflow-toolbox' ),
					'context_use'   => 'draft_grounded_related_context_checked',
					'evidence_refs' => $summary_evidence_refs,
				),
				array(
					'id'            => 'standard_summary',
					'label'         => __( 'Standard summary', 'npcink-workflow-toolbox' ),
					'limit'         => '2_3_sentences',
					'value'         => sanitize_text_field( wp_trim_words( $base, 45, '' ) ),
					'reason'        => __( 'Use for editor review where a slightly fuller article summary is useful, while keeping related content as context evidence rather than new factual material.', 'npcink-workflow-toolbox' ),
					'context_use'   => 'draft_grounded_related_context_checked',
					'evidence_refs' => $summary_evidence_refs,
				),
				array(
					'id'            => 'seo_meta_description',
					'label'         => __( 'SEO meta description', 'npcink-workflow-toolbox' ),
					'limit'         => '155_chars',
					'value'         => sanitize_text_field( wp_html_excerpt( $base, 155, '' ) ),
					'reason'        => __( 'Use only as a Core-governed SEO/meta proposal candidate after comparing the article with related public content.', 'npcink-workflow-toolbox' ),
					'context_use'   => 'draft_grounded_related_context_checked',
					'evidence_refs' => $summary_evidence_refs,
				),
			),
		);
	}

	private function editor_taxonomy_term_candidates( array $context, string $query, array $related_content = array() ): array {
		$input = array(
			'post_id'             => absint( $context['post_id'] ?? 0 ),
			'post_type'           => sanitize_key( (string) ( $context['post_type'] ?? 'post' ) ),
			'taxonomy'            => 'both',
			'query'               => sanitize_textarea_field( $query ),
			'title'               => sanitize_text_field( (string) ( $context['title'] ?? '' ) ),
			'excerpt'             => sanitize_textarea_field( (string) ( $context['excerpt'] ?? '' ) ),
			'content_text'        => sanitize_textarea_field( (string) ( $context['content_text'] ?? '' ) ),
			'selected_text'       => sanitize_textarea_field( (string) ( $context['selected_text'] ?? '' ) ),
			'selected_block_text' => sanitize_textarea_field( (string) ( $context['selected_block_text'] ?? '' ) ),
			'user_instruction'    => sanitize_textarea_field( (string) ( $context['user_instruction'] ?? '' ) ),
			'category_limit'      => 5,
			'tag_limit'           => 8,
			'candidate_limit'     => 10,
			'review_set_limit'    => 8,
		);
		if ( 0 >= (int) $input['post_id'] ) {
			unset( $input['post_id'] );
		}

		$related_term_evidence = Rest_Editor_Summary_Terms::editor_related_content_term_evidence( $related_content );
		if ( array() !== $related_term_evidence ) {
			$input['related_term_evidence'] = array_values( $related_term_evidence );
		}

		$result = Rest_Editor_Summary_Terms::editor_toolkit_taxonomy_suggestions( $input );
		if ( is_wp_error( $result ) ) {
			return $this->empty_toolkit_taxonomy_term_candidates( $result, $related_term_evidence );
		}

		$data           = is_array( $result['data'] ?? null ) ? $result['data'] : $result;
		$taxonomy_terms = is_array( $data['taxonomy_terms'] ?? null ) ? $data['taxonomy_terms'] : array();
		if ( empty( $taxonomy_terms['candidate_type'] ) || 'taxonomy_tag_candidates' !== (string) $taxonomy_terms['candidate_type'] ) {
			return $this->empty_toolkit_taxonomy_term_candidates(
				new WP_Error(
					'npcink_toolbox_taxonomy_toolkit_invalid_artifact',
					__( 'The Toolkit taxonomy suggestion ability returned an invalid artifact.', 'npcink-workflow-toolbox' ),
					array( 'status' => 500 )
				),
				$related_term_evidence
			);
		}

		$taxonomy_terms['source_ability_id']                      = 'npcink-abilities-toolkit/suggest-post-taxonomy-terms';
		$taxonomy_terms['ranking_context']['related_term_policy'] = 'ranking_evidence_only_no_term_creation_or_assignment';
		$review_set_result                                        = Rest_Editor_Summary_Terms::editor_toolkit_taxonomy_review_set( $input );
		if ( is_wp_error( $review_set_result ) ) {
			$taxonomy_terms['taxonomy_tag_review_set'] = Rest_Editor_Taxonomy_Shaping::editor_taxonomy_review_set_from_suggestions( $taxonomy_terms, $review_set_result );
		} else {
			$review_set_data                           = is_array( $review_set_result['data'] ?? null ) ? $review_set_result['data'] : $review_set_result;
			$taxonomy_terms['taxonomy_tag_review_set'] = is_array( $review_set_data ) && 'taxonomy_tag_review_set' === (string) ( $review_set_data['artifact_type'] ?? '' )
				? $review_set_data
				: Rest_Editor_Taxonomy_Shaping::editor_taxonomy_review_set_from_suggestions(
					$taxonomy_terms,
					new WP_Error(
						'npcink_toolbox_taxonomy_review_set_invalid_artifact',
						__( 'The Toolkit taxonomy review-set ability returned an invalid artifact.', 'npcink-workflow-toolbox' ),
						array( 'status' => 500 )
					)
				);
		}

		return $taxonomy_terms;
	}


	public function media_brief( WP_REST_Request $request ) {
		return $this->media_alt->media_brief( $request );
	}
	private function empty_proposed_new_terms_review(): array {
		return array(
			'candidate_type'            => 'proposed_new_terms_review',
			'write_posture'             => 'suggestion_only',
			'direct_wordpress_write'    => false,
			'creation_policy'           => 'deferred_taxonomy_governance',
			'strong_review_required'    => true,
			'duplicate_review_required' => true,
			'blocked_actions'           => array(
				'no_direct_term_creation_in_toolbox',
				'no_auto_approval_request_for_new_terms',
				'no_term_assignment_without_core_policy_review',
			),
			'items'                     => array(),
			'empty_message'             => __( 'New taxonomy creation is deferred. Use existing categories and tags in this stage.', 'npcink-workflow-toolbox' ),
		);
	}

	private function empty_toolkit_internal_link_candidates( WP_Error $error, array $source_knowledge ): array {
		$cloud_status     = sanitize_key( (string) ( $source_knowledge['status'] ?? '' ) );
		$retrieval_status = in_array( $cloud_status, array( 'error', 'failed' ), true ) ? 'cloud_unavailable' : 'no_cloud_evidence';
		$candidate_source = 'cloud_unavailable' === $retrieval_status ? 'cloud_unavailable' : 'local_fallback';
		return array(
			'artifact_type'             => 'internal_link_candidates.v1',
			'candidate_type'            => 'internal_link_candidates',
			'candidate_contract'        => 'recommendation_candidate.v1',
			'write_posture'             => 'suggestion_only',
			'final_write_path'          => 'native_editor_commit',
			'direct_wordpress_write'    => false,
			'source_ability_id'         => 'npcink-abilities-toolkit/resolve-internal-link-targets',
			'candidate_source'          => $candidate_source,
			'source_status'             => $retrieval_status,
			'retrieval_status'          => $retrieval_status,
			'cloud_result_count'        => 0,
			'fallback_used'             => 'local_fallback' === $candidate_source,
			'toolkit_required'          => true,
			'error_code'                => sanitize_key( $error->get_error_code() ),
			'error_message'             => sanitize_text_field( $error->get_error_message() ),
			'items'                     => array(),
			'recommendation_candidates' => array(),
			'owner_label'               => 'human_editor',
			'next_safe_action'          => 'fix_toolkit_or_retry_later',
			'action_policy'             => 'operator_confirmed_visible_editor_apply',
			'source_knowledge'          => $source_knowledge,
			'review_policy'             => array(
				'link_insertion_owner'       => 'human_editor',
				'automatic_anchor_insert'    => false,
				'post_content_patch_handoff' => false,
				'current_post_excluded'      => true,
			),
			'handoff'                   => array(
				'final_writes'           => 'native_editor_commit',
				'direct_wordpress_write' => false,
				'blocked_actions'        => array(
					'no_backend_post_content_patch',
					'no_patch_post_content_handoff_yet',
					'no_automatic_anchor_insertion',
				),
			),
		);
	}

	private function empty_toolkit_taxonomy_term_candidates( WP_Error $error, array $related_term_evidence ): array {
		return array(
			'candidate_type'         => 'taxonomy_tag_candidates',
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
			'source_ability_id'      => 'npcink-abilities-toolkit/suggest-post-taxonomy-terms',
			'toolkit_required'       => true,
			'error_code'             => sanitize_key( $error->get_error_code() ),
			'error_message'          => sanitize_text_field( $error->get_error_message() ),
			'ranking_context'        => array(
				'draft_query_overlap'         => true,
				'related_content_terms'       => array() !== $related_term_evidence,
				'related_term_evidence_count' => count( $related_term_evidence ),
				'related_term_policy'         => 'ranking_evidence_only_no_term_creation_or_assignment',
			),
			'items'                  => array(),
		);
	}
}
