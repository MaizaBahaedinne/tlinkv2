<?php

namespace App\Http\Controllers\Admin;

use App\Models\CardScan;
use App\Models\MemberCard;
use App\Support\Cards\CardCode;
use App\Support\Cards\CardScanner;
use App\Support\Cards\Checkpoints;
use App\Support\MembershipResponsibility;
use App\Support\OrgScopes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/** Pointage : le scanner (caméra ou saisie du numéro) et l'historique des entrées / sorties du périmètre. */
class CheckpointController extends AdminController
{
    public function index(Request $request): View
    {
        $groups = Checkpoints::options($request->user());

        return view('admin.checkpoint.index', [
            'groups' => $groups,
            'selected' => (string) $request->query('cible', ''),
            'hasTargets' => collect($groups)->contains(fn (array $group): bool => $group['items'] !== []),
        ]);
    }

    public function scan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'target' => ['required', 'string', 'regex:/^[a-z_]{1,40}:\d{1,12}$/'],
            'direction' => ['required', Rule::in(['auto', 'in', 'out'])],
            'code' => ['nullable', 'string', 'max:200', 'required_without:number'],
            'number' => ['nullable', 'string', 'max:20', 'required_without:code'],
        ]);

        [$type, $subjectId] = explode(':', $data['target']);
        $subjectId = (int) $subjectId;

        if (filled($data['code'] ?? null)) {
            [$card, $error] = CardCode::resolve($data['code']);
            $method = 'qr';
        } else {
            $card = MemberCard::query()->with(['user:id,name', 'membership.node:id,title', 'mandate:id,label'])
                ->where('number', strtoupper(trim($data['number'])))->first();
            $error = $card === null ? 'Numéro de carte inconnu.' : null;
            $method = 'manual';
        }

        if ($card === null) {
            return response()->json(['ok' => false, 'message' => $error], 422);
        }

        try {
            $result = CardScanner::record($card, $type, $subjectId, $data['direction'], $method, $request->user());
        } catch (RuntimeException $exception) {
            return response()->json(['ok' => false, 'message' => $exception->getMessage(), 'member' => $this->member($card)], 422);
        }

        $scan = $result['scan'];
        $last = CardScan::query()->where('subject_type', $type)->where('subject_id', $subjectId)->selectRaw('max(id) as last_id')->groupBy('member_card_id')->pluck('last_id');

        return response()->json([
            'ok' => true,
            'duplicate' => $result['duplicate'],
            'direction' => $scan->direction,
            'message' => $result['duplicate'] ? 'Déjà pointé à l’instant.' : CardScan::DIRECTIONS[$scan->direction].' enregistrée.',
            'member' => $this->member($card),
            'note' => $result['note'],
            'target' => $scan->subject_label,
            'at' => $scan->scanned_at->format('H:i:s'),
            'present' => CardScan::query()->whereIn('id', $last)->where('direction', 'in')->count(),
        ]);
    }

    public function history(Request $request): View
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::in(array_keys(Checkpoints::labels()))],
            'direction' => ['nullable', Rule::in(['in', 'out'])],
            'date' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $scans = MembershipResponsibility::of($request->user(), 'event.manage')
            ->apply(CardScan::query(), 'scope', 'node_id')
            ->with(['user:id,name', 'card:id,number', 'scanner:id,name'])
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('subject_type', $type))
            ->when($filters['direction'] ?? null, fn ($query, $direction) => $query->where('direction', $direction))
            ->when($filters['date'] ?? null, fn ($query, $date) => $query->whereDate('scanned_at', $date))
            ->when($filters['q'] ?? null, function ($query, $needle): void {
                $like = '%'.addcslashes($needle, '\\%_').'%';
                $query->where(fn ($inner) => $inner->where('subject_label', 'like', $like)
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', $like))
                    ->orWhereHas('card', fn ($card) => $card->where('number', 'like', $like)));
            })
            ->latest('scanned_at')->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.checkpoint.history', [
            'scans' => $scans,
            'filters' => $filters,
            'types' => Checkpoints::labels(),
            'directions' => CardScan::DIRECTIONS,
            'methods' => CardScan::METHODS,
        ]);
    }

    /** @return array{name: string, number: string, entity: string, mandate: string|null, valid: bool} */
    private function member(MemberCard $card): array
    {
        $card->loadMissing(['user:id,name', 'membership.node:id,title', 'mandate:id,label']);

        return [
            'name' => (string) $card->user?->name,
            'number' => $card->number,
            'entity' => (string) ($card->membership?->node?->title ?? (OrgScopes::label($card->membership?->scope) ?: 'Fondation')),
            'mandate' => $card->mandate?->label,
            'valid' => $card->isValid(),
        ];
    }
}
