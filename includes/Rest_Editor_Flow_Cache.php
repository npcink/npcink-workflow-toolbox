<?php
/**
 * Shared editor flow-cache layer: the transient-backed client-result
 * cache and its typed accessors, moved verbatim from
 * Rest_Editor_Content_Support as the shared base for the editor split
 * (Provider Split Refactor Standard v1, section 3: helpers used by more
 * than one future cluster live in an abstract base the facade and every
 * sub-service extend). The Provider_Client dependency travels with the
 * cache because every accessor closes over it.
 *
 * Suggestion-only by contract: cached values are provider results for
 * review surfaces; nothing here writes posts, media, or settings.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

abstract class Rest_Editor_Flow_Cache extends Rest_Controller_Support {

	protected const EDITOR_FLOW_CACHE_TTL = 300;

	protected Provider_Client $client;


	protected function editor_cached_site_knowledge( array $input ) {
		return $this->editor_cached_client_result(
			'site_knowledge',
			$input,
			function () use ( $input ) {
				return $this->client->search_site_knowledge( $input );
			}
		);
	}


	protected function editor_cached_content_discoverability( array $input ) {
		return $this->editor_cached_client_result(
			'content_discoverability',
			$input,
			function () use ( $input ) {
				return $this->client->build_content_discoverability_brief( $input );
			}
		);
	}


	protected function editor_cached_hosted_ai_content_support( array $input, bool $force_refresh = false ) {
		return $this->editor_cached_client_result(
			'hosted_ai_content_support',
			$input,
			function () use ( $input ) {
				return $this->client->run_hosted_ai_content_support( $input );
			},
			$force_refresh
		);
	}


	protected function editor_cached_audio_generation( array $input, bool $force_refresh = false ) {
		return $this->editor_cached_client_result(
			'audio_generation',
			$input,
			function () use ( $input ) {
				return $this->client->run_audio_generation( $input );
			},
			$force_refresh
		);
	}


	protected function editor_cached_cloud_web_search( array $input, bool $force_refresh = false ) {
		return $this->editor_cached_client_result(
			'cloud_web_search',
			$input,
			function () use ( $input ) {
				return $this->client->test_cloud_web_search( $input );
			},
			$force_refresh,
			function ( array $result ) use ( $input ): bool {
				return $this->editor_source_extraction_cacheable( $input, $result );
			},
			true
		);
	}


	protected function editor_source_extraction_cacheable( array $input, array $result ): bool {
		if ( 'source_extraction_preview' !== sanitize_key( (string) ( $input['intent'] ?? '' ) ) ) {
			return true;
		}

		return 'ready' === sanitize_key( (string) ( $result['status'] ?? '' ) )
			&& 'matched' === sanitize_key( (string) ( $result['url_match'] ?? '' ) )
			&& ! empty( $result['results'][0]['reader_excerpt'] ?? '' );
	}


	protected function editor_cached_client_result( string $namespace, array $input, callable $callback, bool $force_refresh = false, ?callable $should_cache = null, bool $replace_cache_on_force = false ) {
		$cache_key = $this->editor_flow_cache_key( $namespace, $input );
		$cached    = $force_refresh ? false : get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			if ( null === $should_cache || $should_cache( $cached ) ) {
				$cached['cache_status'] = 'hit';
				return $cached;
			}
			delete_transient( $cache_key );
		}

		$result = $callback();
		if ( ! is_wp_error( $result ) && is_array( $result ) ) {
			$result['cache_status'] = $force_refresh ? 'bypass' : 'miss';
			$cacheable              = null === $should_cache || $should_cache( $result );
			if ( $cacheable && ( ! $force_refresh || $replace_cache_on_force ) ) {
				set_transient( $cache_key, $result, self::EDITOR_FLOW_CACHE_TTL );
			} elseif ( $force_refresh && $replace_cache_on_force ) {
				delete_transient( $cache_key );
			}
		}

		return $result;
	}


	protected function editor_flow_cache_key( string $namespace, array $input ): string {
		$json = wp_json_encode( $input );
		if ( ! is_string( $json ) ) {
			$json = serialize( $input );
		}

		return 'npcink_toolbox_editor_' . sanitize_key( $namespace ) . '_' . md5( $json );
	}

	/**
	 * Validates one public source URL before Cloud research is requested.
	 *
	 * @param string $value Raw URL.
	 * @return string|WP_Error
	 */
}
