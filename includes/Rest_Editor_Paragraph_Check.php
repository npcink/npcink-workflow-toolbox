<?php
/**
 * Editor paragraph-check cluster: the local, provider-free paragraph
 * review signals - fallback sections, hosted-output overlays, and the
 * structural-glue detector - moved verbatim from
 * Rest_Editor_Content_Support as the first cohesive sub-service of the
 * queued editor split (Provider Split Refactor Standard v1). Static by
 * construction: every method is pure text analysis with no instance
 * state, so the service calls them without wiring a second constructor
 * dependency.
 *
 * Suggestion-only by contract: outputs are review rows and overlay
 * diagnostics; nothing here writes posts, terms, media, or settings.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

final class Rest_Editor_Paragraph_Check {

	public static function editor_paragraph_check_has_output( array $section ): bool {
		if ( ! empty( $section['items'] ) && is_array( $section['items'] ) ) {
			return true;
		}
		if ( '' !== trim( sanitize_textarea_field( (string) ( $section['output_text'] ?? '' ) ) ) ) {
			return true;
		}
		$output_json = is_array( $section['output_json'] ?? null ) ? $section['output_json'] : array();
		foreach ( array( 'clarity_check', 'fact_gaps', 'tone_consistency', 'editing_suggestions', 'assumptions_to_verify' ) as $key ) {
			if ( ! empty( $output_json[ $key ] ) ) {
				return true;
			}
		}
		$result = is_array( $section['result'] ?? null ) ? $section['result'] : array();
		foreach ( array( 'clarity_check', 'fact_gaps', 'tone_consistency', 'editing_suggestions', 'assumptions_to_verify' ) as $key ) {
			if ( ! empty( $result[ $key ] ) ) {
				return true;
			}
		}
		return false;
	}


	public static function editor_paragraph_check_local_fallback_section( array $section, string $selected_text ): array {
		$output = self::editor_paragraph_check_local_output( $selected_text );
		$status = sanitize_key( (string) ( $section['status'] ?? 'unknown' ) );

		$section['provider_execution']      = 'hosted_ai_with_local_empty_fallback';
		$section['hosted_ai_status']        = $status;
		$section['fallback_reason']         = 'local_paragraph_check_after_hosted_ai_empty';
		$section['fallback_source']         = 'current_selected_paragraph_only';
		$section['fallback_write_posture']  = 'suggestion_only_no_replacement_text';
		$section['fallback_signal_profile'] = is_array( $output['signal_profile'] ?? null )
			? array_map( 'boolval', $output['signal_profile'] )
			: array();
		$section['output_json']             = $output;
		$section['items']                   = array(
			array(
				'name'          => __( 'Clarity check', 'npcink-workflow-toolbox' ),
				'detail'        => $output['clarity_check'],
				'action_policy' => 'operator_review_only_no_insert',
				'evidence_refs' => array( 'current_selection:paragraph' ),
			),
			array(
				'name'          => __( 'Fact gaps', 'npcink-workflow-toolbox' ),
				'detail'        => $output['fact_gaps'],
				'action_policy' => 'operator_review_only_no_insert',
				'evidence_refs' => array( 'current_selection:paragraph' ),
			),
			array(
				'name'          => __( 'Tone consistency', 'npcink-workflow-toolbox' ),
				'detail'        => $output['tone_consistency'],
				'action_policy' => 'operator_review_only_no_insert',
				'evidence_refs' => array( 'current_selection:paragraph' ),
			),
			array(
				'name'          => __( 'Editing suggestions', 'npcink-workflow-toolbox' ),
				'detail'        => $output['editing_suggestions'],
				'action_policy' => 'operator_review_only_no_insert',
				'evidence_refs' => array( 'current_selection:paragraph' ),
			),
		);

		return $section;
	}


	public static function editor_paragraph_check_local_overlay_section( array $section, string $selected_text ): array {
		$output  = self::editor_paragraph_check_local_output( $selected_text, false );
		$overlay = array(
			'artifact_type'      => 'paragraph_local_review_overlay.v1',
			'source'             => 'current_selected_paragraph_only',
			'write_posture'      => 'suggestion_only_no_replacement_text',
			'action_policy'      => 'operator_review_only_no_insert',
			'status'             => 'ready',
			'provider_execution' => 'local_signal_overlay_after_hosted_ai_output',
			'signal_profile'     => is_array( $output['signal_profile'] ?? null )
				? array_map( 'boolval', $output['signal_profile'] )
				: array(),
			'output_json'        => $output,
			'items'              => self::editor_paragraph_check_local_overlay_items( $output ),
		);

		$section['local_review_overlay'] = $overlay;
		$section['local_signal_profile'] = $overlay['signal_profile'];

		$output_json                         = is_array( $section['output_json'] ?? null ) ? $section['output_json'] : array();
		$output_json['local_review_overlay'] = $overlay;
		$section['output_json']              = $output_json;

		return $section;
	}


	public static function editor_paragraph_check_local_overlay_items( array $output ): array {
		$signals = is_array( $output['signal_profile'] ?? null ) ? $output['signal_profile'] : array();
		$items   = array();

		if ( ! empty( $signals['has_structural_glue'] ) ) {
			$items[] = array(
				'name'          => __( 'Local structure cross-check', 'npcink-workflow-toolbox' ),
				'detail'        => (string) ( $output['clarity_check'] ?? '' ),
				'action_policy' => 'operator_review_only_no_insert',
				'evidence_refs' => array( 'current_selection:local_overlay' ),
			);
			$items[] = array(
				'name'          => __( 'Local fact-boundary check', 'npcink-workflow-toolbox' ),
				'detail'        => (string) ( $output['fact_gaps'] ?? '' ),
				'action_policy' => 'operator_review_only_no_insert',
				'evidence_refs' => array( 'current_selection:local_overlay' ),
			);
		} elseif ( ! empty( $signals['has_metric_claim'] ) || ! empty( $signals['has_comparison_claim'] ) || ! empty( $signals['long_or_dense'] ) ) {
			$items[] = array(
				'name'          => __( 'Local fact-boundary check', 'npcink-workflow-toolbox' ),
				'detail'        => (string) ( $output['fact_gaps'] ?? '' ),
				'action_policy' => 'operator_review_only_no_insert',
				'evidence_refs' => array( 'current_selection:local_overlay' ),
			);
		} elseif ( ! empty( $signals['has_scope_claim'] ) || ! empty( $signals['has_causal_transition'] ) ) {
			$items[] = array(
				'name'          => __( 'Local scope check', 'npcink-workflow-toolbox' ),
				'detail'        => (string) ( $output['tone_consistency'] ?? '' ),
				'action_policy' => 'operator_review_only_no_insert',
				'evidence_refs' => array( 'current_selection:local_overlay' ),
			);
		}

		if ( ! empty( $items ) ) {
			$items[] = array(
				'name'          => __( 'Local editing guardrail', 'npcink-workflow-toolbox' ),
				'detail'        => (string) ( $output['editing_suggestions'] ?? '' ),
				'action_policy' => 'operator_review_only_no_insert',
				'evidence_refs' => array( 'current_selection:local_overlay' ),
			);
		}

		return array_values(
			array_filter(
				$items,
				static function ( $item ): bool {
					return is_array( $item ) && '' !== trim( (string) ( $item['detail'] ?? '' ) );
				}
			)
		);
	}


	public static function editor_paragraph_check_signal_profile( string $text, int $length, int $punctuation_count ): array {
		$has_performance_claim = 1 === preg_match( '/(快|慢|耗时|性能|经测试|同等服务器|相当|无明显|明显性能|读取|保存)/u', $text );
		$has_metric_claim      = 1 === preg_match( '/(\d|万|倍|%|百分|测试|经测试|数量|规模|id|ID|attachment)/u', $text );
		$has_scope_claim       = 1 === preg_match( '/(可用于|适合|场景|条件|范围|限制|边界|因此|所以|由于|因为)/u', $text );
		$has_comparison_claim  = 1 === preg_match( '/(比|相比|对比|相较|优于|弱于|快于|慢于|高于|低于)/u', $text );
		$has_causal_transition = 1 === preg_match( '/(因此|所以|由于|因为|从而|导致)/u', $text );

		return array(
			'long_or_dense'         => $length > 150 || $punctuation_count >= 3,
			'has_performance_claim' => $has_performance_claim,
			'has_metric_claim'      => $has_metric_claim,
			'has_scope_claim'       => $has_scope_claim,
			'has_comparison_claim'  => $has_comparison_claim,
			'has_causal_transition' => $has_causal_transition,
			'has_structural_glue'   => self::editor_text_has_structural_glue( $text ),
		);
	}


	public static function editor_paragraph_check_local_output( string $selected_text, bool $hosted_ai_empty = true ): array {
		$text              = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $selected_text ) ) ?: '' );
		$length            = function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
		$punctuation_count = preg_match_all( '/[。！？!?；;]/u', $text );
		$signals           = self::editor_paragraph_check_signal_profile( $text, $length, (int) $punctuation_count );

		if ( $signals['has_structural_glue'] ) {
			$clarity = __( '选中段落疑似存在标题词、选项标签或短语串与正文黏连的问题，建议先检查分隔、标点、列表或小标题结构。', 'npcink-workflow-toolbox' );
		} elseif ( $signals['long_or_dense'] && $signals['has_metric_claim'] ) {
			$clarity = __( '段落信息量偏高，建议人工检查测试条件、对比结论和适用边界是否需要拆开呈现，避免读者把多个结论混在一起。', 'npcink-workflow-toolbox' );
		} elseif ( $signals['long_or_dense'] ) {
			$clarity = __( '段落信息量偏高，建议人工检查主语、原因、结论和适用边界是否需要拆开呈现，避免读者把多个判断混在一起。', 'npcink-workflow-toolbox' );
		} else {
			$clarity = __( '段落结构基本清楚；重点检查判断对象、原因和适用边界是否已经在上下文中交代。', 'npcink-workflow-toolbox' );
		}

		$fact_gaps = $signals['has_structural_glue']
			? __( '结构黏连会影响读者判断事实边界；发布前需要确认每个标签、维度、方案、ID 或问答对应的正文是否清楚分开。', 'npcink-workflow-toolbox' )
			: ( $signals['has_performance_claim']
				? __( '包含测试、数量、速度或耗时类结论；发布前需要确认测试条件、数据规模、对比对象和结论来源，避免把单次测试写成通用事实。', 'npcink-workflow-toolbox' )
				: ( $signals['has_metric_claim']
					? __( '包含数字、ID、数量或范围类表述；发布前需要确认这些数字对应的对象、条件和来源，避免把局部证据写成通用事实。', 'npcink-workflow-toolbox' )
					: ( $signals['has_comparison_claim']
						? __( '包含比较性判断；发布前需要确认比较对象、比较条件和依据是否在上下文中明确。', 'npcink-workflow-toolbox' )
						: __( '未发现明显数字或比较结论；仍需人工确认段落中的判断是否有上下文依据。', 'npcink-workflow-toolbox' ) ) ) );

		$tone = $signals['has_scope_claim']
			? __( '语气整体偏说明性；涉及“适合/因此/场景”等判断时，建议保持审慎，不要超过已验证范围。', 'npcink-workflow-toolbox' )
			: ( $signals['has_causal_transition']
				? __( '语气整体偏推论式；建议确认原因和结论之间的关系是否足够明确。', 'npcink-workflow-toolbox' )
				: __( '语气整体中性；保持事实说明，并避免加入选中段落没有承载的新判断。', 'npcink-workflow-toolbox' ) );

		$editing_parts = array( __( '不要直接替换正文。', 'npcink-workflow-toolbox' ) );
		if ( $signals['has_structural_glue'] ) {
			$editing_parts[] = __( '优先拆开标题词、选项标签、短语串和正文说明。', 'npcink-workflow-toolbox' );
		} elseif ( $signals['has_performance_claim'] ) {
			$editing_parts[] = __( '优先核对测试条件、性能口径和对比对象。', 'npcink-workflow-toolbox' );
		} elseif ( $signals['has_metric_claim'] ) {
			$editing_parts[] = __( '优先核对数字、ID、数量口径和对应对象。', 'npcink-workflow-toolbox' );
		} elseif ( $signals['has_comparison_claim'] ) {
			$editing_parts[] = __( '优先核对比较对象和比较条件。', 'npcink-workflow-toolbox' );
		} else {
			$editing_parts[] = __( '优先核对该段判断是否有上下文依据。', 'npcink-workflow-toolbox' );
		}
		if ( $signals['has_scope_claim'] ) {
			$editing_parts[] = __( '必要时缩小适用范围，或把原因和边界分开审阅。', 'npcink-workflow-toolbox' );
		} else {
			$editing_parts[] = __( '必要时补充限定条件，或把原因和结论分开审阅。', 'npcink-workflow-toolbox' );
		}
		$editing = implode( '', $editing_parts );

		return array(
			'clarity_check'         => $clarity,
			'fact_gaps'             => $fact_gaps,
			'tone_consistency'      => $tone,
			'editing_suggestions'   => $editing,
			'assumptions_to_verify' => $hosted_ai_empty
				? __( '托管 AI 本次未返回建议，以上为本地兜底检查；仍以人工编辑和原始测试记录为准。', 'npcink-workflow-toolbox' )
				: __( '本地复核只检查结构、事实口径和语气风险；托管 AI 建议仍需人工审阅。', 'npcink-workflow-toolbox' ),
			'signal_profile'        => $signals,
		);
	}


	public static function editor_text_has_structural_glue( string $text ): bool {
		return $this->editor_text_has_heading_label_glue( $text )
			|| $this->editor_text_has_phrase_cluster_glue( $text )
			|| $this->editor_text_has_alnum_cjk_glue( $text );
	}

}
