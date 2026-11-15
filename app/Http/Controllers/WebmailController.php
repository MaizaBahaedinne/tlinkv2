<?php

namespace App\Http\Controllers;

use App\Models\WebmailContact;
use App\Models\WebmailLabel;
use App\Models\WebmailMailbox;
use App\Models\WebmailMessageLabel;
use App\Support\AuditLogger;
use App\Support\Webmail\FolderRoles;
use App\Support\Webmail\GatewayFactory;
use App\Support\Webmail\HtmlSanitizer;
use App\Support\Webmail\MailGateway;
use App\Support\Webmail\WebmailAccess;
use App\Support\Webmail\WebmailException;
use App\Support\Webmail\WebmailSignature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Application T.Link Webmail : lecture et envoi depuis la boîte partagée d'une entité. Les droits viennent du poste
 * (module « Lecture et envoi ») et de l'entité ; le mot de passe de la boîte n'est jamais montré.
 */
class WebmailController extends Controller
{
    private const PER_PAGE = 25;

    public function __construct(private readonly GatewayFactory $gateways)
    {
    }

    public function index(Request $request): View
    {
        $mailboxes = WebmailAccess::readable($request->user());
        $mailbox = $mailboxes->firstWhere('id', $request->integer('boite')) ?? $mailboxes->first();

        $view = ['mailboxes' => $mailboxes, 'mailbox' => $mailbox, 'folders' => [], 'messages' => null, 'error' => null, 'folder' => 'INBOX', 'search' => '', 'labels' => collect(), 'activeLabel' => null, 'labelMap' => [], 'canWrite' => false, 'canUpdate' => false, 'roleLabels' => FolderRoles::LABELS];

        if ($mailbox === null) {
            return view('webmail.index', $view);
        }

        $folder = $this->folderParam($request, 'dossier');
        $search = trim(mb_substr((string) $request->query('q', ''), 0, 80));
        $labels = $mailbox->labels()->orderBy('name')->get();
        $activeLabel = $labels->firstWhere('id', $request->integer('etiquette'));
        $page = max(1, $request->integer('page', 1));

        $view = array_merge($view, [
            'mailbox' => $mailbox, 'folder' => $folder, 'search' => $search, 'labels' => $labels, 'activeLabel' => $activeLabel,
            'canWrite' => WebmailAccess::allows($request->user(), $mailbox, 'create'),
            'canUpdate' => WebmailAccess::allows($request->user(), $mailbox, 'update'),
            'canDelete' => WebmailAccess::allows($request->user(), $mailbox, 'delete'),
        ]);

        try {
            $gateway = $this->gateways->make($mailbox);
            $folders = $gateway->folders();
            $uids = $activeLabel ? WebmailMessageLabel::query()->where('label_id', $activeLabel->id)->where('folder', $folder)->pluck('uid')->map(fn ($uid): int => (int) $uid)->all() : null;
            $result = $gateway->messages($folder, $page, self::PER_PAGE, $search ?: null, $uids);

            $inbox = collect($folders)->firstWhere('role', 'inbox');
            $mailbox->forceFill(['unseen' => (int) ($inbox['unseen'] ?? 0), 'checked_at' => now(), 'last_error' => null])->save();

            $uidList = array_column($result['items'], 'uid');
            $labelMap = WebmailMessageLabel::query()->where('folder', $folder)->whereIn('uid', $uidList)->whereIn('label_id', $labels->pluck('id'))->get()->groupBy('uid')
                ->map(fn ($rows) => $labels->whereIn('id', $rows->pluck('label_id'))->values())->all();

            $view = array_merge($view, [
                'folders' => $folders,
                'labelMap' => $labelMap,
                'messages' => (new LengthAwarePaginator($result['items'], $result['total'], self::PER_PAGE, $page, ['path' => $request->url()]))->withQueryString(),
            ]);
        } catch (WebmailException $exception) {
            $view['error'] = $exception->getMessage();
        }

        return view('webmail.index', $view);
    }

