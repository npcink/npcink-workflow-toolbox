<?php
/**
 * Verify M4 runtime evidence for the exact local Cloud revision.
 *
 * Exit 75 means SSH/M4 evidence is unavailable.
 * Exit 78 means the local source is not ready for validation.
 * Exit 79 means M4 must first deploy the exact local revision.
 *
 * @package Npcink_Toolbox
 */

$repo_path = '';
foreach ( array_slice( $_SERVER['argv'] ?? array(), 1 ) as $arg ) {
	if ( 0 === strpos( $arg, '--repo-path=' ) ) {
		$repo_path = substr( $arg, strlen( '--repo-path=' ) );
		continue;
	}
	fwrite( STDERR, "Unknown option: {$arg}\n" );
	exit( 2 );
}
if ( '' === $repo_path || ! file_exists( $repo_path . '/.git' ) ) {
	fwrite( STDERR, "Cloud Git worktree is unavailable: {$repo_path}\n" );
	exit( 78 );
}

/**
 * Run a command or use a test fixture.
 *
 * @param string $command Command.
 * @param string $cwd Working directory.
 * @param string $fixture_prefix Fixture variable prefix.
 * @return array{exit:int,output:string}
 */
function npcink_cloud_m4_run( string $command, string $cwd, string $fixture_prefix ): array {
	$fixture_output = getenv( $fixture_prefix . '_OUTPUT' );
	if ( false !== $fixture_output ) {
		$fixture_exit = getenv( $fixture_prefix . '_EXIT' );
		return array(
			'exit'   => false === $fixture_exit ? 0 : (int) $fixture_exit,
			'output' => $fixture_output,
		);
	}
	$process = proc_open(
		'bash -lc ' . escapeshellarg( $command ),
		array(
			0 => array( 'pipe', 'r' ),
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		),
		$pipes,
		$cwd
	);
	if ( ! is_resource( $process ) ) {
		return array( 'exit' => 127, 'output' => 'Failed to start process.' );
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

$status = npcink_cloud_m4_run(
	'git status --porcelain=v1 --untracked-files=all',
	$repo_path,
	'NPCINK_CLOUD_LOCAL_STATUS'
);
if ( 0 !== $status['exit'] || '' !== trim( $status['output'] ) ) {
	fwrite( STDERR, "Cloud source requires a clean worktree before M4 acceptance.\n" );
	exit( 78 );
}
$head = npcink_cloud_m4_run( 'git rev-parse HEAD', $repo_path, 'NPCINK_CLOUD_LOCAL_HEAD' );
if ( 0 !== $head['exit'] || ! preg_match( '/^[0-9a-f]{40}$/', trim( $head['output'] ) ) ) {
	fwrite( STDERR, "Unable to resolve a valid Cloud source revision.\n" );
	exit( 78 );
}
$revision = trim( $head['output'] );

$m4_status = npcink_cloud_m4_run(
	'pnpm run m4:preview:status',
	$repo_path,
	'NPCINK_CLOUD_M4_STATUS'
);
if ( 0 !== $m4_status['exit'] ) {
	fwrite( STDERR, "M4 status evidence is unavailable.\n" );
	exit( 75 );
}

if ( ! preg_match( '/^source_revision=([0-9a-f]{40})$/m', $m4_status['output'], $matches ) ) {
	fwrite( STDERR, "M4 status did not report a valid source revision.\n" );
	exit( 1 );
}
$deployed_revision = $matches[1];
if ( $deployed_revision !== $revision ) {
	fwrite(
		STDERR,
		"M4 needs deployment: expected {$revision}, found {$deployed_revision}.\n"
	);
	exit( 79 );
}

if ( false === strpos( $m4_status['output'], 'acceptance_state=accepted' )
	|| false === strpos( $m4_status['output'], 'source_dirty=false' ) ) {
	fwrite( STDERR, "M4 exact revision still needs clean accepted deployment evidence.\n" );
	exit( 78 );
}

$required_markers = array( '/=200', '/health/live=200' );
foreach ( array( 'postgres', 'redis', 'api', 'frontend', 'proxy', 'worker', 'callback-worker', 'ops-worker' ) as $service ) {
	$required_markers[] = $service . '|status=running';
}
foreach ( $required_markers as $marker ) {
	if ( false === strpos( $m4_status['output'], $marker ) ) {
		fwrite( STDERR, "M4 runtime evidence failed required marker: {$marker}\n" );
		exit( 1 );
	}
}

$m4_test = npcink_cloud_m4_run(
	'pnpm run m4:preview:test -- --full',
	$repo_path,
	'NPCINK_CLOUD_M4_TEST'
);
if ( 0 !== $m4_test['exit'] ) {
	fwrite( STDERR, "M4 full contract/domain gate failed.\n" );
	exit( 1 );
}

echo "authority=m4_runtime\n";
echo "source_revision={$revision}\n";
echo "deployed_revision={$deployed_revision}\n";
echo "runtime_gate=passed\n";
