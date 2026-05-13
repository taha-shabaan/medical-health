<?php

use App\Models\AiKnowledgeEntry;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        AiKnowledgeEntry::query()->firstOrCreate(
            [
                'triggers' => 'تحاليل,نتائج التحاليل,شرح نتائج,تفسير التحاليل,فحص,مختبر,تحليل,دم,sugar,سكر,lab,results,analysis',
            ],
            [
                'response' => 'عادةً تُوضح نتائج التحاليل: المرجع (المدى الطبيعي) بجانب قيمتك. إن كانت نتيجتك ضمن المدى فغالباً تكون في المعدل الطبيعي. الرموز مثل (H) أو (L) قد تعني أعلى أو أقل قليلاً من المرجع ولا تُفسر وحدها. راجع تقرير الطبيب المرفق بسجلك في المنصة إن وُجد، ولا تعتبر هذا تفسيراً طبياً: أي استنتاج دقيق يحتاج مراجعة طبيبك الناظر لحالتك.',
                'role_context' => 'patient',
                'priority' => 55,
                'is_active' => true,
            ]
        );

        AiKnowledgeEntry::query()->firstOrCreate(
            [
                'triggers' => 'تحاليل,تقرير,مختبر,فحوصات,lab,results',
            ],
            [
                'response' => 'لعرض مرفقات المريض أو تفسير تقني للتحاليل استخدم سجلات المريض في لوحة الطبيب. تذكّر: القرار السريري يبقى مسؤوليتك المهنية.',
                'role_context' => 'doctor',
                'priority' => 55,
                'is_active' => true,
            ]
        );
    }

    public function down(): void
    {
        AiKnowledgeEntry::query()
            ->where('triggers', 'تحاليل,نتائج التحاليل,شرح نتائج,تفسير التحاليل,فحص,مختبر,تحليل,دم,sugar,سكر,lab,results,analysis')
            ->delete();

        AiKnowledgeEntry::query()
            ->where('triggers', 'تحاليل,تقرير,مختبر,فحوصات,lab,results')
            ->delete();
    }
};