    public function show(Request $request, WebmailMailbox $mailbox, int $uid): View
    {
        $this->authorizeMailbox($request, $mailbox, 'read');
        $folder = $this->folderParam($request, 'dossier');

        try {
            $gateway = $this->gateways->make($mailbox);
            $message = $gateway->message($folder, $uid);
        } catch (WebmailException $exception) {
            return $this->failure($mailbox, $folder, $exception);
        }

        abort_if($message === null, 404, 'Ce message n’existe plus.');

        $canUpdate = WebmailAccess::allows($request->user(), $mailbox, 'update');

        if (! $message['seen'] && $canUpdate) {
            try {
                $gateway->mark($folder, $uid, 'seen', true);
            } catch (WebmailException) {
            }
        }

        $labels = $mailbox->labels()->orderBy('name')->get();
        $assigned = WebmailMessageLabel::query()->where('folder', $folder)->where('uid', $uid)->whereIn('label_id', $labels->pluck('id'))->pluck('label_id')->all();

        $html = filled($message['html'] ?? null) ? HtmlSanitizer::clean((string) $message['html']) : null;

        return view('webmail.show', [
            'mailbox' => $mailbox,
            'folder' => $folder,
            'message' => $message,
            'html' => $html,
            'labels' => $labels,
            'assigned' => $assigned,
            'folders' => collect($gateway->folders()),
            'canWrite' => WebmailAccess::allows($request->user(), $mailbox, 'create'),
            'canUpdate' => $canUpdate,
            'canDelete' => WebmailAccess::allows($request->user(), $mailbox, 'delete'),
        ]);
    }

