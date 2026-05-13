<?php

namespace App\Services;

use App\Models\SymptomAdvisorRule;
use App\Models\User;
use App\Support\DoctorAvailability;
use Illuminate\Support\Str;
use Throwable;

/**
 * Rule-based symptom → specialty hints + doctor shortlist.
 * Rules load from `symptom_advisor_rules` (seeded); falls back to built-ins if the table is empty or unreadable.
 */
final class SymptomDoctorAdvisor
{
    /**
     * @return array{
     *   emergency: bool,
     *   emergency_message_ar: string|null,
     *   suggested_specialty_ar: string,
     *   matched_hints: list<string>,
     *   doctors: list<array<string, mixed>>,
     *   disclaimer_ar: string
     * }
     */
    public function analyze(string $raw, ?string $patientGovernorate): array
    {
        $text = $this->normalize($raw);

        $emergency = $this->detectEmergency($text);
        if ($emergency !== null) {
            return [
                'emergency' => true,
                'emergency_message_ar' => $emergency,
                'suggested_specialty_ar' => 'طوارئ / مستشفى',
                'matched_hints' => ['طوارئ'],
                'doctors' => [],
                'disclaimer_ar' => 'هذا تنبيه أمان عام وليس تشخيصاً طبياً. في الحالات الخطرة اتصل بالإسعاف أو اذهب لأقرب طوارئ.',
            ];
        }

        $match = $this->matchSpecialty($text);
        $terms = $match['db_terms'];
        $label = $match['label_ar'];
        $hints = $match['hints'];

        $query = User::query()
            ->where('role', 'doctor')
            ->where('is_active', true)
            ->withAvg('doctorRatingsReceived as rating_avg', 'rating');

        $query->where(function ($q) use ($terms) {
            foreach ($terms as $t) {
                $like = '%'.$t.'%';
                $q->orWhere('specialty', 'like', $like);
            }
        });

        $candidates = $query
            ->limit(40)
            ->get([
                'id', 'name', 'email', 'phone', 'avatar', 'specialty',
                'governorate', 'area', 'address', 'consultation_price',
            ]);

        $patientGov = ($patientGovernorate !== null && $patientGovernorate !== '') ? trim($patientGovernorate) : null;

        $sorted = $candidates->sort(function (User $a, User $b) use ($patientGov) {
            $sameA = $patientGov && trim((string) $a->governorate) === $patientGov ? 1 : 0;
            $sameB = $patientGov && trim((string) $b->governorate) === $patientGov ? 1 : 0;
            if ($sameA !== $sameB) {
                return $sameB <=> $sameA;
            }

            return (float) ($b->rating_avg ?? 0) <=> (float) ($a->rating_avg ?? 0);
        })->values();

        $doctorsOut = [];
        foreach ($sorted->take(5) as $doctor) {
            $slot = DoctorAvailability::firstAvailableSlot($doctor);
            $doctorsOut[] = [
                'id' => $doctor->id,
                'name' => $doctor->name,
                'specialty' => $doctor->specialty,
                'governorate' => $doctor->governorate,
                'area' => $doctor->area,
                'address' => $doctor->address,
                'consultation_price' => $doctor->consultation_price,
                'avatar' => $doctor->avatar,
                'rating_avg' => $doctor->rating_avg !== null ? round((float) $doctor->rating_avg, 2) : null,
                'next_slot' => $slot,
                'estimated_wait_minutes' => DoctorAvailability::estimatedWaitMinutes(),
                'same_governorate_as_patient' => $patientGov && trim((string) $doctor->governorate) === $patientGov,
            ];
        }

        return [
            'emergency' => false,
            'emergency_message_ar' => null,
            'suggested_specialty_ar' => $label,
            'matched_hints' => $hints,
            'doctors' => $doctorsOut,
            'disclaimer_ar' => 'الاقتراح مبني على كلمات مفتاحية وليس بديلاً عن الفحص الطبي. يُنصح بمتابعة الطبيب عند استمرار الأعراض.',
        ];
    }

    private function normalize(string $raw): string
    {
        $s = Str::of($raw)->squish()->lower()->value();

        return mb_strtolower($s, 'UTF-8');
    }

