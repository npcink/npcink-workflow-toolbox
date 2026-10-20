<?php
/**
 * Read-only Site Ops insight pack REST bridge.
 *
 * Assembles site_ops_insight_pack.v1 from the local snapshot collector and
 * insight builder. Does not call Cloud site-ops analysis, does not write to
 * WordPress, and does not own run state.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class Rest_Site_Ops_Insight_Bridges {

	private Settings $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * GET /site-ops/insight-pack — local insight pack only.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function site_ops_insight_pack( WP_REST_Request $request ) {
		unset( $request );

		try {
			$pack = $this->assemble_insight_pack();
		} catch ( \Throwable $throwable ) {
			return new WP_Error(
				'npcink_toolbox_site_ops_insight_pack_failed',
				__( 'Could not build the local Site Check insight pack.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response( $pack );
	}

	/**
	 * Builds the same local pack the Site Check admin preview uses, without
	 * Cloud detail or cloud request assembly.
	 *
	 * @return array<string,mixed>
	 */
	public function assemble_insight_pack(): array {
		$collector = new Site_Ops_Snapshot_Collector();
		$builder   = new Site_Ops_Insight_Builder();
		$snapshot  = $collector->collect();
		$context   = array(
			'content_context_ready'      => $this->content_context_ready(),
			'cloud_ready'                => $this->settings->cloud_runtime_available(),
			'internal_link_graph_health' => $this->internal_link_graph_health(),
		);

		return $builder->build( $snapshot, $context );
	}

	private function content_context_ready(): bool {
		$context         = $this->settings->get_content_context();
		$required_fields = array( 'site_positioning', 'target_audience', 'brand_voice', 'primary_keywords' );

		foreach ( $required_fields as $field ) {
			$value = $context[ $field ] ?? '';
			if ( is_array( $value ) ) {
				$parts = array();
				foreach ( $value as $item ) {
					if ( is_scalar( $item ) ) {
						$parts[] = trim( (string) $item );
					}
				}
				$value = implode( ' ', array_filter( $parts ) );
			}
			if ( '' === trim( (string) $value ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Optional Toolkit internal-link graph health; missing Toolkit is non-fatal.
	 *
	 * @return array<string,mixed>
	 */
	private function internal_link_graph_health(): array {
		$ability_id = 'npcink-abilities-toolkit/get-internal-link-graph-health';
		if ( ! function_exists( 'npcink_abilities_toolkit_get_registered' ) || ! current_user_can( 'edit_posts' ) ) {
			return array(
				'available'         => false,
				'source_ability_id' => $ability_id,
			);
		}

		$registered = npcink_abilities_toolkit_get_registered();
		$definition = is_array( $registered[ $ability_id ] ?? null ) ? $registered[ $ability_id ] : array();
		$callback   = $definition['execute_callback'] ?? null;
		if ( ! is_callable( $callback ) ) {
			return array(
				'available'         => false,
				'source_ability_id' => $ability_id,
			);
		}

		$result = call_user_func(
			$callback,
			array(
				'post_type'          => 'post',
				'status'             => 'publish',
				'per_page'           => 24,
				'page'               => 1,
				'min_outbound_links' => 1,
				'max_outbound_links' => 20,
			)
		);
		if ( is_wp_error( $result ) || ! is_array( $result ) || empty( $result['success'] ) || ! is_array( $result['data'] ?? null ) ) {
			return array(
				'available'         => false,
				'source_ability_id' => $ability_id,
			);
		}

		return array(
			'available'         => true,
			'source_ability_id' => $ability_id,
			'data'              => $result['data'],
		);
	}

	/**
	 * POST /site-ops/insight-pack-qa — MVP: local pack + Hosted AI content-support.
	 *
	 * Same transport path as scripts/insight-pack-cloud-qa.php:
	 * Npcink_Cloud_Runtime_Client::execute_runtime with hosted_ai_content_support.v1.
	 * Omits Cloud-rejected Addon-local extras (see docs/insight-pack-cloud-qa-cli.md).
	 * Does not call paused site-ops Cloud detail / analyze-site-ops.
	 * Suggestion-only; no WordPress writes.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function site_ops_insight_pack_qa( WP_REST_Request $request ) {
		$question = trim( sanitize_textarea_field( (string) $request->get_param( 'question' ) ) );
		if ( '' === $question ) {
			return new WP_Error(
				'npcink_toolbox_insight_pack_qa_question_required',
				__( 'Enter a question about the de-identified Site Check pack.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}
		if ( strlen( $question ) > 2000 ) {
			return new WP_Error(
				'npcink_toolbox_insight_pack_qa_question_too_long',
				__( 'Keep the question under 2000 characters.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		if ( ! class_exists( '\Npcink_Cloud_Runtime_Client_Factory' ) ) {
			return new WP_Error(
				'npcink_toolbox_insight_pack_qa_addon_missing',
				__( 'Npcink Cloud Addon is required for Ask about this check (dev test).', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$client = \Npcink_Cloud_Runtime_Client_Factory::configured();
		if ( ! $client ) {
			return new WP_Error(
				'npcink_toolbox_insight_pack_qa_cloud_unconfigured',
				__( 'Connect and verify Npcink Cloud before asking about this check.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		try {
			$pack = $this->assemble_insight_pack();
		} catch ( \Throwable $throwable ) {
			return new WP_Error(
				'npcink_toolbox_site_ops_insight_pack_failed',
				__( 'Could not build the local Site Check insight pack.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}

		$pack_run_id = (string) ( $pack['run_id'] ?? '' );
		$compact     = $this->compact_insight_pack_for_qa( $pack );
		$pack_json   = wp_json_encode( $compact, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $pack_json ) || '' === $pack_json ) {
			return new WP_Error(
				'npcink_toolbox_insight_pack_qa_encode_failed',
				__( 'Could not encode the de-identified insight pack.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}

		$user_content = $question . "\n\n---\nDe-identified site_ops insight pack (JSON):\n" . $pack_json;

		// Cloud RuntimePayload: extra=forbid. Do NOT forward Addon-local-only
		// timeout or prompt-mode extras (see docs/insight-pack-cloud-qa-cli.md).
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
						'content' => 'You are Npcink Workflow Toolbox. Use only the provided de-identified Site Check insight pack. Return concise, reviewable suggestions. Do not claim to write, publish, approve, or bypass governance. Do not invent credentials, emails, or private content.',
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

		$trace_id        = 'insight_pack_qa_' . wp_generate_uuid4();
		$idempotency_key = 'insight_pack_qa_' . wp_generate_uuid4();
		$started         = microtime( true );
		$response        = $client->execute_runtime( $runtime_payload, $trace_id, $idempotency_key );
		$elapsed_ms      = (int) round( ( microtime( true ) - $started ) * 1000 );

		if ( is_wp_error( $response ) ) {
			$code = (string) $response->get_error_code();
			$data = array(
				'status'         => 502,
				'pack_run_id'    => $pack_run_id,
				'trace_id'       => $trace_id,
				'elapsed_ms'     => $elapsed_ms,
				'error_data'     => $response->get_error_data(),
			);
			if ( 'cloud_commercial_concurrency_exceeded' === $code ) {
				$data['hint'] = __( 'Cloud allows one active run at a time on this site. Wait for the prior run to finish, then try again.', 'npcink-workflow-toolbox' );
				$data['status'] = 429;
			}

			return new WP_Error(
				$code !== '' ? $code : 'npcink_toolbox_insight_pack_qa_cloud_failed',
				(string) $response->get_error_message(),
				$data
			);
		}

		if ( ! is_array( $response ) ) {
			return new WP_Error(
				'npcink_toolbox_insight_pack_qa_invalid_response',
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
		$answer = $this->extract_hosted_ai_answer_text( $response );
		$status = (string) ( $data['status'] ?? ( $response['status'] ?? '' ) );

		return rest_ensure_response(
			array(
				'ok'              => ( '' !== $run_id && '' !== $answer ),
				'mvp'             => true,
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
	 * Compact de-identified pack for the QA prompt (avoid shipping full snapshot).
	 *
	 * @param array<string,mixed> $pack Full pack.
	 * @return array<string,mixed>
	 */
	private function compact_insight_pack_for_qa( array $pack ): array {
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
				'severity'              => (string) ( $finding['severity'] ?? '' ),
				'priority_score'         => $finding['priority_score'] ?? null,
				'evidence_summary'       => (string) ( $finding['evidence_summary'] ?? '' ),
				'recommended_action'     => (string) ( $finding['recommended_action'] ?? '' ),
				'write_boundary'         => (string) ( $finding['write_boundary'] ?? '' ),
				'direct_wordpress_write' => (bool) ( $finding['direct_wordpress_write'] ?? false ),
			);
		}

		$blocked = array();
		foreach ( array_slice( is_array( $pack['blocked_items'] ?? null ) ? $pack['blocked_items'] : array(), 0, 8 ) as $item ) {
			if ( is_array( $item ) ) {
				$blocked[] = $item;
			}
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
			'top_findings'           => $findings,
			'blocked_items'          => $blocked,
			'safety'                 => is_array( $pack['safety'] ?? null ) ? $pack['safety'] : array(),
		);
	}

	/**
	 * Pull assistant text from Cloud runtime execute result shapes.
	 *
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
