<?php
/**
 * Read-only page-quality insight pack REST bridge (pack 3).
 *
 * Assembles page_quality_insight_pack.v1 locally and optionally asks
 * Cloud via hosted_ai_content_support.v1. Does not write WordPress and does
 * not use the paused Site Ops Cloud detail path.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class Rest_Page_Quality_Insight_Bridges {

	public function __construct( Settings $settings ) {
		unset( $settings ); // Signature parity with pack 1; pack is local-only.
	}

	/**
	 * GET /page-quality/insight-pack — local pack only.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function page_quality_insight_pack( WP_REST_Request $request ) {
		unset( $request );

		try {
			$pack = $this->assemble_pack();
		} catch ( \Throwable $throwable ) {
			return new WP_Error(
				'npcink_toolbox_page_quality_insight_pack_failed',
				__( 'Could not build the local page-quality insight pack.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response( $pack );
	}

	/**
	 * @return array<string,mixed>
	 */
	public function assemble_pack(): array {
		$builder = new Page_Quality_Insight_Builder();

		return $builder->build();
	}

	/**
	 * POST /page-quality/insight-pack-qa — local pack + Hosted AI.
	 *
	 * Same transport as insight-pack-qa: execute_runtime + hosted_ai_content_support.v1.
	 * Omits Cloud-rejected extras. Suggestion-only; no WordPress writes.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function page_quality_insight_pack_qa( WP_REST_Request $request ) {
		$question = trim( sanitize_textarea_field( (string) $request->get_param( 'question' ) ) );
		if ( '' === $question ) {
			return new WP_Error(
				'npcink_toolbox_page_quality_qa_question_required',
				__( 'Enter a question about your pages.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}
		if ( strlen( $question ) > 2000 ) {
			return new WP_Error(
				'npcink_toolbox_page_quality_qa_question_too_long',
				__( 'Keep the question under 2000 characters.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		if ( ! class_exists( '\Npcink_Cloud_Runtime_Client_Factory' ) ) {
			return new WP_Error(
				'npcink_toolbox_page_quality_qa_addon_missing',
				__( 'Npcink Cloud Addon is required for Smart Ask.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$client = \Npcink_Cloud_Runtime_Client_Factory::configured();
		if ( ! $client ) {
			return new WP_Error(
				'npcink_toolbox_page_quality_qa_cloud_unconfigured',
				__( 'Connect and verify Npcink Cloud before asking.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		try {
			$pack = $this->assemble_pack();
		} catch ( \Throwable $throwable ) {
			return new WP_Error(
				'npcink_toolbox_page_quality_insight_pack_failed',
				__( 'Could not build the local page-quality insight pack.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}

		$pack_run_id = (string) ( $pack['run_id'] ?? '' );
		$compact     = $this->compact_pack_for_qa( $pack );
		$pack_json   = wp_json_encode( $compact, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $pack_json ) || '' === $pack_json ) {
			return new WP_Error(
				'npcink_toolbox_page_quality_qa_encode_failed',
				__( 'Could not encode the de-identified page-quality pack.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}

		$summary      = is_array( $compact['summary'] ?? null ) ? $compact['summary'] : array();
		$sampling     = is_array( $compact['sampling'] ?? null ) ? $compact['sampling'] : array();
		$sample_note  = Insight_Pack_Answer_Guidance::stratified_sample_note(
			(int) ( $sampling['strata_count'] ?? 1 ),
			(int) ( $summary['sample_size'] ?? 0 ),
			(int) ( $summary['published_posts'] ?? 0 ) + (int) ( $summary['published_pages'] ?? 0 )
		);
		$user_content = Insight_Pack_Answer_Guidance::user_message( $question, 'page_quality insight pack', $pack_json, $sample_note );

		// Cloud RuntimePayload: extra=forbid. Do NOT forward Addon-local-only extras.
		$runtime_payload = array(
			'ability_name'        => 'npcink-toolbox/ai-content-support',
			'contract_version'    => 'hosted_ai_content_support.v1',
			'profile_id'          => 'text.ai',
			'execution_kind'      => 'text',
			'execution_pattern'   => 'inline',
			'input'               => array(
				'messages' => array(
					array(
						'role'    => 'system',
						'content' => Insight_Pack_Answer_Guidance::system_prompt( Insight_Pack_Answer_Guidance::PACK_PAGE_QUALITY ),
					),
					array(
						'role'    => 'user',
						'content' => $user_content,
					),
				),
				'params'   => array(
					'temperature' => 0.2,
					'max_tokens'  => 900,
				),
			),
			'data_classification' => 'public_site_content',
			'storage_mode'        => 'result_only',
			'retention_ttl'       => 86400,
			'timeout_seconds'     => 45,
			'retry_max'           => 0,
			'policy'              => array(
				'allow_fallback' => false,
			),
		);

		$trace_id        = 'page_quality_qa_' . wp_generate_uuid4();
		$idempotency_key = 'page_quality_qa_' . wp_generate_uuid4();
		$started         = microtime( true );
		$response        = $client->execute_runtime( $runtime_payload, $trace_id, $idempotency_key );
		$elapsed_ms      = (int) round( ( microtime( true ) - $started ) * 1000 );

		if ( is_wp_error( $response ) ) {
			$code         = (string) $response->get_error_code();
			$raw_message  = (string) $response->get_error_message();
			$error_data   = $response->get_error_data();
			$data         = array(
				'status'      => 502,
				'pack_run_id' => $pack_run_id,
				'trace_id'    => $trace_id,
				'elapsed_ms'  => $elapsed_ms,
				'error_data'  => $error_data,
			);
			$is_busy_slot = (
				'cloud_commercial_concurrency_exceeded' === $code
				|| false !== strpos( $code, 'concurrency' )
				|| false !== strpos( strtolower( $raw_message ), 'max active' )
				|| false !== strpos( strtolower( $raw_message ), 'active cloud run' )
			);
			if ( $is_busy_slot ) {
				$friendly          = Cloud_Error_Plain_Messages::message( Cloud_Error_Plain_Messages::KEY_BUSY );
				$data['hint']      = $friendly;
				$data['status']    = 429;
				$data['busy_slot'] = true;

				return new WP_Error(
					'cloud_commercial_concurrency_exceeded',
					$friendly,
					$data
				);
			}

			return new WP_Error(
				$code !== '' ? $code : 'npcink_toolbox_page_quality_qa_cloud_failed',
				$raw_message,
				$data
			);
		}

		if ( ! is_array( $response ) ) {
			return new WP_Error(
				'npcink_toolbox_page_quality_qa_invalid_response',
				__( 'Cloud returned an unexpected response.', 'npcink-workflow-toolbox' ),
				array(
					'status'      => 502,
					'pack_run_id' => $pack_run_id,
					'elapsed_ms'  => $elapsed_ms,
				)
			);
		}

		$data   = is_array( $response['data'] ?? null ) ? $response['data'] : $response;
		$run_id = (string) ( $data['run_id'] ?? '' );
		$answer = Insight_Pack_Answer_Guidance::strip_first_line_quotes( $this->extract_hosted_ai_answer_text( $response ) );
		$status = (string) ( $data['status'] ?? ( $response['status'] ?? '' ) );

		return rest_ensure_response(
			array(
				'ok'              => ( '' !== $run_id && '' !== $answer ),
				'pack'            => 'page_quality_insight_pack.v1',
				'path'            => 'Npcink_Cloud_Runtime_Client::execute_runtime via hosted_ai_content_support.v1',
				'ability_name'    => 'npcink-toolbox/ai-content-support',
				'contract'        => 'hosted_ai_content_support.v1',
				'write_posture'   => 'suggestion_only',
				'pack_run_id'     => $pack_run_id,
				'run_id'          => $run_id,
				'status'          => $status,
				'trace_id'        => $trace_id,
				'idempotency_key' => $idempotency_key,
				'elapsed_ms'      => $elapsed_ms,
				'question'        => $question,
				'answer'          => $answer,
			)
		);
	}

	/**
	 * Compact de-identified pack for the QA prompt.
	 *
	 * @param array<string,mixed> $pack Full pack.
	 * @return array<string,mixed>
	 */
	private function compact_pack_for_qa( array $pack ): array {
		$findings = array();
		foreach ( array_slice( is_array( $pack['top_findings'] ?? null ) ? $pack['top_findings'] : array(), 0, 8 ) as $finding ) {
			if ( ! is_array( $finding ) ) {
				continue;
			}
			$findings[] = array(
				'id'                     => (string) ( $finding['id'] ?? '' ),
				'title'                  => (string) ( $finding['title'] ?? '' ),
				'issue_type'             => (string) ( $finding['issue_type'] ?? '' ),
				'category'               => (string) ( $finding['category'] ?? '' ),
				'severity'               => (string) ( $finding['severity'] ?? '' ),
				'priority_score'         => $finding['priority_score'] ?? null,
				'evidence_summary'       => (string) ( $finding['evidence_summary'] ?? '' ),
				'recommended_action'     => (string) ( $finding['recommended_action'] ?? '' ),
				'write_boundary'         => (string) ( $finding['write_boundary'] ?? '' ),
				'direct_wordpress_write' => (bool) ( $finding['direct_wordpress_write'] ?? false ),
			);
		}

		$signals = is_array( $pack['page_quality_signals'] ?? null ) ? $pack['page_quality_signals'] : array();
		// Drop full sample flags; keep rates/buckets + short title samples only.
		$compact_signals = array(
			'sample_size'                   => $signals['sample_size'] ?? 0,
			'missing_excerpt_rate'          => $signals['missing_excerpt_rate'] ?? 0,
			'missing_meta_description_rate' => $signals['missing_meta_description_rate'] ?? 0,
			'missing_featured_image_rate'   => $signals['missing_featured_image_rate'] ?? 0,
			'thin_content_rate'             => $signals['thin_content_rate'] ?? 0,
			'multiple_h1_rate'              => $signals['multiple_h1_rate'] ?? 0,
			'missing_heading_rate'          => $signals['missing_heading_rate'] ?? 0,
			'missing_alt_rate'              => $signals['missing_alt_rate'] ?? 0,
			'missing_alt_total'             => $signals['missing_alt_total'] ?? 0,
			'posts_missing_category'        => $signals['posts_missing_category'] ?? 0,
			'posts_missing_tag'             => $signals['posts_missing_tag'] ?? 0,
			'word_count_buckets'            => $signals['word_count_buckets'] ?? array(),
			'age_buckets'                   => $signals['age_buckets'] ?? array(),
			'title_samples'                 => array_slice( is_array( $signals['title_samples'] ?? null ) ? $signals['title_samples'] : array(), 0, 8 ),
			'measurement_kind'              => (string) ( $signals['measurement_kind'] ?? 'page_completeness_sample_only' ),
		);

		$sampling       = is_array( $pack['sampling'] ?? null ) ? $pack['sampling'] : array();
		$compact_strata = array();
		foreach ( array_slice( is_array( $sampling['strata'] ?? null ) ? $sampling['strata'] : array(), 0, 5 ) as $stratum ) {
			if ( ! is_array( $stratum ) ) {
				continue;
			}
			$compact_strata[] = array(
				'id'               => sanitize_key( (string) ( $stratum['id'] ?? '' ) ),
				'label'            => sanitize_text_field( (string) ( $stratum['label'] ?? '' ) ),
				'published_from'   => sanitize_text_field( (string) ( $stratum['published_from'] ?? '' ) ),
				'published_to'     => sanitize_text_field( (string) ( $stratum['published_to'] ?? '' ) ),
				'population_count' => (int) ( $stratum['population_count'] ?? 0 ),
				'sample_count'     => (int) ( $stratum['sample_count'] ?? 0 ),
				'sample_rates'     => is_array( $stratum['sample_rates'] ?? null ) ? array_map( 'floatval', $stratum['sample_rates'] ) : array(),
			);
		}
		$compact_sampling = array(
			'method'            => (string) ( $sampling['method'] ?? '' ),
			'strata_definition' => (string) ( $sampling['strata_definition'] ?? '' ),
			'strata_count'      => (int) ( $sampling['strata_count'] ?? 0 ),
			'total_published'   => (int) ( $sampling['total_published'] ?? 0 ),
			'total_sampled'     => (int) ( $sampling['total_sampled'] ?? 0 ),
			'strata'            => $compact_strata,
			'weighted_rates'    => is_array( $sampling['weighted_rates'] ?? null ) ? array_map( 'floatval', $sampling['weighted_rates'] ) : array(),
			'rate_basis'        => (string) ( $sampling['rate_basis'] ?? '' ),
		);

		$taxonomies = array();
		foreach ( array( 'category', 'post_tag' ) as $tax ) {
			$block              = is_array( $pack['taxonomies'][ $tax ] ?? null ) ? $pack['taxonomies'][ $tax ] : array();
			$taxonomies[ $tax ] = array(
				'total'       => (int) ( $block['total'] ?? 0 ),
				'empty_count' => (int) ( $block['empty_count'] ?? 0 ),
				'low_count'   => (int) ( $block['low_count'] ?? 0 ),
				'top_terms'   => array_slice( is_array( $block['top_terms'] ?? null ) ? $block['top_terms'] : array(), 0, 8 ),
			);
		}

		return array(
			'contract_version'       => (string) ( $pack['contract_version'] ?? '' ),
			'artifact_type'          => (string) ( $pack['artifact_type'] ?? '' ),
			'run_id'                 => (string) ( $pack['run_id'] ?? '' ),
			'site_id'                => (string) ( $pack['site_id'] ?? '' ),
			'generated_at'           => (string) ( $pack['generated_at'] ?? '' ),
			'write_posture'          => (string) ( $pack['write_posture'] ?? '' ),
			'direct_wordpress_write' => (bool) ( $pack['direct_wordpress_write'] ?? false ),
			'summary'                => is_array( $pack['summary'] ?? null ) ? $pack['summary'] : array(),
			'post_counts'            => is_array( $pack['post_counts'] ?? null ) ? $pack['post_counts'] : array(),
			'taxonomies'             => $taxonomies,
			'page_quality_signals'  => $compact_signals,
			'sampling'               => $compact_sampling,
			'top_findings'           => $findings,
			'safety'                 => is_array( $pack['safety'] ?? null ) ? $pack['safety'] : array(),
		);
	}

	/**
	 * @param array<string,mixed> $response Runtime response.
	 */
	private function extract_hosted_ai_answer_text( array $response ): string {
		$data   = is_array( $response['data'] ?? null ) ? $response['data'] : $response;
		$result = is_array( $data['result'] ?? null ) ? $data['result'] : array();

		if ( is_string( $result['output_text'] ?? null ) && '' !== trim( (string) $result['output_text'] ) ) {
			return trim( (string) $result['output_text'] );
		}

		$messages = is_array( $result['messages'] ?? null ) ? $result['messages'] : array();
		foreach ( $messages as $message ) {
			if ( ! is_array( $message ) ) {
				continue;
			}
			if ( 'assistant' !== (string) ( $message['role'] ?? '' ) ) {
				continue;
			}
			$content = $message['content'] ?? '';
			if ( is_string( $content ) && '' !== trim( $content ) ) {
				return trim( $content );
			}
		}

		$output = is_array( $result['output'] ?? null ) ? $result['output'] : array();
		foreach ( $output as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$content = is_array( $item['content'] ?? null ) ? $item['content'] : array();
			foreach ( $content as $part ) {
				if ( is_array( $part ) && is_string( $part['text'] ?? null ) && '' !== trim( (string) $part['text'] ) ) {
					return trim( (string) $part['text'] );
				}
			}
		}

		if ( is_string( $data['message'] ?? null ) && '' !== trim( (string) $data['message'] ) ) {
			return trim( (string) $data['message'] );
		}

		return '';
	}
}
