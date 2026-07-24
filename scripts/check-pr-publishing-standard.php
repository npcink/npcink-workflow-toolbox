<?php

declare(strict_types=1);

$root = dirname( __DIR__ );

function pr_publish_fail( string $message ): never {
	fwrite( STDERR, "[pr-publishing] fail: {$message}\n" );
	exit( 1 );
}

function pr_publish_read( string $path ): string {
	$value = file_get_contents( $path );
	if ( false === $value ) {
		pr_publish_fail( "unable to read {$path}" );
	}

	return $value;
}

function pr_publish_require( bool $condition, string $message ): void {
	if ( ! $condition ) {
		pr_publish_fail( $message );
	}
}

$manifest_path = $root . '/docs/platform/pr-publishing-repositories.json';
$standard_path = $root . '/docs/platform/pr-publishing-standard-v1.md';
$manifest = json_decode( pr_publish_read( $manifest_path ), true );
$standard = pr_publish_read( $standard_path );
$template = pr_publish_read( $root . '/.github/pull_request_template.md' );
$workflow = pr_publish_read( $root . '/.github/workflows/pr-body-contract.yml' );
$publisher = pr_publish_read( $root . '/scripts/publish-pr.sh' );
$composer = pr_publish_read( $root . '/composer.json' );

pr_publish_require( is_array( $manifest ), 'repository inventory must be valid JSON' );
pr_publish_require(
	'npcink_pr_publishing_repositories.v1' === ( $manifest['schema_version'] ?? null ),
	'repository inventory must use the v1 schema'
);
pr_publish_require(
	hash( 'sha256', $publisher ) === ( $manifest['publisher_sha256'] ?? null ),
	'local publisher must match the inventoried v1 SHA-256'
);

$required_sections = $manifest['required_sections'] ?? null;
pr_publish_require(
	array( 'Scope', 'Boundary', 'Verification', 'Risk' ) === $required_sections,
	'repository inventory must keep the shared section order'
);

$repositories = $manifest['repositories'] ?? null;
pr_publish_require( is_array( $repositories ) && 6 === count( $repositories ), 'inventory must contain the six platform repositories' );

$names = array();
foreach ( $repositories as $repository ) {
	pr_publish_require( is_array( $repository ), 'every repository entry must be an object' );
	foreach ( array( 'name', 'github', 'default_base', 'publish_command' ) as $field ) {
		pr_publish_require(
			isset( $repository[ $field ] ) && is_string( $repository[ $field ] ) && '' !== $repository[ $field ],
			"repository entry must contain {$field}"
		);
	}
	$name = $repository['name'];
	pr_publish_require( ! isset( $names[ $name ] ), "duplicate repository entry: {$name}" );
	$names[ $name ] = true;
	pr_publish_require( false !== strpos( $standard, "`{$name}`" ), "standard must list {$name}" );
}

foreach ( $required_sections as $section ) {
	pr_publish_require(
		1 === preg_match( '/^#{1,6}\s+.*' . preg_quote( $section, '/' ) . '/mi', $template ),
		"local PR template must contain the {$section} heading"
	);
	pr_publish_require(
		false !== strpos( $workflow, '"' . $section . '":' ),
		"PR body workflow must validate {$section}"
	);
	pr_publish_require(
		false !== strpos( $publisher, 'for required_heading in Scope Boundary Verification Risk' ),
		'publisher must validate the shared heading set'
	);
}

foreach (
	array(
		'git status --porcelain',
		'git merge-base --is-ancestor "origin/${base_branch}" HEAD',
		'--body-file "${body_path}"',
		'--auto --squash --match-head-commit "${head_sha}"',
		'Approved for production validation by operator.',
	) as $marker
) {
	pr_publish_require( false !== strpos( $publisher, $marker ), "publisher is missing safety marker: {$marker}" );
}

pr_publish_require( false === strpos( $publisher, '--delete-branch' ), 'publisher must not delete branches in multi-worktree repositories' );
pr_publish_require( false !== strpos( $composer, '"pr:publish": "bash scripts/publish-pr.sh"' ), 'Composer must expose pr:publish' );
pr_publish_require( false !== strpos( $standard, 'composer quality:matrix:run' ), 'standard must retain cross-repository closeout' );
pr_publish_require( false !== strpos( $standard, 'does not by itself prove M4 deployment' ), 'standard must separate PR and runtime acceptance evidence' );

fwrite( STDOUT, "[pr-publishing] ok: v1 contract and six-repository inventory\n" );
