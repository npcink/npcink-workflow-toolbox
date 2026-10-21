<?php
/**
 * Plain-language messages for Npcink Cloud failures shown to site owners.
 *
 * Wording and classification mirror Npcink Cloud Addon
 * (Npcink_Cloud_Addon_WordPress_AI_Connector::user_facing_runtime_failure,
 * addon master 76274ef) so both plugins say the same thing. Toolbox keeps its
 * own copy because the Addon helper also records Addon-owned diagnostics and
 * Toolbox must keep working when the Addon is absent. Raw error codes stay in
 * the response data (`diagnostic_code`) for copy/support use only.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Maps Cloud error families to one plain sentence each.
 */
final class Cloud_Error_Plain_Messages {

	public const KEY_BUSY                = 'busy_slot';
	public const KEY_CREDITS_EXHAUSTED   = 'plain_credits_exhausted';
	public const KEY_NOT_CONNECTED       = 'plain_not_connected';
	public const KEY_SITE_INACTIVE       = 'plain_site_inactive';
	public const KEY_RATE_LIMITED        = 'plain_rate_limited';
	public const KEY_SERVICE_UNAVAILABLE = 'plain_service_unavailable';
	public const KEY_FAILED              = 'runtime_failed';

	/**
	 * Registers the REST error rewrite for Toolbox routes.
	 */
	public static function register(): void {
		add_filter( 'rest_post_dispatch', array( self::class, 'filter_rest_response' ), 20, 3 );
	}

	/**
	 * Plain sentence for one family key.
	 *
	 * @param string $key Family key.
	 */
	public static function message( string $key ): string {
		switch ( $key ) {
			case self::KEY_BUSY:
				return __( 'The previous AI task on this site has not finished yet. Wait about 3 minutes, then try again.', 'npcink-workflow-toolbox' );
			case self::KEY_CREDITS_EXHAUSTED:
				return __( 'Your AI credits have run out. Top up on Npcink Cloud, then try again.', 'npcink-workflow-toolbox' );
			case self::KEY_NOT_CONNECTED:
				return __( 'This site is no longer connected to Npcink Cloud. Reconnect it on the Npcink AI > Cloud Addon page, then try again.', 'npcink-workflow-toolbox' );
			case self::KEY_SITE_INACTIVE:
				return __( 'This site is turned off in Npcink Cloud. Turn it on in Npcink Cloud, then try again.', 'npcink-workflow-toolbox' );
			case self::KEY_RATE_LIMITED:
				return __( 'Too many AI requests right now. Wait a minute, then try again.', 'npcink-workflow-toolbox' );
			case self::KEY_SERVICE_UNAVAILABLE:
				return __( 'Npcink Cloud is temporarily unavailable. Try again in a few minutes.', 'npcink-workflow-toolbox' );
		}
		return __( 'The AI request did not succeed. Try again later; if it keeps failing, copy the information on the Checks tab and contact support.', 'npcink-workflow-toolbox' );
	}

	/**
	 * Classifies structured codes (never free-form detail) into a family key.
	 *
	 * @param string $code       Error code.
	 * @param string $diagnostic Extra structured codes (Cloud code, stage).
	 * @param int    $status     HTTP status, if known.
	 */
	public static function classify( string $code, string $diagnostic = '', int $status = 0 ): string {
		$haystack = strtolower( $code . ' ' . $diagnostic );
		if ( false !== strpos( $haystack, 'concurrency' ) || false !== strpos( $haystack, 'max_active' ) ) {
			return self::KEY_BUSY;
		}
		if ( 1 === preg_match( '/(?:insufficient[_ ]credit|credits?[_ ]exhausted|quota[_ ]exhausted|payment[_ ]required|credit[_ ]balance)/', $haystack ) || 402 === $status ) {
			return self::KEY_CREDITS_EXHAUSTED;
		}
		if ( 1 === preg_match( '/(?:site[_ ](?:inactive|not[_ ]active|disabled|suspended)|key[_ ]inactive|secret[_ ]inactive)/', $haystack ) ) {
			return self::KEY_SITE_INACTIVE;
		}
		if ( 1 === preg_match( '/(?:not[_ ]connected|not[_ ]configured|unconfigured|credential[_ ](?:expired|revoked|invalid)|signature[_ ]invalid|key[_ ]revoked|invalid[_ ]key|unauthenticated|unauthorized)/', $haystack ) || 401 === $status ) {
			return self::KEY_NOT_CONNECTED;
		}
		if ( 1 === preg_match( '/(?:rate.?limit|too[_ ]many[_ ]requests|throttl)/', $haystack ) || 429 === $status ) {
			return self::KEY_RATE_LIMITED;
		}
		if ( 1 === preg_match( '/(?:service[_ ]unavailable|cloud[_ ]unavailable|runtime[_ ]unavailable|provider[_ ]unavailable|unreachable|maintenance|overloaded)/', $haystack ) || 503 === $status ) {
			return self::KEY_SERVICE_UNAVAILABLE;
		}
		return self::KEY_FAILED;
	}

