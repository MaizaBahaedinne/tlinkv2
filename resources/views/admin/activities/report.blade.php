@extends('admin.layouts.app')

@section('title', 'Compte rendu — '.$activity->title)
@section('section', 'Défis')

@php
    $money = fn ($value) => $value === null ? '—' : number_format((float) $value, 3, ',', ' ').' DT';
    $tone = ['draft' => 'neutral', 'pending' => 'warning', 'validated' => 'success'][$report->status] ?? 'neutral';
@endphp

@section('content')
    <x-admin.page-header :title="'Compte rendu — '.$activity->title" :subtitle="$activity->challenge->title.' · '.$activity->club->title.' · '.$activity->starts_at->format('d/m/Y')">
        <x-slot:actions>
            <a href="{{ route('admin.activities.show', $activity) }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour à l’activité</a>
        </x-slot:actions>
    </x-admin.page-header>

    <article class="card">
        <div class="card-body text-sm">
            <p><strong>Statut :</strong> <span class="badge badge-{{ $tone }}">{{ $report->statusLabel() }}</span></p>
            <p class="text-muted">Le club rédige le compte rendu, le président du club porteur le valide, puis il est transmis au comité d’évaluation du défi. Sans compte rendu validé, l’activité ne peut pas être notée.</p>
            @if($report->status === 'draft' && $report->president_comment)
                <p style="margin-top:10px"><strong>Le président demande des corrections :</strong> {{ $report->president_comment }}</p>
            @endif
            @if($report->submitted_at)<p>Soumis le {{ $report->submitted_at->format('d/m/Y à H:i') }}@if($report->submitter) par {{ $report->submitter->name }}@endif.</p>@endif
            @if($report->validated_at)<p>Validé le {{ $report->validated_at->format('d/m/Y à H:i') }}@if($report->validator) par {{ $report->validator->name }}@endif.</p>@endif
        </div>
    </article>

    @if($editable)
        <form method="post" action="{{ route('admin.activities.report.update', $activity) }}" enctype="multipart/form-data" class="card card-flush">
            @csrf @method('PUT')
            <div class="card-body">
                <h3>Bilan</h3>
                <div class="form-grid">
                    <x-admin.textarea name="summary" label="Bilan de l’activité" :value="$report->summary" :maxlength="5000" :rows="6" hint="Déroulement, objectifs atteints, difficultés rencontrées. Obligatoire pour soumettre." />
                </div>

                <h3>Ressources humaines — implication des membres</h3>
                <p class="text-sm text-muted">Notez l’implication de chaque membre des clubs organisateurs qui a participé à l’activité (1 = faible, 5 = exceptionnelle).</p>
                @php
                    $members = array_pad($memberRows, max(3, count($memberRows) + 1), []);
                @endphp
                <div class="field {{ $errors->has('members') ? 'has-error' : '' }}">
                    <div data-rows="members">
                        @foreach($members as $i => $row)
                            <div class="form-grid" style="margin-top:8px" data-row>
                                <div class="field {{ $errors->has('members.'.$i.'.user_id') ? 'has-error' : '' }}">
                                    <select name="members[{{ $i }}][user_id]" aria-label="Membre">
                                        <option value="">Membre…</option>
                                        @foreach($candidates as $candidate)<option value="{{ $candidate->id }}" @selected((string) ($row['user_id'] ?? '') === (string) $candidate->id)>{{ $candidate->name }}</option>@endforeach
                                    </select>
                                </div>
                                <div class="field {{ $errors->has('members.'.$i.'.implication') ? 'has-error' : '' }}">
                                    <select name="members[{{ $i }}][implication]" aria-label="Implication">
                                        <option value="">Implication…</option>
                                        @foreach(\App\Models\ActivityReport::IMPLICATION as $score => $label)<option value="{{ $score }}" @selected((string) ($row['implication'] ?? '') === (string) $score)>{{ $score }} — {{ $label }}</option>@endforeach
                                    </select>
                                </div>
                                <div class="field"><input type="text" name="members[{{ $i }}][role]" maxlength="120" placeholder="Rôle (ex. logistique, accueil)" aria-label="Rôle" value="{{ $row['role'] ?? '' }}"></div>
                                <div class="field"><input type="text" name="members[{{ $i }}][comment]" maxlength="400" placeholder="Commentaire (facultatif)" aria-label="Commentaire" value="{{ $row['comment'] ?? '' }}"></div>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" data-add-row="members" style="margin-top:8px">+ Ajouter un membre</button>
                    @if($candidates->isEmpty())<p class="hint">Aucun membre validé n’est rattaché aux clubs organisateurs.</p>@endif
                    @error('members')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div class="form-grid">
                    <x-admin.textarea name="hr_summary" label="Appréciation générale de l’équipe" :value="$report->hr_summary" :maxlength="3000" :rows="3" />
                </div>

                <h3>Marketing</h3>
                <div class="form-grid">
                    <div class="field span-2 {{ $errors->has('poster') ? 'has-error' : '' }}">
                        <label for="f-poster">Affiche de l’activité</label>
                        <input id="f-poster" type="file" name="poster" accept="image/jpeg,image/png,image/webp">
                        <p class="hint">JPG, PNG ou WebP, 5 Mo maximum. Une nouvelle affiche remplace l’ancienne.</p>
                        @error('poster')<p class="error">{{ $message }}</p>@enderror
                        @foreach($report->posters() as $poster)
                            <div style="margin-top:8px">@if($poster->fileUrl())<img src="{{ $poster->fileUrl() }}" alt="Affiche de l’activité" style="max-height:120px;border-radius:8px">@endif
                                <label class="check"><input type="checkbox" name="remove_files[]" value="{{ $poster->id }}"> Retirer l’affiche</label></div>
                        @endforeach
                    </div>
                    <div class="field span-2 {{ $errors->has('proofs') || $errors->has('proofs.*') ? 'has-error' : '' }}">
                        <label for="f-proofs">Preuves de l’activité (photos) <span class="req">*</span></label>
                        <input id="f-proofs" type="file" name="proofs[]" accept="image/jpeg,image/png,image/webp" multiple>
                        <p class="hint">{{ \App\Models\ActivityReport::MAX_PROOFS }} photos au plus, 5 Mo chacune. Au moins une pour soumettre.</p>
                        @error('proofs')<p class="error">{{ $message }}</p>@enderror
                        @error('proofs.*')<p class="error">{{ $message }}</p>@enderror
                        <div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:8px">
                            @foreach($report->proofs() as $proof)
                                <div>@if($proof->fileUrl())<img src="{{ $proof->fileUrl() }}" alt="Preuve de l’activité" style="height:90px;border-radius:8px;display:block">@endif
                                    <label class="check"><input type="checkbox" name="remove_files[]" value="{{ $proof->id }}"> Retirer</label></div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <p class="text-sm text-muted" style="margin-top:14px">Retombées médias : publications Facebook et Instagram, passages TV et radio, articles de magazine.</p>
                @php
                    $media = array_pad($mediaRows, max(3, count($mediaRows) + 1), []);
                @endphp
                <div class="field">
                    <div data-rows="media">
                        @foreach($media as $i => $row)
                            <div class="form-grid" style="margin-top:8px" data-row>
                                <div class="field">
                                    <select name="media[{{ $i }}][kind]" aria-label="Type de retombée">
                                        <option value="">Type…</option>
                                        @foreach(\App\Models\ActivityReport::MEDIA_KINDS as $kind => $label)<option value="{{ $kind }}" @selected(($row['kind'] ?? '') === $kind)>{{ $label }}</option>@endforeach
                                    </select>
                                </div>
                                <div class="field"><input type="text" name="media[{{ $i }}][label]" maxlength="160" placeholder="Précision (chaîne, émission, magazine, date)" aria-label="Précision" value="{{ $row['label'] ?? '' }}"></div>
                                <div class="field span-2 {{ $errors->has('media.'.$i.'.url') ? 'has-error' : '' }}"><input type="url" name="media[{{ $i }}][url]" maxlength="500" placeholder="Lien (https://…)" aria-label="Lien" value="{{ $row['url'] ?? '' }}">@error('media.'.$i.'.url')<p class="error">{{ $message }}</p>@enderror</div>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" data-add-row="media" style="margin-top:8px">+ Ajouter une retombée</button>
                </div>

                <h3>Événements et sponsoring</h3>
                <p class="text-sm text-muted">Partenaires de l’activité et nature de leur soutien.</p>
                @php
                    $partners = array_pad($partnerRows, max(2, count($partnerRows) + 1), []);
                @endphp
                <div class="field">
                    <div data-rows="partners">
                        @foreach($partners as $i => $row)
                            <div class="form-grid" style="margin-top:8px" data-row>
                                <div class="field"><input type="text" name="partners[{{ $i }}][name]" maxlength="160" placeholder="Partenaire" aria-label="Partenaire" value="{{ $row['name'] ?? '' }}"></div>
                                <div class="field">
                                    <select name="partners[{{ $i }}][nature]" aria-label="Nature du soutien">
                                        @foreach(\App\Models\ActivityReport::NATURES as $nature => $label)<option value="{{ $nature }}" @selected(($row['nature'] ?? 'sponsor') === $nature)>{{ $label }}</option>@endforeach
                                    </select>
                                </div>
                                <div class="field"><input type="number" name="partners[{{ $i }}][amount]" min="0" step="0.001" placeholder="Montant (DT, facultatif)" aria-label="Montant" value="{{ $row['amount'] ?? '' }}"></div>
                                <div class="field"><input type="text" name="partners[{{ $i }}][description]" maxlength="400" placeholder="Détail du soutien (facultatif)" aria-label="Détail" value="{{ $row['description'] ?? '' }}"></div>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" data-add-row="partners" style="margin-top:8px">+ Ajouter un partenaire</button>
                </div>

                <h3>Budget de l’activité</h3>
                <div class="form-grid">
                    <x-admin.input name="budget_planned" type="number" label="Budget prévisionnel (DT)" :value="$report->budget_planned" min="0" step="0.001" />
                    <x-admin.input name="budget_spent" type="number" label="Dépenses réelles (DT)" :value="$report->budget_spent" min="0" step="0.001" hint="Obligatoire pour soumettre (0 si aucune dépense)." />
                    <x-admin.input name="budget_income" type="number" label="Recettes, hors sponsoring (DT)" :value="$report->budget_income" min="0" step="0.001" />
                    <x-admin.textarea name="budget_notes" label="Commentaire sur le budget" :value="$report->budget_notes" :maxlength="3000" :rows="3" />
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" name="action" value="draft" class="btn btn-secondary">Enregistrer le brouillon</button>
                <button type="submit" name="action" value="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> Soumettre au président</button>
            </div>
        </form>
    @else
        @include('admin.activities.partials.report-readonly', ['report' => $report, 'money' => $money])

        @if($canValidate)
            <article class="card">
                <div class="card-head"><div><h2>Décision du président</h2><div class="sub">Validez pour transmettre le compte rendu au comité d’évaluation, ou renvoyez-le au club avec vos remarques.</div></div></div>
                <div class="card-body">
                    <form method="post" action="{{ route('admin.activities.report.approve', $activity) }}" class="inline-form" style="margin-bottom:16px">
                        @csrf
                        <button type="submit" class="btn btn-success"><x-admin.icon name="check" :size="16" /> Valider et transmettre au comité</button>
                    </form>
                    <form method="post" action="{{ route('admin.activities.report.return', $activity) }}">
                        @csrf
                        <div class="field {{ $errors->has('president_comment') ? 'has-error' : '' }}">
                            <label for="f-president_comment">Corrections demandées</label>
                            <textarea id="f-president_comment" name="president_comment" rows="3" maxlength="2000" required>{{ old('president_comment') }}</textarea>
                            @error('president_comment')<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit" class="btn btn-secondary" style="margin-top:8px">Renvoyer au club</button>
                    </form>
                </div>
            </article>
        @elseif($report->status === 'pending')
            <p class="text-sm text-muted">En attente de la validation du président du club porteur.</p>
        @endif
    @endif
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-add-row]').forEach(function (button) {
        button.addEventListener('click', function () {
            var box = document.querySelector('[data-rows="' + button.getAttribute('data-add-row') + '"]');
            var rows = box.querySelectorAll('[data-row]');
            var clone = rows[rows.length - 1].cloneNode(true);
            clone.querySelectorAll('input, select').forEach(function (field) {
                if (field.tagName === 'SELECT') { field.selectedIndex = 0; } else { field.value = ''; }
                field.name = field.name.replace(/\[\d+\]/, '[' + rows.length + ']');
            });
            box.appendChild(clone);
        });
    });
</script>
@endpush
