<?php
/**
 * Verify exact-revision Cloud source checks through GitHub.
 *
 * Exit 75 means the GitHub evidence service is unavailable.
 * Exit 78 means the source revision is not ready for remote validation.
 *
 * @package Npcink_Toolbox
 */

$options = array(
	'repo_path'  => '',
	'repository' => 'npcink/npcink-ai-cloud',
);

foreach ( array_slice( $_SERVER['argv'] ?? array(), 1 ) as $arg ) {
	if ( 0 === strpos( $arg, '--repo-path=' ) ) {
		$options['repo_path'] = substr( $arg, strlen( '--repo-path=' ) );
		continue;
	}
	if ( 0 === strpos( $arg, '--repository=' ) ) {
		$options['repository'] = substr( $arg, strlen( '--repository=' ) );
		continue;
	}
	fwrite( STDERR, "Unknown option: {$arg}\n" );
	exit( 2 );
}

if ( '' === $options['repo_path'] || ! file_exists( $options['repo_path'] . '/.git' ) ) {
	fwrite( STDERR, "Cloud Git worktree is unavailable: {$options['repo_path']}\n" );
	exit( 78 );
}

/**
 * Run a bounded shell command.
 *
 * @param string $command Command.
 * @param string $cwd Working directory.
 * @return array{exit:int,output:string}
 */
function npcink_cloud_ci_run( string $command, string $cwd ): array {
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

$status = npcink_cloud_ci_run( 'git status --porcelain=v1 --untracked-files=all', $options['repo_path'] );
if ( 0 !== $status['exit'] || '' !== trim( $status['output'] ) ) {
	fwrite( STDERR, "Cloud source requires a clean worktree before exact-SHA CI validation.\n" );
	exit( 78 );
}

$head = npcink_cloud_ci_run( 'git rev-parse HEAD', $options['repo_path'] );
if ( 0 !== $head['exit'] || ! preg_match( '/^[0-9a-f]{40}$/', trim( $head['output'] ) ) ) {
	fwrite( STDERR, "Unable to resolve a valid Cloud source revision.\n" );
	exit( 78 );
}
$revision = trim( $head['output'] );

$fixture = getenv( 'NPCINK_CLOUD_CHECK_RUNS_JSON' );
if ( false !== $fixture ) {
	$payload = json_decode( $fixture, true );
} else {
	$gh = npcink_cloud_ci_run(
		'gh api ' . escapeshellarg(
			'repos/' . $options['repository'] . '/commits/' . $revision . '/check-runs?per_page=100'
		),
		$options['repo_path']
	);
	if ( 0 !== $gh['exit'] ) {
		fwrite( STDERR, "GitHub CI evidence is unavailable for {$revision}.\n" );
		exit( 75 );
	}
	$payload = json_decode( $gh['output'], true );
}

if ( ! is_array( $payload ) || ! isset( $payload['check_runs'] ) || ! is_array( $payload['check_runs'] ) ) {
	fwrite( STDERR, "GitHub CI returned invalid check-run evidence.\n" );
	exit( 75 );
}

$required = array(
	'backend',
	'frontend',
	'Secret scan',
	'Analyze (python)',
	'Analyze (javascript-typescript)',
);
$by_name  = array();
foreach ( $payload['check_runs'] as $check ) {
	if ( ! is_array( $check ) ) {
		continue;
	}
	$name = (string) ( $check['name'] ?? '' );
	if ( '' === $name ) {
		continue;
	}
	$by_name[ $name ][] = $check;
}

$pending = array();
$failed  = array();
foreach ( $required as $name ) {
	$checks = $by_name[ $name ] ?? array();
	if ( empty( $checks ) ) {
		$pending[] = $name . ' (missing)';
		continue;
	}
	usort(
		$checks,
		static function ( array $left, array $right ): int {
			return (int) ( $right['id'] ?? 0 ) <=> (int) ( $left['id'] ?? 0 );
		}
	);
	$latest = $checks[0];
	if ( 'completed' !== (string) ( $latest['status'] ?? '' ) ) {
		$pending[] = $name . ' (pending)';
		continue;
	}
	if ( 'success' !== (string) ( $latest['conclusion'] ?? '' ) ) {
		$failed[] = $name;
	}
}

if ( ! empty( $failed ) ) {
	fwrite( STDERR, 'Cloud exact-SHA GitHub checks failed: ' . implode( ', ', $failed ) . "\n" );
	exit( 1 );
}
if ( ! empty( $pending ) ) {
	fwrite( STDERR, 'Cloud exact-SHA GitHub checks need validation: ' . implode( ', ', $pending ) . "\n" );
	exit( 78 );
}

echo "authority=github_ci\n";
echo "source_revision={$revision}\n";
echo 'required_checks=' . implode( ',', $required ) . "\n";
echo "source_gate=passed\n";