    public function attachment(Request $request, WebmailMailbox $mailbox, int $uid, int $index): Response
    {
        $this->authorizeMailbox($request, $mailbox, 'read');

        try {
            $file = $this->gateways->make($mailbox)->attachment($this->folderParam($request, 'dossier'), $uid, $index);
        } catch (WebmailException) {
            abort(502, 'La boîte ne répond pas.');
        }

        abort_if($file === null, 404);

        $name = Str::of(basename(str_replace('\\', '/', $file['name'])))->replaceMatches('/[\x00-\x1F"]/', '')->limit(150, '')->toString() ?: 'piece-jointe';

        return response($file['content'], 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.Str::ascii($name).'"; filename*=UTF-8\'\''.rawurlencode($name),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function compose(Request $request, WebmailMailbox $mailbox): View|RedirectResponse
    {
        $this->authorizeMailbox($request, $mailbox, 'create');

        $mode = in_array($request->query('mode'), ['reply', 'replyall', 'forward', 'draft'], true) ? (string) $request->query('mode') : 'new';
        $folder = $this->folderParam($request, 'dossier');
        $draft = ['to' => '', 'cc' => '', 'subject' => '', 'body' => '', 'in_reply_to' => '', 'references' => '', 'draft_uid' => null, 'reply_uid' => null, 'reply_folder' => $folder];

        if ($mode !== 'new' && $request->integer('uid') > 0) {
            try {
                $source = $this->gateways->make($mailbox)->message($folder, $request->integer('uid'));
            } catch (WebmailException $exception) {
                return $this->failure($mailbox, $folder, $exception);
            }

            abort_if($source === null, 404, 'Ce message n’existe plus.');
            $draft = $this->prefill($mode, $source, $mailbox, $draft, $request->integer('uid'));
        }

        return view('webmail.compose', [
            'mailbox' => $mailbox,
            'draft' => $draft,
            'mode' => $mode,
            'contacts' => $mailbox->contacts()->orderBy('name')->orderBy('email')->limit(300)->get(),
            'folder' => $folder,
        ]);
    }

    public function send(Request $request, WebmailMailbox $mailbox): RedirectResponse
    {
        $this->authorizeMailbox($request, $mailbox, 'create');

        $asDraft = $request->input('action') === 'draft';

        $data = $request->validate([
            'to' => [$asDraft ? 'nullable' : 'required', 'string', 'max:2000'],
            'cc' => ['nullable', 'string', 'max:2000'],
            'bcc' => ['nullable', 'string', 'max:2000'],
            'subject' => ['nullable', 'string', 'max:250'],
            'body' => ['nullable', 'string', 'max:100000'],
            'in_reply_to' => ['nullable', 'string', 'max:500'],
            'references' => ['nullable', 'string', 'max:2000'],
            'draft_uid' => ['nullable', 'integer', 'min:1'],
            'reply_uid' => ['nullable', 'integer', 'min:1'],
            'reply_folder' => ['nullable', 'string', 'max:190'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:10240'],
        ], [
            'to.required' => 'Indiquez au moins un destinataire.',
            'attachments.*.max' => 'Chaque pièce jointe est limitée à 10 Mo.',
            'attachments.max' => 'Dix pièces jointes au plus.',
        ]);

        $files = collect($request->file('attachments', []));

        if ($files->sum(fn ($file): int => (int) $file->getSize()) > 25 * 1024 * 1024) {
            throw ValidationException::withMessages(['attachments' => 'Les pièces jointes dépassent 25 Mo au total.']);
        }

        $message = [
            'to' => $this->recipients($data['to'] ?? null, 'to'),
            'cc' => $this->recipients($data['cc'] ?? null, 'cc'),
            'bcc' => $this->recipients($data['bcc'] ?? null, 'bcc'),
            'subject' => trim(preg_replace('/[\r\n]+/', ' ', (string) ($data['subject'] ?? '')) ?? ''),
            'body' => (string) ($data['body'] ?? ''),
            'in_reply_to' => $this->headerValue($data['in_reply_to'] ?? ''),
            'references' => $this->headerValue($data['references'] ?? ''),
            'attachments' => $files->map(fn ($file): array => ['path' => $file->getRealPath(), 'name' => $file->getClientOriginalName(), 'mime' => (string) $file->getMimeType()])->all(),
        ];

        $draftUid = isset($data['draft_uid']) ? (int) $data['draft_uid'] : null;

        try {
            $gateway = $this->gateways->make($mailbox);

            if ($asDraft) {
                $gateway->saveDraft($message, $draftUid);

                return redirect()->route('webmail.index', ['boite' => $mailbox->id, 'dossier' => $this->draftsPath($gateway)])->with('status', 'Brouillon enregistré.');
            }

            $gateway->send($message);

            if ($draftUid !== null) {
                $drafts = $this->draftsPath($gateway);
                $drafts !== null && $gateway->trash($drafts, $draftUid);
            }

            if (! empty($data['reply_uid'])) {
                try {
                    $gateway->mark($this->cleanFolder($data['reply_folder'] ?? null), (int) $data['reply_uid'], 'answered', true);
                } catch (WebmailException) {
                }
            }
        } catch (WebmailException $exception) {
            return back()->withInput($request->except('attachments'))->withErrors(['send' => $exception->getMessage()]);
        }

        foreach (array_merge($message['to'], $message['cc'], $message['bcc']) as $recipient) {
            [$name, $email] = $this->split($recipient);
            WebmailContact::query()->firstOrCreate(['mailbox_id' => $mailbox->id, 'email' => $email], ['name' => $name ?: null]);
        }

        $this->audit($request, 'webmail.message.sent', $mailbox, ['address' => $mailbox->address, 'recipients' => count($message['to']) + count($message['cc']) + count($message['bcc']), 'attachments' => count($message['attachments'])]);

        return redirect()->route('webmail.index', ['boite' => $mailbox->id])->with('status', 'Message envoyé depuis '.$mailbox->address.'.');
    }

    public function mark(Request $request, WebmailMailbox $mailbox, int $uid): RedirectResponse
    {
        $this->authorizeMailbox($request, $mailbox, 'update');

        $data = $request->validate(['flag' => ['required', Rule::in(['seen', 'flagged'])], 'on' => ['required', 'boolean']]);
        $folder = $this->folderParam($request, 'dossier');

        try {
            $this->gateways->make($mailbox)->mark($folder, $uid, $data['flag'], $request->boolean('on'));
        } catch (WebmailException $exception) {
            return $this->failure($mailbox, $folder, $exception);
        }

        return $this->backToList($request, $mailbox, $folder);
    }

    public function move(Request $request, WebmailMailbox $mailbox, int $uid): RedirectResponse
    {
        $this->authorizeMailbox($request, $mailbox, 'update');

        $data = $request->validate(['target' => ['required', 'string', 'max:190']]);
        $folder = $this->folderParam($request, 'dossier');

        try {
            $gateway = $this->gateways->make($mailbox);
            abort_unless(collect($gateway->folders())->contains('path', $data['target']), 422, 'Dossier inconnu.');
            $gateway->move($folder, $uid, $data['target']);
        } catch (WebmailException $exception) {
            return $this->failure($mailbox, $folder, $exception);
        }

        $this->forgetLabels($mailbox, $folder, $uid);

        return $this->backToList($request, $mailbox, $folder, 'Message déplacé.');
    }

    public function trash(Request $request, WebmailMailbox $mailbox, int $uid): RedirectResponse
    {
        $this->authorizeMailbox($request, $mailbox, 'delete');
        $folder = $this->folderParam($request, 'dossier');

        try {
            $this->gateways->make($mailbox)->trash($folder, $uid);
        } catch (WebmailException $exception) {
            return $this->failure($mailbox, $folder, $exception);
        }

        $this->forgetLabels($mailbox, $folder, $uid);
        $this->audit($request, 'webmail.message.deleted', $mailbox, ['address' => $mailbox->address, 'folder' => $folder]);

        return $this->backToList($request, $mailbox, $folder, 'Message supprimé.');
    }

    /** Remplace les étiquettes d'un message. */
    public function tag(Request $request, WebmailMailbox $mailbox, int $uid): RedirectResponse
    {
        $this->authorizeMailbox($request, $mailbox, 'update');

        $folder = $this->folderParam($request, 'dossier');
        $ids = $mailbox->labels()->whereIn('id', array_map('intval', (array) $request->input('labels', [])))->pluck('id')->all();

        $this->forgetLabels($mailbox, $folder, $uid);

        foreach ($ids as $id) {
            WebmailMessageLabel::query()->create(['label_id' => $id, 'folder' => $folder, 'uid' => $uid]);
        }

        return redirect()->route('webmail.show', ['mailbox' => $mailbox, 'uid' => $uid, 'dossier' => $folder])->with('status', 'Étiquettes enregistrées.');
    }

    public function settings(Request $request, WebmailMailbox $mailbox): View
    {
        $this->authorizeMailbox($request, $mailbox, 'read');

        return view('webmail.settings', [
            'mailbox' => $mailbox,
            'labels' => $mailbox->labels()->orderBy('name')->get(),
            'contacts' => $mailbox->contacts()->orderBy('name')->orderBy('email')->paginate(30, ['*'], 'page')->withQueryString(),
            'colors' => WebmailLabel::COLORS,
            'canUpdate' => WebmailAccess::allows($request->user(), $mailbox, 'update'),
            'canDelete' => WebmailAccess::allows($request->user(), $mailbox, 'delete'),
        ]);
    }

    public function signature(Request $request, WebmailMailbox $mailbox): RedirectResponse
    {
        $this->authorizeMailbox($request, $mailbox, 'update');

        $data = $request->validate(['signature' => ['nullable', 'string', 'max:'.WebmailSignature::MAX_LENGTH]], ['signature.max' => 'La signature est trop longue.']);
        $mailbox->update(['signature' => WebmailSignature::normalize($data['signature'] ?? null)]);

        return back()->with('status', 'Signature enregistrée : elle est ajoutée à chaque message envoyé.');
    }

    public function storeLabel(Request $request, WebmailMailbox $mailbox): RedirectResponse
    {
        $this->authorizeMailbox($request, $mailbox, 'update');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:40', Rule::unique('webmail_labels', 'name')->where('mailbox_id', $mailbox->id)],
            'color' => ['required', Rule::in(array_keys(WebmailLabel::COLORS))],
        ], ['name.unique' => 'Cette étiquette existe déjà.']);

        $mailbox->labels()->create($data);

        return back()->with('status', 'Étiquette ajoutée.');
    }

    public function destroyLabel(Request $request, WebmailMailbox $mailbox, WebmailLabel $label): RedirectResponse
    {
        $this->authorizeMailbox($request, $mailbox, 'delete');
        abort_unless($label->mailbox_id === $mailbox->id, 404);

        $label->delete();

        return back()->with('status', 'Étiquette supprimée.');
    }

    public function storeContact(Request $request, WebmailMailbox $mailbox): RedirectResponse
    {
        $this->authorizeMailbox($request, $mailbox, 'update');

        $data = $request->validate(['name' => ['nullable', 'string', 'max:120'], 'email' => ['required', 'email', 'max:190']]);

        WebmailContact::query()->updateOrCreate(['mailbox_id' => $mailbox->id, 'email' => strtolower($data['email'])], ['name' => $data['name'] ?? null]);

        return back()->with('status', 'Contact enregistré.');
    }

    public function destroyContact(Request $request, WebmailMailbox $mailbox, WebmailContact $contact): RedirectResponse
    {
        $this->authorizeMailbox($request, $mailbox, 'delete');
        abort_unless($contact->mailbox_id === $mailbox->id, 404);

        $contact->delete();

        return back()->with('status', 'Contact supprimé.');
    }

    private function audit(Request $request, string $action, object $target, array $metadata = []): void
    {
        AuditLogger::record(actorId: $request->user()->id, action: $action, targetType: $target::class, targetId: $target->getKey(), metadata: $metadata, ip: $request->ip());
    }

    private function authorizeMailbox(Request $request, WebmailMailbox $mailbox, string $action): void
    {
        abort_unless(WebmailAccess::allows($request->user(), $mailbox, $action), 403, 'Les droits de votre compte ne couvrent pas cette action sur cette boîte.');
    }

    private function folderParam(Request $request, string $key): string
    {
        return $this->cleanFolder($request->input($key, $request->query($key)));
    }

    private function cleanFolder(mixed $value): string
    {
        $folder = mb_substr((string) preg_replace('/[\x00-\x1F]/', '', (string) $value), 0, 190);

        return $folder !== '' ? $folder : 'INBOX';
    }

    private function draftsPath(MailGateway $gateway): ?string
    {
        return collect($gateway->folders())->firstWhere('role', 'drafts')['path'] ?? null;
    }

    private function forgetLabels(WebmailMailbox $mailbox, string $folder, int $uid): void
    {
        WebmailMessageLabel::query()->where('folder', $folder)->where('uid', $uid)->whereIn('label_id', $mailbox->labels()->pluck('id'))->delete();
    }

    private function backToList(Request $request, WebmailMailbox $mailbox, string $folder, ?string $status = null): RedirectResponse
    {
        $redirect = redirect()->route('webmail.index', ['boite' => $mailbox->id, 'dossier' => $folder]);

        return $status ? $redirect->with('status', $status) : $redirect;
    }

    private function failure(WebmailMailbox $mailbox, string $folder, WebmailException $exception): RedirectResponse
    {
        return redirect()->route('webmail.index', ['boite' => $mailbox->id, 'dossier' => $folder])->withErrors(['webmail' => $exception->getMessage()]);
    }

    /** « a@b.tn, Nom <c@d.tn> » devient une liste d'adresses valides. @return array<int, string> */
    private function recipients(?string $value, string $field): array
    {
        $list = [];

        foreach (preg_split('/[;,]+/', (string) $value) ?: [] as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            [$name, $email] = $this->split($part);

            if (! filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $part)) {
                throw ValidationException::withMessages([$field => 'Adresse invalide : '.Str::limit($part, 60)]);
            }

            $list[] = $name !== '' ? '"'.str_replace(['"', '\\'], '', $name).'" <'.$email.'>' : $email;
        }

        return $list;
    }

