<?php
/**
 * Shared request-context predicate for maintenance-lane work.
 *
 * Legacy retirement and scheduler maintenance only ever run from admin,
 * REST, or cron contexts; front-end traffic skips their option reads and
 * cron lookups entirely. The check must run inside callbacks (init plus
 * rest_api_init mounts) because REST_REQUEST is not defined at
 * hook-registration time.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

final class Toolbox_Request_Context {

	public static function is_maintenance_request(): bool {
		return ( function_exists( 'is_admin' ) && is_admin() )
			|| ( defined( 'REST_REQUEST' ) && REST_REQUEST )
			|| ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() );
	}
}
