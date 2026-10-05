<?php
/**
 * Shared REST input validation helpers for the controller facade and its
 * extracted cluster services.
 *
 * Internal plumbing for the Rest_Controller split, never an extension
 * point: bound text extraction that Cloud calls rely on.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

abstract class Rest_Controller_Support {

	protected const REQUIRED_TEXT_MAX_CHARS = 500;

	protected function required_text( WP_REST_Request $request, string $key, int $max_chars = self::REQUIRED_TEXT_MAX_CHARS ) {
		$value = trim( sanitize_textarea_field( (string) $request->get_param( $key ) ) );
		if ( '' === $value ) {
			return new WP_Error(
				'npcink_toolbox_missing_' . sanitize_key( $key ),
				sprintf(
					/* translators: %s: field name. */
					__( '%s is required.', 'npcink-workflow-toolbox' ),
					$key
				),
				array( 'status' => 400 )
			);
		}

		$max_chars = max( 1, $max_chars );
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			if ( mb_strlen( $value ) > $max_chars ) {
				return mb_substr( $value, 0, $max_chars );
			}
			return $value;
		}

		if ( strlen( $value ) > $max_chars ) {
			return substr( $value, 0, $max_chars );
		}

		return $value;
	}
}