	/**
	 * Whether a code belongs to a Cloud call (local validation errors are left alone).
	 *
	 * @param string $code Error code.
	 */
	public static function is_cloud_code( string $code ): bool {
		$code = strtolower( $code );
		if ( '' === $code || 0 === strpos( $code, 'rest_' ) || false !== strpos( $code, 'request_cancelled' ) || false !== strpos( $code, 'request_superseded' ) ) {
			return false;
		}
		return 1 === preg_match( '/(?:^cloud_|^npcink_cloud|^commercial|^runtime_|^provider_|_cloud_|cloud_failed|cloud_unavailable|_runtime_|_qa_cloud_failed|_qa_invalid_response)/', $code );
	}

	/**
	 * Rewrites a Cloud WP_Error into a plain one, keeping the code for diagnostics.
	 *
	 * @param WP_Error $error Original error.
	 */
	public static function humanize_error( WP_Error $error ): WP_Error {
		$code = (string) $error->get_error_code();
		$data = $error->get_error_data();
		$data = is_array( $data ) ? $data : array();
		$key  = self::classify( $code, self::diagnostic_from_data( $data ), self::upstream_status( $data, 0 ) );
		return new WP_Error( $code, self::message( $key ), self::plain_data( $data, $code, $key ) );
	}

	/**
	 * Text safe to show for any WP_Error: the plain sentence for Cloud failures,
	 * the original (already user-facing) message for local errors.
	 *
	 * @param WP_Error $error Error.
	 */
	public static function visible_message( WP_Error $error ): string {
		$code = (string) $error->get_error_code();
		if ( ! self::is_cloud_code( $code ) ) {
			return (string) $error->get_error_message();
		}
		return (string) self::humanize_error( $error )->get_error_message();
	}

	/**
	 * Plain sentence for a code that arrives without a WP_Error (embedded 200 payloads).
	 *
	 * @param string $code       Error code.
	 * @param string $diagnostic Extra structured codes.
	 * @param int    $status     Upstream status.
	 */
	public static function message_for_code( string $code, string $diagnostic = '', int $status = 0 ): string {
		return self::message( self::classify( $code, $diagnostic, $status ) );
	}

	/**
	 * Visible message for a Cloud payload that came back as HTTP 200 but carries
	 * a failure (status failed/error/... or an error code). Success messages pass through.
	 *
	 * @param string $status     Payload status.
	 * @param string $message    Payload message.
	 * @param string $error_code Payload error code, if any.
	 */
	public static function embedded_message( string $status, string $message, string $error_code = '' ): string {
		$failed = in_array( strtolower( $status ), array( 'failed', 'failure', 'error', 'errored', 'blocked', 'rejected', 'unavailable', 'denied', 'timeout', 'timed_out' ), true );
		if ( ! $failed && '' === $error_code ) {
			return $message;
		}
		return self::message_for_code( '' !== $error_code ? $error_code : 'cloud_' . strtolower( $status ) );
	}

