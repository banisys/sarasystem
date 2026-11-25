<?php

namespace Modules\Exam\Http\Middleware;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Tenant;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Modules\Exam\Models\Exam;
use Modules\Exam\Support\ExamContexts;
use Symfony\Component\HttpFoundation\Response;

class ResolveSharedExamTenant
{
    public function __construct(private ExamContexts $contexts) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $next($request);
        }

        $tenant = $this->resolve($request, $user);
        if ($tenant === null || $tenant->isCurrent()) {
            return $next($request);
        }

        $request->attributes->set(HandleInertiaRequests::PANEL_TENANT, Tenant::current());
        $user->unsetRelation('roles')->unsetRelation('permissions');

        try {
            return $tenant->execute(fn (): Response => $next($request));
        } finally {
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }

    private function resolve(Request $request, User $user): ?Tenant
    {
        $type = $request->route('type');
        $context = $request->route('context');
        if (is_string($type) && is_numeric($context)) {
            return $this->contexts->sharedTenantForVisit($type, (int) $context, $user);
        }

        $id = $request->route('exam');
        $types = $this->contexts->sharedTypes();
        if (! is_numeric($id) || $types === []) {
            return null;
        }

        $exam = Exam::withoutTenantScope()->whereIn('context_type', $types)->where('is_published', true)
            ->find($id, ['id', 'tenant_id', 'context_type', 'context_id']);
        if ($exam === null) {
            return null;
        }

        $tenant = $this->contexts->sharedTenantForVisit($exam->context_type, $exam->context_id, $user);

        return $tenant?->getKey() === $exam->tenant_id ? $tenant : null;
    }
}