    private function detectEmergency(string $text): ?string
    {
        try {
            $rows = SymptomAdvisorRule::query()
                ->where('type', 'emergency')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();
        } catch (Throwable) {
            $rows = collect();
        }

        if ($rows->isEmpty()) {
            return $this->detectEmergencyFallback($text);
        }

        foreach ($rows as $row) {
            if ($row->match_mode === 'compound_chest') {
                if (str_contains($text, 'ألم') && str_contains($text, 'صدر') && (str_contains($text, 'شديد') || str_contains($text, 'حاد'))) {
                    return (string) $row->message_ar;
                }

                continue;
            }

            foreach ($row->keywords ?? [] as $n) {
                $nNorm = mb_strtolower(trim((string) $n), 'UTF-8');
                if ($nNorm !== '' && str_contains($text, $nNorm)) {
                    return (string) $row->message_ar;
                }
            }
        }

        return null;
    }

    private function detectEmergencyFallback(string $text): ?string
    {
        $rules = [
            ['ألم شديد في الصدر', 'ألم صدر شديد', 'ضغط على الصدر', 'ضيق شديد في التنفس مع ألم صدر', 'ألم يمتد للذراع', 'تعرق بارد مع ألم صدر', 'chest pain severe', 'crushing chest'],
            ['فقدان وعي', 'اغماء شديد', 'لا استجابة', 'unconscious', 'loss of consciousness'],
            ['نزيف شديد لا يتوقف', 'نزيف حاد', 'severe bleeding'],
            ['اختناق', 'لا يتنفس', 'توقف تنفس', 'not breathing', 'choking'],
        ];

        foreach ($rules as $needles) {
            foreach ($needles as $n) {
                $nNorm = mb_strtolower(trim($n), 'UTF-8');
                if ($nNorm !== '' && str_contains($text, $nNorm)) {
                    return '🚨 يُفضّل التوجه فوراً لأقرب مستشفى أو قسم طوارئ، أو الاتصال بالإسعاف. لا تعتمد على الحجز الإلكتروني في الحالات الحرجة.';
                }
            }
        }

        if (str_contains($text, 'ألم') && str_contains($text, 'صدر') && (str_contains($text, 'شديد') || str_contains($text, 'حاد'))) {
            return '🚨 يُفضّل التوجه فوراً لأقرب مستشفى أو قسم طوارئ، أو الاتصال بالإسعاف. لا تعتمد على الحجز الإلكتروني في الحالات الحرجة.';
        }

        return null;
    }

