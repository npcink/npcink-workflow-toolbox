<?php
/**
 * Pack 2 CLI: content_structure_insight_pack.v1 + one Hosted AI question.
 *
 * Same transport as insight-pack-cloud-qa: Addon execute_runtime with
 * hosted_ai_content_support.v1. Omits Cloud-rejected extras
 * (summary_prompt_mode / http_timeout_seconds / connect_timeout_seconds).
 * Suggestion-only; no WordPress writes; does not use paused cloud_inspection.
 *
 * Prefer the shell wrapper:
 *   scripts/content-structure-pack-cloud-qa.sh
 *   scripts/content-structure-pack-cloud-qa.sh "内容结构优先看什么？"
 *
 * Env:
 *   NPCINK_CONTENT_STRUCTURE_QA_QUESTION
 *   NPCINK_CONTENT_STRUCTURE_QA_OUT
 *   NPCINK_CONTENT_STRUCTURE_QA_USER (or NPCINK_INSIGHT_QA_USER)
 *
 * Docs: docs/content-structure-pack-cloud-qa-cli.md
 * Boundary: docs/deidentified-assistant-boundary-v1.md
 *
 * @package Npcink_Toolbox
 */

defined( 'ABSPATH' ) || exit;

$default_question = '根据这份内容结构摘要，SEO / 内容健康上优先该处理哪 1–3 件事？用大白话回答。';

$question = '';
if ( isset( $args ) && is_array( $args ) && isset( $args[0] ) && is_string( $args[0] ) && '' !== trim( $args[0] ) ) {
	$question = trim( $args[0] );
}
if ( '' === $question ) {
	$env_q = getenv( 'NPCINK_CONTENT_STRUCTURE_QA_QUESTION' );
	if ( is_string( $env_q ) && '' !== trim( $env_q ) ) {
		$question = trim( $env_q );
	}
}
if ( '' === $question ) {
	$question = $default_question;
}

$out_path = getenv( 'NPCINK_CONTENT_STRUCTURE_QA_OUT' );
$out_path = is_string( $out_path ) && '' !== trim( $out_path ) ? trim( $out_path ) : '';

$fail = static function ( string $code, string $message, array $extra = array() ): void {
	$payload = array_merge(
		array(
			'ok'      => false,
			'error'   => $code,
			'message' => $message,
		),
		$extra
	);
	fwrite( STDERR, wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n" );
	exit( 1 );
};

if ( ! class_exists( '\Npcink_Toolbox\Rest_Content_Structure_Insight_Bridges' ) ) {
	$fail( 'bridge_missing', 'Rest_Content_Structure_Insight_Bridges is not loaded.' );
}
if ( ! class_exists( '\Npcink_Toolbox\Settings' ) ) {
	$fail( 'settings_missing', 'Npcink_Toolbox\\Settings is not loaded.' );
}

$settings = new \Npcink_Toolbox\Settings();
$bridge   = new \Npcink_Toolbox\Rest_Content_Structure_Insight_Bridges( $settings );

try {
	$pack = $bridge->assemble_pack();
} catch ( \Throwable $throwable ) {
	$fail(
		'pack_failed',
		$throwable->getMessage(),
		array( 'exception' => get_class( $throwable ) )
	);
}

$pack_run_id = (string) ( $pack['run_id'] ?? '' );
fwrite( STDERR, "pack_run_id={$pack_run_id} contract=" . (string) ( $pack['contract_version'] ?? '' ) . "\n" );

$request = new \WP_REST_Request( 'POST', '/npcink-toolbox/v1/content-structure/insight-pack-qa' );
$request->set_param( 'question', $question );
$result = $bridge->content_structure_insight_pack_qa( $request );

if ( is_wp_error( $result ) ) {
	$fail(
		(string) $result->get_error_code(),
		(string) $result->get_error_message(),
		array(
			'pack_run_id' => $pack_run_id,
			'error_data'  => $result->get_error_data(),
		)
	);
}

$data = $result instanceof \WP_REST_Response ? $result->get_data() : $result;
if ( ! is_array( $data ) ) {
	$fail( 'invalid_response', 'Unexpected QA response shape.', array( 'pack_run_id' => $pack_run_id ) );
}

$payload = array_merge(
	array(
		'ok'          => ! empty( $data['ok'] ),
		'pack_run_id' => $pack_run_id,
		'question'    => $question,
	),
	$data
);

$json = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
if ( ! is_string( $json ) ) {
	$fail( 'encode_failed', 'Could not encode result JSON.' );
}

if ( '' !== $out_path ) {
	$dir = dirname( $out_path );
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	file_put_contents( $out_path, $json . "\n" );
	fwrite( STDERR, "wrote {$out_path}\n" );
}

fwrite( STDERR, 'run_id=' . (string) ( $payload['run_id'] ?? '' ) . ' elapsed_ms=' . (string) ( $payload['elapsed_ms'] ?? '' ) . "\n" );
$answer = (string) ( $payload['answer'] ?? '' );
fwrite( STDOUT, $answer . ( '' !== $answer ? "\n" : '' ) );

if ( empty( $payload['ok'] ) || '' === $answer ) {
	exit( 2 );
}
