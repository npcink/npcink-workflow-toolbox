<?php
/**
 * Builds a de-identified environment / performance-inventory insight pack.
 *
 * Config and inventory only: WP/PHP versions, memory limits, bounded theme and
 * plugin lists, debug and object-cache booleans, content volume aggregates.
 * Never returns secrets, absolute paths, phpinfo dumps, raw logs, Lighthouse
 * scores, or real speed measurements.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

final class Environment_Performance_Insight_Builder {
	public const CONTRACT_VERSION = 'environment_performance_insight_pack.v1';
	public const ARTIFACT_TYPE    = 'environment_performance_insight_pack';

	public const PLUGIN_LIST_LIMIT_DEFAULT = 40;
	public const PLUGIN_LIST_LIMIT_MIN     = 10;
	public const PLUGIN_LIST_LIMIT_MAX     = 80;
	public const PLUGIN_LIST_LIMIT_FILTER  = 'npcink_toolbox_environment_performance_plugin_list_limit';

	/**
	 * @return array<string,mixed>
	 */
	public function build(): array {
		$generated_at = gmdate( 'c' );
		$runtime      = $this->collect_runtime();
		$theme        = $this->collect_theme();
		$plugins      = $this->collect_plugins();
		$volume       = $this->collect_volume();
		$cache_debug  = $this->collect_cache_and_debug();
		$signals      = $this->inventory_signals( $runtime, $theme, $plugins, $volume, $cache_debug );
		$findings     = $this->build_findings( $signals, $plugins );

		usort(
			$findings,
			static function ( array $left, array $right ): int {
				$left_score  = (int) ( $left['priority_score'] ?? 0 );
				$right_score = (int) ( $right['priority_score'] ?? 0 );
				if ( $left_score === $right_score ) {
					return strcmp( (string) ( $left['id'] ?? '' ), (string) ( $right['id'] ?? '' ) );
				}

				return $right_score <=> $left_score;
			}
		);
		$findings = array_slice( $findings, 0, 8 );

		return array(
			'artifact_type'          => self::ARTIFACT_TYPE,
			'contract_version'       => self::CONTRACT_VERSION,
			'version'                => 1,
			'run_id'                 => 'environment-performance-' . gmdate( 'YmdHis' ),
			'site_id'                => $this->site_id(),
			'generated_at'           => $generated_at,
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
			'cloud_required'         => false,
			'core_proposal_created'  => false,
			'measurement_kind'       => 'config_and_inventory_only',
			'summary'                => $this->summary( $runtime, $plugins, $volume, $cache_debug, $findings ),
			'runtime'                => $runtime,
			'theme'                  => $theme,
			'plugins'                => $plugins,
			'volume'                 => $volume,
			'cache_and_debug'        => $cache_debug,
			'inventory_signals'      => $signals,
			'top_findings'           => $findings,
			'opportunities'          => $this->opportunities( $findings ),
			'safety'                 => array(
				'operator_review_required' => true,
				'direct_wordpress_write'   => false,
				'automatic_core_proposal'  => false,
				'secrets_returned'         => false,
				'absolute_paths_returned'  => false,
				'phpinfo_returned'         => false,
				'raw_logs_returned'        => false,
				'speed_score_returned'     => false,
				'lighthouse_run'           => false,
				'cloud_called'             => false,
				'local_scheduler_created'  => false,
				'custom_tables_created'    => false,
				'config_written'           => false,
			),
		);
	}

	public static function plugin_list_limit(): int {
		$limit = self::PLUGIN_LIST_LIMIT_DEFAULT;
		if ( function_exists( 'apply_filters' ) ) {
			$limit = (int) apply_filters( self::PLUGIN_LIST_LIMIT_FILTER, $limit );
		}

		return max( self::PLUGIN_LIST_LIMIT_MIN, min( self::PLUGIN_LIST_LIMIT_MAX, $limit ) );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function collect_runtime(): array {
		$wp_version = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'version' ) : '';
		$php_full   = PHP_VERSION;
		$parts      = explode( '.', $php_full );
		$php_mm     = isset( $parts[0], $parts[1] ) ? ( $parts[0] . '.' . $parts[1] ) : $php_full;
		if ( isset( $parts[2] ) && '' !== $parts[2] ) {
			$patch  = preg_replace( '/[^0-9].*$/', '', (string) $parts[2] );
			$php_mm = $php_mm . ( '' !== (string) $patch ? ( '.' . $patch ) : '' );
		}

		$home = function_exists( 'home_url' ) ? (string) home_url( '/' ) : '';
		$site = function_exists( 'site_url' ) ? (string) site_url( '/' ) : '';

		$uploads_writable = null;
		if ( function_exists( 'wp_upload_dir' ) ) {
			$uploads = wp_upload_dir();
			if ( is_array( $uploads ) && empty( $uploads['error'] ) && isset( $uploads['basedir'] ) && is_string( $uploads['basedir'] ) ) {
				$uploads_writable = is_writable( $uploads['basedir'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- boolean only; path never returned.
			}
		}

		return array(
			'wordpress_version'    => sanitize_text_field( $wp_version ),
			'php_version'          => sanitize_text_field( $php_mm ),
			'memory_limit'         => (string) ini_get( 'memory_limit' ),
			'max_execution_time'   => (int) ini_get( 'max_execution_time' ),
			'is_multisite'         => function_exists( 'is_multisite' ) ? (bool) is_multisite() : false,
			'home_is_https'        => 0 === strpos( $home, 'https://' ),
			'site_is_https'        => 0 === strpos( $site, 'https://' ),
			'uploads_dir_writable' => $uploads_writable,
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function collect_theme(): array {
		$slug    = function_exists( 'get_stylesheet' ) ? (string) get_stylesheet() : '';
		$version = '';
		if ( function_exists( 'wp_get_theme' ) ) {
			$theme = wp_get_theme();
			if ( is_object( $theme ) && method_exists( $theme, 'get' ) ) {
				$version = (string) $theme->get( 'Version' );
				if ( '' === $slug && method_exists( $theme, 'get_stylesheet' ) ) {
					$slug = (string) $theme->get_stylesheet();
				}
			}
		}

		return array(
			'stylesheet' => sanitize_key( $slug ),
			'version'    => sanitize_text_field( $version ),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function collect_plugins(): array {
		$limit  = self::plugin_list_limit();
		$active = array();
		$raw    = array();
		if ( function_exists( 'get_option' ) ) {
			$opt = get_option( 'active_plugins', array() );
			$raw = is_array( $opt ) ? $opt : array();
		}
		$rows = array();
		foreach ( $raw as $file ) {
			if ( ! is_string( $file ) || '' === $file ) {
				continue;
			}
			$slug = dirname( $file );
			if ( '.' === $slug || '' === $slug ) {
				$slug = preg_replace( '/\.php$/', '', basename( $file ) );
			}
			$rows[] = array(
				'slug'    => sanitize_key( (string) $slug ),
				'version' => $this->plugin_file_version( $file ),
			);
		}
		usort(
			$rows,
			static function ( array $a, array $b ): int {
				return strcmp( (string) ( $a['slug'] ?? '' ), (string) ( $b['slug'] ?? '' ) );
			}
		);
		$total     = count( $rows );
		$truncated = $total > $limit;
		$list      = array_slice( $rows, 0, $limit );

		$slugs      = array_map(
			static function ( array $row ): string {
				return (string) ( $row['slug'] ?? '' );
			},
			$rows
		);
		$cache_like = array(
			'redis_object_cache' => in_array( 'redis-cache', $slugs, true ) || in_array( 'redis-object-cache', $slugs, true ),
			'memcached'          => in_array( 'memcached', $slugs, true ) || in_array( 'memcached-redux', $slugs, true ),
			'wp_super_cache'     => in_array( 'wp-super-cache', $slugs, true ),
			'w3_total_cache'     => in_array( 'w3-total-cache', $slugs, true ),
			'litespeed_cache'    => in_array( 'litespeed-cache', $slugs, true ),
		);

		return array(
			'active_plugin_count'  => $total,
			'listed_plugin_count'  => count( $list ),
			'list_truncated'       => $truncated,
			'list_limit'           => $limit,
			'active_plugins'       => $list,
			'common_cache_plugins' => $cache_like,
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function collect_volume(): array {
		$posts = 0;
		$pages = 0;
		$media = 0;
		if ( function_exists( 'wp_count_posts' ) ) {
			$pc    = wp_count_posts( 'post' );
			$pg    = wp_count_posts( 'page' );
			$posts = is_object( $pc ) ? max( 0, (int) ( $pc->publish ?? 0 ) ) : 0;
			$pages = is_object( $pg ) ? max( 0, (int) ( $pg->publish ?? 0 ) ) : 0;
		}
		if ( function_exists( 'wp_count_attachments' ) ) {
			$att = wp_count_attachments();
			if ( is_object( $att ) ) {
				foreach ( get_object_vars( $att ) as $n ) {
					$media += max( 0, (int) $n );
				}
			}
		}

		$cron_count = null;
		if ( function_exists( '_get_cron_array' ) ) {
			$cron = _get_cron_array();
			if ( is_array( $cron ) ) {
				$n = 0;
				foreach ( $cron as $hooks ) {
					if ( is_array( $hooks ) ) {
						$n += count( $hooks );
					}
				}
				$cron_count = $n;
			}
		}

		$autoload = $this->autoload_size_estimate();

		return array(
			'published_posts'       => $posts,
			'published_pages'       => $pages,
			'attachment_count'      => $media,
			'cron_event_hook_count' => $cron_count,
			'autoload_options'      => $autoload,
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function autoload_size_estimate(): array {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_row' ) ) {
			return array(
				'available' => false,
				'reason'    => 'unavailable',
			);
		}
		// Cheap aggregate only; no option names or values leave the site.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row( "SELECT COUNT(*) AS c, COALESCE(SUM(LENGTH(option_value)),0) AS bytes FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto','auto-on')" );
		if ( ! is_object( $row ) ) {
			return array(
				'available' => false,
				'reason'    => 'query_failed',
			);
		}

		return array(
			'available'    => true,
			'option_count' => (int) ( $row->c ?? 0 ),
			'approx_bytes' => (int) ( $row->bytes ?? 0 ),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function collect_cache_and_debug(): array {
		$using_ext = function_exists( 'wp_using_ext_object_cache' ) ? (bool) wp_using_ext_object_cache() : false;
		$drop_in   = false;
		if ( defined( 'WP_CONTENT_DIR' ) ) {
			$drop_in = is_readable( WP_CONTENT_DIR . '/object-cache.php' );
		}

		return array(
			'using_ext_object_cache' => $using_ext,
			'object_cache_dropin'    => $drop_in,
			'wp_debug'               => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'wp_debug_display'       => defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY,
			'wp_debug_log'           => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
			'script_debug'           => defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG,
			'savequeries'            => defined( 'SAVEQUERIES' ) && SAVEQUERIES,
		);
	}

	/**
	 * @param array<string,mixed> $runtime Runtime.
	 * @param array<string,mixed> $theme Theme.
	 * @param array<string,mixed> $plugins Plugins.
	 * @param array<string,mixed> $volume Volume.
	 * @param array<string,mixed> $cache_debug Cache/debug.
	 * @return array<string,mixed>
	 */
	private function inventory_signals( array $runtime, array $theme, array $plugins, array $volume, array $cache_debug ): array {
		$php       = (string) ( $runtime['php_version'] ?? '' );
		$mem       = (string) ( $runtime['memory_limit'] ?? '' );
		$mem_bytes = $this->memory_to_bytes( $mem );

		return array(
			'measurement_kind'        => 'config_and_inventory_only',
			'php_version'             => $php,
			'wordpress_version'       => (string) ( $runtime['wordpress_version'] ?? '' ),
			'memory_limit'            => $mem,
			'memory_limit_bytes'      => $mem_bytes,
			'memory_looks_low'        => ( null !== $mem_bytes && $mem_bytes > 0 && $mem_bytes < 128 * 1024 * 1024 ),
			'active_plugin_count'     => (int) ( $plugins['active_plugin_count'] ?? 0 ),
			'plugin_list_truncated'   => ! empty( $plugins['list_truncated'] ),
			'using_ext_object_cache'  => ! empty( $cache_debug['using_ext_object_cache'] ),
			'object_cache_dropin'     => ! empty( $cache_debug['object_cache_dropin'] ),
			'wp_debug_display'        => ! empty( $cache_debug['wp_debug_display'] ),
			'wp_debug'                => ! empty( $cache_debug['wp_debug'] ),
			'home_is_https'           => ! empty( $runtime['home_is_https'] ),
			'published_total'         => (int) ( $volume['published_posts'] ?? 0 ) + (int) ( $volume['published_pages'] ?? 0 ),
			'attachment_count'        => (int) ( $volume['attachment_count'] ?? 0 ),
			'theme_stylesheet'        => (string) ( $theme['stylesheet'] ?? '' ),
			'any_common_cache_plugin' => in_array( true, array_values( is_array( $plugins['common_cache_plugins'] ?? null ) ? $plugins['common_cache_plugins'] : array() ), true ),
		);
	}

	/**
	 * @param array<string,mixed>             $signals Signals.
	 * @param array<string,mixed>             $plugins Plugins.
	 * @return array<int,array<string,mixed>>
	 */
	private function build_findings( array $signals, array $plugins ): array {
		$findings = array();
		$count    = (int) ( $signals['active_plugin_count'] ?? 0 );

		if ( ! empty( $signals['wp_debug_display'] ) ) {
			$findings[] = $this->finding(
				'debug_display_on',
				__( 'Debug display may be on for visitors', 'npcink-workflow-toolbox' ),
				'debug_posture',
				88,
				'high',
				__( 'WP_DEBUG_DISPLAY looks enabled. That can slow pages and show internal messages to visitors.', 'npcink-workflow-toolbox' ),
				__( 'Debug output is meant for troubleshooting, not everyday visitors.', 'npcink-workflow-toolbox' ),
				__( 'Ask your host or developer to turn off front-end debug display after fixing issues. Toolbox will not change wp-config.', 'npcink-workflow-toolbox' ),
				'manual_review_only',
				array()
			);
		} elseif ( ! empty( $signals['wp_debug'] ) ) {
			$findings[] = $this->finding(
				'debug_mode_on',
				__( 'Debug mode appears to be on', 'npcink-workflow-toolbox' ),
				'debug_posture',
				70,
				'medium',
				__( 'WP_DEBUG is enabled. Fine while troubleshooting; leave it off for normal days.', 'npcink-workflow-toolbox' ),
				__( 'Debug mode can add overhead.', 'npcink-workflow-toolbox' ),
				__( 'Confirm with whoever manages the server whether debug should stay on. Suggestion only.', 'npcink-workflow-toolbox' ),
				'manual_review_only',
				array()
			);
		}

		if ( empty( $signals['using_ext_object_cache'] ) && empty( $signals['object_cache_dropin'] ) && empty( $signals['any_common_cache_plugin'] ) ) {
			$findings[] = $this->finding(
				'no_object_cache_signal',
				__( 'No object cache signal showed up', 'npcink-workflow-toolbox' ),
				'cache_inventory',
				72,
				'medium',
				__( 'This inventory did not see an object-cache drop-in or a common cache plugin. That is not a speed score—only a missing helper signal.', 'npcink-workflow-toolbox' ),
				__( 'Object cache can reduce repeated database work on busy sites.', 'npcink-workflow-toolbox' ),
				__( 'Ask your host whether object cache is available, or review caching plugins carefully. Do not treat this as a lab speed test.', 'npcink-workflow-toolbox' ),
				'manual_review_only',
				array()
			);
		}

		if ( ! empty( $signals['memory_looks_low'] ) ) {
			$findings[] = $this->finding(
				'memory_limit_looks_low',
				__( 'PHP memory limit looks low on paper', 'npcink-workflow-toolbox' ),
				'runtime_config',
				75,
				'medium',
				sprintf(
					/* translators: %s: memory_limit string. */
					__( 'memory_limit is set to %s. That may be tight for a large admin or many plugins—this is a config reading, not a measured crash.', 'npcink-workflow-toolbox' ),
					(string) ( $signals['memory_limit'] ?? '' )
				),
				__( 'Low PHP memory limits sometimes cause failed admin tasks.', 'npcink-workflow-toolbox' ),
				__( 'Ask the host whether raising the PHP memory limit is appropriate. Toolbox will not edit server config.', 'npcink-workflow-toolbox' ),
				'manual_review_only',
				array()
			);
		}

		if ( $count >= 40 ) {
			$findings[] = $this->finding(
				'many_active_plugins',
				__( 'Quite a few plugins are active', 'npcink-workflow-toolbox' ),
				'plugin_inventory',
				68,
				$count >= 60 ? 'high' : 'medium',
				sprintf(
					/* translators: %d: active plugin count. */
					__( '%d plugins are active. More plugins often mean more work for each page—still not a measured speed score.', 'npcink-workflow-toolbox' ),
					$count
				),
				__( 'Unused plugins add maintenance and sometimes load cost.', 'npcink-workflow-toolbox' ),
				__( 'In Plugins, turn off anything you no longer need, then watch the site. Suggestion only; no automatic deactivation.', 'npcink-workflow-toolbox' ),
				'manual_review_only',
				array()
			);
		}

		if ( empty( $signals['home_is_https'] ) ) {
			$findings[] = $this->finding(
				'home_not_https',
				__( 'Site address may not be using HTTPS', 'npcink-workflow-toolbox' ),
				'transport',
				80,
				'high',
				__( 'home_url does not start with https:// in this inventory.', 'npcink-workflow-toolbox' ),
				__( 'HTTPS protects visitors and is expected by modern browsers.', 'npcink-workflow-toolbox' ),
				__( 'Check Settings → General and your host SSL panel. Toolbox will not rewrite URLs.', 'npcink-workflow-toolbox' ),
				'manual_review_only',
				array()
			);
		}

		unset( $plugins );

		return $findings;
	}

	/**
	 * @param array<string,mixed>             $runtime Runtime.
	 * @param array<string,mixed>             $plugins Plugins.
	 * @param array<string,mixed>             $volume Volume.
	 * @param array<string,mixed>             $cache_debug Cache/debug.
	 * @param array<int,array<string,mixed>>  $findings Findings.
	 * @return array<string,mixed>
	 */
	private function summary( array $runtime, array $plugins, array $volume, array $cache_debug, array $findings ): array {
		return array(
			'measurement_kind'       => 'config_and_inventory_only',
			'wordpress_version'      => (string) ( $runtime['wordpress_version'] ?? '' ),
			'php_version'            => (string) ( $runtime['php_version'] ?? '' ),
			'memory_limit'           => (string) ( $runtime['memory_limit'] ?? '' ),
			'active_plugin_count'    => (int) ( $plugins['active_plugin_count'] ?? 0 ),
			'listed_plugin_count'    => (int) ( $plugins['listed_plugin_count'] ?? 0 ),
			'plugin_list_truncated'  => ! empty( $plugins['list_truncated'] ),
			'published_posts'        => (int) ( $volume['published_posts'] ?? 0 ),
			'published_pages'        => (int) ( $volume['published_pages'] ?? 0 ),
			'using_ext_object_cache' => ! empty( $cache_debug['using_ext_object_cache'] ),
			'wp_debug_display'       => ! empty( $cache_debug['wp_debug_display'] ),
			'top_finding_count'      => count( $findings ),
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $findings Findings.
	 * @return array<int,array<string,mixed>>
	 */
	private function opportunities( array $findings ): array {
		$out = array();
		foreach ( array_slice( $findings, 0, 5 ) as $finding ) {
			$out[] = array(
				'id'                 => (string) ( $finding['id'] ?? '' ),
				'title'              => (string) ( $finding['title'] ?? '' ),
				'recommended_action' => (string) ( $finding['recommended_action'] ?? '' ),
				'write_boundary'     => (string) ( $finding['write_boundary'] ?? 'manual_review_only' ),
			);
		}

		return $out;
	}

	/**
	 * @param array<int,array<string,mixed>> $source_refs Refs.
	 * @return array<string,mixed>
	 */
	private function finding( string $id, string $title, string $issue_type, int $priority_score, string $severity, string $evidence_summary, string $impact, string $recommended_action, string $write_boundary, array $source_refs ): array {
		return array(
			'id'                     => $id,
			'title'                  => $title,
			'issue_type'             => $issue_type,
			'category'               => 'environment_performance',
			'priority_score'         => $priority_score,
			'severity'               => $severity,
			'evidence_summary'       => $evidence_summary,
			'impact'                 => $impact,
			'recommended_action'     => $recommended_action,
			'write_boundary'         => $write_boundary,
			'direct_wordpress_write' => false,
			'source_refs'            => $source_refs,
		);
	}


	/**
	 * Read only the Version header from an active plugin main file.
	 * Reads Version from the plugin main file without loading core plugin admin helpers.
	 */
	private function plugin_file_version( string $relative_file ): string {
		if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
			return '';
		}
		$path = WP_PLUGIN_DIR . '/' . ltrim( str_replace( '\\', '/', $relative_file ), '/' );
		// Refuse path escape outside the plugins directory.
		$root = realpath( WP_PLUGIN_DIR );
		$real = realpath( $path );
		if ( false === $root || false === $real || 0 !== strpos( $real, $root ) || ! is_readable( $real ) ) {
			return '';
		}
		$fh = fopen( $real, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $fh ) {
			return '';
		}
		$head = fread( $fh, 8192 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		if ( ! is_string( $head ) || '' === $head ) {
			return '';
		}
		if ( 1 !== preg_match( '/^[ \t\/*#@]*Version:\s*(.+)$/mi', $head, $m ) ) {
			return '';
		}

		return sanitize_text_field( trim( (string) $m[1] ) );
	}

	private function memory_to_bytes( string $value ): ?int {
		$value = trim( $value );
		if ( '' === $value || '-1' === $value ) {
			return null;
		}
		if ( ! preg_match( '/^(\d+)\s*([KMG])?B?$/i', $value, $m ) ) {
			if ( ctype_digit( $value ) ) {
				return (int) $value;
			}

			return null;
		}
		$n = (int) $m[1];
		$u = strtoupper( (string) ( $m[2] ?? '' ) );
		if ( 'K' === $u ) {
			return $n * 1024;
		}
		if ( 'M' === $u ) {
			return $n * 1024 * 1024;
		}
		if ( 'G' === $u ) {
			return $n * 1024 * 1024 * 1024;
		}

		return $n;
	}

	private function site_id(): string {
		$host = '';
		if ( function_exists( 'home_url' ) ) {
			$host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		}

		return '' !== $host ? sanitize_text_field( $host ) : 'local-site';
	}
}