	/**
	 * Filters Toolbox REST error responses.
	 *
	 * @param mixed           $response Response.
	 * @param mixed           $server   Server.
	 * @param WP_REST_Request $request  Request.
	 * @return mixed
	 */
	public static function filter_rest_response( $response, $server, $request ) {
		if ( ! $response instanceof WP_HTTP_Response || ! is_object( $request ) || ! method_exists( $request, 'get_route' ) ) {
			return $response;
		}
		if ( 0 !== strpos( ltrim( (string) $request->get_route(), '/' ), Plugin::REST_NAMESPACE ) || (int) $response->get_status() < 400 ) {
			return $response;
		}
		$body = $response->get_data();
		if ( ! is_array( $body ) || ! self::is_cloud_code( (string) ( $body['code'] ?? '' ) ) ) {
			return $response;
		}
		$code = (string) $body['code'];
		$data = is_array( $body['data'] ?? null ) ? $body['data'] : array();
		$key  = self::classify( $code, self::diagnostic_from_data( $data ), self::upstream_status( $data, (int) $response->get_status() ) );

		$body['message'] = self::message( $key );
		$body['data']    = self::plain_data( $data, $code, $key );
		unset( $body['additional_errors'] );
		$response->set_data( $body );
		return $response;
	}

	/**
	 * Real upstream HTTP status: nested Cloud status wins over the bridge's wrapper status.
	 *
	 * @param array<string,mixed> $data     Error data.
	 * @param int                 $fallback Wrapper status.
	 */
	private static function upstream_status( array $data, int $fallback ): int {
		$nested = $data['error_data'] ?? null;
		foreach ( array( $nested, $data ) as $source ) {
			if ( is_array( $source ) && isset( $source['cloud_http_status'] ) && is_numeric( $source['cloud_http_status'] ) ) {
				return (int) $source['cloud_http_status'];
			}
		}
		if ( is_array( $nested ) && isset( $nested['status'] ) && is_numeric( $nested['status'] ) ) {
			return (int) $nested['status'];
		}
		if ( isset( $data['status'] ) && is_numeric( $data['status'] ) ) {
			return (int) $data['status'];
		}
		return $fallback;
	}

	/**
	 * Structured diagnostic codes from error data.
	 *
	 * @param array<string,mixed> $data Error data.
	 */
	private static function diagnostic_from_data( array $data ): string {
		$parts = array();
		foreach ( array( 'cloud_error_code', 'error_code', 'diagnostic_code', 'code' ) as $key ) {
			if ( isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) ) {
				$parts[] = (string) $data[ $key ];
			}
		}
		$nested = $data['error_data'] ?? null;
		if ( is_array( $nested ) ) {
			foreach ( array( 'code', 'error_code', 'cloud_error_code' ) as $key ) {
				if ( isset( $nested[ $key ] ) && is_scalar( $nested[ $key ] ) ) {
					$parts[] = (string) $nested[ $key ];
				}
			}
		}
		return implode( ' ', $parts );
	}

	/**
	 * Keeps diagnostics out of visible fields.
	 *
	 * @param array<string,mixed> $data Original data.
	 * @param string              $code Original code.
	 * @param string              $key  Family key.
	 * @return array<string,mixed>
	 */
	private static function plain_data( array $data, string $code, string $key ): array {
		$diagnostic = array(
			'code' => $code,
		);
		foreach ( array( 'status', 'cloud_error_code', 'pack_run_id', 'trace_id', 'run_id' ) as $field ) {
			if ( isset( $data[ $field ] ) && is_scalar( $data[ $field ] ) ) {
				$diagnostic[ $field ] = $data[ $field ];
			}
		}
		$plain = array(
			'plain_message'   => self::message( $key ),
			'plain_family'    => $key,
			'diagnostic_code' => $code,
			'diagnostic'      => $diagnostic,
		);
		foreach ( array( 'status', 'busy_slot', 'cloud_error_code', 'pack_run_id' ) as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$plain[ $field ] = $data[ $field ];
			}
		}
		if ( self::KEY_BUSY === $key ) {
			$plain['busy_slot'] = true;
			$plain['hint']      = self::message( $key );
		}
		return $plain;
	}
}
