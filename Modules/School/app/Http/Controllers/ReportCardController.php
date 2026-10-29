<?php

namespace Modules\School\Http\Controllers;

use App\Guardians\Guardians;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\School\Models\Enrollment;
use Modules\School\Models\ScorePeriod;
use Modules\School\Services\ReportCards;

/**
 * «کارنامه» in the user panel (ADR 0032): a learner's published report
 * cards in the current tenant, or (`?for=`) a ward's for their guardian
 * (ADR 0027), with a PDF of each.
 */
class ReportCardController extends Controller
{
    public function index(Request $request, ReportCards $cards): Response
    {
        $person = $this->person($request);

        return Inertia::render('School::ReportCards', [
            'ward' => $person->is($request->user()) ? null : ['id' => $person->getKey(), 'name' => $person->name],
            'cards' => $cards->publishedFor($person->getKey(), Tenant::current()->getKey())->map(fn (array $item): array => [
                'enrollment_id' => $item['enrollment']->getKey(),
                'period_id' => $item['period']->getKey(),
                ...array_replace($cards->build($item['enrollment'], $item['period']), ['scale' => $item['enrollment']->grade->stage->scale->value]),
            ])->values(),
        ]);
    }

    public function pdf(Request $request, Enrollment $enrollment, int $periodId, ReportCards $cards): HttpResponse
    {
        $person = $this->person($request);
        $period = ScorePeriod::definedPeriods()->where('is_published', true)->where('term_id', $enrollment->term_id)->find($periodId);
        abort_if($period === null || $enrollment->user_id !== $person->getKey(), 404);

        $card = $cards->build($enrollment, $period);

        return response($cards->pdf($card), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$cards->fileName($card).'"',
        ]);
    }

    /**
     * Whose report cards: the user's, or a ward's in the current tenant.
     */
    private function person(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        if (! $request->filled('for')) {
            return $user;
        }

        return app(Guardians::class)->wardsIn($user, Tenant::current())->firstWhere('id', (int) $request->query('for')) ?? abort(404);
    }
}
