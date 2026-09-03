<?php
/**
 * Behavioral tests for explicit repository paths in the quality matrix.
 *
 * @package Npcink_Toolbox
 */

$root = dirname( __DIR__ );
$temp = sys_get_temp_dir() . '/npcink-quality-matrix-path-' . bin2hex( random_bytes( 6 ) );
mkdir( $temp, 0700, true );

/**
 * Remove an isolated fixture tree.
 *
 * @param string $path Fixture path.
 * @return void
 */
function npcink_quality_matrix_path_remove_tree( string $path ): void {
	if ( ! is_dir( $path ) ) {
		return;
	}
	$items = scandir( $path );
	if ( false === $items ) {
		return;
	}
	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}
		$target = $path . DIRECTORY_SEPARATOR . $item;
		if ( is_dir( $target ) && ! is_link( $target ) ) {
			npcink_quality_matrix_path_remove_tree( $target );
		} else {
			unlink( $target );
		}
	}
	rmdir( $path );
}

/**
 * Run a command with optional environment additions.
 *
 * @param string $command Command.
 * @param array  $env Environment additions.
 * @return array{exit:int,output:string}
 */
function npcink_quality_matrix_path_run( string $command, array $env = array() ): array {
	$process = proc_open(
		'bash -lc ' . escapeshellarg( $command ),
		array(
			0 => array( 'pipe', 'r' ),
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		),
		$pipes,
		null,
		array_merge( getenv(), $env )
	);
	if ( ! is_resource( $process ) ) {
		throw new RuntimeException( 'Failed to start test process.' );
	}
	fclose( $pipes[0] );
	$output = stream_get_contents( $pipes[1] );
	$error  = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	return array(
		'exit'   => (int) proc_close( $process ),
		'output' => trim( (string) $output . "\n" . (string) $error ),
	);
}

/**
 * Create a committed Git repository fixture.
 *
 * @param string $path Fixture path.
 * @param string $remote Origin URL.
 * @return void
 */
function npcink_quality_matrix_path_create_repo( string $path, string $remote ): void {
	mkdir( $path, 0700, true );
	file_put_contents( $path . '/README.md', "fixture\n" );
	$setup = npcink_quality_matrix_path_run(
		'git init -q ' . escapeshellarg( $path )
		. ' && git -C ' . escapeshellarg( $path ) . ' config user.email fixture@example.com'
		. ' && git -C ' . escapeshellarg( $path ) . ' config user.name Fixture'
		. ' && git -C ' . escapeshellarg( $path ) . ' add README.md'
		. ' && git -C ' . escapeshellarg( $path ) . ' commit -qm fixture'
		. ' && git -C ' . escapeshellarg( $path ) . ' remote add origin ' . escapeshellarg( $remote )
	);
	if ( 0 !== $setup['exit'] ) {
		throw new RuntimeException( 'Unable to create Git repository fixture: ' . $setup['output'] );
	}
}

/**
 * Assert a test condition.
 *
 * @param bool   $condition Condition.
 * @param string $message Message.
 * @return void
 */
function npcink_quality_matrix_path_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

