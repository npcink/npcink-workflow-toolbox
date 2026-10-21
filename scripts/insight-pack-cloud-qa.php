<?php
/**
 * MVP CLI: load local site_ops insight pack and ask Cloud one question.
 *
 * Uses Addon Npcink_Cloud_Runtime_Client::execute_runtime with
 * hosted_ai_content_support.v1. Intentionally omits Cloud-rejected extras:
 * summary_prompt_mode, http_timeout_seconds, connect_timeout_seconds
 * (Cloud RuntimePayload has extra=forbid; Addon may still allow those keys
 * locally, but forwarding them fails the Cloud request).
 *
 * Does not write WordPress. Does not call paused cloud_inspection /
 * analyze-site-ops. Suggestion-only.
 *
 * Usage (prefer the shell wrapper):
 *
 *   scripts/insight-pack-cloud-qa.sh
 *   scripts/insight-pack-cloud-qa.sh "站点优先该处理什么？"
 *   scripts/insight-pack-cloud-qa.sh "问题" "/Users/muze/Local Sites/magick-ai/app/public"
 *
 * Or via WP-CLI directly (must run as an admin who can manage_options):
 *
 *   wp --path="$WP_PATH" --user=178 eval-file \
 *     /Users/muze/gitee/npcink-workflow-toolbox/scripts/insight-pack-cloud-qa.php \
 *     "根据这份巡检摘要，站点优先该处理哪 1–3 件事？"
 *
 * Env overrides:
 *   NPCINK_INSIGHT_QA_QUESTION  — question text
 *   NPCINK_INSIGHT_QA_OUT       — optional path to write full JSON result
 *   NPCINK_INSIGHT_QA_USER      — WP user login or id (default 178)
 *
 * Prints: pack_run_id, cloud run_id, and answer text.
 *
 * Notes:
 *   - Retries a few times on commercial.concurrency_exceeded / invalid JSON
 *     (site often has max_active_runs=1; a hung tunnel call can occupy the slot).
 *   - Avoids paused paths: analyze-site-ops / cloud_inspection / analyze-nightly.

 *
 *
 * Full runbook (humans + other AIs): docs/insight-pack-cloud-qa-cli.md
 * MVP boundary draft: docs/deidentified-assistant-boundary-draft-v1.md
 *
 * @package Npcink_Toolbox
 */

defined( 'ABSPATH' ) || exit;

$default_question = '根据这份巡检摘要，站点优先该处理哪 1–3 件事？用大白话回答。';

$question = '';
if ( isset( $args ) && is_array( $args ) && isset( $args[0] ) && is_string( $args[0] ) && '' !== trim( $args[0] ) ) {
	$question = trim( $args[0] );
}
if ( '' === $question ) {
	$env_q = getenv( 'NPCINK_INSIGHT_QA_QUESTION' );
	if ( is_string( $env_q ) && '' !== trim( $env_q ) ) {
		$question = trim( $env_q );
	}
}
if ( '' === $question ) {
	$question = $default_question;
}

$out_path = getenv( 'NPCINK_INSIGHT_QA_OUT' );
$out_path = is_string( $out_path ) && '' !== trim( $out_path ) ? trim( $out_path ) : '';

/**
 * Fail fast with a clear line for CLI operators.
 *
 * @param string               $code Error code.
 * @param string               $message Human message.
 * @param array<string,mixed> $extra Optional context.
 */
$fail = static function ( string $code, string $message, array $extra = array() ): void {
	$payload = array_merge(
		array(
			'ok'      => false,
			'error'   => $code,
			'message' => $message,
		),
		$extra
	);
	fwrite( STDERR, wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . "\n" );
	exit( 1 );
};

if ( ! current_user_can( 'manage_options' ) ) {
	$fail( 'forbidden', 'manage_options required (try --user=178 for magick-ai admin login 2).' );
}

if ( ! class_exists( '\Npcink_Cloud_Runtime_Client_Factory' ) ) {
	$fail( 'addon_missing', 'Npcink Cloud Addon is not loaded (need Npcink_Cloud_Runtime_Client_Factory).' );
}

