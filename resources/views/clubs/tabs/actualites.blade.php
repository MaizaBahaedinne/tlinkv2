<div class="space-y-5">
    @if($canManage)
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
            <h2 class="font-display text-lg font-semibold text-slate-900">Publier une actualité</h2>
            <form method="post" action="{{ route('club.news.store', $club) }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                @csrf
                <div>
                    <label for="news-title" class="text-xs font-semibold text-slate-600">Titre</label>
                    <input id="news-title" name="title" type="text" required maxlength="160" value="{{ old('title') }}" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-tlink-blue focus:outline-none">
                    @error('title')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="news-body" class="text-xs font-semibold text-slate-600">Texte</label>
                    <textarea id="news-body" name="body" rows="5" required maxlength="5000" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-tlink-blue focus:outline-none">{{ old('body') }}</textarea>
                    @error('body')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="news-cover" class="text-xs font-semibold text-slate-600">Image (facultative, 5 Mo max)</label>
                    <input id="news-cover" name="cover" type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-xl file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-semibold">
                    @error('cover')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Publier</button>
            </form>
        </section>
    @endif

    @forelse($news as $item)
        <article class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-soft">
            @if($item->coverUrl())
                <img src="{{ $item->coverUrl() }}" alt="" loading="lazy" class="max-h-72 w-full object-cover">
            @endif
            <div class="p-6">
                <p class="text-xs text-slate-500">{{ $item->published_at->translatedFormat('j F Y') }}@if($item->author) · {{ $item->author->name }}@endif</p>
                <h2 class="mt-1 font-display text-xl font-semibold text-slate-900">{{ $item->title }}</h2>
                <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ $item->body }}</p>
                @if($canManage)
                    <form method="post" action="{{ route('club.news.destroy', [$club, $item]) }}" class="mt-4" onsubmit="return confirm('Supprimer cette actualité ?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-xl border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Supprimer</button>
                    </form>
                @endif
            </div>
        </article>
    @empty
        <p class="rounded-3xl border border-slate-200 bg-white px-4 py-10 text-center text-sm text-slate-500 shadow-soft">Ce club n’a pas encore publié d’actualité.</p>
    @endforelse
</div>