try {
	$default_path = $temp . '/npcink-ai-cloud';
	$clean_path   = $temp . '/clean-cloud-worktree';
	$wrong_path   = $temp . '/wrong-repository';
	$non_git_path = $temp . '/not-a-worktree';
	npcink_quality_matrix_path_create_repo( $default_path, 'https://github.com/npcink/npcink-ai-cloud.git' );
	npcink_quality_matrix_path_create_repo( $clean_path, 'git@github-magick-ai-cloud:npcink/npcink-ai-cloud.git' );
	npcink_quality_matrix_path_create_repo( $wrong_path, 'https://github.com/npcink/npcink-cloud-addon.git' );
	mkdir( $non_git_path, 0700, true );
	file_put_contents( $default_path . '/README.md', "dirty\n" );

	$checks = array();
	foreach ( array( 'backend', 'frontend', 'Secret scan', 'Analyze (python)', 'Analyze (javascript-typescript)' ) as $name ) {
		$checks[] = array(
			'name'       => $name,
			'status'     => 'completed',
			'conclusion' => 'success',
		);
	}
	$environment = array(
		'NPCINK_REPO_FAMILY_ROOT'    => $temp,
		'NPCINK_CLOUD_CHECK_RUNS_JSON' => json_encode( array( 'check_runs' => $checks ) ),
	);
	$base_command = escapeshellarg( PHP_BINARY ) . ' '
		. escapeshellarg( $root . '/scripts/cross-repo-quality-matrix.php' )
		. ' --repo=npcink-ai-cloud --run-gates --json';

	$default = npcink_quality_matrix_path_run( $base_command, $environment );
	npcink_quality_matrix_path_assert( 1 === $default['exit'], 'Dirty default Cloud path must fail closed.' );
	$default_report = json_decode( $default['output'], true );
	npcink_quality_matrix_path_assert( 'default' === ( $default_report['results'][0]['path_source'] ?? '' ), 'Default path must identify its source.' );
	npcink_quality_matrix_path_assert( 'needs_validation' === ( $default_report['results'][0]['gate_status'] ?? '' ), 'Dirty default Cloud path must need validation.' );

	$override = npcink_quality_matrix_path_run(
		$base_command . ' ' . escapeshellarg( '--repo-path=npcink-ai-cloud:' . $clean_path ),
		$environment
	);
	npcink_quality_matrix_path_assert( 0 === $override['exit'], 'Clean explicit Cloud path should pass exact-SHA checks.' );
	$override_report = json_decode( $override['output'], true );
	npcink_quality_matrix_path_assert( 'override' === ( $override_report['results'][0]['path_source'] ?? '' ), 'Explicit path must identify its source.' );
	npcink_quality_matrix_path_assert( $clean_path === ( $override_report['results'][0]['path'] ?? '' ), 'Explicit path must be used by the gate.' );
	npcink_quality_matrix_path_assert( 'passed' === ( $override_report['results'][0]['gate_status'] ?? '' ), 'Clean explicit Cloud path must pass.' );

	$relative = npcink_quality_matrix_path_run( $base_command . ' --repo-path=npcink-ai-cloud:relative/path', $environment );
	npcink_quality_matrix_path_assert( 2 === $relative['exit'], 'Relative repository override must be rejected.' );
	$missing = npcink_quality_matrix_path_run( $base_command . ' --repo-path=npcink-ai-cloud:/missing/npcink-ai-cloud', $environment );
	npcink_quality_matrix_path_assert( 2 === $missing['exit'], 'Missing repository override must be rejected.' );
	$unknown = npcink_quality_matrix_path_run( $base_command . ' --repo-path=unknown-repository:/tmp/unknown-repository', $environment );
	npcink_quality_matrix_path_assert( 2 === $unknown['exit'], 'Unknown repository override must be rejected.' );
	$non_git = npcink_quality_matrix_path_run(
		$base_command . ' ' . escapeshellarg( '--repo-path=npcink-ai-cloud:' . $non_git_path ),
		$environment
	);
	npcink_quality_matrix_path_assert( 2 === $non_git['exit'], 'Repository override that is not a Git worktree must be rejected.' );
	$wrong = npcink_quality_matrix_path_run(
		$base_command . ' ' . escapeshellarg( '--repo-path=npcink-ai-cloud:' . $wrong_path ),
		$environment
	);
	npcink_quality_matrix_path_assert( 2 === $wrong['exit'], 'Repository override with the wrong origin must be rejected.' );
	$duplicate = npcink_quality_matrix_path_run(
		$base_command . ' ' . escapeshellarg( '--repo-path=npcink-ai-cloud:' . $clean_path ) . ' '
		. escapeshellarg( '--repo-path=npcink-ai-cloud:' . $clean_path ),
		$environment
	);
	npcink_quality_matrix_path_assert( 2 === $duplicate['exit'], 'Duplicate repository override must be rejected.' );

	echo "Cross-repo quality matrix path behavior passed.\n";
} finally {
	npcink_quality_matrix_path_remove_tree( $temp );
}
