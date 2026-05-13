<?php

namespace Database\Seeders;

use App\Models\SymptomAdvisorRule;
use Illuminate\Database\Seeder;

class SymptomAdvisorRulesSeeder extends Seeder
{
    private const EMERGENCY_MSG = '🚨 يُفضّل التوجه فوراً لأقرب مستشفى أو قسم طوارئ، أو الاتصال بالإسعاف. لا تعتمد على الحجز الإلكتروني في الحالات الحرجة.';

    public function run(): void
    {
        $emergencyPhrases = [
            'ألم شديد في الصدر', 'ألم صدر شديد', 'ضغط على الصدر', 'ضيق شديد في التنفس مع ألم صدر',
            'ألم يمتد للذراع', 'تعرق بارد مع ألم صدر', 'chest pain severe', 'crushing chest',
            'فقدان وعي', 'اغماء شديد', 'لا استجابة', 'unconscious', 'loss of consciousness',
            'نزيف شديد لا يتوقف', 'نزيف حاد', 'severe bleeding',
            'اختناق', 'لا يتنفس', 'توقف تنفس', 'not breathing', 'choking',
        ];

        SymptomAdvisorRule::query()->updateOrCreate(
            ['slug' => 'emergency_keyword_list'],
            [
                'type' => 'emergency',
                'match_mode' => 'any_keyword',
                'label_ar' => null,
                'message_ar' => self::EMERGENCY_MSG,
                'keywords' => $emergencyPhrases,
                'db_specialty_terms' => null,
                'sort_order' => 0,
                'is_active' => true,
            ]
        );

        SymptomAdvisorRule::query()->updateOrCreate(
            ['slug' => 'emergency_compound_severe_chest_pain'],
            [
                'type' => 'emergency',
                'match_mode' => 'compound_chest',
                'label_ar' => null,
                'message_ar' => self::EMERGENCY_MSG,
                'keywords' => null,
                'db_specialty_terms' => null,
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $specialty = [
            ['slug' => 'specialty_neuro', 'sort' => 10, 'label' => 'مخ وأعصاب', 'kw' => ['صداع', 'دوخة', 'دوخه', 'migraine', 'headache', 'نوبة صرع', 'خدر', 'نمنمة'], 'db' => ['مخ', 'أعصاب']],
            ['slug' => 'specialty_chest_internal', 'sort' => 20, 'label' => 'أمراض باطنة / صدرية', 'kw' => ['كحة', 'كحه', 'بلغم', 'ضيق تنفس', 'تنفس', 'صدر', 'سعال', 'cough', 'breath'], 'db' => ['باطنة', 'باطنه', 'قلب']],
            ['slug' => 'specialty_pediatrics', 'sort' => 30, 'label' => 'طب الأطفال', 'kw' => ['طفل', 'طفلي', 'ابني', 'ابنتي', 'رضيع', 'أطفال', 'سخونية', 'حرارة', 'كحة', 'كحه', 'pediatric', 'child'], 'db' => ['أطفال', 'طفل']],
            ['slug' => 'specialty_dental', 'sort' => 40, 'label' => 'طب الأسنان', 'kw' => ['سن', 'ضرس', 'اسنان', 'أسنان', 'لثة', 'dental', 'tooth'], 'db' => ['أسنان', 'سنان']],
            ['slug' => 'specialty_ophthalmology', 'sort' => 50, 'label' => 'طب وجراحة العيون', 'kw' => ['عين', 'عيون', 'رمد', 'ليزك', 'ضعف نظر', 'eye', 'ophthal'], 'db' => ['عيون', 'عين']],
            ['slug' => 'specialty_ortho', 'sort' => 60, 'label' => 'عظام', 'kw' => ['كسر', 'ركبة', 'مفصل', 'ظهر', 'رقبة', 'عظام', 'ortho'], 'db' => ['عظام']],
            ['slug' => 'specialty_gi_internal', 'sort' => 70, 'label' => 'أمراض باطنة', 'kw' => ['معدة', 'بطن', 'غثيان', 'قيء', 'إسهال', 'امساك', 'حرقان', 'stomach', 'abdomen'], 'db' => ['باطنة', 'باطنه']],
            ['slug' => 'specialty_cardio', 'sort' => 80, 'label' => 'أمراض القلب والباطنة', 'kw' => ['قلب', 'ضغط', 'خفقان', 'دوار', 'cardio', 'heart'], 'db' => ['قلب', 'باطنة', 'باطنه']],
            ['slug' => 'specialty_ent', 'sort' => 90, 'label' => 'أنف وأذن وحنجرة', 'kw' => ['أنف', 'أذن', 'حنجرة', 'حلق', 'ENT', 'otitis'], 'db' => ['أنف', 'أذن', 'حنجرة']],
            ['slug' => 'specialty_physio', 'sort' => 100, 'label' => 'علاج طبيعي', 'kw' => ['علاج طبيعي', 'تأهيل', 'physio', 'rehab'], 'db' => ['علاج طبيعي', 'طبيعي']],
            ['slug' => 'specialty_fallback_internal', 'sort' => 9999, 'label' => 'باطنة عامة', 'kw' => [], 'db' => ['باطنة', 'باطنه', 'داخلية']],
        ];

        foreach ($specialty as $row) {
            SymptomAdvisorRule::query()->updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'type' => 'specialty',
                    'match_mode' => 'any_keyword',
                    'label_ar' => $row['label'],
                    'message_ar' => null,
                    'keywords' => $row['kw'],
                    'db_specialty_terms' => $row['db'],
                    'sort_order' => $row['sort'],
                    'is_active' => true,
                ]
            );
        }
    }
}
