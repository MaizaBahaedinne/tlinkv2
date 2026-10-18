<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminContext;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

abstract class AdminController extends Controller
{
    protected function context(Request $request): AdminContext
    {
        return AdminContext::of($request);
    }

    /**
     * Restreint une requete aux entites que l'utilisateur peut gerer pour cette permission.
     */
    protected function scoped(Builder $query, Request $request, string $permission, string $column = 'entity_id'): Builder
    {
        $scope = $this->context($request)->scope($permission);

        abort_if($scope === null, 403, 'Acces refuse.');

        return $scope === [] ? $query : $query->whereIn($column, $scope);
    }

    protected function authorizeEntity(Request $request, string $permission, ?int $entityId): void
    {
        abort_unless(
            $this->context($request)->canManageEntity($permission, $entityId),
            403,
            'Cet element est hors de votre perimetre.'
        );
    }

    /**
     * Entites selectionnables dans les formulaires selon le perimetre.
     *
     * @return array<int, string> id => nom
     */
    protected function entityOptions(Request $request, string $permission): array
    {
        return $this->scoped(\App\Models\Entity::query(), $request, $permission, 'id')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
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

    /**
     * Genere un slug unique pour un modele donne, avec contraintes optionnelles.
     *
     * @param class-string<\Illuminate\Database\Eloquent\Model> $modelClass
     * @param array<string, mixed> $constraints
     */
    protected function uniqueSlug(string $modelClass, string $value, array $constraints = [], ?int $ignoreId = null): string
    {
        $base = \Illuminate\Support\Str::slug($value);
        $base = $base !== '' ? $base : 'item';
        $slug = $base;
        $counter = 1;

        while (
            $modelClass::query()
                ->where($constraints)
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $counter++;
            $slug = $base.'-'.$counter;
        }

        return $slug;
    }
}
