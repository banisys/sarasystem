<?php

namespace Modules\Class\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Class\Models\ClassSession;
use Modules\Class\Services\Online\OnlineClassException;
use Modules\Class\Services\Online\OnlineEntry;

/**
 * «ورود به کلاس آنلاین» from «کلاس‌های من» and the admin panel (ADR 0073):
 * sends the person into the session's room on its platform, or says why
 * not. The session's tenant becomes current for the request, so it works
 * whichever tenant the person picked in the user panel.
 */
class OnlineSessionController extends Controller
{
    public function __invoke(Request $request, int $session, OnlineEntry $entry): RedirectResponse|Response
    {
        /** @var User $user */
        $user = $request->user();
        $session = ClassSession::withoutTenantScope()->findOrFail($session);
        $tenant = Tenant::query()->findOrFail($session->tenant_id);

        abort_unless($user->isSuperAdmin() || $user->belongsToTenant($tenant), 404);

        if (! $tenant->isCurrent()) {
            $tenant->makeCurrent();
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }

        $session->load(['classroom.onlineRooms.server', 'teachers']);

        try {
            return redirect()->away($entry->enter($user, $session));
        } catch (OnlineClassException $exception) {
            return Inertia::render('Class::OnlineRefused', [
                'message' => $exception->getMessage(),
                'class' => $session->classroom?->title,
                'time' => $session->starts_at->format('H:i').'–'.$session->ends_at->format('H:i'),
            ]);
        }
    }
}
