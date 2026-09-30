<?php
/**
 * Provider_Image_Source_Service extracted from Provider_Client.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Provider_Image_Source_Service extends Provider_Client_Support {

	private Provider_Client $client;

	public function __construct( Settings $settings, Provider_Client $client ) {
		parent::__construct( $settings );
		$this->client = $client;
	}

	public function image_candidates( string $query, array $options = array() ) {
		$provider = sanitize_key( (string) ( $options['provider'] ?? 'auto' ) );
		if ( ! in_array( $provider, array( 'auto', 'cloud', 'unsplash', 'pixabay', 'pexels', 'ai_generated', 'site_media' ), true ) ) {
			$provider = 'auto';
		}

		if ( 'site_media' === $provider ) {
			return $this->search_site_media_library( $query, $options );
		}

		if ( 'ai_generated' === $provider || $this->client->should_include_ai_generated_images( $options ) ) {
			$result = $this->client->search_ai_generated_images( $query, $options );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return $this->normalize_image_source_candidates_response(
				array(
					'provider'       => 'ai_generated',
					'provider_mode'  => 'ai_generated',
					'active_sources' => array( array( 'provider' => 'ai_generated', 'count' => count( (array) ( $result['images'] ?? array() ) ) ) ),
					'images'         => is_array( $result['images'] ?? null ) ? $result['images'] : array(),
					'raw'            => is_array( $result['raw'] ?? null ) ? $result['raw'] : array(),
				),
				$query,
				'ai_generated'
			);
		}

		return $this->execute_image_source_cloud_request( $query, $options, $provider );
	}

	private function execute_image_source_cloud_request( string $query, array $options, string $provider ) {
		$per_page     = max( 1, min( 30, (int) ( $options['per_page'] ?? 9 ) ) );
		$latency_mode = $this->image_source_latency_mode( $options );
		$fast_first   = 'fast_first' === $latency_mode;
		$input        = array(
			'query'              => $query,
			'provider'           => $provider,
			'provider_origin'    => 'cloud',
			'per_page'           => $per_page,
			'latency_mode'       => $latency_mode,
			'latency_budget_seconds' => $fast_first ? 5 : 60,
			'enhancement_mode'   => $fast_first ? 'deferred' : 'inline',
			'orientation'        => sanitize_key( (string) ( $options['orientation'] ?? '' ) ),
			'color'              => sanitize_key( (string) ( $options['color'] ?? '' ) ),
			'purpose'            => sanitize_key( (string) ( $options['purpose'] ?? 'image_reference_candidate' ) ),
			'candidate_contract' => 'image_candidate.v1',
		);
		$refresh_variant = sanitize_text_field( (string) ( $options['refresh_variant'] ?? '' ) );
		if ( '' !== $refresh_variant ) {
			$input['refresh_variant'] = $refresh_variant;
		}
		if ( $fast_first ) {
			$input['deferred_cloud_ai_steps'] = array(
				'site_context_vectors',
				'candidate_rerank',
				'media_seo_suggestions',
			);
		}
		$visual_context = $this->image_visual_context_input( $query, $options, $per_page );
		if ( array() !== $visual_context ) {
			$input['visual_context'] = $visual_context;
		}
		$data_classification = $this->runtime_payload_data_classification( $input, 'public_reference_media', $options );
		$runtime_payload = array(
			'ability_name'        => 'npcink-toolbox/search-image-source',
			'contract_version'    => 'image_source_cloud_request.v1',
			'execution_pattern'   => 'inline',
			'execution_kind'      => 'image_source',
			'profile_id'          => 'image-source.managed',
			'input'               => $this->sanitize_payload( $input ),
			'data_classification' => $data_classification,
			'storage_mode'        => $this->runtime_payload_storage_mode( $data_classification ),
			'retention_ttl'       => 3600,
			'timeout_seconds'     => $fast_first ? 5 : 60,
			'http_timeout_seconds' => $fast_first ? 5 : 60,
			'connect_timeout_seconds' => self::HTTP_CONNECT_TIMEOUT,
			'retry_max'           => 0,
			'policy'              => array(
				'allow_fallback' => true,
			),
		);

		$runtime_payload = apply_filters( 'npcink_toolbox_image_source_runtime_payload', $runtime_payload, $query, $options );
		if ( ! is_array( $runtime_payload ) ) {
			return new WP_Error(
				'npcink_toolbox_invalid_image_source_runtime_payload',
				__( 'The image-source runtime payload was not valid.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}
		$runtime_payload = $this->runtime_payload_with_data_classification( $runtime_payload, 'public_reference_media', $options );

		$handled = apply_filters( 'npcink_toolbox_image_source_cloud_request', null, $runtime_payload, $query, $options );
		if ( is_wp_error( $handled ) ) {
			return $handled;
		}
		if ( is_array( $handled ) ) {
			return $this->normalize_image_source_candidates_response( $handled, $query, $provider, $runtime_payload );
		}

		$trace_id        = $this->trace_id( 'image_source' );
		$idempotency_key = $this->trace_id( 'image_source_cloud_request' );
		$request         = $this->toolbox_image_source_runtime_request( $runtime_payload );

		if ( function_exists( 'npcink_cloud_addon_execute_toolbox_image_source_runtime' ) ) {
			$response = npcink_cloud_addon_execute_toolbox_image_source_runtime( $request, $trace_id, $idempotency_key );
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			return $this->normalize_image_source_candidates_response( is_array( $response ) ? $response : array(), $query, $provider, $runtime_payload );
		}

		return new WP_Error(
			'npcink_toolbox_image_source_cloud_unavailable',
			__( 'Connect Npcink Cloud before searching managed image-source candidates. Reviewed image URLs can still be adopted from the editor image sidebar.', 'npcink-workflow-toolbox' ),
			array( 'status' => 503 )
		);
	}

	private function toolbox_image_source_runtime_request( array $runtime_payload ): array {
		$input = is_array( $runtime_payload['input'] ?? null ) ? $runtime_payload['input'] : array();

		$input['contract_version']       = 'image_source_cloud_request.v1';
		$input['profile_id']             = sanitize_text_field( (string) ( $runtime_payload['profile_id'] ?? 'image-source.managed' ) );
		$input['timeout_seconds']        = absint( $runtime_payload['timeout_seconds'] ?? 60 );
		$input['retention_ttl']          = absint( $runtime_payload['retention_ttl'] ?? 3600 );
		$input['storage_mode']           = sanitize_key( (string) ( $runtime_payload['storage_mode'] ?? 'result_only' ) );
		$input['data_classification']    = sanitize_key( (string) ( $runtime_payload['data_classification'] ?? 'public_reference_media' ) );
		$input['write_posture']          = 'suggestion_only';
		$input['direct_wordpress_write'] = false;
		$input['allow_fallback']         = ! empty( $runtime_payload['policy']['allow_fallback'] );

		return $this->sanitize_payload( $input );
	}

	private function image_visual_context_input( string $query, array $options, int $per_page ): array {
		$context = is_array( $options['visual_context'] ?? null ) ? $options['visual_context'] : array();
		if ( array() === $context && ! empty( $options['post_context'] ) && is_array( $options['post_context'] ) ) {
			$context = $options['post_context'];
		}
		$latency_mode = $this->image_source_latency_mode(
			array_merge(
				$options,
				array(
					'latency_mode' => $context['latency_mode'] ?? ( $options['latency_mode'] ?? '' ),
				)
			)
		);
		$fast_first   = 'fast_first' === $latency_mode;

		$selection = trim( sanitize_textarea_field( (string) ( $context['selected_text'] ?? $context['selected_block_text'] ?? '' ) ) );
		$title     = trim( sanitize_text_field( (string) ( $context['title'] ?? '' ) ) );
		$excerpt   = trim( sanitize_textarea_field( (string) ( $context['excerpt'] ?? '' ) ) );
		$content   = trim( sanitize_textarea_field( (string) ( $context['content_summary'] ?? $context['content_text'] ?? $context['content'] ?? '' ) ) );
		$post_id   = max( 0, absint( $context['post_id'] ?? $options['post_id'] ?? 0 ) );
		$mode      = sanitize_key( (string) ( $context['image_mode'] ?? $context['image_use'] ?? $options['image_mode'] ?? 'featured_image' ) );
		if ( ! in_array( $mode, array( 'featured_image', 'paragraph_image', 'inline_image', 'setting_image' ), true ) ) {
			$mode = 'featured_image';
		}

		$visual_context = array(
			'contract_version'       => 'image_visual_brief_request.v1',
			'locale'                 => function_exists( 'determine_locale' ) ? determine_locale() : get_locale(),
			'image_use'              => $mode,
			'latency_mode'           => $latency_mode,
			'latency_budget_seconds' => $fast_first ? 5 : 60,
			'manual_query'           => sanitize_text_field( (string) ( $context['manual_query'] ?? $options['manual_query'] ?? '' ) ),
			'fallback_query'         => sanitize_text_field( $query ),
			'refresh_variant'        => sanitize_text_field( (string) ( $context['refresh_variant'] ?? $options['refresh_variant'] ?? '' ) ),
			'post_id'                => $post_id,
			'title'                  => wp_trim_words( $title, 18, '' ),
			'excerpt'                => wp_trim_words( $excerpt, 36, '' ),
			'selected_text'          => wp_trim_words( $selection, 80, '' ),
			'content_summary'        => wp_trim_words( $content, 80, '' ),
			'selected_block_name'    => sanitize_key( (string) ( $context['selected_block_name'] ?? '' ) ),
			'query_intent'           => array(
				'rewrite_abstract_terms'       => ! empty( $context['query_intent']['rewrite_abstract_terms'] ),
				'prefer_concrete_visual_scene' => ! empty( $context['query_intent']['prefer_concrete_visual_scene'] ),
				'return_alternate_queries'     => ! empty( $context['query_intent']['return_alternate_queries'] ),
				'direction_count'              => max( 1, min( 4, absint( $context['query_intent']['direction_count'] ?? $options['direction_count'] ?? 3 ) ) ),
				'prompt_candidate_count'       => max( 1, min( 4, absint( $context['query_intent']['prompt_candidate_count'] ?? $options['prompt_candidate_count'] ?? 3 ) ) ),
			),
			'constraints'            => array(
				'avoid_brand_logos'     => ! empty( $context['avoid_brand_logos'] ),
				'prefer_editorial_safe' => true,
				'write_posture'         => 'suggestion_only',
			),
			'cloud_ai_steps'         => $fast_first
				? array( 'visual_brief' )
				: array(
					'visual_brief',
					'site_context_vectors',
					'candidate_rerank',
					'media_seo_suggestions',
				),
			'deferred_cloud_ai_steps' => $fast_first
				? array(
					'site_context_vectors',
					'candidate_rerank',
					'media_seo_suggestions',
				)
				: array(),
			'quality_filters'        => array(
				'dedupe_similar_images'       => true,
				'avoid_visible_watermarks'     => true,
				'avoid_brand_logos'            => ! empty( $context['avoid_brand_logos'] ),
				'minimum_width'                => 1200,
				'minimum_height'               => 675,
				'prefer_editorial_over_stock'  => true,
			),
			'rights_requirements'    => array(
				'preserve_attribution'         => true,
				'preserve_source_url'          => true,
				'preserve_download_location'   => true,
				'return_license_review_status' => true,
			),
			'ui_contract'            => array(
				'return_match_reason'           => ! $fast_first,
				'return_quality_tags'           => true,
				'return_risk_flags'             => true,
				'return_empty_query_suggestions' => true,
			),
			'candidate_limits'       => array(
				'returned_candidates'      => $per_page,
				'max_source_candidates'    => $fast_first ? max( $per_page, min( 12, max( 8, $per_page * 2 ) ) ) : max( $per_page, min( 30, max( 20, $per_page * 3 ) ) ),
				'max_site_context_results' => $fast_first ? 0 : 4,
			),
			'fallback_policy'        => array(
				'plain_image_search' => true,
				'defer_rerank'       => $fast_first,
				'keep_candidate_order_when_rerank_unavailable' => true,
			),
			'data_minimization'      => array(
				'full_post_content_sent' => false,
				'content_truncated'      => true,
			),
		);

		if ( '' === $visual_context['title'] && '' === $visual_context['excerpt'] && '' === $visual_context['selected_text'] && '' === $visual_context['content_summary'] && '' === $visual_context['manual_query'] ) {
			return array();
		}

		return $this->sanitize_payload( $visual_context );
	}

	private function normalize_image_visual_brief( array $result, array $runtime_payload ): array {
		$input = is_array( $runtime_payload['input'] ?? null ) ? $runtime_payload['input'] : array();
		$brief = array();
		foreach ( array( 'visual_brief', 'search_brief', 'image_brief' ) as $key ) {
			if ( is_array( $result[ $key ] ?? null ) ) {
				$brief = $result[ $key ];
				break;
			}
		}

		$primary_query = sanitize_text_field( (string) ( $brief['primary_query'] ?? $result['primary_query'] ?? $result['optimized_query'] ?? $input['query'] ?? '' ) );
		$visual_intent = sanitize_textarea_field( (string) ( $brief['visual_intent'] ?? $result['visual_intent'] ?? '' ) );
		$style = sanitize_text_field( (string) ( $brief['style'] ?? $result['style'] ?? '' ) );
		$orientation = sanitize_key( (string) ( $brief['preferred_orientation'] ?? $input['orientation'] ?? '' ) );

		return array(
			'status'                => sanitize_key( (string) ( $result['visual_brief_status'] ?? $result['brief_status'] ?? ( array() !== $brief ? 'ready' : 'fallback' ) ) ),
			'primary_query'         => $primary_query,
			'visual_intent'         => $visual_intent,
			'query_suggestions'     => $this->sanitize_image_query_suggestions( $brief['query_suggestions'] ?? $result['query_suggestions'] ?? $result['empty_query_suggestions'] ?? array() ),
			'negative_terms'        => array_slice( $this->sanitize_string_list( $brief['negative_terms'] ?? $result['negative_terms'] ?? array() ), 0, 8 ),
			'preferred_orientation' => $orientation,
			'style'                 => $style,
			'match_criteria'        => array_slice( $this->sanitize_string_list( $brief['match_criteria'] ?? $result['match_criteria'] ?? array() ), 0, 8 ),
			'site_context_status'   => sanitize_key( (string) ( $result['site_context_status'] ?? $result['vector_context_status'] ?? '' ) ),
			'rerank_status'         => sanitize_key( (string) ( $result['rerank_status'] ?? $result['candidate_rerank_status'] ?? '' ) ),
			'cloud_ai_steps'        => $this->sanitize_string_list( $input['visual_context']['cloud_ai_steps'] ?? array() ),
		);
	}

	private function sanitize_image_query_suggestions( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$suggestions = array();
		foreach ( array_slice( $value, 0, 5 ) as $item ) {
			if ( is_array( $item ) ) {
				$label = sanitize_text_field( html_entity_decode( (string) ( $item['display_label'] ?? $item['label'] ?? $item['query'] ?? '' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
				$query = sanitize_text_field( html_entity_decode( (string) ( $item['search_query'] ?? $item['query'] ?? $item['display_label'] ?? '' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
				if ( '' !== $label && '' !== $query ) {
					$suggestions[] = array(
						'display_label' => $label,
						'search_query'  => $query,
					);
				}
				continue;
			}
			$query = sanitize_text_field( html_entity_decode( (string) $item, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			if ( '' !== $query ) {
				$suggestions[] = array(
					'display_label' => $query,
					'search_query'  => $query,
				);
			}
		}
		return $suggestions;
	}

	private function normalize_image_source_candidates_response( array $response, string $query, string $provider_mode, array $runtime_payload = array() ): array {
		$result = $this->extract_cloud_runtime_result( $response );

		$images = $this->extract_image_source_candidate_items( $result );

		$contract_images = array();
		foreach ( array_slice( $this->dedupe_image_candidates( $images ), 0, max( 1, min( 30, (int) ( $runtime_payload['input']['per_page'] ?? 8 ) ) ) ) as $image ) {
			if ( is_array( $image ) ) {
				$image['provider_origin'] = $image['provider_origin'] ?? 'cloud';
				$contract_images[]        = $this->client->normalize_image_candidate_contract( $image );
			}
		}

		$active_sources = is_array( $result['active_sources'] ?? null ) ? $this->sanitize_payload( $result['active_sources'] ) : array();
		if ( array() === $active_sources && $provider_mode ) {
			$active_sources[] = array(
				'provider' => 'cloud' === $provider_mode || 'auto' === $provider_mode ? 'cloud_image_sources' : $provider_mode,
				'count'    => count( $contract_images ),
			);
		}
		$resolved_provider = sanitize_key( (string) ( $result['resolved_provider'] ?? $result['provider_mode'] ?? '' ) );
		if ( '' === $resolved_provider && is_array( $active_sources[0] ?? null ) ) {
			$resolved_provider = sanitize_key( (string) ( $active_sources[0]['provider'] ?? '' ) );
		}
		$visual_brief = $this->normalize_image_visual_brief( $result, $runtime_payload );
		$prompt_candidates = is_array( $result['prompt_candidates'] ?? null ) ? $this->sanitize_payload( $result['prompt_candidates'] ) : array();
		$ai_generation_handoff = is_array( $result['ai_generation_handoff'] ?? null ) ? $this->sanitize_payload( $result['ai_generation_handoff'] ) : array();
		$result_handoff        = is_array( $result['handoff'] ?? null ) ? $this->sanitize_payload( $result['handoff'] ) : array();
		if ( array() !== $ai_generation_handoff ) {
			$result_handoff['ai_generation_handoff'] = $ai_generation_handoff;
			$actions = is_array( $result_handoff['available_actions'] ?? null ) ? $result_handoff['available_actions'] : array();
			if ( ! in_array( 'ai_generation_handoff', $actions, true ) ) {
				$actions[] = 'ai_generation_handoff';
			}
			$result_handoff['available_actions'] = array_values( $actions );
		}

		$payload = $this->with_output_contract(
			array(
				'provider'                   => 'npcink_cloud',
				'provider_mode'              => $provider_mode,
				'requested_provider_mode'    => sanitize_key( (string) ( $result['requested_provider_mode'] ?? $provider_mode ) ),
				'resolved_provider'          => $resolved_provider,
				'auto_strategy'              => sanitize_key( (string) ( $result['auto_strategy'] ?? '' ) ),
				'candidate_contract_version' => 'image_candidate.v1',
				'cloud_ability'              => sanitize_text_field( (string) ( $runtime_payload['ability_name'] ?? 'npcink-toolbox/search-image-source' ) ),
				'cloud_runtime'              => 'npcink_cloud_addon',
				'status'                     => sanitize_key( (string) ( $result['status'] ?? $response['status'] ?? 'unknown' ) ),
				'message'                    => sanitize_text_field( (string) ( $result['message'] ?? $result['error_message'] ?? $response['message'] ?? '' ) ),
				'retrieval_readiness'        => is_array( $result['retrieval_readiness'] ?? null ) ? $this->sanitize_payload( $result['retrieval_readiness'] ) : array(),
				'candidate_source_count'     => count( $images ),
				'result_count'               => count( $contract_images ),
				'active_sources'             => $active_sources,
					'provider_errors'            => is_array( $result['provider_errors'] ?? null ) ? $this->sanitize_payload( $result['provider_errors'] ) : array(),
					'query'                      => $query,
					'visual_brief'               => $visual_brief,
					'prompt_candidates'          => $prompt_candidates,
					'optimized_query'            => sanitize_text_field( (string) ( $result['optimized_query'] ?? $visual_brief['primary_query'] ?? $query ) ),
					'query_suggestions'          => $visual_brief['query_suggestions'],
					'rerank_status'              => $visual_brief['rerank_status'],
					'site_context_status'        => $visual_brief['site_context_status'],
				'images'                     => $contract_images,
				'handoff'                    => array(
					'candidate_contract'    => 'image_candidate.v1',
					'final_writes'          => 'core_proposal_required',
					'direct_wordpress_write' => false,
				) + $result_handoff,
				'ai_generation_handoff'      => $ai_generation_handoff,
			),
			'image_source_candidates',
			'image_source_candidates'
		);

		return $this->with_optional_raw( $payload, is_array( $response['raw'] ?? null ) ? $response['raw'] : $response );
	}

	private function extract_image_source_candidate_items( array $result ): array {
		if ( $this->is_list( $result ) ) {
			return array_values( array_filter( $result, 'is_array' ) );
		}

		foreach ( array( 'images', 'image_source_candidates', 'source_candidates', 'media_candidates', 'assets', 'candidates', 'image_candidates', 'results', 'items', 'photos' ) as $key ) {
			if ( ! is_array( $result[ $key ] ?? null ) ) {
				continue;
			}

			$value = $result[ $key ];
			if ( $this->is_list( $value ) ) {
				return array_values( array_filter( $value, 'is_array' ) );
			}

			$nested = $this->extract_image_source_candidate_items( $value );
			if ( array() !== $nested ) {
				return $nested;
			}
		}

		foreach ( array( 'payload', 'data', 'result', 'output', 'response' ) as $key ) {
			if ( is_array( $result[ $key ] ?? null ) ) {
				$nested = $this->extract_image_source_candidate_items( $result[ $key ] );
				if ( array() !== $nested ) {
					return $nested;
				}
			}
		}

		return array();
	}

	private function image_source_latency_mode( array $options ): string {
		$mode = sanitize_key( (string) ( $options['latency_mode'] ?? $options['image_latency_mode'] ?? '' ) );
		return 'fast_first' === $mode ? 'fast_first' : 'complete';
	}

	private function dedupe_image_candidates( array $images ): array {
		$seen = array();
		$out  = array();

		foreach ( $images as $image ) {
			if ( ! is_array( $image ) ) {
				continue;
			}

			$key = (string) ( $image['source_url'] ?? $image['html_url'] ?? $image['regular_url'] ?? $image['id'] ?? '' );
			if ( '' === $key || isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$out[]        = $image;
		}

		return $out;
	}

	private function search_site_media_library( string $query, array $options ) {
		$knowledge = $this->client->search_site_knowledge(
			array(
				'query'              => $query,
				'intent'             => 'media_library_search',
				'max_results'        => max( 1, min( 10, absint( $options['per_page'] ?? 9 ) ) ),
				'result_granularity' => 'document',
				'filters'            => array(
					'post_types'  => array( 'attachment' ),
					'status'      => array( 'publish' ),
					'source_types' => array( 'media' ),
				),
			)
		);
		if ( is_wp_error( $knowledge ) ) {
			return $knowledge;
		}
		$results = is_array( $knowledge['results'] ?? null ) ? $knowledge['results'] : array();
		$status = sanitize_key( (string) ( $knowledge['status'] ?? 'ready' ) );
		$retrieval_readiness = is_array( $knowledge['retrieval_readiness'] ?? null ) ? $knowledge['retrieval_readiness'] : array();
		$message = '';
		if (
			'not_ready' === $status
			&& 'semantic_embedding_required' === sanitize_key( (string) ( $retrieval_readiness['status'] ?? '' ) )
		) {
			$message = __( 'Site media semantic search is not ready. Configure the development embedding service, then refresh the media index.', 'npcink-workflow-toolbox' );
		}
		$attachment_ids = array_values(
			array_unique(
				array_filter(
					array_map(
						static fn( $result ): int => absint( is_array( $result ) ? ( $result['source_id'] ?? $result['post_id'] ?? 0 ) : 0 ),
						$results
					)
				)
			)
		);
		$inventory = $this->client->toolkit_media_inventory(
			array(
				'mime_type'      => 'image',
				'attachment_ids' => array_slice( $attachment_ids, 0, 20 ),
				'page'           => 1,
				'per_page'       => 20,
			)
		);
		if ( is_wp_error( $inventory ) ) {
			return $inventory;
		}
		$rows = array();
		foreach ( (array) ( $inventory['items'] ?? array() ) as $item ) {
			if ( is_array( $item ) ) {
				$rows[ absint( $item['attachment_id'] ?? 0 ) ] = $item;
			}
		}
		$evidence_by_attachment_id = array();
		$status                    = $this->client->get_site_knowledge_status(
			array(
				'media_attachment_ids' => array_slice( $attachment_ids, 0, 20 ),
			)
		);
		if ( is_array( $status ) ) {
			foreach ( (array) ( $status['media_evidence_items'] ?? array() ) as $evidence_item ) {
				if ( ! is_array( $evidence_item ) ) {
					continue;
				}
				$evidence_attachment_id = absint( $evidence_item['attachment_id'] ?? 0 );
				$visual_evidence        = is_array( $evidence_item['visual_evidence'] ?? null ) ? $evidence_item['visual_evidence'] : array();
				if ( $evidence_attachment_id <= 0 || 'ready' !== sanitize_key( (string) ( $visual_evidence['status'] ?? '' ) ) ) {
					continue;
				}
				$evidence_by_attachment_id[ $evidence_attachment_id ] = array(
					'media_fingerprint' => sanitize_text_field( (string) ( $evidence_item['media_fingerprint'] ?? '' ) ),
					'alt_text_basis'    => sanitize_text_field( (string) ( $visual_evidence['alt_text_basis'] ?? '' ) ),
					'visual_summary'    => sanitize_textarea_field( (string) ( $visual_evidence['visual_summary'] ?? '' ) ),
					'evidence_reuse'    => sanitize_key( (string) ( $visual_evidence['evidence_reuse'] ?? 'site_knowledge_projection' ) ),
					'visual_reuse_policy' => sanitize_key( (string) ( $visual_evidence['visual_reuse_policy'] ?? '' ) ),
				);
			}
		}
		$images = array();
		foreach ( $results as $result ) {
			$attachment_id = absint( is_array( $result ) ? ( $result['source_id'] ?? $result['post_id'] ?? 0 ) : 0 );
			$item = is_array( $rows[ $attachment_id ] ?? null ) ? $rows[ $attachment_id ] : array();
			if ( $attachment_id <= 0 || empty( $item['url'] ) ) {
				continue;
			}
			$format            = is_array( $item['format_inspection'] ?? null ) ? $item['format_inspection'] : array();
			$media_fingerprint = sanitize_text_field( (string) ( $item['media_fingerprint'] ?? '' ) );
			$visual_evidence   = is_array( $evidence_by_attachment_id[ $attachment_id ] ?? null ) ? $evidence_by_attachment_id[ $attachment_id ] : array();
			$visual_reuse_policy = $this->client->media_visual_evidence_reuse_policy( $attachment_id, $media_fingerprint, (string) ( $visual_evidence['media_fingerprint'] ?? '' ), $visual_evidence );
			if (
				'' === $visual_reuse_policy
			) {
				$visual_evidence = array();
				$visual_reuse_policy = '';
			}
			$suggested_alt = sanitize_text_field( (string) ( $visual_evidence['alt_text_basis'] ?? '' ) );
			$images[] = array(
				'id'                 => 'site-media-' . $attachment_id,
				'attachment_id'      => $attachment_id,
				'candidate_contract' => 'image_candidate.v1',
				'provider'           => 'site_media',
				'source'             => 'site_media_library',
				'source_type'        => 'owned',
				'provider_origin'    => 'wordpress_local',
				'title'              => sanitize_text_field( (string) ( $item['title'] ?? '' ) ),
				'description'        => sanitize_textarea_field( (string) ( $result['chunk'] ?? $item['description'] ?? '' ) ),
				'alt_description'    => sanitize_text_field( (string) ( $item['alt'] ?? '' ) ),
				'url'                => esc_url_raw( (string) $item['url'] ),
				'preview_url'        => esc_url_raw( (string) $item['url'] ),
				'download_url'       => esc_url_raw( (string) $item['url'] ),
				'mime_type'          => sanitize_text_field( (string) ( $item['mime_type'] ?? '' ) ),
				'width'              => absint( $format['width'] ?? 0 ),
				'height'             => absint( $format['height'] ?? 0 ),
				'match_score'        => (float) ( $result['score'] ?? 0 ),
				'match_reason'       => sanitize_text_field( (string) ( $result['reason'] ?? '' ) ),
				'media_fingerprint'  => $media_fingerprint,
				'suggested_alt'      => $suggested_alt,
				'visual_summary'     => sanitize_textarea_field( (string) ( $visual_evidence['visual_summary'] ?? '' ) ),
				'evidence_reuse'     => sanitize_key( (string) ( $visual_evidence['evidence_reuse'] ?? '' ) ),
				'visual_reuse_policy' => $visual_reuse_policy,
				'needs_human_visual_check' => 'reuse_with_human_check' === $visual_reuse_policy,
				'seo_suggestions'    => '' !== $suggested_alt ? array( 'alt' => $suggested_alt ) : array(),
				'requires_local_review' => true,
				'direct_wordpress_write' => false,
			);
		}

		return $this->normalize_image_source_candidates_response(
			array(
				'provider'       => 'site_media',
				'provider_mode'  => 'site_media',
				'active_sources' => array( array( 'provider' => 'site_media', 'count' => count( $images ) ) ),
				'images'         => $images,
				'status'         => $status,
				'message'        => $message,
				'retrieval_readiness' => $this->sanitize_payload( $retrieval_readiness ),
			),
			$query,
			'site_media',
			array( 'input' => array( 'per_page' => max( 1, min( 10, absint( $options['per_page'] ?? 9 ) ) ) ) )
		);
	}
}
