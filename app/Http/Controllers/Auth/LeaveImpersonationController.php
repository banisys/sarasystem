<?php

namespace App\Http\Controllers\Auth;

use App\Auth\Impersonation;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends an impersonation: the super admin is logged back in as themselves.
 */
class LeaveImpersonationController extends Controller
{
    /**
     * A full page load either way: the way back is usually the admin panel,
     * which is not an Inertia page.
     */
    public function __invoke(): Response
    {
        abort_unless(Impersonation::isActive(), 404);

        return Inertia::location(Impersonation::stop());
    }
}
