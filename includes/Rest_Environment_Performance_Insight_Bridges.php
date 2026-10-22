<?php
/**
 * Read-only environment-performance insight pack REST bridge (pack 4).
 *
 * Assembles environment_performance_insight_pack.v1 locally and optionally asks
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

final class Rest_Environment_Performance_Insight_Bridges {

	public function __construct( Settings $settings ) {
		unset( $settings ); // Signature parity with pack 1; pack is local-only.
	}

	/**
	 * GET /environment-performance/insight-pack — local pack only.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function environment_performance_insight_pack( WP_REST_Request $request ) {
		unset( $request );

		try {
			$pack = $this->assemble_pack();
		} catch ( \Throwable $throwable ) {
			return new WP_Error(
				'npcink_toolbox_environment_performance_insight_pack_failed',
				__( 'Could not build the local environment inventory pack.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response( $pack );
	}

	/**
	 * @return array<string,mixed>
	 */
	public function assemble_pack(): array {
		$builder = new Environment_Performance_Insight_Builder();

		return $builder->build();
	}

	/**
	 * POST /environment-performance/insight-pack-qa — local pack + Hosted AI.
	 *
	 * Same transport as insight-pack-qa: execute_runtime + hosted_ai_content_support.v1.
	 * Omits Cloud-rejected extras. Suggestion-only; no WordPress writes.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function environment_performance_insight_pack_qa( WP_REST_Request $request ) {
		$question = trim( sanitize_textarea_field( (string) $request->get_param( 'question' ) ) );
		if ( '' === $question ) {
			return new WP_Error(
				'npcink_toolbox_environment_performance_qa_question_required',
				__( 'Enter a question about your site environment.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}
		if ( strlen( $question ) > 2000 ) {
			return new WP_Error(
				'npcink_toolbox_environment_performance_qa_question_too_long',
				__( 'Keep the question under 2000 characters.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		if ( ! class_exists( '\Npcink_Cloud_Runtime_Client_Factory' ) ) {
			return new WP_Error(
				'npcink_toolbox_environment_performance_qa_addon_missing',
				__( 'Npcink Cloud Addon is required for Smart Ask.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$client = \Npcink_Cloud_Runtime_Client_Factory::configured();
		if ( ! $client ) {
			return new WP_Error(
				'npcink_toolbox_environment_performance_qa_cloud_unconfigured',
				__( 'Connect and verify Npcink Cloud before asking.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		try {
			$pack = $this->assemble_pack();
		} catch ( \Throwable $throwable ) {
			return new WP_Error(
				'npcink_toolbox_environment_performance_insight_pack_failed',
				__( 'Could not build the local environment inventory pack.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}

		$pack_run_id = (string) ( $pack['run_id'] ?? '' );
		$compact     = $this->compact_pack_for_qa( $pack );
		$pack_json   = wp_json_encode( $compact, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $pack_json ) || '' === $pack_json ) {
			return new WP_Error(
				'npcink_toolbox_environment_performance_qa_encode_failed',
				__( 'Could not encode the de-identified environment inventory pack.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}

		$summary      = is_array( $compact['summary'] ?? null ) ? $compact['summary'] : array();
		$plugins      = is_array( $compact['plugins'] ?? null ) ? $compact['plugins'] : array();
		$sample_note  = Insight_Pack_Answer_Guidance::plugin_list_sample_note(
			(int) ( $plugins['listed_plugin_count'] ?? ( $summary['listed_plugin_count'] ?? 0 ) ),
			(int) ( $plugins['active_plugin_count'] ?? ( $summary['active_plugin_count'] ?? 0 ) ),
			! empty( $plugins['list_truncated'] ) || ! empty( $summary['plugin_list_truncated'] )
		);
		$user_content = Insight_Pack_Answer_Guidance::user_message( $question, 'environment_performance insight pack', $pack_json, $sample_note );

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
						'content' => Insight_Pack_Answer_Guidance::system_prompt( Insight_Pack_Answer_Guidance::PACK_ENVIRONMENT_PERFORMANCE ),
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

		$trace_id        = 'environment_performance_qa_' . wp_generate_uuid4();
		$idempotency_key = 'environment_performance_qa_' . wp_generate_uuid4();
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
				$code !== '' ? $code : 'npcink_toolbox_environment_performance_qa_cloud_failed',
				$raw_message,
				$data
			);
		}

		if ( ! is_array( $response ) ) {
			return new WP_Error(
				'npcink_toolbox_environment_performance_qa_invalid_response',
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
				'pack'            => 'environment_performance_insight_pack.v1',
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

		$plugins     = is_array( $pack['plugins'] ?? null ) ? $pack['plugins'] : array();
		$plugin_rows = array();
		foreach ( array_slice( is_array( $plugins['active_plugins'] ?? null ) ? $plugins['active_plugins'] : array(), 0, 40 ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$plugin_rows[] = array(
				'slug'    => sanitize_key( (string) ( $row['slug'] ?? '' ) ),
				'version' => sanitize_text_field( (string) ( $row['version'] ?? '' ) ),
			);
		}

		$runtime = is_array( $pack['runtime'] ?? null ) ? $pack['runtime'] : array();
		$theme   = is_array( $pack['theme'] ?? null ) ? $pack['theme'] : array();
		$volume  = is_array( $pack['volume'] ?? null ) ? $pack['volume'] : array();
		$cache   = is_array( $pack['cache_and_debug'] ?? null ) ? $pack['cache_and_debug'] : array();
		$signals = is_array( $pack['inventory_signals'] ?? null ) ? $pack['inventory_signals'] : array();

		return array(
			'contract_version'       => (string) ( $pack['contract_version'] ?? '' ),
			'artifact_type'          => (string) ( $pack['artifact_type'] ?? '' ),
			'run_id'                 => (string) ( $pack['run_id'] ?? '' ),
			'site_id'                => (string) ( $pack['site_id'] ?? '' ),
			'generated_at'           => (string) ( $pack['generated_at'] ?? '' ),
			'write_posture'          => (string) ( $pack['write_posture'] ?? '' ),
			'direct_wordpress_write' => (bool) ( $pack['direct_wordpress_write'] ?? false ),
			'measurement_kind'       => 'config_and_inventory_only',
			'summary'                => is_array( $pack['summary'] ?? null ) ? $pack['summary'] : array(),
			'runtime'                => array(
				'wordpress_version'    => (string) ( $runtime['wordpress_version'] ?? '' ),
				'php_version'          => (string) ( $runtime['php_version'] ?? '' ),
				'memory_limit'         => (string) ( $runtime['memory_limit'] ?? '' ),
				'max_execution_time'   => (int) ( $runtime['max_execution_time'] ?? 0 ),
				'is_multisite'         => (bool) ( $runtime['is_multisite'] ?? false ),
				'home_is_https'        => (bool) ( $runtime['home_is_https'] ?? false ),
				'site_is_https'        => (bool) ( $runtime['site_is_https'] ?? false ),
				'uploads_dir_writable' => $runtime['uploads_dir_writable'] ?? null,
			),
			'theme'                  => array(
				'stylesheet' => (string) ( $theme['stylesheet'] ?? '' ),
				'version'    => (string) ( $theme['version'] ?? '' ),
			),
			'plugins'                => array(
				'active_plugin_count'  => (int) ( $plugins['active_plugin_count'] ?? 0 ),
				'listed_plugin_count'  => (int) ( $plugins['listed_plugin_count'] ?? 0 ),
				'list_truncated'       => (bool) ( $plugins['list_truncated'] ?? false ),
				'list_limit'           => (int) ( $plugins['list_limit'] ?? 0 ),
				'active_plugins'       => $plugin_rows,
				'common_cache_plugins' => is_array( $plugins['common_cache_plugins'] ?? null ) ? $plugins['common_cache_plugins'] : array(),
			),
			'volume'                 => array(
				'published_posts'       => (int) ( $volume['published_posts'] ?? 0 ),
				'published_pages'       => (int) ( $volume['published_pages'] ?? 0 ),
				'attachment_count'      => (int) ( $volume['attachment_count'] ?? 0 ),
				'cron_event_hook_count' => $volume['cron_event_hook_count'] ?? null,
				'autoload_options'      => is_array( $volume['autoload_options'] ?? null ) ? array(
					'available'    => (bool) ( $volume['autoload_options']['available'] ?? false ),
					'option_count' => (int) ( $volume['autoload_options']['option_count'] ?? 0 ),
					'approx_bytes' => (int) ( $volume['autoload_options']['approx_bytes'] ?? 0 ),
					'reason'       => (string) ( $volume['autoload_options']['reason'] ?? '' ),
				) : array( 'available' => false ),
			),
			'cache_and_debug'        => array(
				'using_ext_object_cache' => (bool) ( $cache['using_ext_object_cache'] ?? false ),
				'object_cache_dropin'    => (bool) ( $cache['object_cache_dropin'] ?? false ),
				'wp_debug'               => (bool) ( $cache['wp_debug'] ?? false ),
				'wp_debug_display'       => (bool) ( $cache['wp_debug_display'] ?? false ),
				'wp_debug_log'           => (bool) ( $cache['wp_debug_log'] ?? false ),
				'script_debug'           => (bool) ( $cache['script_debug'] ?? false ),
				'savequeries'            => (bool) ( $cache['savequeries'] ?? false ),
			),
			'inventory_signals'      => array(
				'measurement_kind'        => 'config_and_inventory_only',
				'memory_looks_low'        => (bool) ( $signals['memory_looks_low'] ?? false ),
				'plugin_list_truncated'   => (bool) ( $signals['plugin_list_truncated'] ?? false ),
				'any_common_cache_plugin' => (bool) ( $signals['any_common_cache_plugin'] ?? false ),
				'published_total'         => (int) ( $signals['published_total'] ?? 0 ),
			),
			'top_findings'           => $findings,
			'safety'                 => is_array( $pack['safety'] ?? null ) ? $pack['safety'] : array(),
		);
	}

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
