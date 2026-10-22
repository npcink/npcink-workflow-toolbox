<?php
/**
 * Shared plain-language answer guidance for de-identified pack Ask (both packs).
 *
 * Builds the system instruction and user message sent to Hosted AI so answers
 * read well for a non-technical Chinese site owner. Prompt text only: no
 * Cloud calls, no WordPress writes, no local fallback answers.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

final class Insight_Pack_Answer_Guidance {

	public const PACK_SITE_OPS                = 'site_ops';
	public const PACK_CONTENT_STRUCTURE       = 'content_structure';
	public const PACK_PAGE_QUALITY            = 'page_quality';
	public const PACK_ENVIRONMENT_PERFORMANCE = 'environment_performance';

	/**
	 * Everyday wording for pack field names / jargon (key => plain Chinese).
	 *
	 * @return array<string,string>
	 */
	public static function glossary(): array {
		return array(
			'meta description / missing_meta_description_rate' => '搜索结果里的简介（缺简介的文章占比）',
			'excerpt / missing_excerpt_rate'       => '文章摘要',
			'featured image'                       => '文章封面图',
			'thin content / thin_content_rate'     => '内容太短的文章',
			'stale / 180+ days'                    => '半年以上没更新的文章',
			'taxonomy / category / tag'            => '分类和标签',
			'internal links / orphan'              => '站内互相链接（没有其他文章链到它的“孤立文章”）',
			'alt text'                             => '图片的文字说明',
			'priority_score / severity'            => '只用“最该先做 / 其次 / 可以稍后”表达，不写分数',
			'manual_review_only'                   => '需要你自己看一眼再决定',
			'core_handoff_candidate'               => '可以交给现有的审核流程去改（不会自动改）',
			'Site Context'                         => '站点简介（在「站点资料」里填写）',
			'metadata 流程'                          => '在编辑器里补简介/摘要的现有功能',
			'heading structure / multiple H1'      => '标题层级（大标题有几个）',
			'missing_heading_rate'                 => '较长文章缺少分节标题',
			'missing_alt_count / missing_alt_rate' => '图片缺文字说明',
			'measurement_kind'                     => '只看清单/抽样完备度，不是测速分数',
			'object cache drop-in'                 => '对象缓存（加快重复查询的一种机制）',
			'memory_limit'                         => 'PHP 可用内存上限',
			'WP_DEBUG / WP_DEBUG_DISPLAY'          => '调试模式（排错用；平时不宜对着访客开显示）',
			'active_plugin_count'                  => '正在使用的插件数量',
			'Lighthouse / LCP / PageSpeed'         => '不要提及；本摘要没有实验室测速数据',
		);
	}

	public static function system_prompt( string $pack ): string {
		if ( self::PACK_CONTENT_STRUCTURE === $pack ) {
			$pack_label = '内容结构摘要（SEO / 内容健康）';
		} elseif ( self::PACK_PAGE_QUALITY === $pack ) {
			$pack_label = '页面质量摘要（基础完备度）';
		} elseif ( self::PACK_ENVIRONMENT_PERFORMANCE === $pack ) {
			$pack_label = '环境与性能清单（仅配置/清单，不是测速）';
		} else {
			$pack_label = '站点巡检摘要';
		}
		$terms = array();
		foreach ( self::glossary() as $term => $plain ) {
			$terms[] = '- ' . $term . ' → ' . $plain;
		}

		return implode(
			"\n",
			array(
				'You are Npcink Workflow Toolbox. 你在帮一位不懂技术的中文站长看懂他的网站。只能使用随附的脱密' . $pack_label . '（JSON）。',
				'',
				'【写法】',
				'- 全程用简体中文、日常口语，像对朋友解释；句子短。',
				'- 不要出现 JSON 字段名、英文术语、分数或比率原值（例如 missing_meta_description_rate、priority_score、manual_review_only、core_handoff_candidate、thin_content_rate、taxonomy）。换成日常说法，例如“24 篇里有 18 篇缺搜索结果里的简介”。',
				'- 实在避不开的词，用括号加一句短解释。',
				'',
				'【结构（严格按顺序，全文不超过 300 字，宁短勿长）】',
				'1. 若用户消息给出了「第一行要写的话」，回答第一行只原样写那句话本身，不带任何前缀、标签、引号或说明。',
				'2. 结论：1–2 句话说清网站现在最主要的情况。',
				'3. 最多 3 件要做的事，按先后编号；每件写三行：做什么 / 为什么 / 怎么做（用简单步骤，指向站长能找到的地方）。「为什么」「怎么做」各只写一句、不超过 40 字，每件最多引用一个数字。',
				'4. 不要写长篇背景、免责声明或总结段。',
				'',
				'【边界】',
				'- 只依据摘要里真实存在的数据。摘要不含流量、访问量、排名、点击、转化、收入等数据：回答里直接不谈这些，不得提及或假设“高流量页面”“热门文章”“排名下降”等。',
				'- 摘要若标明只是环境清单（不是测速）：禁止编造网站得分、Lighthouse/PageSpeed 分数、LCP/CLS 实测；只能根据清单给检查建议。',
				'- 不要在回答里解释或复述这些规则，也不要说明摘要缺少哪些数据；直接给建议即可。',
				'- 只给建议：不要声称已经或将会修改、发布、审批任何内容，也不要绕过审核流程。',
				'- 不编造账号、邮箱、密钥或私密内容；不要索要正文或评论原文。',
				'',
				'【术语对照（只用右侧说法）】',
				implode( "\n", $terms ),
			)
		);
	}

	/**
	 * Chinese caveat when the sample is small relative to published total.
	 */
	public static function sample_note( int $sample_size, int $published_total ): string {
		if ( $sample_size <= 0 ) {
			return '';
		}
		if ( $published_total > $sample_size ) {
			return sprintf( '只抽查了 %1$d 篇（全站已发布 %2$d 篇），结论仅供参考。', $sample_size, $published_total );
		}
		if ( $sample_size < 10 ) {
			return sprintf( '只看到 %d 篇已发布内容，样本较少，结论仅供参考。', $sample_size );
		}

		return '';
	}

	/**
	 * Chinese caveat for a publish-date stratified sample (no field names).
	 */
	public static function stratified_sample_note( int $strata_count, int $sampled, int $published_total ): string {
		if ( $sampled <= 0 ) {
			return '';
		}
		if ( $strata_count <= 1 || $published_total <= $sampled ) {
			return self::sample_note( $sampled, $published_total );
		}
		$batches = array(
			2 => '两',
			3 => '三',
			4 => '四',
			5 => '五',
		);
		$label   = $batches[ $strata_count ] ?? (string) $strata_count;

		return sprintf( '按发布时间分%1$s批共抽查 %2$d 篇（全站已发布 %3$d 篇），结论仅供参考。', $label, $sampled, $published_total );
	}

	/**
	 * Strips one outer pair of 「」 / “” / "" when it wraps the whole first non-empty
	 * line of a Cloud answer. Quotes anywhere else are left untouched.
	 */
	public static function strip_first_line_quotes( string $answer ): string {
		if ( 1 !== preg_match( '/^(\s*)([^\n]*)(.*)$/su', $answer, $m ) ) {
			return $answer;
		}
		$line  = trim( $m[2] );
		$pairs = array(
			array( '「', '」' ),
			array( '“', '”' ),
			array( '"', '"' ),
		);
		foreach ( $pairs as $pair ) {
			$open  = $pair[0];
			$close = $pair[1];
			$ol    = strlen( $open );
			$cl    = strlen( $close );
			if ( strlen( $line ) > $ol + $cl && 0 === strpos( $line, $open ) && substr( $line, -$cl ) === $close ) {
				$inner = substr( $line, $ol, strlen( $line ) - $ol - $cl );
				// Only strip when the pair wraps the line, not two separate quoted spans.
				if ( false === strpos( $inner, $open ) && false === strpos( $inner, $close ) ) {
					return $m[1] . trim( $inner ) . $m[3];
				}
			}
		}

		return $answer;
	}

	public static function user_message( string $question, string $pack_label_en, string $pack_json, string $sample_note ): string {
		$parts = array( '站长的问题：' . $question );
		if ( '' !== $sample_note ) {
			$parts[] = "第一行要写的话（只写下一行这句话本身，不要写这条说明）：\n" . $sample_note;
		}
		$parts[] = "---\nDe-identified " . $pack_label_en . " (JSON):\n" . $pack_json;

		return implode( "\n\n", $parts );
	}

	/**
	 * Published post+page total from wp_count_posts (0 when unavailable).
	 */

	/**
	 * Chinese caveat when the active-plugin list was truncated for the Cloud payload.
	 */
	public static function plugin_list_sample_note( int $listed, int $active_total, bool $truncated ): string {
		if ( ! $truncated || $listed <= 0 || $active_total <= $listed ) {
			return '';
		}

		return sprintf( '只列出了前 %1$d 个插件名称（共 %2$d 个在用），结论仅供参考。', $listed, $active_total );
	}

	public static function published_total(): int {
		if ( ! function_exists( 'wp_count_posts' ) ) {
			return 0;
		}
		$total = 0;
		foreach ( array( 'post', 'page' ) as $type ) {
			$counts = wp_count_posts( $type );
			$total += is_object( $counts ) ? max( 0, (int) ( $counts->publish ?? 0 ) ) : 0;
		}

		return $total;
	}
}