    /**
     * @return array{label_ar: string, db_terms: list<string>, hints: list<string>}
     */
    private function matchSpecialty(string $text): array
    {
        try {
            $rules = SymptomAdvisorRule::query()
                ->where('type', 'specialty')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();
        } catch (Throwable) {
            $rules = collect();
        }

        if ($rules->isEmpty()) {
            return $this->matchSpecialtyFallback($text);
        }

        $best = null;
        $bestScore = 0;
        $fallbackRow = null;

        foreach ($rules as $row) {
            if ($row->slug === 'specialty_fallback_internal') {
                $fallbackRow = $row;

                continue;
            }

            $score = 0;
            $hitHints = [];
            foreach ($row->keywords ?? [] as $kw) {
                $k = mb_strtolower((string) $kw, 'UTF-8');
                if ($k !== '' && str_contains($text, $k)) {
                    $score++;
                    $hitHints[] = $kw;
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = [
                    'label_ar' => (string) $row->label_ar,
                    'db_terms' => array_values(array_unique($row->db_specialty_terms ?? [])),
                    'hints' => $hitHints,
                ];
            }
        }

        if ($bestScore === 0 && $fallbackRow) {
            return [
                'label_ar' => (string) $fallbackRow->label_ar,
                'db_terms' => array_values(array_unique($fallbackRow->db_specialty_terms ?? [])),
                'hints' => ['لم يُعثر على كلمات محددة؛ عرض أطباء باطنة'],
            ];
        }

        if ($best === null || $bestScore === 0) {
            return $this->matchSpecialtyFallback($text);
        }

        return [
            'label_ar' => $best['label_ar'],
            'db_terms' => array_values(array_unique($best['db_terms'])),
            'hints' => $best['hints'] ?? [],
        ];
    }

    /**
     * @return array{label_ar: string, db_terms: list<string>, hints: list<string>}
     */
    private function matchSpecialtyFallback(string $text): array
    {
        $rules = [
            [
                'keywords' => ['صداع', 'دوخة', 'دوخه', 'migraine', 'headache', 'نوبة صرع', 'خدر', 'نمنمة'],
                'label_ar' => 'مخ وأعصاب',
                'db_terms' => ['مخ', 'أعصاب'],
            ],
            [
                'keywords' => ['كحة', 'كحه', 'بلغم', 'ضيق تنفس', 'تنفس', 'صدر', 'سعال', 'cough', 'breath'],
                'label_ar' => 'أمراض باطنة / صدرية',
                'db_terms' => ['باطنة', 'باطنه', 'قلب'],
            ],
            [
                'keywords' => ['طفل', 'طفلي', 'ابني', 'ابنتي', 'رضيع', 'أطفال', 'سخونية', 'حرارة', 'كحة', 'كحه', 'pediatric', 'child'],
                'label_ar' => 'طب الأطفال',
                'db_terms' => ['أطفال', 'طفل'],
            ],
            [
                'keywords' => ['سن', 'ضرس', 'اسنان', 'أسنان', 'لثة', 'dental', 'tooth'],
                'label_ar' => 'طب الأسنان',
                'db_terms' => ['أسنان', 'سنان'],
            ],
            [
                'keywords' => ['عين', 'عيون', 'رمد', 'ليزك', 'ضعف نظر', 'eye', 'ophthal'],
                'label_ar' => 'طب وجراحة العيون',
                'db_terms' => ['عيون', 'عين'],
            ],
            [
                'keywords' => ['كسر', 'ركبة', 'مفصل', 'ظهر', 'رقبة', 'عظام', 'ortho'],
                'label_ar' => 'عظام',
                'db_terms' => ['عظام'],
            ],
            [
                'keywords' => ['معدة', 'بطن', 'غثيان', 'قيء', 'إسهال', 'امساك', 'حرقان', 'stomach', 'abdomen'],
                'label_ar' => 'أمراض باطنة',
                'db_terms' => ['باطنة', 'باطنه'],
            ],
            [
                'keywords' => ['قلب', 'ضغط', 'خفقان', 'دوار', 'cardio', 'heart'],
                'label_ar' => 'أمراض القلب والباطنة',
                'db_terms' => ['قلب', 'باطنة', 'باطنه'],
            ],
            [
                'keywords' => ['أنف', 'أذن', 'حنجرة', 'حلق', 'ENT', 'otitis'],
                'label_ar' => 'أنف وأذن وحنجرة',
                'db_terms' => ['أنف', 'أذن', 'حنجرة'],
            ],
            [
                'keywords' => ['علاج طبيعي', 'تأهيل', 'physio', 'rehab'],
                'label_ar' => 'علاج طبيعي',
                'db_terms' => ['علاج طبيعي', 'طبيعي'],
            ],
        ];

        $best = null;
        $bestScore = 0;

        foreach ($rules as $rule) {
            $score = 0;
            $hitHints = [];
            foreach ($rule['keywords'] as $kw) {
                $k = mb_strtolower($kw, 'UTF-8');
                if ($k !== '' && str_contains($text, $k)) {
                    $score++;
                    $hitHints[] = $kw;
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $rule;
                $best['hints'] = $hitHints;
            }
        }

        if ($best === null || $bestScore === 0) {
            return [
                'label_ar' => 'باطنة عامة',
                'db_terms' => ['باطنة', 'باطنه', 'داخلية'],
                'hints' => ['لم يُعثر على كلمات محددة؛ عرض أطباء باطنة'],
            ];
        }

        return [
            'label_ar' => $best['label_ar'],
            'db_terms' => array_values(array_unique($best['db_terms'])),
            'hints' => $best['hints'] ?? [],
        ];
    }
}