$client = \Npcink_Cloud_Runtime_Client_Factory::configured();
if ( ! $client ) {
	$fail( 'cloud_unconfigured', 'Npcink Cloud Addon is not configured (site key / endpoint).' );
}

/**
 * Build the same pack Site Check / GET insight-pack uses.
 *
 * @return array<string,mixed>
 */
$load_pack = static function (): array {
	if ( class_exists( '\Npcink_Toolbox\Rest_Site_Ops_Insight_Bridges' ) && class_exists( '\Npcink_Toolbox\Settings' ) ) {
		$bridges = new \Npcink_Toolbox\Rest_Site_Ops_Insight_Bridges( new \Npcink_Toolbox\Settings() );
		$pack    = $bridges->assemble_insight_pack();
		if ( is_array( $pack ) && ! empty( $pack ) ) {
			return $pack;
		}
	}

	if ( function_exists( 'rest_do_request' ) && class_exists( 'WP_REST_Request' ) ) {
		$request  = new \WP_REST_Request( 'GET', '/npcink-toolbox/v1/site-ops/insight-pack' );
		$response = rest_do_request( $request );
		if ( ! is_wp_error( $response ) ) {
			$data = $response->get_data();
			if ( is_array( $data ) && ! empty( $data ) ) {
				return $data;
			}
		}
	}

	if ( class_exists( '\Npcink_Toolbox\Site_Ops_Snapshot_Collector' ) && class_exists( '\Npcink_Toolbox\Site_Ops_Insight_Builder' ) ) {
		$collector = new \Npcink_Toolbox\Site_Ops_Snapshot_Collector();
		$builder   = new \Npcink_Toolbox\Site_Ops_Insight_Builder();
		return $builder->build( $collector->collect(), array() );
	}

	throw new \RuntimeException( 'No insight-pack builder or REST route available (is Toolbox active on this branch?).' );
};

try {
	$pack = $load_pack();
} catch ( \Throwable $e ) {
	$fail( 'pack_failed', $e->getMessage() );
}

$pack_run_id = (string) ( $pack['run_id'] ?? '' );

/**
 * Compact de-identified pack for the prompt (avoid shipping full snapshot).
 *
 * @param array<string,mixed> $pack Full pack.
 * @return array<string,mixed>
 */
$compact_pack = static function ( array $pack ): array {
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
};