    /** @return array{0: string, 1: string} nom, adresse */
    private function split(string $part): array
    {
        if (preg_match('/^(.*)<([^>]+)>\s*$/', $part, $matches)) {
            return [trim($matches[1], " \t\"'"), strtolower(trim($matches[2]))];
        }

        return ['', strtolower(trim($part))];
    }

    private function headerValue(string $value): string
    {
        return trim((string) preg_replace('/[\r\n]+/', ' ', $value));
    }

    /**
     * @param array<string, mixed> $source
     * @param array<string, mixed> $draft
     * @return array<string, mixed>
     */
    private function prefill(string $mode, array $source, WebmailMailbox $mailbox, array $draft, int $uid): array
    {
        $format = fn (array $address): string => $address['name'] !== '' ? $address['name'].' <'.$address['email'].'>' : $address['email'];
        $subject = (string) $source['subject'];
        $text = $source['text'] ?? null;

        if (blank($text) && filled($source['html'] ?? null)) {
            $text = html_entity_decode(strip_tags((string) preg_replace('/<br\s*\/?>|<\/p>|<\/div>/i', "\n", (string) $source['html'])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $text = Str::limit(trim((string) $text), 8000, '…');

        if ($mode === 'draft') {
            return array_merge($draft, [
                'to' => collect($source['to'])->map($format)->implode(', '),
                'cc' => collect($source['cc'])->map($format)->implode(', '),
                'subject' => $subject === '(sans objet)' ? '' : $subject,
                'body' => $text,
                'draft_uid' => $uid,
            ]);
        }

        $quoted = "\n\nLe ".($source['date'] ? \Carbon\Carbon::parse($source['date'])->timezone(config('app.timezone'))->format('d/m/Y à H:i') : '').', '.$format($source['from'])." a écrit :\n".preg_replace('/^/m', '> ', $text);

        if ($mode === 'forward') {
            return array_merge($draft, [
                'subject' => preg_match('/^(tr|fwd?)\s*:/i', $subject) ? $subject : 'Tr: '.$subject,
                'body' => "\n\n---------- Message transféré ----------\nDe : ".$format($source['from'])."\nObjet : ".$subject."\n\n".$text,
            ]);
        }

        $own = strtolower($mailbox->address);
        $others = collect(array_merge($source['to'], $source['cc']))->filter(fn (array $address): bool => $address['email'] !== $own && $address['email'] !== ($source['from']['email'] ?? ''))->map($format);

        return array_merge($draft, [
            'to' => $format($source['from']),
            'cc' => $mode === 'replyall' ? $others->implode(', ') : '',
            'subject' => preg_match('/^(re|réf?)\s*:/iu', $subject) ? $subject : 'Re: '.$subject,
            'body' => $quoted,
            'in_reply_to' => (string) ($source['message_id'] ?? ''),
            'references' => trim((string) ($source['references'] ?? '')),
            'reply_uid' => $uid,
        ]);
    }
}
