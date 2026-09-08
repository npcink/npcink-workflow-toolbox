<?php
/** Run with wp eval-file; uses the installed WordPress HTML tokenizer, no network. */

use Npcink_Toolbox\Editor_Content_Format;

function format_result( string $source, string $candidate ): array {
	return array(
		'contract_version' => 'content_format_candidate.v1', 'format' => 'html',
		'source_sha256' => hash( 'sha256', $source ), 'candidate_sha256' => hash( 'sha256', $candidate ),
		'candidate' => $candidate, 'status' => $source === $candidate ? 'UNCHANGED' : 'CHANGED',
		'inserted_spaces' => strlen( $candidate ) - strlen( $source ),
		'visible_characters_preserved' => true, 'protected_content_skipped' => false, 'direct_wordpress_write' => false,
	);
}
function format_assert( bool $value, string $message ): void {
	if ( ! $value ) { throw new RuntimeException( $message ); }
	WP_CLI::log( 'PASS: ' . $message );
}
$source = "<!-- wp:paragraph -->\r\n<p>中文AI工具 &amp; 测试<strong>加粗AI</strong>。</p>\r\n<!-- /wp:paragraph -->";
$candidate = str_replace( array( '中文AI工具', '加粗AI' ), array( '中文 AI 工具', '加粗 AI' ), $source );
format_assert( Editor_Content_Format::valid_result( $source, format_result( $source, $candidate ) ), 'Safe spacing preserves markup and CRLF.' );
format_assert( Editor_Content_Format::valid_result( $source, format_result( $source, $source ) ), 'Unchanged result is accepted.' );
foreach ( array(
	str_replace( '中文', '修改', $source ),
	str_replace( '<p>', '<p onclick="alert(1)">', $source ),
	str_replace( 'wp:paragraph', 'wp: paragraph', $source ),
	str_replace( "\r\n", "\n", $source ),
) as $index => $invalid ) {
	if ( $source === $invalid ) { continue; }
	format_assert( ! Editor_Content_Format::valid_result( $source, format_result( $source, $invalid ) ), 'Reject mutation ' . $index );
}
foreach ( array( 'a href="https://example.test/中文AI"', 'code', 'pre', 'table' ) as $tag ) {
	$name = explode( ' ', $tag )[0];
	$protected = '<' . $tag . '>中文AI</' . $name . '>';
	$invalid = str_replace( '中文AI', '中文 AI', $protected );
	format_assert( ! Editor_Content_Format::valid_result( $protected, format_result( $protected, $invalid ) ), 'Protect ' . $name );
}
foreach ( array( 'source_sha256' => 'bad', 'candidate_sha256' => 'bad', 'status' => 'REVIEW', 'direct_wordpress_write' => true, 'extra' => 'must_not_echo' ) as $key => $value ) {
	$invalid = format_result( $source, $candidate );
	$invalid[ $key ] = $value;
	format_assert( ! Editor_Content_Format::valid_result( $source, $invalid ), 'Reject invalid result ' . $key );
}
$request = new WP_REST_Request( 'POST', '/npcink-toolbox/v1/editor/content-support' );
$protected = '<!-- wp:gallery --><figure><!-- wp:image {"id":7} --><figure><img src="/中文AI.jpg" alt="中文AI"/></figure><!-- /wp:image --></figure><!-- /wp:gallery -->';
$mixed = $source . $protected;
$partial = format_result( $mixed, $candidate . $protected );
$partial['status'] = 'PARTIAL';
$partial['protected_content_skipped'] = true;
format_assert( Editor_Content_Format::valid_result( $mixed, $partial ), 'Partial spacing accepts untouched gallery and images.' );
$tampered = $partial;
$tampered['candidate'] = str_replace( 'alt="中文AI"', 'alt="中文 AI"', $tampered['candidate'] );
$tampered['candidate_sha256'] = hash( 'sha256', $tampered['candidate'] );
$tampered['inserted_spaces']++;
format_assert( ! Editor_Content_Format::valid_result( $mixed, $tampered ), 'Partial status never authorizes image mutation.' );
$noop = format_result( $mixed, $mixed );
$noop['status'] = 'REVIEW';
$noop['protected_content_skipped'] = true;
format_assert( Editor_Content_Format::valid_result( $mixed, $noop ), 'Protected no-op is distinguished from integrity failure.' );
$struct_source = '<!-- wp:paragraph --><p>介绍。地址：<a href="/buy">详情</a>演示：<a href="/demo">详情</a>价格：12</p><!-- /wp:paragraph -->';
$struct_candidate = '<!-- wp:paragraph --><p>介绍。</p><!-- /wp:paragraph --><!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>地址：<a href="/buy">详情</a></li><!-- /wp:list-item --><!-- wp:list-item --><li>演示：<a href="/demo">详情</a></li><!-- /wp:list-item --><!-- wp:list-item --><li>价格：12</li><!-- /wp:list-item --></ul><!-- /wp:list -->';
$v2 = format_result( $struct_source, $struct_candidate );
$v2['contract_version'] = 'content_format_candidate.v2';
$v2['structural_changes'] = 1;
$v2['inserted_spaces'] = 0;
format_assert( Editor_Content_Format::valid_result( $struct_source, $v2 ), 'v2 permits paragraph to native list without text/link changes.' );
$code_source = '<!-- wp:paragraph --><p><code>keep exact</code></p><!-- /wp:paragraph -->';
$code_noop = format_result( $code_source, $code_source );
$code_noop['contract_version'] = 'content_format_candidate.v2';
$code_noop['structural_changes'] = 0;
$code_noop['protected_content_skipped'] = true;
$code_noop['status'] = 'REVIEW';
format_assert( Editor_Content_Format::valid_result( $code_source, $code_noop ), 'v2 protected no-op does not become another false integrity error.' );
$rich_source = '<!-- wp:paragraph --><p>介绍<strong>重点</strong>。<code>中文AI &lt;x&gt;</code>后续。</p><!-- /wp:paragraph -->';
$rich_candidate = str_replace( '。<code>', '。</p><!-- /wp:paragraph --><!-- wp:paragraph --><p><code>', $rich_source );
$rich_result = format_result( $rich_source, $rich_candidate );
$rich_result['contract_version'] = 'content_format_candidate.v2';
$rich_result['structural_changes'] = 1;
$rich_result['inserted_spaces'] = 0;
format_assert( Editor_Content_Format::valid_result( $rich_source, $rich_result ), 'v2 splits rich paragraphs while preserving inline code.' );
foreach ( array( str_replace( '中文AI', '中文 AI', $rich_candidate ), str_replace( '<strong>', '<strong class="new">', $rich_candidate ) ) as $invalid ) {
	$bad = $rich_result;
	$bad['candidate'] = $invalid;
	$bad['candidate_sha256'] = hash( 'sha256', $invalid );
	format_assert( ! Editor_Content_Format::valid_result( $rich_source, $bad ), 'v2 rejects inline code or emphasis attribute mutation.' );
}
foreach ( array(
	str_replace( '/buy', '/other', $struct_candidate ),
	str_replace( '价格：12', '价格：13', $struct_candidate ),
	str_replace( '<li>', '<li onclick="alert(1)">', $struct_candidate ),
	str_replace( '<a href="/buy">详情</a>', '<a href="/buy">详 情</a>', $struct_candidate ),
	$struct_candidate . '<!-- wp:image --><img src="/new"/><!-- /wp:image -->',
) as $invalid ) {
	$bad = $v2;
	$bad['candidate'] = $invalid;
	$bad['candidate_sha256'] = hash( 'sha256', $invalid );
	format_assert( ! Editor_Content_Format::valid_result( $struct_source, $bad ), 'v2 rejects changed links, numbers, attributes and media.' );
}
$request->set_header( 'Content-Type', 'application/json' );
$request->set_body( wp_json_encode( array( 'intent' => 'format_content', 'post_id' => 1, 'content' => $source ) ) );
wp_set_current_user( 0 );
$denied = Editor_Content_Format::request( $request );
format_assert( is_wp_error( $denied ) && 403 === $denied->get_error_data()['status'], 'Unauthenticated formatting is denied before Cloud.' );
