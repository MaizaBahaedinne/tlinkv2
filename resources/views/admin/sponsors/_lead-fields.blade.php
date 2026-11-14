{{-- Champs d'une piste. Variables : $lead (SponsorLead, éventuellement vide), $clubOptions, $canBeFoundation, $sponsorOptions (liste) ou $sponsor (fixé), $contacts (optionnel). --}}
@php
    $prefix = 'l'.($lead->id ?? 'new').'-';
@endphp
<div class="form-grid">
    @if(! empty($sponsorOptions))
        <div class="field"><label for="{{ $prefix }}sponsor">Sponsor ou partenaire <span class="req">*</span></label>
            <select id="{{ $prefix }}sponsor" name="sponsor_id" required>
                <option value="">Choisir…</option>
                @foreach($sponsorOptions as $id => $label)
                    <option value="{{ $id }}" @selected((int) old('sponsor_id', $lead->sponsor_id ?? $preset ?? 0) === (int) $id)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    @else
        <input type="hidden" name="sponsor_id" value="{{ $sponsor->id }}">
    @endif
    <div class="field"><label for="{{ $prefix }}club">Club qui mène la piste @unless($canBeFoundation)<span class="req">*</span>@endunless</label>
        <select id="{{ $prefix }}club" name="club_id" @unless($canBeFoundation) required @endunless>
            <option value="">{{ $canBeFoundation ? 'Fondation (sans club)' : 'Choisir un club' }}</option>
            @foreach($clubOptions as $id => $label)
                <option value="{{ $id }}" @selected((int) old('club_id', $lead->club_id ?? 0) === (int) $id)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="field span-2"><label for="{{ $prefix }}title">Intitulé de la piste <span class="req">*</span></label>
        <input id="{{ $prefix }}title" type="text" name="title" value="{{ old('title', $lead->title ?? '') }}" maxlength="190" required placeholder="Ex. : Sponsoring de la soirée de gala">
    </div>
    <div class="field"><label for="{{ $prefix }}kind">Nature</label>
        <select id="{{ $prefix }}kind" name="kind">
            @foreach(\App\Models\SponsorLead::KINDS as $key => $label)
                <option value="{{ $key }}" @selected(old('kind', $lead->kind ?? 'sponsoring') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="field"><label for="{{ $prefix }}amount">Montant espéré (TND)</label>
        <input id="{{ $prefix }}amount" type="number" name="amount" min="0" step="0.01" value="{{ old('amount', $lead->amount ?? '') }}">
    </div>
    <div class="field"><label for="{{ $prefix }}status">Étape</label>
        <select id="{{ $prefix }}status" name="status">
            @foreach(\App\Models\SponsorLead::STATUSES as $key => $label)
                <option value="{{ $key }}" @selected(old('status', $lead->status ?? 'idea') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    @if(isset($contacts))
        <div class="field"><label for="{{ $prefix }}contact">Interlocuteur</label>
            <select id="{{ $prefix }}contact" name="contact_id">
                <option value="">—</option>
                @foreach($contacts as $contact)
                    <option value="{{ $contact->id }}" @selected((int) old('contact_id', $lead->contact_id ?? 0) === $contact->id)>{{ $contact->name }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div class="field"><label for="{{ $prefix }}date">Prochaine action le</label>
        <input id="{{ $prefix }}date" type="date" name="next_action_at" value="{{ old('next_action_at', optional($lead->next_action_at ?? null)->format('Y-m-d')) }}">
    </div>
    <div class="field span-2"><label for="{{ $prefix }}next">Prochaine action</label>
        <input id="{{ $prefix }}next" type="text" name="next_action" value="{{ old('next_action', $lead->next_action ?? '') }}" maxlength="1000" placeholder="Ex. : envoyer le dossier de sponsoring">
    </div>
    <div class="field span-2"><label for="{{ $prefix }}notes">Notes</label>
        <textarea id="{{ $prefix }}notes" name="notes" rows="2" maxlength="5000">{{ old('notes', $lead->notes ?? '') }}</textarea>
    </div>
</div>
