<?php

namespace App\Support;

/**
 * التقييم الائتماني للعميل (من 100) - بيتحسب من التزامه بالأقساط:
 *
 *   نسبة الأقساط اللي اتدفعت في ميعادها   ← الأساس
 *   − 15 نقطة عن كل قسط متأخر دلوقتي
 *   − نص نقطة عن كل يوم تأخير في المتوسط
 *
 *   85+ ممتاز  |  70+ جيد  |  50+ متوسط  |  أقل = خطر
 *   عميل كاش (مفيش أقساط) أو لسه جديد (أقل من قسطين) = مفيش حكم
 */
class CustomerScore
{
    public static function calculate(int $paidTotal, int $paidOnTime, int $overdueNow, float $avgDaysLate, int $contracts): array
    {
        if ($paidTotal === 0 && $overdueNow === 0) {
            return $contracts > 0
                ? ['score' => null, 'label' => 'عميل كاش', 'tone' => 'blue', 'hint' => 'اشترى من غير تقسيط']
                : ['score' => null, 'label' => 'لسه مااشتراش', 'tone' => 'slate', 'hint' => 'مفيش بيانات'];
        }

        if ($paidTotal < 2 && $overdueNow === 0) {
            return ['score' => null, 'label' => 'جديد', 'tone' => 'slate', 'hint' => 'محتاج قسطين على الأقل عشان يتقيّم'];
        }

        $onTimeRate = $paidTotal > 0 ? $paidOnTime / $paidTotal : 0;
        $score = (int) round(max(0, min(100, $onTimeRate * 100 - $overdueNow * 15 - $avgDaysLate * 0.5)));

        [$label, $tone, $hint] = match (true) {
            $score >= 85 => ['ممتاز', 'emerald', 'ملتزم - ينفع تقسّطله تاني بشروط أحسن'],
            $score >= 70 => ['جيد', 'blue', 'ملتزم غالباً مع تأخير بسيط'],
            $score >= 50 => ['متوسط', 'amber', 'تقسيط بمقدم أعلى ومتابعة'],
            default      => ['خطر', 'rose', 'مايتقسّطلوش من غير ضامن'],
        };

        return compact('score', 'label', 'tone', 'hint');
    }
}