$compact   = $compact_pack( $pack );
$pack_json = wp_json_encode( $compact, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
if ( ! is_string( $pack_json ) || '' === $pack_json ) {
	$fail( 'pack_encode_failed', 'Could not JSON-encode compact insight pack.' );
}

// Same plain-language guidance as the admin Ask (Insight_Pack_Answer_Guidance).
$guidance_ready = class_exists( '\Npcink_Toolbox\Insight_Pack_Answer_Guidance' );
$sample_note    = $guidance_ready ? \Npcink_Toolbox\Insight_Pack_Answer_Guidance::sample_note( (int) ( $compact['summary']['scanned_posts'] ?? 0 ), \Npcink_Toolbox\Insight_Pack_Answer_Guidance::published_total() ) : '';
$user_content   = $guidance_ready
	? \Npcink_Toolbox\Insight_Pack_Answer_Guidance::user_message( $question, 'site_ops insight pack', $pack_json, $sample_note )
	: $question . "\n\n---\nDe-identified site_ops insight pack (JSON):\n" . $pack_json;
$system_content = $guidance_ready
	? \Npcink_Toolbox\Insight_Pack_Answer_Guidance::system_prompt( \Npcink_Toolbox\Insight_Pack_Answer_Guidance::PACK_SITE_OPS )
	: 'You are Npcink Workflow Toolbox. Use only the provided de-identified Site Check insight pack. Return concise, reviewable suggestions in plain Chinese. Do not claim to write WordPress.';

// Cloud RuntimePayload: extra=forbid. Do NOT send summary_prompt_mode /
// http_timeout_seconds / connect_timeout_seconds (Addon-local only).
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
				'content' => $system_content,
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

$started   = microtime( true );
$response  = null;
$attempts  = 0;
$max_tries = 4;
while ( $attempts < $max_tries ) {
	++$attempts;
	// Fresh idempotency on each attempt so a hung prior lease is not replayed wrongly.
	if ( $attempts > 1 ) {
		$idempotency_key = 'insight_pack_qa_' . wp_generate_uuid4();
		// Back off long enough for an orphaned run to stop holding the slot.
		// Cloud stops counting an inline run against max_active_runs after its
		// admission lease (~180s), so a flat 20s retry only re-runs into the same
		// rejection. Cumulative wait across 4 attempts: 40 + 60 + 80 = 180s.
		$backoff = 20 * $attempts;
		fwrite( STDERR, "retry {$attempts}/{$max_tries} after concurrency/transient failure; waiting {$backoff}s...\n" );
		sleep( $backoff );
	}
	$response = $client->execute_runtime( $runtime_payload, $trace_id, $idempotency_key );
	if ( ! is_wp_error( $response ) ) {
		break;
	}
	$code = (string) $response->get_error_code();
	if ( 'cloud_commercial_concurrency_exceeded' !== $code && 'cloud_runtime_response_invalid' !== $code ) {
		break;
	}
}
$elapsed = (int) round( ( microtime( true ) - $started ) * 1000 );

if ( is_wp_error( $response ) ) {
	$fail(
		(string) $response->get_error_code(),
		(string) $response->get_error_message(),
		array(
			'pack_run_id'     => $pack_run_id,
			'trace_id'        => $trace_id,
			'idempotency_key' => $idempotency_key,
			'elapsed_ms'      => $elapsed,
			'attempts'        => $attempts,
			'error_data'      => $response->get_error_data(),
			'hint'            => ( 'cloud_commercial_concurrency_exceeded' === (string) $response->get_error_code() )
				? 'Cloud still holds the single run slot for this site: the previous run may not have finished cleanly, so waiting for it to "finish" can wait forever. Wait a few minutes and re-run; if it keeps failing, end that run in Cloud first.'
				: '',
		)
	);
}

if ( ! is_array( $response ) ) {
	$fail( 'invalid_response', 'execute_runtime returned a non-array response.', array( 'pack_run_id' => $pack_run_id ) );
}

/**
 * Pull assistant text from Cloud runtime execute result shapes.
 *
 * @param array<string,mixed> $response Runtime response.
 */
$extract_text = static function ( array $response ): string {
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
};

$data   = is_array( $response['data'] ?? null ) ? $response['data'] : $response;
$run_id = (string) ( $data['run_id'] ?? '' );
$answer = $extract_text( $response );
$status = (string) ( $data['status'] ?? ( $response['status'] ?? '' ) );

$result = array(
	'ok'              => ( '' !== $run_id && '' !== $answer ),
	'path'            => 'Npcink_Cloud_Runtime_Client::execute_runtime via hosted_ai_content_support.v1 (no summary_prompt_mode/http_timeout_seconds/connect_timeout_seconds)',
	'ability_name'    => 'npcink-toolbox/ai-content-support',
	'contract'        => 'hosted_ai_content_support.v1',
	'pack_run_id'     => $pack_run_id,
	'run_id'          => $run_id,
	'status'          => $status,
	'trace_id'        => $trace_id,
	'idempotency_key' => $idempotency_key,
	'elapsed_ms'      => $elapsed,
	'question'        => $question,
	'answer'          => $answer,
	'response'        => $response,
);

if ( '' !== $out_path ) {
	$dir = dirname( $out_path );
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	file_put_contents( $out_path, wp_json_encode( $result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . "\n" );
}

// Human-readable summary first (operators reading the terminal).
echo "pack_run_id: {$pack_run_id}\n";
echo "run_id: {$run_id}\n";
echo "status: {$status}\n";
echo "elapsed_ms: {$elapsed}\n";
echo "--- answer ---\n";
echo ( '' !== $answer ? $answer : '(empty answer — inspect NPCINK_INSIGHT_QA_OUT JSON)' ) . "\n";

if ( '' === $run_id || '' === $answer ) {
	exit( 2 );
}
