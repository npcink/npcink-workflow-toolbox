<?php
/**
 * Opt-in, single-code-block Cloud preview.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

final class Editor_Code_Format {

	public static function enabled(): bool {
		return defined( 'NPCINK_TOOLBOX_CODE_FORMAT_PILOT' ) && true === constant( 'NPCINK_TOOLBOX_CODE_FORMAT_PILOT' );
	}

	public static function request( WP_REST_Request $request ) {
		if ( ! self::enabled() ) {
			return self::failure( 'disabled', 404 );
		}
		$params   = $request->get_json_params();
		$keys     = is_array( $params ) ? array_keys( $params ) : array();
		$code     = $params['code'] ?? null;
		$language = $params['language'] ?? null;
		$post_id  = $params['post_id'] ?? null;
		sort( $keys );
		if ( array( 'code', 'intent', 'language', 'post_id' ) !== $keys || 'format_code_block' !== $params['intent']
			|| ! self::valid_code( $code, 16384 ) || ! in_array( $language, array( 'javascript', 'css', 'json' ), true )
			|| ! is_int( $post_id ) || $post_id < 1 ) {
			return self::failure( 'invalid_input', 400 );
		}
		if ( ! Rest_Controller::user_can_use_editor_support() || ! current_user_can( 'edit_post', $post_id ) || ! get_post( $post_id ) ) {
			return self::failure( 'forbidden', 403 );
		}
		$credential_error = Article_Credential_Check::guard_fields( array( $code ) );
		if ( null !== $credential_error ) {
			return $credential_error;
		}
		if ( ! function_exists( 'npcink_cloud_addon_execute_toolbox_code_preview_runtime' ) ) {
			return self::failure( 'unavailable', 503 );
		}
		$upstream = npcink_cloud_addon_execute_toolbox_code_preview_runtime(
			array(
				'code'          => $code,
				'language'      => $language,
				'source_sha256' => hash( 'sha256', $code ),
			)
		);
		if ( is_wp_error( $upstream ) || ! is_array( $upstream ) || 'succeeded' !== ( $upstream['data']['status'] ?? null ) ) {
			return self::failure( 'cloud', 502 );
		}
		$result = $upstream['data']['result'] ?? null;
		if ( ! self::valid_result( $code, $language, $result ) ) {
			return self::failure( 'invalid_candidate', 422 );
		}
		$result['persisted'] = false;
		$response            = rest_ensure_response( $result );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	/** @param mixed $result Untrusted Cloud candidate. */
	public static function valid_result( string $source, string $language, $result ): bool {
		$keys      = is_array( $result ) ? array_keys( $result ) : array();
		$candidate = is_array( $result ) ? ( $result['candidate'] ?? null ) : null;
		sort( $keys );
		return array( 'candidate', 'candidate_sha256', 'contract_version', 'direct_wordpress_write', 'language', 'source_sha256', 'status' ) === $keys
			&& 'code_format_candidate.v1' === $result['contract_version'] && $language === $result['language']
			&& false === $result['direct_wordpress_write'] && self::valid_code( $candidate, 65536 )
			&& hash( 'sha256', $source ) === $result['source_sha256'] && hash( 'sha256', $candidate ) === $result['candidate_sha256']
			&& ( $candidate !== $source ? 'CHANGED' === $result['status'] : in_array( $result['status'], array( 'UNCHANGED', 'SKIPPED' ), true ) );
	}

	/** @param mixed $code Exact UTF-8 source, never sanitized as HTML. */
	private static function valid_code( $code, int $max ): bool {
		return is_string( $code ) && '' !== trim( $code ) && strlen( $code ) <= $max
			&& false === strpos( $code, "\0" ) && 1 === preg_match( '//u', $code );
	}

	private static function failure( string $reason, int $status ): WP_Error {
		return new WP_Error( 'npcink_code_format_' . $reason, __( 'The code preview is unavailable. The original code is unchanged.', 'npcink-workflow-toolbox' ), array( 'status' => $status ) );
	}
}
