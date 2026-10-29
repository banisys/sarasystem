<?php

namespace Modules\School\Services;

use App\Models\Tenant;
use App\Support\PersianPdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Class\Enums\AttendanceStatus;
use Modules\Class\Models\Attendance;
use Modules\Class\Models\HomeworkSubmission;
use Modules\School\Enums\ScoreScale;
use Modules\School\Models\Curriculum;
use Modules\School\Models\Enrollment;
use Modules\School\Models\Score;
use Modules\School\Models\ScorePeriod;

/**
 * Report cards (ADR 0032), worked out when shown, not stored: a learner's
 * record for the period's academic year gives the grade and major; the
 * curriculum gives the subjects and coefficients; the period's scores fill
 * them in. Numeric cards get the weighted average of the scored subjects.
 * Absences, lateness and the homework average come from the period's days
 * in the record's tenant.
 */
class ReportCards
{
    /**
     * @return array{learner: string, national_code: ?string, tenant: string, year: string, period: string, grade: string, major: ?string, classroom: ?string, scale: ScoreScale, rows: list<array{subject: string, coefficient: string, result: ?string, note: ?string}>, average: ?string, absences: int, excused: int, lates: int, homework_average: ?string, is_published: bool}
     */
    public function build(Enrollment $enrollment, ScorePeriod $period): array
    {
        $enrollment->loadMissing(['user', 'grade.stage', 'major', 'classroom', 'term']);
        $scale = $enrollment->grade->stage->scale;
        $scores = Score::withoutTenantScope()->where('tenant_id', $enrollment->tenant_id)->where('period_id', $period->getKey())
            ->where('user_id', $enrollment->user_id)->get()->keyBy('subject_id');

        $curriculum = Curriculum::query()->with('subject')->where('grade_id', $enrollment->grade_id)
            ->where(fn (Builder $query) => $query->whereNull('major_id')->when($enrollment->major_id, fn (Builder $query, int $major) => $query->orWhere('major_id', $major)))
            ->orderBy('sort')->get();

        $rows = $curriculum->map(function (Curriculum $item) use ($scores, $scale): array {
            /** @var Score|null $score */
            $score = $scores->get($item->subject_id);

            return [
                'subject' => $item->subject->title,
                'coefficient' => self::number($item->coefficient),
                'result' => $scale === ScoreScale::Numeric
                    ? ($score?->score !== null ? self::number($score->score) : null)
                    : $score?->level?->getLabel(),
                'note' => $score?->note,
            ];
        })->values()->all();

        [$absences, $excused, $lates] = $this->attendance($enrollment, $period);

        return [
            'learner' => $enrollment->user->name,
            'national_code' => $enrollment->user->national_code,
            'tenant' => Tenant::query()->find($enrollment->tenant_id)?->name ?? '',
            'year' => $enrollment->term->title,
            'period' => $period->title,
            'grade' => $enrollment->grade->title.' ('.$enrollment->grade->stage->title.')',
            'major' => $enrollment->major?->title,
            'classroom' => $enrollment->classroom?->title,
            'scale' => $scale,
            'rows' => $rows,
            'average' => $scale === ScoreScale::Numeric ? $this->average($curriculum, $scores) : null,
            'absences' => $absences,
            'excused' => $excused,
            'lates' => $lates,
            'homework_average' => $this->homeworkAverage($enrollment, $period),
            'is_published' => $period->is_published,
        ];
    }

    /**
     * The learner's records in the tenant whose academic year has a
     * published period: what they (and their guardians) may see.
     *
     * @return Collection<int, array{enrollment: Enrollment, period: ScorePeriod}>
     */
    public function publishedFor(int $userId, int $tenantId): Collection
    {
        $enrollments = Enrollment::withoutTenantScope()->where('tenant_id', $tenantId)->where('user_id', $userId)->get()->keyBy('term_id');

        return ScorePeriod::definedPeriods()->where('is_published', true)->whereIn('term_id', $enrollments->keys())->get()
            ->map(fn (ScorePeriod $period): array => ['enrollment' => $enrollments[$period->term_id], 'period' => $period])
            ->sortByDesc(fn (array $card): string => $card['period']->starts_on->toDateString())
            ->values();
    }

    /**
     * @param  array<string, mixed>  $card  from build()
     */
    public function pdf(array $card): string
    {
        return PersianPdf::render("کارنامه {$card['learner']} - {$card['period']}", $this->html($card));
    }

