<?php
/**
 * Process environment for tests that create temporary Git repositories.
 *
 * @package Npcink_Toolbox
 */

/**
 * Keep Git fixtures independent of host config and worktree overrides.
 *
 * @param array<string,string> $overrides Test-specific environment.
 * @return array<string,string>
 */
function npcink_toolbox_git_fixture_environment( array $overrides ): array {
	$environment = array_filter( getenv(), static fn( string $key ): bool => ! str_starts_with( $key, 'GIT_' ), ARRAY_FILTER_USE_KEY );
	$environment['GIT_CONFIG_NOSYSTEM'] = '1';
	$environment['GIT_CONFIG_GLOBAL']   = '/dev/null';

	return array_merge( $environment, $overrides );
}
