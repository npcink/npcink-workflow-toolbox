<?php

declare( strict_types=1 );

$root = dirname( __DIR__ );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}
if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = '' ): string {
		return $text;
	}
}
require_once $root . '/includes/Article_Credential_Rules.php';
if ( '--rules' === ( $argv[1] ?? '' ) ) {
	echo json_encode( array( 'rules' => \Npcink_Toolbox\Article_Credential_Rules::definitions(), 'maxBytes' => \Npcink_Toolbox\Article_Credential_Rules::MAX_BYTES ) );
	exit;
}
$rules = file_get_contents( $root . '/includes/Article_Credential_Rules.php' );
$check = file_get_contents( $root . '/includes/Article_Credential_Check.php' );
$editor = file_get_contents( $root . '/assets/article-credential-editor.js' );
$admin = file_get_contents( $root . '/assets/article-credential-admin.js' );

foreach ( array( $rules, $check, $editor, $admin ) as $source ) {
	if ( false === $source ) {
		fwrite( STDERR, "Unable to read credential check source.\n" );
		exit( 1 );
	}
}

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
};

$assert( false !== strpos( $rules, 'sk_live_' ) && false !== strpos( $rules, 'PRIVATE KEY' ), 'Credential rules cover API keys and private keys.' );
$assert( array( 'api_key' ) === \Npcink_Toolbox\Article_Credential_Rules::categories( 'token=sk_' . 'live_' . str_repeat( 'a', 24 ), true ), 'API key rule detects a synthetic key-shaped value and returns only a category.' );
$assert( array( 'access_token' ) === \Npcink_Toolbox\Article_Credential_Rules::categories( 'ghp&#95;' . str_repeat( 'b', 36 ) ), 'HTML-encoded credentials are detected.' );
$assert( array( 'connection_password' ) === \Npcink_Toolbox\Article_Credential_Rules::categories( 'postgresql://demo:example@localhost/sample' ), 'Connection-string password is detected.' );
$assert( in_array( 'private_key', \Npcink_Toolbox\Article_Credential_Rules::categories( '-----BEGIN PRIVATE KEY-----' ), true ), 'Private key rule detects a PEM header.' );
$assert( array() === \Npcink_Toolbox\Article_Credential_Rules::categories( 'Use YOUR_API_KEY in the environment.' ), 'Placeholders do not produce findings.' );
$assert( array( 'generic_credential' ) === \Npcink_Toolbox\Article_Credential_Rules::categories( 'password = "not-a-real-value"' ), 'Generic assignments are visible in a report.' );
$assert( array() === \Npcink_Toolbox\Article_Credential_Rules::categories( 'password = "not-a-real-value"', true ), 'Generic assignments do not block a transfer.' );
$assert( array() === \Npcink_Toolbox\Article_Credential_Rules::categories( 'password = "YOUR_PASSWORD"' ), 'Common instructional placeholders are not flagged.' );
$assert( false !== strpos( $check, 'current_user_can( \'manage_options\' )' ), 'Global scan is administrator-only.' );
$assert( false !== strpos( $check, 'check_ajax_referer' ) && false !== strpos( $check, 'LIMIT %d' ), 'Global scan uses nonce protection and a bounded batch.' );
$assert( false !== strpos( $check, 'npcink_article_credential_check_hit' ) && false !== strpos( $check, 'Nothing was sent to Cloud' ), 'Cloud-bound article checks fail closed on a credential hit.' );
$assert( false === strpos( $check, 'post_content' ) || false !== strpos( $check, 'OCTET_LENGTH(post_content)' ), 'Global scan bounds content reads before loading bodies.' );
$assert( false !== strpos( $editor, 'updateBlockAttributes' ) && false === strpos( $editor, 'savePost(' ), 'Single-article replacement stays in native editor state.' );
$assert( false !== strpos( $admin, 'Resume scan' ) && false !== strpos( $admin, 'partial' ), 'Global scan can stop and resume without claiming completeness.' );

function current_user_can( string $cap, int $id = 0 ): bool {
	return 'manage_options' === $cap || ( 'edit_post' === $cap && 3 !== $id );
}
function wp_strip_all_tags( string $text ): string { return strip_tags( $text ); }
function admin_url( string $path ): string { return 'https://example.invalid/wp-admin/' . $path; }
class wpdb {
	public string $posts = 'wp_posts';
	public string $last_error = '';
	public array $loads = array();
	public function get_var( string $sql ): int { return 4; }
	public function prepare( string $sql, int ...$values ): array { return array( $sql, $values ); }
	public function get_results( array $statement ): array {
		return array(
			(object) array( 'ID' => 1, 'article_bytes' => 200 ),
			(object) array( 'ID' => 2, 'article_bytes' => 524289 ),
			(object) array( 'ID' => 3, 'article_bytes' => 200 ),
			(object) array( 'ID' => 4, 'article_bytes' => 10 ),
		);
	}
	public function get_row( array $statement ): object {
		$this->loads[] = $statement[1][0];
		return 1 === $statement[1][0]
			? (object) array( 'post_title' => 'sk_' . 'live_' . str_repeat( 'a', 24 ), 'post_excerpt' => 'password="not-a-real-value"', 'post_content' => '<p>gh<strong>p_' . str_repeat( 'a', 36 ) . '</strong></p>' )
			: (object) array( 'post_title' => 'Clean', 'post_excerpt' => '', 'post_content' => '<p>Routine article.</p>' );
	}
}
require_once $root . '/includes/Toolbox_Asset_Paths.php';
require_once $root . '/includes/Article_Credential_Check.php';
$wpdb = new wpdb();
$result = ( new \Npcink_Toolbox\Article_Credential_Check() )->batch( 0, 0 );
$assert( array( 1, 4 ) === $wpdb->loads && 2 === $result['scanned'] && 2 === $result['skipped'] && $result['done'], 'Only load bounded records with object permission, and distinguish skipped posts.' );
$assert( array( array( 'field' => 'title', 'type' => 'api_key' ), array( 'field' => 'excerpt', 'type' => 'generic_credential' ), array( 'field' => 'body', 'type' => 'access_token' ) ) === $result['items'][0]['findings'], 'Report only field identifiers and categories across all three article fields, including formatted tokens.' );
$assert( 1 === count( $result['items'] ) && ! str_contains( json_encode( $result ), 'not-a-real-value' ), 'Findings never contain the synthetic credential values or clean articles.' );
echo "Article credential check behavior contracts: ok\n";
