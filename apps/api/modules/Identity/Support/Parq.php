<?php

namespace Modules\Identity\Support;

/**
 * PAR-Q+ (Physical Activity Readiness Questionnaire) — the 7 standard general
 * health questions. Any "yes" flags the Person for professional clearance before
 * AI may prescribe exercise (FR-AI-007). Question text is i18n-ready (en/ar) later.
 */
final class Parq
{
    /** @return array<int, array{key:string, text:string, order:int}> */
    public static function questions(): array
    {
        $text = [
            'q1_heart_condition_or_high_bp' => 'Has your doctor ever said that you have a heart condition OR high blood pressure?',
            'q2_chest_pain' => 'Do you feel pain in your chest at rest, during daily activities, or when you do physical activity?',
            'q3_dizziness_or_loss_of_consciousness' => 'Do you lose balance because of dizziness or have you lost consciousness in the last 12 months?',
            'q4_other_chronic_condition' => 'Have you ever been diagnosed with another chronic medical condition (other than heart disease or high blood pressure)?',
            'q5_chronic_condition_medication' => 'Are you currently taking prescribed medications for a chronic medical condition?',
            'q6_bone_joint_soft_tissue_problem' => 'Do you have a bone, joint, or soft-tissue problem that could be made worse by becoming more physically active?',
            'q7_doctor_supervised_activity_only' => 'Has your doctor ever said you should only do medically supervised physical activity?',
        ];

        $order = 0;
        $out = [];
        foreach ($text as $key => $t) {
            $out[] = ['key' => $key, 'text' => $t, 'order' => ++$order];
        }

        return $out;
    }

    /** @return string[] */
    public static function keys(): array
    {
        return array_column(self::questions(), 'key');
    }

    /**
     * Score answers. All "no" → passed; any "yes" → flagged (with the offending keys).
     *
     * @param  array<string, bool>  $answers
     * @return array{result:string, flagged_questions:string[]}
     */
    public static function score(array $answers): array
    {
        $flagged = [];
        foreach (self::keys() as $key) {
            if (! empty($answers[$key])) {
                $flagged[] = $key;
            }
        }

        return [
            'result' => $flagged === [] ? 'passed' : 'flagged',
            'flagged_questions' => $flagged,
        ];
    }
}
