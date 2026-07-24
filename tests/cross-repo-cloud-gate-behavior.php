<?php
/**
 * Behavioral tests for Cloud source and M4 evidence gates.
 *
 * @package Npcink_Toolbox
 */

$root = dirname( __DIR__ );
$temp = sys_get_temp_dir() . '/npcink-cloud-gate-' . bin2hex( random_bytes( 6 ) );
mkdir( $temp, 0700, true );

/**
 * Recursively remove the isolated test repository.
 *
 * @param string $path Path.
 * @return void
 */
function npcink_cloud_gate_remove_tree( string $path ): void {
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
			npcink_cloud_gate_remove_tree( $target );
		} else {
			unlink( $target );
		}
	}
	rmdir( $path );
}

/**
 * Run a command.
 *
 * @param string $command Command.
 * @param array  $env Environment additions.
 * @return array{exit:int,output:string}
 */
function npcink_cloud_gate_test_run( string $command, array $env = array() ): array {
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
 * Assert a test condition.
 *
 * @param bool   $condition Condition.
 * @param string $message Message.
 * @return void
 */
function npcink_cloud_gate_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

try {
	file_put_contents( $temp . '/README.md', "fixture\n" );
	$setup = npcink_cloud_gate_test_run(
		'git init -q ' . escapeshellarg( $temp )
		. ' && git -C ' . escapeshellarg( $temp ) . ' config user.email fixture@example.com'
		. ' && git -C ' . escapeshellarg( $temp ) . ' config user.name Fixture'
		. ' && git -C ' . escapeshellarg( $temp ) . ' add README.md'
		. ' && git -C ' . escapeshellarg( $temp ) . ' commit -qm fixture'
	);
	npcink_cloud_gate_assert( 0 === $setup['exit'], 'Unable to create fixture Git repository.' );
	$head = trim(
		npcink_cloud_gate_test_run(
			'git -C ' . escapeshellarg( $temp ) . ' rev-parse HEAD'
		)['output']
	);

	$checks = array();
	foreach ( array( 'backend', 'frontend', 'Secret scan', 'Analyze (python)', 'Analyze (javascript-typescript)' ) as $name ) {
		$checks[] = array(
			'name'       => $name,
			'status'     => 'completed',
			'conclusion' => 'success',
		);
	}
	$source = npcink_cloud_gate_test_run(
		escapeshellarg( PHP_BINARY ) . ' '
		. escapeshellarg( $root . '/scripts/check-cloud-github-ci.php' ) . ' '
		. escapeshellarg( '--repo-path=' . $temp ),
		array( 'NPCINK_CLOUD_CHECK_RUNS_JSON' => json_encode( array( 'check_runs' => $checks ) ) )
	);
	npcink_cloud_gate_assert( 0 === $source['exit'], 'Exact-SHA GitHub source fixture should pass.' );
	npcink_cloud_gate_assert( false !== strpos( $source['output'], "source_revision={$head}" ), 'Source gate must report exact revision.' );

	$pending_checks                = $checks;
	$pending_checks[0]['status']   = 'in_progress';
	$pending_checks[0]['conclusion'] = null;
	$pending = npcink_cloud_gate_test_run(
		escapeshellarg( PHP_BINARY ) . ' '
		. escapeshellarg( $root . '/scripts/check-cloud-github-ci.php' ) . ' '
		. escapeshellarg( '--repo-path=' . $temp ),
		array( 'NPCINK_CLOUD_CHECK_RUNS_JSON' => json_encode( array( 'check_runs' => $pending_checks ) ) )
	);
	npcink_cloud_gate_assert( 78 === $pending['exit'], 'Pending GitHub checks must need validation.' );
	$unavailable_source = npcink_cloud_gate_test_run(
		escapeshellarg( PHP_BINARY ) . ' '
		. escapeshellarg( $root . '/scripts/check-cloud-github-ci.php' ) . ' '
		. escapeshellarg( '--repo-path=' . $temp ),
		array( 'NPCINK_CLOUD_CHECK_RUNS_JSON' => '{}' )
	);
	npcink_cloud_gate_assert( 75 === $unavailable_source['exit'], 'Invalid GitHub evidence must be blocked_environment.' );

	$status_lines = array(
		'acceptance_state=accepted',
		"source_revision={$head}",
		'source_dirty=false',
		'/=200',
		'/health/live=200',
	);
	foreach ( array( 'postgres', 'redis', 'api', 'frontend', 'proxy', 'worker', 'callback-worker', 'ops-worker' ) as $service ) {
		$status_lines[] = $service . '|status=running|restart=unless-stopped';
	}
	$runtime = npcink_cloud_gate_test_run(
		escapeshellarg( PHP_BINARY ) . ' '
		. escapeshellarg( $root . '/scripts/check-cloud-m4-runtime.php' ) . ' '
		. escapeshellarg( '--repo-path=' . $temp ),
		array(
			'NPCINK_CLOUD_M4_STATUS_OUTPUT' => implode( "\n", $status_lines ),
			'NPCINK_CLOUD_M4_STATUS_EXIT'   => '0',
			'NPCINK_CLOUD_M4_TEST_OUTPUT'   => 'passed',
			'NPCINK_CLOUD_M4_TEST_EXIT'     => '0',
		)
	);
	npcink_cloud_gate_assert( 0 === $runtime['exit'], 'Exact-revision M4 fixture should pass.' );

	$mismatch_lines    = $status_lines;
	$mismatch_lines[1] = 'source_revision=' . str_repeat( 'a', 40 );
	$mismatch = npcink_cloud_gate_test_run(
		escapeshellarg( PHP_BINARY ) . ' '
		. escapeshellarg( $root . '/scripts/check-cloud-m4-runtime.php' ) . ' '
		. escapeshellarg( '--repo-path=' . $temp ),
		array(
			'NPCINK_CLOUD_M4_STATUS_OUTPUT' => implode( "\n", $mismatch_lines ),
			'NPCINK_CLOUD_M4_STATUS_EXIT'   => '0',
			'NPCINK_CLOUD_M4_TEST_OUTPUT'   => 'passed',
			'NPCINK_CLOUD_M4_TEST_EXIT'     => '0',
		)
	);
	npcink_cloud_gate_assert( 79 === $mismatch['exit'], 'Stale M4 revision must require deployment.' );
	$candidate_lines    = $status_lines;
	$candidate_lines[0] = 'acceptance_state=candidate';
	$candidate = npcink_cloud_gate_test_run(
		escapeshellarg( PHP_BINARY ) . ' '
		. escapeshellarg( $root . '/scripts/check-cloud-m4-runtime.php' ) . ' '
		. escapeshellarg( '--repo-path=' . $temp ),
		array(
			'NPCINK_CLOUD_M4_STATUS_OUTPUT' => implode( "\n", $candidate_lines ),
			'NPCINK_CLOUD_M4_STATUS_EXIT'   => '0',
			'NPCINK_CLOUD_M4_TEST_OUTPUT'   => 'passed',
			'NPCINK_CLOUD_M4_TEST_EXIT'     => '0',
		)
	);
	npcink_cloud_gate_assert( 78 === $candidate['exit'], 'Candidate M4 evidence must need validation.' );

	$unavailable_m4 = npcink_cloud_gate_test_run(
		escapeshellarg( PHP_BINARY ) . ' '
		. escapeshellarg( $root . '/scripts/check-cloud-m4-runtime.php' ) . ' '
		. escapeshellarg( '--repo-path=' . $temp ),
		array(
			'NPCINK_CLOUD_M4_STATUS_OUTPUT' => 'ssh unavailable',
			'NPCINK_CLOUD_M4_STATUS_EXIT'   => '255',
		)
	);
	npcink_cloud_gate_assert( 75 === $unavailable_m4['exit'], 'Unavailable M4 must be blocked_environment.' );

	file_put_contents( $temp . '/README.md', "dirty\n" );
	$dirty = npcink_cloud_gate_test_run(
		escapeshellarg( PHP_BINARY ) . ' '
		. escapeshellarg( $root . '/scripts/check-cloud-github-ci.php' ) . ' '
		. escapeshellarg( '--repo-path=' . $temp ),
		array( 'NPCINK_CLOUD_CHECK_RUNS_JSON' => json_encode( array( 'check_runs' => $checks ) ) )
	);
	npcink_cloud_gate_assert( 78 === $dirty['exit'], 'Dirty Cloud source must need validation.' );

	echo "Cloud source/M4 evidence gate behavior passed.\n";
} finally {
	npcink_cloud_gate_remove_tree( $temp );
}
