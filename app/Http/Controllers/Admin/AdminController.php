<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminContext;
use App\Support\AuditLogger;
use App\Support\OrgScopes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

abstract class AdminController extends Controller
{
    protected function context(Request $request): AdminContext
    {
        return AdminContext::of($request);
    }

    /**
     * Restreint une requete aux perimetres que l'utilisateur peut gerer pour cette permission.
     */
    protected function scoped(Builder $query, Request $request, string $permission, string $column = 'scope'): Builder
    {
        $scope = $this->context($request)->scope($permission);

        abort_if($scope === null, 403, 'Acces refuse.');

        return $scope === [] ? $query : $query->whereIn($column, $scope);
    }

    protected function authorizeScope(Request $request, string $permission, ?string $scope): void
    {
        abort_unless(
            $this->context($request)->canManageScope($permission, $scope),
            403,
            'Cet element est hors de votre perimetre.'
        );
    }

    /**
     * Perimetres selectionnables dans les formulaires selon les droits.
     *
     * @return array<string, string> slug => nom
     */
    protected function scopeOptions(Request $request, string $permission): array
    {
        $scope = $this->context($request)->scope($permission);

        abort_if($scope === null, 403, 'Acces refuse.');

        return $scope === [] ? OrgScopes::all() : array_intersect_key(OrgScopes::all(), array_flip($scope));
    }

    protected function audit(Request $request, string $action, object $target, array $metadata = []): void
    {
        AuditLogger::record(
            actorId: $request->user()->id,
            action: $action,
            targetType: $target::class,
            targetId: $target->getKey(),
            metadata: $metadata,
            ip: $request->ip(),
        );
    }
}