    /**
     * @param  array<string, mixed>  $card  from build()
     */
    public function html(array $card): string
    {
        $cell = 'border:1px solid #999;padding:6px;';
        $head = collect([
            'نام' => $card['learner'], 'کد ملی' => $card['national_code'], config('sarasystem.tenant_label') => $card['tenant'],
            'سال تحصیلی' => $card['year'], 'دوره' => $card['period'], 'پایه' => $card['grade'], 'رشته' => $card['major'], 'کلاس' => $card['classroom'],
        ])->filter()->map(fn (string $value, string $label): string => '<td style="'.$cell.'"><b>'.e($label).':</b> '.e($value).'</td>')->chunk(4)
            ->map(fn (Collection $row): string => '<tr>'.$row->join('').'</tr>')->join('');
        $numeric = $card['scale'] === ScoreScale::Numeric;
        $rows = collect($card['rows'])->map(fn (array $row): string => '<tr><td style="'.$cell.'">'.e($row['subject']).'</td>'
            .($numeric ? '<td style="'.$cell.'text-align:center">'.e($row['coefficient']).'</td>' : '')
            .'<td style="'.$cell.'text-align:center">'.e($row['result'] ?? '-').'</td><td style="'.$cell.'">'.e($row['note'] ?? '').'</td></tr>')->join('');

        $summary = collect([
            // Shabnam has no «—»; the PDF uses «-».
            $numeric ? 'معدل: '.($card['average'] ?? '-') : null,
            'غیبت: '.$card['absences'].($card['excused'] ? ' (+'.$card['excused'].' موجه)' : ''),
            'تأخیر: '.$card['lates'],
            $card['homework_average'] !== null ? 'میانگین تکالیف: '.$card['homework_average'].' از ۲۰' : null,
        ])->filter()->map(fn (string $item): string => e($item))->join(' &nbsp;|&nbsp; ');

        return '<h2 style="text-align:center">کارنامه‌ی '.e($card['period']).'</h2>'
            .'<table style="width:100%;border-collapse:collapse;margin-bottom:12px">'.$head.'</table>'
            .'<table style="width:100%;border-collapse:collapse"><tr style="background:#eee"><th style="'.$cell.'">درس</th>'
            .($numeric ? '<th style="'.$cell.'">ضریب</th>' : '').'<th style="'.$cell.'">'.($numeric ? 'نمره' : 'ارزشیابی').'</th><th style="'.$cell.'">توضیح</th></tr>'
            .$rows.'</table><p style="margin-top:12px">'.$summary.'</p>';
    }

    /**
     * @param  array<string, mixed>  $card  from build()
     */
    public function fileName(array $card): string
    {
        return 'report-card-'.($card['national_code'] ?? 'learner').'.pdf';
    }

    /**
     * The weighted average of the scored subjects, or null.
     *
     * @param  Collection<int, Curriculum>  $curriculum
     * @param  Collection<int, Score>  $scores
     */
    private function average(Collection $curriculum, Collection $scores): ?string
    {
        $scored = $curriculum->filter(fn (Curriculum $item): bool => $scores->get($item->subject_id)?->score !== null);
        $weights = $scored->sum(fn (Curriculum $item): float => (float) $item->coefficient);

        if ($weights <= 0) {
            return null;
        }

        $sum = $scored->sum(fn (Curriculum $item): float => (float) $item->coefficient * (float) $scores->get($item->subject_id)->score);

        return self::number(round($sum / $weights, 2));
    }

    /**
     * @return array{int, int, int} absences, excused, lates in the period
     */
    private function attendance(Enrollment $enrollment, ScorePeriod $period): array
    {
        $counts = Attendance::withoutTenantScope()->where('class_attendances.tenant_id', $enrollment->tenant_id)->where('user_id', $enrollment->user_id)
            ->whereHas('session', fn (Builder $session) => $session->withoutGlobalScopes()
                ->whereDate('starts_at', '>=', $period->starts_on)->whereDate('starts_at', '<=', $period->ends_on))
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            (int) ($counts[AttendanceStatus::Absent->value] ?? 0),
            (int) ($counts[AttendanceStatus::Excused->value] ?? 0),
            (int) ($counts[AttendanceStatus::Late->value] ?? 0),
        ];
    }

    /**
     * Graded homework of the period's sessions, each out of 20, averaged.
     */
    private function homeworkAverage(Enrollment $enrollment, ScorePeriod $period): ?string
    {
        $scores = HomeworkSubmission::withoutTenantScope()->where('class_homework_submissions.tenant_id', $enrollment->tenant_id)
            ->where('user_id', $enrollment->user_id)->whereNotNull('score')
            ->whereHas('session', fn (Builder $session) => $session->withoutGlobalScopes()
                ->whereDate('starts_at', '>=', $period->starts_on)->whereDate('starts_at', '<=', $period->ends_on))
            ->with(['session' => fn ($session) => $session->withoutGlobalScopes()])
            ->get()
            ->map(fn (HomeworkSubmission $submission): float => (float) $submission->score / max(1, $submission->session->homework_max_score ?? (int) config('class.homework.default_max_score')) * 20);

        return $scores->isEmpty() ? null : self::number(round($scores->avg(), 2));
    }

    /**
     * 18.50 → «18.5», 1.00 → «1».
     */
    private static function number(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }
}
