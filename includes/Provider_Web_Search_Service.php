<?php
/**
 * Cloud-managed web search service for the provider client.
 *
 * Owns the Cloud web search test/diagnostic bridges, the shared evidence
 * helpers that article and discoverability flows attach to suggestions, and
 * the Cloud web search response normalization. Provider routing, keys, and
 * quota stay Cloud owned; Toolbox never runs a local search provider.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Provider_Web_Search_Service extends Provider_Client_Support {
	private Provider_Client $client;

	public function __construct( Settings $settings, Provider_Client $client ) {
		parent::__construct( $settings );
		$this->client = $client;
	}

	public function cloud_web_search_notice(): array {
		return array(
			'provider'       => 'cloud_web_search',
			'provider_mode'  => 'cloud_managed',
			'active_sources' => array(),
			'results'        => array(),
			'status'         => 'cloud_managed',
			'message'        => __( 'External web search is provided by Npcink Cloud. Toolbox no longer stores local web search provider configuration.', 'npcink-workflow-toolbox' ),
		);
	}


	private function cloud_web_search_error_notice( WP_Error $error ): array {
		$notice                 = $this->cloud_web_search_notice();
		$notice['status']       = 'failed';
		$notice['error_code']   = sanitize_key( (string) $error->get_error_code() );
		$notice['error']        = sanitize_text_field( $error->get_error_message() );
		$notice['result_count'] = 0;

		return $notice;
	}


	public function cloud_web_search_for_content( string $query, string $intent = 'writing_context', int $max_results = 3 ): array {
		$result = $this->test_cloud_web_search(
			array(
				'query'        => $query,
				'intent'       => $intent,
				'provider'     => 'auto',
				'max_results'  => $max_results,
				'recency_days' => 'news' === $intent ? 7 : 30,
			)
		);

		return is_wp_error( $result ) ? $this->cloud_web_search_error_notice( $result ) : $result;
	}


	public function test_cloud_web_search( array $input ) {
		$query = trim( sanitize_textarea_field( (string) ( $input['query'] ?? '' ) ) );
		if ( '' === $query ) {
			return new WP_Error(
				'npcink_toolbox_missing_web_search_query',
				__( 'A query is required for Cloud web search testing.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$intent = sanitize_key( (string) ( $input['intent'] ?? 'news' ) );
		if ( ! in_array( $intent, array( 'general_research', 'article_background', 'fact_check', 'news', 'writing_context', 'competitor_research', 'pricing_snapshot', 'product_comparison', 'source_discovery', 'source_extraction_preview', 'external_links', 'zhihu_global_search', 'zhihu_research', 'zhihu_hot_topics', 'zhida_simple', 'zhida_deep', 'zhida_deepsearch' ), true ) ) {
			$intent = 'news';
		}

		$max_results  = max( 1, min( 5, absint( $input['max_results'] ?? 3 ) ) );
		$recency_days = max( 0, min( 30, absint( $input['recency_days'] ?? 7 ) ) );
		$managed_source = sanitize_key( (string) ( $input['managed_source'] ?? '' ) );
		$runtime_input = array(
			'contract_version'    => 'web_search.v1',
			'query'               => $query,
			'intent'              => $intent,
			'max_results'         => $max_results,
			'recency_days'        => $recency_days,
			'evidence_policy'     => array(
				'required_sources' => 1,
				'no_hit_policy'    => 'abstain',
			),
			'write_posture'       => 'suggestion_only',
		);
		if ( 'source_extraction_preview' === $intent ) {
			$runtime_input['source_url'] = esc_url_raw( (string) ( $input['source_url'] ?? '' ), array( 'http', 'https' ) );
		}
		$allowed_domains = $this->sanitize_string_list( $input['allowed_domains'] ?? array() );
		if ( ! empty( $allowed_domains ) ) {
			$runtime_input['allowed_domains'] = array_slice( $allowed_domains, 0, 3 );
		}
		if ( ! empty( $input['enhance_with_reader'] ) ) {
			$runtime_input['enhance_with_reader'] = true;
		}
		if ( 'zhihu_research' === $managed_source ) {
			$runtime_input['provider']         = 'zhihu';
			$runtime_input['source_type']      = 'zhihu_research';
		}
		if ( 'zhihu_hot_topics' === $managed_source ) {
			$runtime_input['provider']         = 'zhihu';
			$runtime_input['managed_source']   = 'zhihu_hot_topics';
			$runtime_input['source_type']      = 'zhihu_hot_list';
		}
		if ( 'zhihu_global_search' === $managed_source ) {
			$runtime_input['provider']         = 'zhihu';
			$runtime_input['source_type']      = 'zhihu_global_search';
		}
		if ( in_array( $managed_source, array( 'zhida_simple', 'zhida_deep', 'zhida_deepsearch' ), true ) ) {
			$runtime_input['provider']         = 'zhihu';
			$runtime_input['source_type']      = $managed_source;
		}

		$runtime_payload = array(
			'ability_name'        => 'npcink-cloud/web-search',
			'ability_family'      => 'knowledge',
			'contract_version'    => 'web_search.v1',
			'channel'             => 'toolbox_admin',
			'execution_kind'      => 'web_search',
			'profile_id'          => 'web-search.managed',
			'execution_pattern'   => 'inline',
			'data_classification' => 'public',
			'storage_mode'        => 'result_only',
			'retention_ttl'       => 3600,
			'timeout_seconds'     => 30,
			'http_timeout_seconds' => 30,
			'connect_timeout_seconds' => self::HTTP_CONNECT_TIMEOUT,
			'retry_max'           => 0,
			'input'               => $this->sanitize_payload( $runtime_input ),
			'policy'              => array(
				'allow_fallback' => true,
			),
		);

		$runtime_payload = apply_filters( 'npcink_toolbox_web_search_runtime_payload', $runtime_payload, $runtime_input );
		if ( ! is_array( $runtime_payload ) ) {
			return new WP_Error(
				'npcink_toolbox_invalid_web_search_runtime_payload',
				__( 'The web search runtime payload was not valid.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}

		$handled = apply_filters( 'npcink_toolbox_web_search_cloud_request', null, $runtime_payload, $runtime_input );
		if ( is_wp_error( $handled ) ) {
			return $handled;
		}
		if ( is_array( $handled ) ) {
			return $this->normalize_cloud_web_search_response( $handled, $runtime_payload );
		}

		$trace_id        = $this->trace_id( 'web_search' );
		$idempotency_key = $this->trace_id( 'web_search_cloud_test' );
		$request         = $this->toolbox_web_search_runtime_request( $runtime_payload );

		if ( function_exists( 'npcink_cloud_addon_execute_toolbox_web_search_runtime' ) ) {
			$response = npcink_cloud_addon_execute_toolbox_web_search_runtime( $request, $trace_id, $idempotency_key );
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			return $this->normalize_cloud_web_search_response( is_array( $response ) ? $response : array(), $runtime_payload );
		}

		return new WP_Error(
			'npcink_toolbox_web_search_cloud_unavailable',
			__( 'Connect Npcink Cloud before testing managed web search.', 'npcink-workflow-toolbox' ),
			array( 'status' => 503 )
		);
	}


	private function toolbox_web_search_runtime_request( array $runtime_payload ): array {
		$input = is_array( $runtime_payload['input'] ?? null ) ? $runtime_payload['input'] : array();

		$input['contract_version']       = 'web_search.v1';
		$input['profile_id']             = sanitize_text_field( (string) ( $runtime_payload['profile_id'] ?? 'web-search.managed' ) );
		$input['timeout_seconds']        = absint( $runtime_payload['timeout_seconds'] ?? 30 );
		$input['retention_ttl']          = absint( $runtime_payload['retention_ttl'] ?? 3600 );
		$input['write_posture']          = 'suggestion_only';
		$input['direct_wordpress_write'] = false;
		$input['allow_fallback']         = ! empty( $runtime_payload['policy']['allow_fallback'] );

		return $this->sanitize_payload( $input );
	}


	public function diagnose_automatic_web_search( array $input ) {
		$topic = trim( sanitize_text_field( (string) ( $input['topic'] ?? $input['query'] ?? '' ) ) );
		if ( '' === $topic ) {
			return new WP_Error(
				'npcink_toolbox_missing_web_search_diagnostic_topic',
				__( 'A topic is required for the Cloud web search workflow diagnostic.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$scenario = sanitize_key( (string) ( $input['scenario'] ?? 'discoverability' ) );
		if ( ! in_array( $scenario, array( 'discoverability', 'publish_preflight' ), true ) ) {
			$scenario = 'discoverability';
		}

		$artifact = $this->client->build_content_discoverability_brief(
			array(
				'topic'                  => $topic,
				'title'                  => sanitize_text_field( (string) ( $input['title'] ?? $topic ) ),
				'external_search_intent' => 'publish_preflight' === $scenario ? 'fact_check' : 'writing_context',
				'include_external_search' => true,
			)
		);

		if ( is_wp_error( $artifact ) ) {
			return $artifact;
		}

		$artifact = is_array( $artifact ) ? $artifact : array();
		$search   = $this->extract_workflow_web_search_report( $artifact, $scenario );
		$status   = sanitize_key( (string) ( $search['status'] ?? '' ) );
		$triggered = array() !== $search && ! in_array( $status, array( '', 'cloud_managed', 'skipped' ), true );

		return $this->with_output_contract(
			array(
				'provider'              => 'toolbox',
				'scenario'              => $scenario,
				'topic'                 => $topic,
				'status'                => $triggered ? $status : 'not_triggered',
				'search_triggered'      => $triggered,
				'workflow_artifact_type' => sanitize_key( (string) ( $artifact['artifact_type'] ?? '' ) ),
				'workflow_search'       => $search,
				'result_count'          => absint( $search['result_count'] ?? 0 ),
				'source_count'          => absint( $search['source_count'] ?? 0 ),
				'provider_call_count'   => absint( $search['provider_call_count'] ?? 0 ),
				'provider_mode'         => sanitize_key( (string) ( $search['provider_mode'] ?? '' ) ),
				'cloud_provider'        => sanitize_key( (string) ( $search['provider'] ?? '' ) ),
				'usage_summary'         => is_array( $search['usage_summary'] ?? null ) ? $this->sanitize_payload( $search['usage_summary'] ) : array(),
				'error_code'            => sanitize_key( (string) ( $search['error_code'] ?? '' ) ),
				'handoff'               => array(
					'cloud_runtime'          => 'npcink_cloud_addon',
					'final_writes'           => 'core_proposal_required',
					'direct_wordpress_write' => false,
				),
			),
			'web_search_diagnostics',
			'workflow_search_diagnostic'
		);
	}


	private function normalize_cloud_web_search_response( array $response, array $runtime_payload ): array {
		$result = $this->extract_cloud_runtime_result( $response );
		$input  = is_array( $runtime_payload['input'] ?? null ) ? $runtime_payload['input'] : array();

		$results = array();
		foreach ( array_slice( is_array( $result['results'] ?? null ) ? $result['results'] : array(), 0, max( 1, min( 10, (int) ( $input['max_results'] ?? 3 ) ) ) ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$results[] = array(
				'title'                  => sanitize_text_field( (string) ( $item['title'] ?? '' ) ),
				'url'                    => esc_url_raw( (string) ( $item['url'] ?? '' ) ),
				'snippet'                => sanitize_textarea_field( (string) ( $item['snippet'] ?? $item['content'] ?? '' ) ),
				'reader_excerpt'         => sanitize_textarea_field( (string) ( $item['reader_excerpt'] ?? '' ) ),
				'reader_status'          => sanitize_key( (string) ( $item['reader_status'] ?? '' ) ),
				'reader_provider'        => sanitize_key( (string) ( $item['reader_provider'] ?? '' ) ),
				'score'                  => is_numeric( $item['score'] ?? null ) ? (float) $item['score'] : null,
				'source'                 => sanitize_key( (string) ( $item['source'] ?? $result['provider'] ?? '' ) ),
				'content_type'           => sanitize_key( (string) ( $item['content_type'] ?? '' ) ),
				'author_name'            => sanitize_text_field( (string) ( $item['author_name'] ?? '' ) ),
				'comment_count'          => absint( $item['comment_count'] ?? 0 ),
				'vote_up_count'          => absint( $item['vote_up_count'] ?? 0 ),
				'authority_level'        => sanitize_text_field( (string) ( $item['authority_level'] ?? '' ) ),
				'write_posture'          => sanitize_key( (string) ( $item['write_posture'] ?? 'suggestion_only' ) ),
				'direct_wordpress_write' => false,
			);
		}
		$atomic_outputs = is_array( $result['atomic_outputs'] ?? null ) ? $this->sanitize_payload( $result['atomic_outputs'] ) : array();
		$result_count   = array() !== $results ? count( $results ) : absint( $result['result_count'] ?? 0 );
		$hot_topic_pool = $this->cloud_web_search_hot_topic_pool( $results, $atomic_outputs, $input, $result );

		$payload = $this->with_output_contract(
			array(
				'artifact_type'        => sanitize_key( (string) ( $result['artifact_type'] ?? '' ) ),
				'provider'             => sanitize_key( (string) ( $result['provider'] ?? 'cloud_web_search' ) ),
				'provider_mode'        => sanitize_key( (string) ( $result['provider_mode'] ?? 'cloud_managed' ) ),
				'contract_version'     => sanitize_text_field( (string) ( $runtime_payload['contract_version'] ?? 'web_search.v1' ) ),
				'output_contract'      => sanitize_text_field( (string) ( $result['output_contract'] ?? $result['evidence_pack']['contract_version'] ?? '' ) ),
				'requested_url'        => esc_url_raw( (string) ( $result['requested_url'] ?? '' ) ),
				'resolved_url'         => esc_url_raw( (string) ( $result['resolved_url'] ?? '' ) ),
				'url_match'            => sanitize_key( (string) ( $result['url_match'] ?? '' ) ),
				'title'                => sanitize_text_field( (string) ( $result['title'] ?? '' ) ),
				'language'             => sanitize_text_field( (string) ( $result['language'] ?? '' ) ),
				'published_at'         => sanitize_text_field( (string) ( $result['published_at'] ?? '' ) ),
				'content_hash'         => sanitize_text_field( (string) ( $result['content_hash'] ?? '' ) ),
				'char_count'           => absint( $result['char_count'] ?? 0 ),
				'word_count'           => absint( $result['word_count'] ?? 0 ),
				'preview_start'        => sanitize_textarea_field( (string) ( $result['preview_start'] ?? '' ) ),
				'preview_end'          => sanitize_textarea_field( (string) ( $result['preview_end'] ?? '' ) ),
				'coverage'             => is_array( $result['coverage'] ?? null ) ? $this->sanitize_payload( $result['coverage'] ) : array(),
				'content_trust'        => sanitize_key( (string) ( $result['content_trust'] ?? '' ) ),
				'prompt_injection_review_required' => ! empty( $result['prompt_injection_review_required'] ),
				'source_priority'      => sanitize_key( (string) ( $result['source_priority'] ?? $result['evidence_pack']['source_priority'] ?? '' ) ),
				'cloud_ability'        => sanitize_text_field( (string) ( $runtime_payload['ability_name'] ?? 'npcink-cloud/web-search' ) ),
				'cloud_runtime'        => 'npcink_cloud_addon',
				'status'               => sanitize_key( (string) ( $result['status'] ?? ( $response['status'] ?? 'unknown' ) ) ),
				'run_id'               => sanitize_text_field( (string) ( $response['run_id'] ?? ( ( $response['data']['run_id'] ?? null ) ?: ( $result['run_id'] ?? '' ) ) ) ),
				'query'                => sanitize_text_field( (string) ( $input['query'] ?? '' ) ),
				'intent'               => sanitize_key( (string) ( $result['intent'] ?? $input['intent'] ?? '' ) ),
				'max_results'          => max( 1, min( 10, (int) ( $input['max_results'] ?? 3 ) ) ),
				'result_count'         => $result_count,
				'evidence_gate'        => is_array( $result['evidence_gate'] ?? null ) ? $this->sanitize_payload( $result['evidence_gate'] ) : array(),
				'evidence_pack'        => is_array( $result['evidence_pack'] ?? null ) ? $this->sanitize_payload( $result['evidence_pack'] ) : array(),
				'atomic_outputs'       => $atomic_outputs,
				'provider_call_count'  => absint( $response['provider_call_count'] ?? ( $response['data']['provider_call_count'] ?? 0 ) ),
				'usage_summary'        => array(
					'provider'             => sanitize_key( (string) ( $result['provider'] ?? 'cloud_web_search' ) ),
					'provider_mode'        => sanitize_key( (string) ( $result['provider_mode'] ?? 'cloud_managed' ) ),
					'output_contract'      => sanitize_text_field( (string) ( $result['output_contract'] ?? $result['evidence_pack']['contract_version'] ?? '' ) ),
					'source_priority'      => sanitize_key( (string) ( $result['source_priority'] ?? $result['evidence_pack']['source_priority'] ?? '' ) ),
					'provider_call_count'  => absint( $response['provider_call_count'] ?? ( $response['data']['provider_call_count'] ?? 0 ) ),
					'result_count'         => $result_count,
					'evidence_status'      => sanitize_key( (string) ( $result['evidence_gate']['status'] ?? '' ) ),
					'failure_reason'       => sanitize_text_field( (string) ( $result['error_code'] ?? $response['error_code'] ?? '' ) ),
				),
				'results'              => $results,
				'handoff'              => array(
					'cloud_runtime'          => 'npcink_cloud_addon',
					'final_writes'           => 'core_proposal_required',
					'direct_wordpress_write' => false,
				),
			),
			'web_search_results',
			'external_web_evidence'
		);
		if ( array() !== $hot_topic_pool ) {
			$payload['hot_topic_pool'] = $hot_topic_pool;
		}

		if ( $this->settings->raw_responses_enabled() ) {
			$payload['cloud_response'] = $this->sanitize_debug_payload( $response );
		}

		return $payload;
	}


	private function cloud_web_search_hot_topic_pool( array $results, array $atomic_outputs, array $input, array $result ): array {
		$intent      = sanitize_key( (string) ( $result['intent'] ?? $input['intent'] ?? '' ) );
		$source_type = sanitize_key( (string) ( $input['source_type'] ?? $result['source_type'] ?? '' ) );
		if ( 'zhihu_hot_topics' !== $intent && 'zhihu_hot_list' !== $source_type ) {
			return array();
		}

		$topic_candidates = is_array( $atomic_outputs['topic_candidates'] ?? null ) ? $atomic_outputs['topic_candidates'] : array();
		$source_items     = is_array( $topic_candidates['items'] ?? null ) && array() !== $topic_candidates['items']
			? $topic_candidates['items']
			: $results;
		$items            = array();

		foreach ( array_slice( $source_items, 0, max( 1, min( 10, (int) ( $input['max_results'] ?? 5 ) ) ) ) as $index => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$title = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
			if ( '' === $title ) {
				continue;
			}

			$url     = esc_url_raw( (string) ( $item['url'] ?? '' ) );
			$items[] = array(
				'title'                  => $title,
				'url'                    => $url,
				'rank'                   => absint( $item['rank'] ?? ( $index + 1 ) ),
				'signal'                 => sanitize_textarea_field( (string) ( $item['signal'] ?? $item['snippet'] ?? '' ) ),
				'selection_reason'       => sanitize_textarea_field( (string) ( $item['selection_reason'] ?? $item['suggested_use'] ?? $item['snippet'] ?? '' ) ),
				'source'                 => sanitize_key( (string) ( $item['source'] ?? 'zhihu_hot_list' ) ),
				'score'                  => is_numeric( $item['score'] ?? null ) ? (float) $item['score'] : null,
				'suggested_use'          => sanitize_textarea_field( (string) ( $item['suggested_use'] ?? __( 'Topic selection and manual research queue.', 'npcink-workflow-toolbox' ) ) ),
				'next_action'            => sanitize_key( (string) ( $item['next_action'] ?? 'manual_topic_selection_then_focused_research' ) ),
				'evidence_refs'          => $url ? array( $url ) : array(),
				'write_posture'          => 'suggestion_only',
				'direct_wordpress_write' => false,
				'action_policy'          => 'operator_review_only_no_write',
			);
		}

		return array(
			'artifact_type'           => 'zhihu_hot_topic_pool',
			'contract_version'        => 'zhihu_hot_topic_pool.v1',
			'cloud_atomic_contract'   => sanitize_text_field( (string) ( $topic_candidates['contract_version'] ?? 'topic_candidate.v1' ) ),
			'status'                  => array() === $items ? 'empty' : 'ready',
			'problem_solved'          => 'daily_topic_selection',
			'use_cases'               => array(
				'choose_today_topic',
				'screen_audience_fit',
				'build_manual_research_queue',
			),
			'operator_next_action'    => 'select_topic_then_manual_research',
			'source_priority'         => 'trend_signal_not_factual_source',
			'result_count'            => count( $items ),
			'items'                   => $items,
			'write_posture'           => 'suggestion_only',
			'direct_wordpress_write'  => false,
		);
	}


	public function cloud_web_search_evidence( array $research ): array {
		$status = sanitize_key( (string) ( $research['status'] ?? '' ) );
		if ( 'ready' !== $status ) {
			return array();
		}

		$results = is_array( $research['results'] ?? null ) ? $research['results'] : array();
		$report  = array(
			'status'                 => $status,
			'provider'               => sanitize_key( (string) ( $research['provider'] ?? 'cloud_web_search' ) ),
			'provider_mode'          => sanitize_key( (string) ( $research['provider_mode'] ?? '' ) ),
			'intent'                 => sanitize_key( (string) ( $research['intent'] ?? '' ) ),
			'result_count'           => absint( $research['result_count'] ?? count( $results ) ),
			'source_count'           => absint( $research['source_count'] ?? count( $results ) ),
			'provider_call_count'    => absint( $research['provider_call_count'] ?? 0 ),
			'usage_summary'          => is_array( $research['usage_summary'] ?? null ) ? $this->sanitize_payload( $research['usage_summary'] ) : array(),
			'evidence_gate'          => is_array( $research['evidence_gate'] ?? null ) ? $this->sanitize_payload( $research['evidence_gate'] ) : array(),
			'error_code'             => sanitize_key( (string) ( $research['error_code'] ?? '' ) ),
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
		);

		return array(
			'web_search' => array(
				'source'                 => 'cloud_managed_toolbox_content_search',
				'report'                 => $report,
				'result'                 => $this->sanitize_payload( $research ),
				'write_posture'          => 'suggestion_only',
				'direct_wordpress_write' => false,
			),
		);
	}


	private function extract_workflow_web_search_report( array $artifact, string $scenario ): array {
		$research = is_array( $artifact['external_research'] ?? null ) ? $artifact['external_research'] : array();
		$results  = is_array( $research['results'] ?? null ) ? $research['results'] : array();

		return array(
			'status'        => sanitize_key( (string) ( $research['status'] ?? '' ) ),
			'provider'      => sanitize_key( (string) ( $research['provider'] ?? 'cloud_web_search' ) ),
			'provider_mode' => sanitize_key( (string) ( $research['provider_mode'] ?? '' ) ),
			'result_count'  => absint( $research['result_count'] ?? count( $results ) ),
			'source_count'  => count( $results ),
			'provider_call_count' => absint( $research['provider_call_count'] ?? 0 ),
			'usage_summary' => is_array( $research['usage_summary'] ?? null ) ? $this->sanitize_payload( $research['usage_summary'] ) : array(),
			'error_code'    => sanitize_key( (string) ( $research['error_code'] ?? '' ) ),
			'evidence_gate' => is_array( $research['evidence_gate'] ?? null ) ? $this->sanitize_payload( $research['evidence_gate'] ) : array(),
			'sources'       => $this->sanitize_payload( array_slice( $results, 0, 5 ) ),
		);
	}
}
