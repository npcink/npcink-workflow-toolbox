<?php
/** Local, deterministic article credential checks. @package Npcink_Toolbox */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

final class Article_Credential_Rules {

	public const MAX_BYTES = 524288;

	/** @return array<string,array{pattern:string, flags:string, label:string, replacement:string, advisory?:true}> */
	public static function definitions(): array {
		return array(
			'api_key'             => array(
				'pattern'     => '(?<![A-Za-z0-9_-])(?:sk-(?:proj-|svcacct-)[A-Za-z0-9_-]{30,256}|sk_live_[A-Za-z0-9]{24,256})(?![A-Za-z0-9_-])',
				'flags'       => '',
				'label'       => __( 'API key', 'npcink-workflow-toolbox' ),
				'replacement' => 'YOUR_API_KEY',
			),
			'access_token'        => array(
				'pattern'     => '(?<![A-Za-z0-9_-])(?:gh[pousr]_[A-Za-z0-9]{36}|github_pat_[A-Za-z0-9_]{60,128}|xox[baprs]-[0-9]{8,}-[A-Za-z0-9-]{12,200})(?![A-Za-z0-9_-])',
				'flags'       => '',
				'label'       => __( 'Access token', 'npcink-workflow-toolbox' ),
				'replacement' => 'YOUR_ACCESS_TOKEN',
			),
			'private_key'         => array(
				'pattern'     => '(-----BEGIN (?:[A-Z0-9]+ )?PRIVATE KEY-----)',
				'flags'       => '',
				'label'       => __( 'Private key (review manually)', 'npcink-workflow-toolbox' ),
				'replacement' => '',
			),
			'connection_password' => array(
				'pattern'     => '(?:^|[^A-Za-z0-9+.-])(?:postgres(?:ql)?|mysql|mongodb(?:\+srv)?|redis(?:s)?|amqps?|mssql)://[^\s:@<>"\']{0,128}:([^\s@<>"\'/]{1,256})@[^\s/<>"\']+',
				'flags'       => 'i',
				'label'       => __( 'Connection string with password (review manually)', 'npcink-workflow-toolbox' ),
				'replacement' => '',
			),
			'generic_credential'  => array(
				'pattern'     => '(?<![A-Za-z0-9_.-])(?:password|passwd|api[_-]?key|(?:access[_-]?)?token|(?:client[_-]?)?secret)["\']?[ \t]*[:=][ \t]*["\']?(?!your[_-]|replace[_-]|placeholder|example["\'\s,;]|changeme(?:[^A-Za-z0-9_-]|$))([A-Za-z0-9_!%+./@~-]{6,128})(?![A-Za-z0-9_!%+./@~-])',
				'flags'       => 'i',
				'label'       => __( 'Password or token assignment', 'npcink-workflow-toolbox' ),
				'replacement' => '',
				'advisory'    => true,
			),
		);
	}

	/** Remove HTML without joining separate paragraphs or code blocks. */
	public static function without_markup( string $html ): string {
		$html = preg_replace( '~<br\s*/?>|</(?:p|h[1-6]|li|div|pre|blockquote|td|th|tr|figcaption)\s*>~i', "$0\n", $html ) ?? '';
		return wp_strip_all_tags( $html );
	}

	/** @return list<string> Rule IDs only, never a source substring. */
	public static function categories( string $text, bool $blocking_only = false ): array {
		$types   = array();
		$decoded = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		foreach ( self::definitions() as $id => $rule ) {
			if ( $blocking_only && ! empty( $rule['advisory'] ) ) {
				continue;
			}
			$pattern = '~' . str_replace( '~', '\\~', $rule['pattern'] ) . '~' . $rule['flags'];
			if ( preg_match( $pattern, $text ) || ( $decoded !== $text && preg_match( $pattern, $decoded ) ) ) {
				$types[] = $id;
			}
		}
		return $types;
	}
}
